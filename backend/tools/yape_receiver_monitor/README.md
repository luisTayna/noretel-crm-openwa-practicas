# Monitor receptor Yape con celular Android real

Este monitor automatiza la app Yape del receptor usando ADB. Ya no requiere que el operador abra manualmente el detalle del movimiento.

## Flujo

1. Consulta a Laravel los intents Yape en `waiting_receiver_event`.
2. Abre Yape en el celular.
3. Toca la pestaña/lista de movimientos.
4. Captura la pantalla de movimientos.
5. Usa OCR con posiciones para buscar candidatos por monto + nombre parcial + fecha/hora.
6. Toca el movimiento candidato.
7. Captura el detalle.
8. Extrae el número de operación por OCR.
9. Envía `receiver-events` a Laravel.
10. Vuelve atrás y repite.

## Variables principales

```env
YAPE_BACKEND_URL=http://127.0.0.1:8000
YAPE_MONITOR_TOKEN=token-largo-interno
YAPE_ADB_PATH=C:/ruta/a/android-sdk/platform-tools/adb.exe
YAPE_DEVICE_SERIAL=9b9237a2
YAPE_RECEIVER_MODE=adb_navigation
YAPE_RECEIVER_APP_PACKAGE=com.bcp.innovacxion.yapeapp
```

## Calibración de coordenadas

Estas coordenadas dependen del tamaño/resolución del celular:

```env
YAPE_MOVEMENTS_TAB_X=540
YAPE_MOVEMENTS_TAB_Y=1800
YAPE_MOVEMENT_TAP_X=540
YAPE_MOVEMENTS_LIST_TOP_Y=350
YAPE_MOVEMENTS_LIST_BOTTOM_Y=1700
YAPE_MOVEMENT_MIN_SCORE=60
```

Para obtener coordenadas, activa en Android:

```text
Opciones de desarrollador → Ubicación del puntero
```

Luego toca manualmente la pestaña Movimientos y observa las coordenadas X/Y.

## Ejecutar

```powershell
python tools\yape_receiver_monitor\monitor.py
```

## Modo pasivo de emergencia

Si quieres volver al comportamiento anterior:

```env
YAPE_RECEIVER_MODE=passive
```

En modo pasivo, el script solo captura la pantalla actual y la envía si detecta un número de operación.
