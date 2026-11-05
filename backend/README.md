# NoreTel CRM

## Guía rápida para ejecutar el backend y OpenWA

El backend Laravel está en este repositorio. El gateway OpenWA completo es un **proyecto separado**, ubicado en el entorno de desarrollo como carpeta hermana `noretel-openwa`; debe obtenerse por separado. `tools/openwa_gateway` contiene un puente OCR, no el servidor OpenWA. La carpeta `tools/openwa-lid-resolution` es una copia parcial y no incluye el dashboard ni los archivos de Docker.

### 1. Backend Laravel

Requisitos: PHP compatible con `^8.1` y las extensiones exigidas por Composer, Composer, Node.js, npm y MySQL/MariaDB. Use una **base de datos local nueva y vacía**, nunca una base empresarial.

```bash
composer install
npm ci
cp .env.example .env
php artisan key:generate
```

En Windows PowerShell, use `Copy-Item .env.example .env` en lugar de `cp` si corresponde. Edite `.env` y configure `APP_URL` y `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD` para su base local. No copie credenciales de producción.

```bash
php artisan migrate
npm run build
php artisan serve
```

Abra `http://127.0.0.1:8000`. Si necesita datos de prueba, revise primero los seeders y ejecute `php artisan db:seed` **solo en esa base local**. Las credenciales que esos seeders crean son exclusivamente de prueba; no deben usarse en un servidor público. Los comandos se han contrastado con el proyecto, pero esta guía no acredita una instalación completa desde cero: podrían requerirse ajustes del entorno o de migraciones.

### 2. Gateway OpenWA

Requisitos: el proyecto completo `noretel-openwa` y Docker con Compose. Desde su carpeta, cree una configuración local a partir de su propio ejemplo y reemplace los valores sensibles por credenciales nuevas de prueba:

```bash
cd ../noretel-openwa
cp .env.example .env
docker compose up -d --build
docker compose ps
```

En PowerShell, puede usar `Copy-Item .env.example .env`. La configuración del Compose expone normalmente el dashboard en `http://localhost:2886` y la API en `http://localhost:2785/api`. Cree una sesión de prueba y vincule un número autorizado mediante el QR del dashboard. No utilice sesiones ni números de clientes reales. Docker Compose y la vinculación no se ejecutaron para verificar esta guía.

En el `.env` **del backend**, configure `YAPE_OPENWA_API_URL` con la URL accesible de la API, `YAPE_OPENWA_API_KEY` con su clave de prueba, `YAPE_OPENWA_SESSION` con el ID de la sesión creada y `YAPE_OPENWA_WEBHOOK_SECRET` con un secreto de prueba nuevo. Configure en OpenWA el evento `message.received` hacia `http://DIRECCION_DEL_BACKEND:8000/api/billing/yape/openwa/webhook`, enviando el mismo secreto en `X-OpenWA-Webhook-Secret`. La dirección debe ser alcanzable desde el contenedor; `localhost` dentro del contenedor no es necesariamente la máquina anfitriona.

El webhook recibe las evidencias del cliente. La conciliación completa también necesita la evidencia del receptor; el OCR local o remoto y el dispositivo receptor son componentes adicionales cuando se usa ese flujo. No se ha verificado una conciliación real de extremo a extremo con estas instrucciones.

### Antes de publicar

Revise ambos proyectos antes de compartirlos: excluya `.env`, claves, sesiones de WhatsApp, bases de datos, comprobantes, archivos de clientes, logs y documentación con direcciones o datos internos. **No ejecute `git add .` sin revisar**: este directorio también contiene copias académicas y otros archivos ajenos a la entrega. Revise también los archivos ya rastreados y el historial antes de publicar. Se necesita autorización para compartir el código de la empresa.

NoreTel CRM is a Laravel 10 modular application for managing ISP operations: customers, services, contracts, network resources, onboarding/installation workflows, billing, payments, audit/security, and optional Yape/OpenWA automation.

This guide is written for developers who need to run the project locally with a predictable setup.

## Quick local setup

Use this path when you already have PHP, Composer, Node.js, NPM, and MySQL/MariaDB installed.

```bash
git clone https://example.invalid noretel
cd noretel

composer install
npm install

cp .env.example .env
php artisan key:generate
```

Create a local database, update `.env`, then run:

```bash
php artisan migrate --seed
npm run build
php artisan serve
```

Open the application at:

```text
http://127.0.0.1:8000
```

Default seeded users:

| Role | Email | Password |
|------|-------|----------|
| Admin | `administrador@example.test` | `Admin123!` |
| Customer test user | `personal@example.test` | `Cliente123!` |

## Requirements

| Tool | Required version / note |
|------|--------------------------|
| PHP | `^8.1` |
| Composer | Required for PHP dependencies |
| Node.js + NPM | Required for Vite assets |
| Database | MySQL or MariaDB |
| Web server | `php artisan serve` is enough for local development |

The project is a Laravel 10 application and uses:

- `nwidart/laravel-modules` for modular structure.
- `spatie/laravel-permission` for roles and permissions.
- `laravel/sanctum` for API authentication support.
- `barryvdh/laravel-dompdf` for PDF generation.
- Vite for frontend asset compilation.

## Project structure

```text
app/                 Shared Laravel application code
config/              Laravel and package configuration
database/            Migrations, factories, and root seeders
Modules/             Business modules
public/              Public web root and compiled assets
resources/           Blade views, CSS, and JavaScript assets
routes/              Application routes
storage/             Logs, cache, uploads, and generated files
tests/               PHPUnit test suites
tools/               Supporting tools, including Yape Android tooling
```

Current modules:

