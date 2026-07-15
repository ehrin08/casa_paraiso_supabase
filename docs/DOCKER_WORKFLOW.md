# Casa Paraiso Dedicated Docker Workflow

## Purpose

This repository uses its own Docker Engine inside the Ubuntu 24.04 WSL2 distribution `CasaParaisoDocker`. It does not use Docker Desktop and does not share images, containers, networks, or volumes with the inherited `C:\casa_paraiso` project.

Application source remains in `C:\casa_paraiso_supabase`. Docker data lives inside the dedicated WSL distribution. A small hidden `wsl.exe` keepalive owned by the project scripts keeps the on-demand daemon and demo tunnel alive between commands; `stop` removes it.

The inherited Docker Desktop MariaDB stack is a read-only migration source. Never stop, recreate, seed, or otherwise mutate it from this repository.

## Services and Local Ports

- `laravel.test`: Sail PHP 8.2 at `http://localhost:18001`
- `mariadb`: isolated migration/demo database at `127.0.0.1:13307`
- `pgsql`: PostgreSQL 17 migration target at `127.0.0.1:15432`
- `mailpit`: SMTP `11026`, dashboard `http://localhost:18025`
- `cloudflared`: profile-gated rotating Quick Tunnel
- Vite: `127.0.0.1:15173`

Composer dependencies inside the app use the `sail-vendor` named volume. MariaDB data uses `sail-mariadb`, and PostgreSQL data uses `sail-postgres`. Published host ports bind only to loopback.

## Provision the Dedicated Engine

Run once from an elevated or normal PowerShell session. The script imports Ubuntu 24.04 into `C:\WSL\CasaParaisoDocker`, installs Docker Engine and Compose, enables systemd, labels the daemon, and verifies its identity.

```powershell
.\scripts\provision-casa-docker.ps1 -Action Install
```

Read-only verification:

```powershell
.\scripts\provision-casa-docker.ps1 -Action Verify
```

Every project Compose command checks for the daemon label `com.casaparaiso.engine=dedicated` and refuses an unexpected engine.

## First Project Start

The host Composer install is needed because Sail's build context comes from `vendor/laravel/sail`. The container install then populates the Linux-backed vendor volume.

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

Run only after confirming `C:\casa_paraiso` is the intended read-only Docker Desktop source:

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

The local PostgreSQL service retains a separate database and volume. Migrate its empty schema, preview the transfer, apply it once, then run the read-only comparison and integrity audit:

```powershell
.\scripts\casa-docker.ps1 compose exec -T --user sail laravel.test sh -lc 'DB_CONNECTION=pgsql DB_HOST=pgsql DB_PORT=5432 DB_DATABASE=casa_paraiso DB_USERNAME=sail DB_PASSWORD=password php artisan migrate --force'
.\scripts\casa-docker.ps1 compose exec -T --user sail laravel.test php artisan casa:transfer-to-postgres
.\scripts\casa-docker.ps1 compose exec -T --user sail laravel.test php artisan casa:transfer-to-postgres --apply
.\scripts\casa-docker.ps1 compose exec -T --user sail laravel.test php artisan casa:transfer-to-postgres --validate
.\scripts\casa-docker.ps1 compose exec -T --user sail laravel.test sh -lc 'DB_CONNECTION=pgsql DB_HOST=pgsql DB_PORT=5432 DB_DATABASE=casa_paraiso DB_USERNAME=sail DB_PASSWORD=password php artisan casa:audit-crud-integrity'
```

`casa:transfer-to-postgres` reads `migration_source` and `migration_target` from the uncommitted environment. It is dry-run-first, uses a read-only source transaction, refuses target business data, copies identity and business tables in foreign-key order, preserves IDs and credential hashes, repairs PostgreSQL sequences, and never transfers runtime sessions, tokens, queues, caches, reset tokens, migration history, or obsolete adjustment data. A fresh migration creates five deterministic RFM preset rows; the command recognizes only that exact migration baseline and replaces it transactionally during `--apply`. `--validate` performs a full-row comparison and sequence check without writing.

For Supabase, set `MIGRATION_TARGET_DB_URL` to the migration/import account's Supavisor session-pooler connection string and keep `MIGRATION_TARGET_DB_SSLMODE=require`. Never commit that URL or expose it to the APK.

## Daily Commands

```powershell
# Start the daemon keepalive and project services
.\scripts\casa-docker.ps1 start

# Inspect services
.\scripts\casa-docker.ps1 status

# Pass any Compose arguments through the verified engine
.\scripts\casa-docker.ps1 compose ps --all
.\scripts\casa-docker.ps1 compose exec -T --user sail laravel.test php artisan migrate
.\scripts\casa-docker.ps1 compose exec -T laravel.test npm run build
.\scripts\casa-docker.ps1 compose exec -T --user sail laravel.test php artisan test

# Stop services, daemon, and WSL keepalive
.\scripts\casa-docker.ps1 stop
```

Do not use bare `docker compose` for this repository. The wrapper is the engine-isolation guard.

## Quick Tunnel and Secure Pairing

Start a demo session:

```powershell
.\scripts\mobile-demo.ps1 -Action Start
```

The helper starts the dedicated stack and tunnel, temporarily hardens the ignored `.env`, validates `/api/v1/meta`, issues one eight-digit code valid for five minutes, prints the rotating URL and Google callback, and sends a `casaparaiso://pair` deep link when exactly one ADB device is available.

Pairing codes are HMAC-digested in the file cache, bound to the current server UUID and tunnel host, single-use, rate-limited, and never stored by the APK. The app accepts only exact HTTPS `*.trycloudflare.com` origins and persists only the verified URL, instance UUID, and pairing timestamp in Capacitor Preferences.

Rotate or inspect the demo:

```powershell
.\scripts\mobile-demo.ps1 -Action Rotate
.\scripts\mobile-demo.ps1 -Action Status
```

Always stop after the demonstration. This closes the public tunnel, restores the prior `.env` values, clears Laravel configuration, stops services and the daemon, and removes the WSL keepalive:

```powershell
.\scripts\mobile-demo.ps1 -Action Stop
```

A Quick Tunnel has no uptime guarantee. The bundled APK UI remains installed and usable as an app shell, but a rotated or stopped backend requires a new URL and code.

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

The dedicated local `.env` uses:

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
MIGRATION_SOURCE_DB_HOST=mariadb
MIGRATION_SOURCE_DB_DATABASE=casa_paraiso
MIGRATION_TARGET_DB_HOST=pgsql
MIGRATION_TARGET_DB_DATABASE=casa_paraiso
MIGRATION_TARGET_DB_SSLMODE=prefer
MAIL_HOST=mailpit
MAIL_PORT=1025
FORWARD_MAILPIT_PORT=11026
FORWARD_MAILPIT_DASHBOARD_PORT=18025
```

Secrets, the generated server UUID, pairing state, dumps, build outputs, and local Android SDK paths are ignored.

## Deployment Boundary

Docker and the Quick Tunnel are the approved demonstration backend, not the final hosted production backend. The Android UI is bundled into the APK and must never use Capacitor `server.url` to wrap a remote web page. Local PostgreSQL portability and transfer are verified; provisioning the dedicated Supabase project, production hosting, release signing, and physical-device acceptance remain delivery milestones.

XAMPP remains a fallback for inherited browser comparison only. It must not share or replace the dedicated project database.
