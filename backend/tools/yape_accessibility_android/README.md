# NoreTel Yape Notification Receiver

App Android nativa Kotlin para capturar notificaciones reales de Yape con `NotificationListenerService`.
No usa Accessibility, PIN, screenshots, apertura de Yape ni navegación automática.

## Requisitos

- Android Studio instalado.
- Android SDK 36.
- Celular receptor dedicado.
- Yape instalado: `com.bcp.innovacxion.yapeapp`.
- Laravel accesible desde el celular por LAN o túnel.

## Compilar desde terminal

```powershell
$env:JAVA_HOME="C:\Program Files\Android\Android Studio\jbr"
$env:Path="$env:JAVA_HOME\bin;$env:Path"
$env:ANDROID_HOME="$env:LOCALAPPDATA\Android\Sdk"
$env:ANDROID_SDK_ROOT="$env:LOCALAPPDATA\Android\Sdk"

cd C:\xampp\htdocs\noretelCRMfinal\noretel\tools\yape_accessibility_android
.\gradlew.bat assembleDebug
```

## Configuración en el celular

1. Instalar APK debug.
2. Abrir `NoreTel Yape Notification Receiver`.
3. Configurar:
   - Backend URL: IP accesible desde el celular, no `127.0.0.1` salvo que Laravel corra en el propio Android.
   - Monitor token: `YAPE_MONITOR_TOKEN` de Laravel.
   - Package Yape: `com.bcp.innovacxion.yapeapp`.
   - Enabled: activo.
4. Abrir ajustes de acceso a notificaciones y habilitar `NoreTel Yape Notification Receiver`.

## Seguridad

- No se guarda PIN.
- No se abre Yape.
- No se capturan screenshots.
- No se navega por la interfaz de Yape.
- No se envía evento automático si falta `security_code` o si hay ambigüedad.

## Flujo

1. Captura notificaciones cuyo package sea `com.bcp.innovacxion.yapeapp`.
2. Extrae `amount`, `security_code`, `sender_name`, `raw_text` y `occurred_at`.
3. Consulta `/api/billing/yape/pending-receiver-intents`.
4. Elige candidato solo si existe match único por `amount + security_code`.
5. Envía `/api/billing/yape/receiver-events` con `source=android_notification`.