| Module | Responsibility |
|--------|----------------|
| `Core` | Users, roles, permissions, auth-related foundations |
| `Customer` | Customer data and customer-facing domain records |
| `Services` | ISP services, plans, promotions, and additional services |
| `Contract` | Contracts and SLA-related data |
| `Network` | Network resources such as routes, routers, nodes, or zones |
| `Operations` | Onboarding, installations, service requests, and operational flow |
| `Billing` | Invoices, payments, credit notes, Yape validation, OpenWA flows |
| `Public` | Public-facing endpoints or pages |

## Environment configuration

Copy the example file:

```bash
cp .env.example .env
```

For a normal local setup, start with these values:

```env
APP_NAME=NoreTel
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
APP_TIMEZONE=America/Lima

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=noretel_local
DB_USERNAME=root
DB_PASSWORD=

CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file

VITE_API_URL=http://127.0.0.1:8000/api
VITE_APP_NAME=NoreTel
VITE_APP_ENV=local
```

> Important: keep `.env` local. Do not commit real credentials, API keys, WhatsApp tokens, Yape tokens, or production database values.

## Database setup

Create the database manually in MySQL/MariaDB:

```sql
CREATE DATABASE noretel_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Then run migrations and seeders:

```bash
php artisan migrate --seed
```

The root seeder runs:

1. `Modules\Core\Database\Seeders\CoreDatabaseSeeder`
2. `Modules\Services\Database\Seeders\ServicesDatabaseSeeder`
3. `Database\Seeders\DemoDataSeeder`

This creates the role/permission matrix, the admin user, a test customer user, service catalog data, and demo data.

If you need to reset your local database:

```bash
php artisan migrate:fresh --seed
```

Use this only in local development because it deletes existing database tables.

## Running the application

Backend:

```bash
php artisan serve
```

Frontend assets for development:

```bash
npm run dev
```

Or build assets once:

```bash
npm run build
```

Typical local workflow:

1. Start MySQL/MariaDB.
2. Run `php artisan serve`.
3. In a second terminal, run `npm run dev` if you are editing frontend assets.
4. Open `http://127.0.0.1:8000`.

## Useful commands

| Task | Command |
|------|---------|
| Install PHP dependencies | `composer install` |
| Install Node dependencies | `npm install` |
| Generate app key | `php artisan key:generate` |
| Run migrations | `php artisan migrate` |
| Run migrations with seeders | `php artisan migrate --seed` |
| Reset local database | `php artisan migrate:fresh --seed` |
| Start Laravel server | `php artisan serve` |
| Start Vite dev server | `npm run dev` |
| Build frontend assets | `npm run build` |
| Clear app cache | `php artisan optimize:clear` |
| List routes | `php artisan route:list` |
| Run tests | `php artisan test` |

## Optional: Yape and OpenWA local development

The base CRM works without Yape/OpenWA automation. Keep these integrations disabled unless you are specifically working on payment validation or WhatsApp flows.

Relevant `.env` groups:

```env
YAPE_MONITOR_TOKEN=
YAPE_RECEIVER_NAME=NORETEL
YAPE_RECEIVER_PHONE=
YAPE_TIME_TOLERANCE_MINUTES=10
YAPE_CONFIDENCE_THRESHOLD=0.88

YAPE_OPENWA_WEBHOOK_SECRET=
YAPE_OPENWA_API_URL=http://127.0.0.1:2785/api
YAPE_OPENWA_API_KEY=
YAPE_OPENWA_SESSION=noretel-yape
YAPE_OPENWA_OCR_ENABLED=true

COMMERCIAL_OPENWA_ENABLED=false
COMMERCIAL_OPENWA_WEBHOOK_SECRET=
COMMERCIAL_OPENWA_API_URL=
COMMERCIAL_OPENWA_API_KEY=
COMMERCIAL_OPENWA_SESSION=
```

Important local rules:

- Use a separate WhatsApp session/number for commercial messaging and Yape payment validation.
- Keep `COMMERCIAL_OPENWA_ENABLED=false` until a dedicated commercial session exists.
- Do not use production OpenWA API keys or webhook secrets in local development.
- The Yape receiver automation may require Android Debug Bridge (`adb`) and device-specific coordinates. Only configure it when testing the receiver-side automation.

## Tests

Run the full test suite:

```bash
php artisan test
```

PHPUnit is configured in `phpunit.xml`. The default test environment uses:

- `APP_ENV=testing`
- `CACHE_DRIVER=array`
- `QUEUE_CONNECTION=sync`
- `SESSION_DRIVER=array`

`phpunit.xml` configura SQLite en memoria para las pruebas. Algunas pruebas pueden requerir un motor o una configuración adicional; verifique siempre que ningún comando apunte a una base compartida o de producción.

## Troubleshooting

### The page loads without styles or JavaScript

Run one of:

```bash
npm run dev
```

or:

```bash
npm run build
```

### `.env` exists but Laravel says the application key is missing

Run:

```bash
php artisan key:generate
php artisan optimize:clear
```

### Permission or role changes are not reflected

Clear cached permissions and application cache:

```bash
php artisan optimize:clear
```

If you changed seeders, rerun the relevant seeder or reset the local database.

### Migrations fail because tables already exist

For local-only databases, reset everything:

```bash
php artisan migrate:fresh --seed
```

Do not run this against production or shared databases.

### The app cannot connect to MySQL

Check:

1. MySQL/MariaDB is running.
2. `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` are correct.
3. The database exists.
4. The database user has permissions for that schema.

## Development discipline

Before changing code:

1. Understand the module you are touching.
2. Check whether the behavior belongs in an existing module instead of adding logic to a random controller.
3. Prefer small, reviewable changes.
4. Run the relevant tests or at least validate routes, migrations, and syntax for the files you changed.

This project has business-heavy workflows. Do not guess business rules. Verify them in code, data, or product requirements before implementing changes.
