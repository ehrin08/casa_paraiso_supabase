# Casa Paraiso Docker Desktop Workflow

## Purpose

This repository uses Docker Desktop's `desktop-linux` engine through `scripts\casa-docker.ps1`. The wrapper verifies the daemon identity and fixes the Compose project name to `casa-paraiso-supabase-desktop`, keeping it separate from the inherited `C:\casa_paraiso` project and from older partial volumes.

Application source remains in `C:\casa_paraiso_supabase`. Docker Desktop owns the active images, containers, networks, and named volumes. The prior `CasaParaisoDocker` WSL2 engine remains installed with its stopped pre-migration volumes as a rollback copy; access it only through `scripts\casa-dedicated-docker.ps1` after stopping Docker Desktop services because both stacks publish the same loopback ports.

The inherited `casa_paraiso` Docker Desktop MariaDB stack is a read-only migration source. Never start, recreate, seed, or otherwise mutate it from this repository.

The isolated MariaDB service in this repository is also frozen as the verified 2026-07-17 Supabase rollback source. Do not migrate, seed, or write new business records to it after cutover.

## Services and Local Ports

- `laravel.test`: Sail PHP 8.2 at `http://localhost:18001`
- `mariadb`: isolated migration/demo database at `127.0.0.1:13307`
- `pgsql`: isolated PostgreSQL 17 portability/test service at `127.0.0.1:15432`
- `mailpit`: SMTP `11026`, dashboard `http://localhost:18025`
- `cloudflared`: profile-gated rotating Quick Tunnel
- Vite: `127.0.0.1:15173`

Composer dependencies inside the app use the `sail-vendor` named volume. MariaDB data uses `sail-mariadb`, and PostgreSQL data uses `sail-postgres`. Published host ports bind only to loopback.

## Docker Desktop and Rollback Engine

Start Docker Desktop before using the project. Read-only verification of the active project and daemon identity is available through:

```powershell
.\scripts\casa-docker.ps1 status
```

The wrapper always targets Docker context `desktop-linux` and Compose project `casa-paraiso-supabase-desktop`, regardless of the shell's current Docker context.

The former dedicated engine is retained only for rollback inspection. Stop the Docker Desktop project before using it:

```powershell
.\scripts\casa-docker.ps1 stop
.\scripts\casa-dedicated-docker.ps1 status
```

`provision-casa-docker.ps1` and `CasaDedicatedDocker.psm1` remain for that rollback engine; they are no longer the primary workflow.

## First Project Start

