# NoreTel CRM + OpenWA

Esta distribución reúne el **backend real de NoreTel CRM** y el gateway OpenWA para revisión académica y ejecución local aislada. No es una aplicación de demostración independiente. La automatización de pagos descrita aquí corresponde a la validación de evidencias de **Yape**; OpenWA, OCR y el dispositivo receptor son componentes del flujo cuando se habilitan.

> **Importante:** no es una instalación lista para producción. No contiene bases de clientes, secretos ni sesiones de WhatsApp. La instalación completa y el flujo de pago de extremo a extremo no se han validado en esta distribución.

## Componentes

| Ruta | Función |
| --- | --- |
| [`backend/`](backend/) | CRM en Laravel 10: módulos de clientes, servicios, contratos, operaciones y facturación. |
| [`openwa/`](openwa/) | Gateway de WhatsApp con API y panel; se ejecuta como servicio separado mediante Docker Compose. |
| [`backend/.env.example`](backend/.env.example) y [`openwa/.env.example`](openwa/.env.example) | Plantillas de configuración local. No contienen las credenciales que se deben usar en producción. |

## Requisitos

- **Backend:** PHP `^8.1`, Composer, Node.js/npm compatibles con `backend/package-lock.json` y MySQL o MariaDB.
- **OpenWA:** Docker y Docker Compose. Si se vinculará una sesión, use un teléfono y una cuenta de WhatsApp de prueba autorizados.
- **Base de datos:** cree una base local nueva y vacía, exclusiva para esta prueba. No conecte el CRM a la base de la empresa.

## Inicio local

Ejecute el backend y OpenWA en terminales separadas. Desde la carpeta raíz del repositorio:

### 1. Preparar y arrancar el backend

```powershell
cd backend
cp .env.example .env
composer install
php artisan key:generate
```

Edite `backend/.env` y configure la conexión a su base local: `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD`. Después, cree las tablas y compile los recursos del frontend:

```powershell
php artisan migrate
npm ci
npm run build
php artisan serve
```

El backend queda disponible normalmente en <http://127.0.0.1:8000>.

**Opcional — datos de prueba:** en `backend/.env`, defina una contraseña local propia en `DEMO_SEED_PASSWORD` y ejecute `php artisan db:seed` solo en la base nueva. No use este comando en una base existente, compartida o de producción.

### 2. Arrancar OpenWA

En una segunda terminal, desde la raíz del repositorio:

```powershell
cd openwa
cp .env.example .env
```

Edite `openwa/.env` y establezca credenciales nuevas de prueba, incluida `API_MASTER_KEY`. No copie llaves, sesiones ni valores de producción. Luego inicie los contenedores:

```powershell
docker compose up -d --build
docker compose ps
```

Con la configuración predeterminada, los servicios se exponen solo en el equipo local:

| Servicio | Dirección |
| --- | --- |
| Panel OpenWA | <http://localhost:2886> |
| API OpenWA | <http://localhost:2785/api/docs> |
| Estado de la API | <http://localhost:2785/api/health> |

Si necesita probar una sesión real, cree una sesión de prueba y vincúlela con el QR del panel. Mantenga `data/`, archivos de sesión y `.env` fuera de Git. Si Laravel y OpenWA se ejecutan en contenedores o equipos distintos, configure una dirección de API alcanzable entre ellos; `localhost` puede apuntar al contenedor equivocado.

### 3. Conectar el flujo Yape (opcional)

Para probar la integración, configure únicamente credenciales y destinos de prueba. En `backend/.env`, revise las variables `YAPE_OPENWA_API_URL`, `YAPE_OPENWA_API_KEY`, `YAPE_OPENWA_SESSION` y `YAPE_OPENWA_WEBHOOK_SECRET`. Configure en OpenWA el webhook `message.received` para apuntar a `/api/billing/yape/openwa/webhook` del backend y use el mismo secreto de prueba.

El OCR y la evidencia obtenida desde el teléfono receptor son componentes adicionales del flujo; arrancar el backend y OpenWA por separado no demuestra que la conciliación Yape esté funcionando. `YAPE_RECEIVER_PHONE` está vacío en la plantilla, por lo que no se deben esperar instrucciones de pago Yape en recordatorios hasta configurar un destino de prueba.

## Comprobación

- Abra <http://127.0.0.1:8000> para comprobar que Laravel responde.
- Ejecute `docker compose ps` dentro de `openwa/` y consulte la documentación o el estado de la API indicados arriba.
- Para ejecutar las pruebas del backend, desde `backend/` use `php artisan test`. Algunas pruebas pueden requerir un motor MySQL/MariaDB de prueba.

Estas comprobaciones locales no equivalen a una validación completa del flujo de pagos con WhatsApp, OCR y teléfono receptor.

## Limitación conocida

En una comprobación previa con MariaDB 10.4 falló la migración `2026_07_15_000001_allow_multiple_contracts_per_customer_router.php`: intenta eliminar un índice requerido por una clave foránea. La migración original se conserva en esta copia. Si falla durante `php artisan migrate`, detenga la prueba y use una base local aislada; no intente resolverlo sobre datos de producción. La instalación desde cero debe volver a probarse después de corregir esa incompatibilidad.

## Seguridad y privacidad

- Use bases, números, sesiones, contraseñas, tokens y secretos creados exclusivamente para pruebas.
- No suba `.env`, bases de datos, comprobantes, logs, sesiones de WhatsApp ni datos personales.
- No envíe mensajes ni pagos de prueba a clientes reales.
- Esta copia no incluye el historial Git original; mantenga los datos de operación y las credenciales fuera de la distribución.
