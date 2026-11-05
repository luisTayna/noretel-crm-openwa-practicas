# Validación Yape local: WhatsApp/OpenWA + notificaciones Android

Este flujo NO usa subida manual de captura desde el formulario.

El backend Laravel es la fuente de verdad:

1. El operador registra un cobro con método **Yape**.
2. Laravel crea un `yape_payment_intent` en espera.
3. `tools/yape_whatsapp_monitor` procesa la captura que llega por WhatsApp Web local.
4. La app Android captura las notificaciones reales del Yape receptor.
5. Laravel compara monto, operación, hora, cliente y receptor. Solo si coincide crea el pago real.

## Variables `.env` de Laravel

```env
YAPE_MONITOR_TOKEN=token-largo-interno
YAPE_RECEIVER_NAME=NORETEL
YAPE_RECEIVER_PHONE=51999999999
YAPE_TIME_TOLERANCE_MINUTES=10
YAPE_CONFIDENCE_THRESHOLD=0.88
YAPE_STORAGE_DISK=local
YAPE_WHATSAPP_INBOX_PATH=C:\ruta\a\capturas_whatsapp
YAPE_ADB_PATH=C:\ruta\a\adb.exe
YAPE_DEVICE_SERIAL=127.0.0.1:5555
```

El mismo `YAPE_MONITOR_TOKEN` debe existir en el entorno donde corren los workers.

## Worker WhatsApp

```powershell
set YAPE_BACKEND_URL=http://127.0.0.1:8000
set YAPE_MONITOR_TOKEN=token-largo-interno
set YAPE_WHATSAPP_INBOX_PATH=C:\ruta\a\capturas_whatsapp
python tools\yape_whatsapp_monitor\monitor.py
```

El worker abre WhatsApp Web local y vigila la carpeta configurada. Para asociación automática, el nombre del archivo o carpeta debe contener el teléfono del cliente, por ejemplo:

```text
51987654321_2026-05-24_1015.png
```

## Receptor por notificaciones Android

```powershell
set YAPE_BACKEND_URL=http://127.0.0.1:8000
set YAPE_MONITOR_TOKEN=token-largo-interno
set YAPE_ADB_PATH=C:\ruta\a\adb.exe
set YAPE_DEVICE_SERIAL=127.0.0.1:5555
python tools\yape_receiver_monitor\monitor.py
```

El worker ejecuta:

```text
adb exec-out screencap -p
```

y envía la captura al backend cuando OCR detecta un código de operación.

## Health-check

```powershell
curl -H "Authorization: Bearer token-largo-interno" http://127.0.0.1:8000/api/billing/yape/health
```

## Nota importante

WhatsApp Web no ofrece una API local estable para extraer imágenes entrantes. Esta primera versión deja la integración operativa y segura usando una carpeta de ingesta local; si se quiere automatizar descarga/click dentro del DOM de WhatsApp Web, debe hacerse como una capa separada y frágil, porque los selectores de WhatsApp cambian con frecuencia.