The host Composer install is needed because Sail's build context comes from `vendor/laravel/sail`. The container install then populates Docker Desktop's Linux-backed vendor volume.

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
.\scripts\casa-docker.ps1 start
.\scripts\casa-docker.ps1 compose exec -T laravel.test composer install
.\scripts\casa-docker.ps1 compose restart laravel.test
.\scripts\casa-docker.ps1 compose exec -T --user sail laravel.test php artisan migrate
.\scripts\casa-docker.ps1 compose exec -T laravel.test npm ci
.\scripts\casa-docker.ps1 compose exec -T laravel.test npm run build
```

Never use `migrate:fresh`, `db:wipe`, table drops, truncation, or a destructive reseed against an account-bearing database.

## Account-Preserving Inherited Data Clone

Run only after confirming the inherited `casa_paraiso` containers and volumes in Docker Desktop are the intended read-only source:

```powershell
.\scripts\clone-inherited-db.ps1 -Apply
```

The clone tool:

- waits for the dedicated destination to become healthy;
- applies additive destination migrations;
- refuses to overwrite an account-bearing destination unless its account/profile counts already match the source;
- exports data only, excluding migrations, caches, sessions, queues, reset tokens, failed jobs, and obsolete `transaction_adjustments`;
- uses complete, insert-ignore statements so a safely interrupted initial clone can resume;
- omits the stale source-only `appointments.quoted_amount` and `transactions.amount_paid` fields, which are absent from the authoritative repository schema;
- verifies `users`, `customer_profiles`, and `staff_profiles` counts;
- never writes to the Docker Desktop source database.

Use `-KeepDump` only when a temporary ignored SQL artifact is needed for diagnosis. Dumps live under `storage/backups/migration` and must never be committed.

## MariaDB-to-PostgreSQL Transfer

The local PostgreSQL service retains a separate database and volume for transfer rehearsal. With an explicitly local migration target, migrate its empty schema, preview the transfer, apply it once, then run the read-only comparison and integrity audit:

```powershell
.\scripts\casa-docker.ps1 compose exec -T --user sail laravel.test sh -lc 'DB_CONNECTION=pgsql DB_HOST=pgsql DB_PORT=5432 DB_DATABASE=casa_paraiso DB_USERNAME=sail DB_PASSWORD=password php artisan migrate --force'
.\scripts\casa-docker.ps1 compose exec -T --user sail laravel.test php artisan casa:transfer-to-postgres
.\scripts\casa-docker.ps1 compose exec -T --user sail laravel.test php artisan casa:transfer-to-postgres --apply
.\scripts\casa-docker.ps1 compose exec -T --user sail laravel.test php artisan casa:transfer-to-postgres --validate
.\scripts\casa-docker.ps1 compose exec -T --user sail laravel.test sh -lc 'DB_CONNECTION=pgsql DB_HOST=pgsql DB_PORT=5432 DB_DATABASE=casa_paraiso DB_USERNAME=sail DB_PASSWORD=password php artisan casa:audit-crud-integrity'
```

`casa:transfer-to-postgres` reads `migration_source` and `migration_target` from the uncommitted environment. It is dry-run-first, uses a read-only source transaction, refuses target business data, copies identity and business tables in foreign-key order, preserves IDs and credential hashes, repairs PostgreSQL sequences, and never transfers runtime sessions, tokens, queues, caches, reset tokens, migration history, or obsolete adjustment data. A fresh migration creates five deterministic RFM preset rows; the command recognizes only that exact migration baseline and replaces it transactionally during `--apply`. `--validate` performs a full-row comparison and sequence check without writing.

`--apply` performs the complete row, count, credential/Google identity, and sequence validation inside the target transaction. Any mismatch throws before commit and rolls back every imported row. `--validate` repeats the same comparison independently after commit.

### Production Supabase Target

The approved production database is the existing **Casa Paraiso** project (`pnichczvgkdxnhcezqyn`) in Sydney. Application objects live in the private `casa` schema:

- `casa_migrator` owns `casa` and runs Laravel migrations/imports;
- `casa_runtime` is Laravel's normal connection and has only schema usage, table DML, and sequence usage;
- `PUBLIC`, `anon`, `authenticated`, and `service_role` have no schema or table access;
- the Data API is disabled, SSL enforcement is enabled, and Supabase Auth is not used;
- both roles connect through `aws-0-ap-southeast-2.pooler.supabase.com:5432` using usernames suffixed with `.pnichczvgkdxnhcezqyn`.

Keep passwords, the downloaded Supabase CA, and dumps in ignored storage. The relevant uncommitted values are:

```dotenv
MIGRATION_SOURCE_DB_HOST=mariadb
MIGRATION_SOURCE_DB_PORT=3306
MIGRATION_TARGET_DB_HOST=aws-0-ap-southeast-2.pooler.supabase.com
MIGRATION_TARGET_DB_PORT=5432
MIGRATION_TARGET_DB_DATABASE=postgres
MIGRATION_TARGET_DB_USERNAME=casa_migrator.pnichczvgkdxnhcezqyn
MIGRATION_TARGET_DB_SEARCH_PATH=casa,public
MIGRATION_TARGET_DB_SSLMODE=verify-full
MIGRATION_TARGET_DB_SSLROOTCERT=/var/www/html/storage/app/private/supabase/prod-ca-2021.crt

DB_CONNECTION=pgsql
DB_HOST=aws-0-ap-southeast-2.pooler.supabase.com
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=casa_runtime.pnichczvgkdxnhcezqyn
DB_SEARCH_PATH=casa,public
DB_SSLMODE=verify-full
DB_SSLROOTCERT=/var/www/html/storage/app/private/supabase/prod-ca-2021.crt
```

Run production schema changes only through the migrator connection:

```powershell
.\scripts\casa-docker.ps1 compose exec -T --user sail laravel.test php artisan migrate --database=migration_target --force
```

The 2026-07-17 cutover used the current MariaDB source directly under maintenance, followed by dry-run, transactional apply, independent validation, CRUD integrity, runtime DML/denied-DDL probes, role workspace smoke tests, and checksumed pre/post exports. Evidence remains ignored under `storage/app/private/supabase/cutover`. Keep MariaDB read-only: before any new Supabase business write, rollback is an environment switch; after one, use a reviewed reverse-data migration.

## Daily Commands

```powershell
# Start the Docker Desktop project services
.\scripts\casa-docker.ps1 start

# Inspect services
.\scripts\casa-docker.ps1 status

# Pass any Compose arguments through the verified engine
.\scripts\casa-docker.ps1 compose ps --all
.\scripts\casa-docker.ps1 compose exec -T --user sail laravel.test php artisan migrate
.\scripts\casa-docker.ps1 compose exec -T laravel.test npm run build
.\scripts\casa-docker.ps1 compose exec -T --user sail laravel.test php artisan test

