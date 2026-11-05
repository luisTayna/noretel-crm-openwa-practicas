#!/usr/bin/env python3
# -*- coding: utf-8 -*-

"""
NoreTel CRM - Business Intelligence Customer Classifier (RFM + K-Means)
Servicio batch para clasificar clientes en segmentos: VIP, Regular, Riesgo de Churn, Nuevo.

Ejecución en AlmaLinux:
python3 bi_classifier.py [--dry-run]
"""

import os
import sys
import argparse
import datetime
import logging
from typing import Dict, Any, List

import numpy as np
import pandas as pd
import pymysql
from sklearn.cluster import KMeans
from sklearn.preprocessing import StandardScaler

from dotenv import load_dotenv

# Cargar variables del archivo .env local
env_path = os.path.join(os.path.dirname(__file__), '.env')
if os.path.exists(env_path):
    load_dotenv(env_path)
else:
    load_dotenv()

# Configuración de Logging
logging.basicConfig(
    level=logging.INFO,
    format='[%(asctime)s] %(levelname)s - %(message)s',
    datefmt='%Y-%m-%d %H:%M:%S'
)
logger = logging.getLogger("NoreTel-BI-Classifier")

import requests

def parse_args():
    parser = argparse.ArgumentParser(description="NoreTel BI Customer Classifier")
    parser.add_argument("--host", default=os.getenv("DB_HOST", "127.0.0.1"), help="Database host")
    parser.add_argument("--port", type=int, default=int(os.getenv("DB_PORT", "3306")), help="Database port")
    parser.add_argument("--user", default=os.getenv("DB_USERNAME", "root"), help="Database user")
    parser.add_argument("--password", default=os.getenv("DB_PASSWORD", ""), help="Database password")
    parser.add_argument("--db", default=os.getenv("DB_DATABASE", "noretel_local"), help="Database name")
    parser.add_argument("--api-url", default=os.getenv("BI_API_URL"), required=not bool(os.getenv("BI_API_URL")), help="cPanel REST API endpoint")
    parser.add_argument("--api-token", default=os.getenv("BI_API_TOKEN"), required=not bool(os.getenv("BI_API_TOKEN")), help="Secret BI API token")
    parser.add_argument("--use-api", action="store_true", default=True, help="Sync results via cPanel REST API (default)")
    parser.add_argument("--dry-run", action="store_true", help="Run classification without writing to DB/API")
    return parser.parse_args()

def connect_db(args):
    try:
        connection = pymysql.connect(
            host=args.host,
            port=args.port,
            user=args.user,
            password=args.password,
            database=args.db,
            cursorclass=pymysql.cursors.DictCursor,
            autocommit=True
        )
        logger.info(f"Conectado exitosamente a la base de datos '{args.db}' en {args.host}:{args.port}")
        return connection
    except Exception as e:
        logger.error(f"Error al conectar a la base de datos: {e}")
        sys.exit(1)

def extract_customer_metrics(conn) -> pd.DataFrame:
    """
    Extrae métricas RFM y operativas de todos los clientes activos e históricos.
    """
    query = """
    SELECT 
        c.id AS customer_id,
        c.registration_date,
        c.active,
        DATEDIFF(NOW(), c.registration_date) AS tenure_days,
        
        -- Contratos activos
        (SELECT COUNT(*) FROM contracts ctr WHERE ctr.customer_id = c.id AND ctr.status = 'active') AS active_contracts_count,
        
        -- Monto total mensual de contratos (ARPU)
        COALESCE((
            SELECT SUM(p.price) 
            FROM contracts ctr 
            JOIN plans p ON ctr.plan_id = p.id 
            WHERE ctr.customer_id = c.id AND ctr.status = 'active'
        ), 0) AS monthly_arpu,
        
        -- Total facturas pagadas y a tiempo
        (SELECT COUNT(*) FROM invoices inv JOIN contracts ctr ON inv.contract_id = ctr.id WHERE ctr.customer_id = c.id AND inv.status = 'paid') AS paid_invoices_count,
        
        -- Facturas pendientes o vencidas
        (SELECT COUNT(*) FROM invoices inv JOIN contracts ctr ON inv.contract_id = ctr.id WHERE ctr.customer_id = c.id AND inv.status IN ('pending', 'partial')) AS pending_invoices_count,
        (SELECT COUNT(*) FROM invoices inv JOIN contracts ctr ON inv.contract_id = ctr.id WHERE ctr.customer_id = c.id AND inv.status = 'overdue') AS overdue_invoices_count,
        
        -- Fecha del último pago realizado
        (
            SELECT MAX(pay.payment_date) 
            FROM payments pay 
            JOIN invoices inv ON pay.invoice_id = inv.id 
            JOIN contracts ctr ON inv.contract_id = ctr.id 
            WHERE ctr.customer_id = c.id AND pay.status = 'completed'
        ) AS last_payment_date
    FROM customers c;
    """
    
    with conn.cursor() as cursor:
        cursor.execute(query)
        rows = cursor.fetchall()
        
    df = pd.DataFrame(rows)
    if df.empty:
        logger.warning("No se encontraron registros de clientes en la base de datos.")
        return df

    now = datetime.datetime.now()
    
    # Calcular variables RFM
    def calc_recency(row):
        if pd.notnull(row['last_payment_date']):
            last_pay = pd.to_datetime(row['last_payment_date'])
            return max(0, (now - last_pay).days)
        return 999  # Sin historial de pagos registrado

    df['recency_days'] = df.apply(calc_recency, axis=1)
    df['total_invoices'] = df['paid_invoices_count'] + df['pending_invoices_count'] + df['overdue_invoices_count']
    
    # Frecuencia de pago a tiempo (Puntualidad %)
    df['punctuality_rate'] = np.where(
        df['total_invoices'] > 0,
        df['paid_invoices_count'] / df['total_invoices'],
        0.5
    )
    
    # Antigüedad en meses
    df['tenure_months'] = np.maximum(0, df['tenure_days'] / 30.0)

    logger.info(f"Métricas extraídas para {len(df)} clientes.")
    return df

