# CRM Noretel y OpenWA

Código del backend Laravel y del gateway OpenWA para una instalación independiente de evaluación. No contiene bases de clientes ni sesiones de producción. **Los pasos no se han ejecutado de extremo a extremo en esta distribución**; compruebe las versiones y dependencias en los archivos de cada proyecto.

## 1. Requisitos

- PHP 8.1 o superior compatible con `backend/composer.lock`, Composer 2 y las extensiones PHP indicadas por Composer.
- MySQL o MariaDB y una base de datos nueva, exclusiva para pruebas.
- Node.js y npm compatibles con `openwa/package-lock.json`; Docker y Docker Compose si se utiliza el despliegue con contenedores.
- Una cuenta y un teléfono de WhatsApp de prueba si se desea conectar una sesión real. Nunca utilice sesiones ni datos de clientes.

## 2. Backend Laravel

En `backend/`:

```bash
composer install
cp .env.example .env
php artisan key:generate
```

En Windows, en lugar de `cp`, use `copy .env.example .env`. Cree `storage/framework/sessions` antes de iniciar Laravel: en Unix use `mkdir -p storage/framework/sessions`; en PowerShell, `New-Item -ItemType Directory -Force storage/framework/sessions`. Edite `.env` con una base de datos vacía **local y exclusiva para pruebas**. Verifique `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD` antes de continuar. No use la base de la empresa.

```bash
php artisan migrate
npm ci
npm run build
php artisan serve
```

Si va a crear los usuarios y datos de ejemplo, establezca `DEMO_SEED_PASSWORD` en el `.env` local y ejecute `php artisan db:seed` **solo sobre la base de pruebas nueva**. El seeder de usuarios limpia asignaciones de permisos; no lo ejecute en una base existente. Un usuario de ejemplo es `administrador@example.test`; la contraseña es la que haya configurado. No se incluyen datos de clientes.

**Limitación conocida:** en una comprobación anterior con MariaDB 10.4, la migración `2026_07_15_000001_allow_multiple_contracts_per_customer_router.php` falló al eliminar un índice requerido por una clave foránea. Esta distribución conserva la migración original: debe resolverse y volver a probarse en una base de pruebas antes de afirmar que la instalación desde cero funciona.

## 3. OpenWA

El proyecto completo está en `openwa/`. Revise `docker-compose.yml`, los puertos y `.env.example` antes de usar Docker. En un entorno de prueba, copie el ejemplo a `.env`, sustituya todos los valores sensibles por credenciales nuevas y ejecute desde `openwa/`:

```bash
docker compose up -d --build
```

La API usa el puerto 2785 por defecto y ofrece documentación en `http://localhost:2785/api/docs`. El panel depende de la configuración de Docker y sus puertos. Cree una sesión de prueba y vincúlela mediante su QR. El directorio `data/` y los archivos de sesión son privados y no se deben subir a Git.

## 4. Conectar OpenWA y Laravel

Las automatizaciones y el OCR vienen desactivados en el ejemplo; actívelos solo para pruebas controladas. En el `.env` del backend configure la URL de la API OpenWA accesible desde Laravel, una clave de API nueva, el identificador de la sesión de prueba y un secreto nuevo para el webhook. El evento `message.received` debe apuntar a `/api/billing/yape/openwa/webhook` del backend; utilice el mismo secreto en ambos lados. Si los procesos están en contenedores o máquinas distintas, `localhost` puede referirse a equipos diferentes: use una dirección de prueba accesible entre ellos.

El OCR y la señal del teléfono receptor son componentes adicionales del flujo Yape; la API por sí sola no reproduce el proceso completo. Nunca ejecute pruebas enviando mensajes o pagos a clientes reales.

## 5. Pruebas y seguridad

Desde `backend/` se puede ejecutar `php artisan test`; `phpunit.xml` configura SQLite en memoria, aunque algunas pruebas pueden necesitar un motor MySQL/MariaDB de pruebas. Antes de compartir revise todos los archivos y la autorización para distribuir código de la empresa. Esta copia no contiene el historial Git original; no copie `.env`, bases de datos, logs, capturas, tokens ni sesiones.

El clasificador opcional `backend/tools/customer_bi_classifier/bi_classifier.py` requiere especificar `BI_API_URL` y `BI_API_TOKEN`, o los argumentos correspondientes, para un servicio de prueba; no trae una URL ni un token de producción por defecto.

La validación aislada del control de token BI se ejecuta desde `backend/` con `php tests/Standalone/BiTokenGuardTest.php`; esta comprobación no requiere base de datos.

La herramienta Android del receptor es opcional y no incluye el binario `gradle-wrapper.jar`; para compilarla se debe obtener o regenerar el wrapper desde una distribución Gradle confiable. Los archivos fuente del backend y OpenWA no dependen de ese binario para arrancar.

La variable opcional `WHATSAPP_BUSINESS_DESTINATION` identifica el destino comercial en la metadata de clics. Déjela vacía si no cuenta con un destino de prueba; si necesita probarla, configure exclusivamente un valor ficticio. No se define ningún número real por defecto. `YAPE_RECEIVER_PHONE` también está vacío por defecto: mientras no se configure, los recordatorios no incluyen instrucciones de pago Yape.