# Stop project services; Docker Desktop itself remains running
.\scripts\casa-docker.ps1 stop
```

Do not use bare `docker compose` for this repository. The wrapper is the Docker Desktop identity and project-name guard.

## Quick Tunnel and Secure Pairing

Start a demo session:

```powershell
.\scripts\mobile-demo.ps1 -Action Start
```

The helper requires an existing signed release APK, starts the Docker Desktop stack and profile-gated `cloudflared` container, temporarily hardens the ignored `.env`, validates `/api/v1/meta`, prints a temporary APK download URL plus the connection link and Google callback, and sends a URL-only `casaparaiso://pair` deep link when exactly one ADB device is available. The APK download route is disabled whenever the demo pairing flag is off.

The app accepts the bare connection URL or the APK download URL, reduces it to an exact HTTPS `*.trycloudflare.com` origin, validates the Casa Paraiso service identity and instance UUID through the rate-limited metadata endpoint, and persists only the verified URL, instance UUID, and pairing timestamp in Capacitor Preferences. Pairing grants no authenticated session; password or Google sign-in remains required.

Rotate or inspect the demo:

```powershell
.\scripts\mobile-demo.ps1 -Action Rotate
.\scripts\mobile-demo.ps1 -Action Status
```

Always stop after the demonstration. This closes the public tunnel, restores the prior `.env` values, clears Laravel configuration, and stops the Docker Desktop project services:

```powershell
.\scripts\mobile-demo.ps1 -Action Stop
```

A Quick Tunnel has no uptime guarantee. The bundled APK UI remains installed and usable as an app shell, but a rotated or stopped backend requires pasting the new connection link.

## Mobile Build

```powershell
Set-Location mobile
npm ci
npm test
npm run android:sync
$env:ANDROID_HOME = Join-Path $env:LOCALAPPDATA 'Android\Sdk'
.\android\gradlew.bat -p android assembleDebug
```

Capacitor 8 compiles and targets API 36 with minimum API 24. Install `platforms;android-36` and the matching build tools through Android's `sdkmanager` if the local SDK is missing them. The debug APK is generated under `mobile/android/app/build/outputs/apk/debug/` and is ignored by Git.

For the signed version `1.0.0` release APK, follow [`PHONE_INSTALLATION.md`](PHONE_INSTALLATION.md). The one-time initializer creates a 4096-bit key under `%USERPROFILE%\.casa-paraiso`; subsequent builds reuse it and verify the APK before reporting its SHA-256 checksum:

```powershell
.\scripts\build-mobile-release.ps1 -InitializeSigning
.\scripts\build-mobile-release.ps1
.\scripts\build-mobile-release.ps1 -Install
```

Never delete or replace the external signing directory after distributing the APK. Android requires the same key for in-place updates.

## Environment Defaults

The Docker Desktop local `.env` uses:

```env
APP_URL=http://localhost:18001
APP_PORT=18001
FORWARD_VITE_PORT=15173
DB_CONNECTION=mariadb
DB_HOST=mariadb
DB_PORT=3306
DB_DATABASE=casa_paraiso
DB_USERNAME=sail
DB_PASSWORD=password
FORWARD_DB_PORT=13307
POSTGRES_DB=casa_paraiso
POSTGRES_USER=sail
POSTGRES_PASSWORD=password
FORWARD_PGSQL_PORT=15432
LOCAL_MARIADB_DATABASE=casa_paraiso
LOCAL_MARIADB_USERNAME=sail
LOCAL_MARIADB_PASSWORD=password
MIGRATION_SOURCE_DB_HOST=mariadb
MIGRATION_SOURCE_DB_DATABASE=casa_paraiso
MIGRATION_TARGET_DB_HOST=pgsql
MIGRATION_TARGET_DB_DATABASE=casa_paraiso
MIGRATION_TARGET_DB_SEARCH_PATH=public
MIGRATION_TARGET_DB_SSLMODE=prefer
MAIL_HOST=mailpit
MAIL_PORT=1025
FORWARD_MAILPIT_PORT=11026
FORWARD_MAILPIT_DASHBOARD_PORT=18025
```

These are clean-checkout local defaults, not the current production database connection. `phpunit.xml` force-pins tests to the isolated local `pgsql/testing` database even when the ignored `.env` points Laravel at Supabase. Secrets, the Supabase CA, generated server UUID, pairing state, dumps, build outputs, and local Android SDK paths are ignored.

## Deployment Boundary

Docker and the Quick Tunnel are the approved demonstration backend, not the final hosted production backend. The Android UI is bundled into the APK and must never use Capacitor `server.url` to wrap a remote web page. Supabase provisioning, restricted-role setup, verified-TLS cutover, and data acceptance are complete; production backend hosting, live Google-provider acceptance, signing-key backup, and physical-device acceptance remain delivery milestones.

XAMPP remains a fallback for inherited browser comparison only. It must not share or replace the Docker Desktop project database. The preserved dedicated WSL2 engine is a rollback copy, not a concurrently active development environment.