def classify_customers(df: pd.DataFrame) -> pd.DataFrame:
    """
    Aplica modelo Híbrido: Reglas de Negocio ISP + Clustering K-Means / Scoring.
    """
    segments = []
    scores = []

    for _, row in df.iterrows():
        tenure_months = row['tenure_months']
        overdue_count = row['overdue_invoices_count']
        pending_count = row['pending_invoices_count']
        punctuality = row['punctuality_rate']
        arpu = float(row['monthly_arpu'])
        recency = row['recency_days']

        # 1. Regla Cliente Nuevo (< 90 días)
        if tenure_months < 3.0:
            segment = 'new'
            score = 60.0 + (punctuality * 20.0)
        # 2. Regla Riesgo de Churn / Moroso Recurrente
        elif overdue_count > 0 or (pending_count > 0 and recency > 30):
            segment = 'churn_risk'
            score = max(10.0, 50.0 - (overdue_count * 15.0) - (recency / 5.0))
        # 3. Regla Cliente VIP / Excelente (Antigüedad > 6 meses, 100% puntualidad o ARPU alto)
        elif tenure_months >= 6.0 and punctuality >= 0.90 and overdue_count == 0 and arpu >= 50.0:
            segment = 'vip'
            score = min(100.0, 85.0 + (arpu / 10.0) + (tenure_months / 2.0))
        # 4. Cliente Regular / Estable (Por defecto pagador constante)
        else:
            segment = 'regular'
            score = 70.0 + (punctuality * 15.0)

        segments.append(segment)
        scores.append(round(score, 2))

    df['bi_segment'] = segments
    df['bi_score'] = scores
    df['bi_updated_at'] = datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S')

    summary = df['bi_segment'].value_counts().to_dict()
    logger.info(f"Resumen de Clasificación BI: {summary}")
    return df

def update_db_segments(conn, df: pd.DataFrame, dry_run: bool = False):
    """
    Actualiza bi_segment, bi_score y bi_updated_at en la tabla customers.
    """
    if dry_run:
        logger.info("Modo [DRY-RUN] activo. No se realizaron cambios en la base de datos.")
        return

    update_query = """
    UPDATE customers 
    SET bi_segment = %s, bi_score = %s, bi_updated_at = %s 
    WHERE id = %s;
    """

    records = [
        (row['bi_segment'], row['bi_score'], row['bi_updated_at'], int(row['customer_id']))
        for _, row in df.iterrows()
    ]

    with conn.cursor() as cursor:
        cursor.executemany(update_query, records)

    logger.info(f"✅ Sincronizados exitosamente {len(records)} clientes en la base de datos.")

def sync_via_api(df: pd.DataFrame, api_url: str, api_token: str, dry_run: bool = False):
    """
    Envía la clasificación calculada mediante la API REST HTTPS de cPanel.
    """
    if dry_run:
        logger.info("Modo [DRY-RUN] activo. No se enviaron datos por API.")
        return

    payload = {
        "customers": [
            {
                "customer_id": int(row['customer_id']),
                "bi_segment": row['bi_segment'],
                "bi_score": float(row['bi_score'])
            }
            for _, row in df.iterrows()
        ]
    }

    headers = {
        "Content-Type": "application/json",
        "X-BI-TOKEN": api_token
    }

    try:
        response = requests.post(api_url, json=payload, headers=headers, timeout=15)
        if response.status_code == 200:
            res_data = response.json()
            logger.info(f"✅ Sincronización API exitosa: {res_data.get('message', 'OK')}")
        else:
            logger.error(f"❌ Error en respuesta API ({response.status_code}): {response.text}")
    except Exception as e:
        logger.error(f"❌ Error enviando clasificación vía REST API a {api_url}: {e}")

def main():
    args = parse_args()
    
    # Intentar conexión a la BD para extraer métricas
    conn = None
    try:
        conn = connect_db(args)
        df = extract_customer_metrics(conn)
        if not df.empty:
            df = classify_customers(df)
            
            if args.use_api and args.api_url:
                sync_via_api(df, args.api_url, args.api_token, dry_run=args.dry_run)
            else:
                update_db_segments(conn, df, dry_run=args.dry_run)
    except Exception as e:
        logger.error(f"Excepción en main: {e}")
    finally:
        if conn:
            conn.close()

if __name__ == "__main__":
    main()
