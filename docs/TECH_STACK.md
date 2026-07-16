# Casa Paraiso Tech Stack

## Approved Stack

This standalone repository delivers a mobile-first Android application while retaining the production-ready Laravel monolith and Blade UI as its API/business-logic backend and fallback interface. Earlier Hostinger/MariaDB notes describe the inherited baseline, not this repository's final database target.

- Backend: Laravel 12
- Runtime: PHP 8.2 or higher
- Views: Laravel Blade server-rendered fallback plus a bundled Vue 3/TypeScript mobile client
- Frontend styling: Tailwind CSS v4 through Vite in both web and mobile builds
- Mobile runtime: Capacitor 8 Android application, bundled locally with no remote `server.url`
- Database: the existing Casa Paraiso Supabase PostgreSQL 17 project in Sydney, private `casa` schema
- Database workflow: Laravel migrations and seeders
- Package management: Composer for PHP dependencies, npm for frontend build dependencies
- Authentication: Laravel Breeze email/password authentication with email verification, plus Laravel Socialite Google OAuth
- Primary local development: Docker Desktop with Laravel Sail services managed through `scripts/casa-docker.ps1`
- Fallback local development: XAMPP / Apache
- Authentication authority: Laravel Breeze/Socialite and Sanctum; Supabase Auth remains unused
- Demonstration backend: Docker Desktop Laravel through a rotating Cloudflare Quick Tunnel
- Inherited fallback: Blade plus MariaDB/MySQL on XAMPP or compatible hosting

## Frontend Decision

Use Tailwind CSS instead of Bootstrap for the MVP frontend.

Rationale:

- Tailwind supports a more custom commercial-grade visual identity for Casa Paraiso.
- Tailwind works well with Laravel Blade and Vite.
- Tailwind allows the team to build polished admin, staff, and customer interfaces without adopting a full SPA.
- The extra npm/Vite build step is acceptable because Node is only used during development and asset compilation.

Bootstrap is not selected for the MVP frontend because it prioritizes fastest CRUD scaffolding and built-in components over a more custom product interface.

## Frontend Boundaries

Vue 3 is approved only for the bundled `mobile/` Capacitor application. The inherited Blade frontend remains server-rendered. Do not introduce these into the Blade application unless explicitly approved later:

- Inertia
- React
- Livewire
- External UI kits
- Persistent Node.js runtime in production

Use targeted JavaScript only where it directly improves a workflow, such as appointment filters, availability checks, charts, confirmation dialogs, or report filtering.

Turbo Drive 8 is approved as the targeted navigation enhancement for the Blade application. It is bundled into the compiled Vite assets and is enabled only for safe same-origin GET links and filter forms; state-changing forms, exports, and specialized panels retain normal Laravel behavior.

## Authenticated Workspace UI

- Keep the application server-rendered. Alpine.js may manage local disclosure and calendar state, but URLs, filtering, sorting, and pagination remain normal Laravel requests.
- Use the shared Blade components for workspace consistency: `page-heading`, `stat-strip`, `list-toolbar`, `table-shell`, `app-card`, and `metric-card`.
- Laravel's default paginator view is `pagination.compact`. `casa.pagination.per_page` is the single server-controlled page size and is fixed at 15 records; controllers preserve active query state with `withQueryString()` and never honor a request `per_page` value.
- Screens with two record sets must use independent paginator query keys and, where useful, a fragment target. The Team & Services workspace uses `page` for staff and `services_page` with `#service-catalog` for services.
- Record-list filters collapse below the 1024px breakpoint while retaining accessible expanded state and active-filter counts. Tables and compact calendar date strips use labeled, keyboard-focusable overflow regions.
- Keep appointment calendars and other bounded operational previews unpaginated; they continue to read from role-scoped JSON feeds or their existing server-rendered collections.
- Follow `docs/BRAND_UI_GUIDE.md` for density, responsive behavior, interaction targets, typography, and accessibility details.

## Database And Data Workflow

- Use Laravel migrations as the source of truth for schema changes.
- Use Laravel seeders for initial roles, default settings, sample services, and demo data.
- Keep migrations portable enough for the frozen MariaDB rollback source while treating PostgreSQL as authoritative after cutover.
- Run Supabase migrations/imports as `casa_migrator`; run Laravel requests as DML-only `casa_runtime` through the session pooler with `verify-full` TLS.
- Keep the Data API disabled and deny Supabase API roles on the private `casa` schema. No database password, privileged key, or direct database access belongs in the APK.
- Preserve MariaDB as a read-only rollback source until cutover acceptance. Use checksumed exports and Laravel migrations rather than manual phpMyAdmin or dashboard schema edits.

## Deployment Notes

- Compile production frontend assets with `npm run build`.
- Deploy compiled assets from Laravel's public build output.
- Use Docker/Sail for local development and the approved demonstration backend; it is not the bundled Android UI.
- Do not require Node.js at Laravel runtime; Node is used only to compile web/mobile assets.
- Keep `.env` credentials out of committed source files.
- Set `APP_ENV=production` and `APP_DEBUG=false` in production.
- Any final Laravel host must point web requests to `public/index.php` and keep application source and credentials private.
- When Hostinger Terminal or SSH is available, run `composer install --no-dev --optimize-autoloader` followed by `php artisan optimize` after the production environment is configured.
- Do not build or upload Laravel configuration/view caches from Windows; production caches must be generated on the Linux hosting environment.
- Treat `docs/SECURITY_HARDENING.md` as the production release gate, including HTTPS, trusted-host, session-cookie, least-privilege database, backup, and restore checks.

## Local Development Checks

For primary Docker/Sail development:

- Confirm Docker Desktop is running with the `desktop-linux` context available.
- Start services with `.\scripts\casa-docker.ps1 start`.
- Use the Sail MariaDB service only for the frozen migration source/rollback comparison and the local PostgreSQL service for isolated tests and portability checks.
- Use `.\scripts\casa-docker.ps1 compose exec laravel.test ...` for Composer, npm, Artisan, migrations, and tests.
- Avoid `.\vendor\bin\sail.bat` on this machine unless Bash/WSL is repaired.

For XAMPP fallback:

- Confirm PHP is 8.2 or higher.
- Confirm required PHP extensions are enabled for Laravel.
- Confirm Node and npm are available for Vite and Tailwind asset builds.
- Confirm MySQL/MariaDB is available locally through XAMPP.

Current local environment observed during planning:

- PHP: 8.2.12
- Composer: 2.9.7
- Node: 24.15.0
- npm: 11.12.1
- Docker: 29.6.1
- Docker Compose: 5.1.3

## Verification Commands After Scaffolding

Use these commands once the Laravel project exists:

```powershell
.\scripts\casa-docker.ps1 start
.\scripts\casa-docker.ps1 compose exec -T laravel.test composer install
.\scripts\casa-docker.ps1 compose restart laravel.test
.\scripts\casa-docker.ps1 compose exec -T laravel.test npm install
.\scripts\casa-docker.ps1 compose exec -T laravel.test npm run build
.\scripts\casa-docker.ps1 compose exec -T --user sail laravel.test php artisan migrate --database=migration_target --force
.\scripts\casa-docker.ps1 compose exec -T --user sail laravel.test php artisan test
```

Demo seeding is restricted to local and testing environments. Production deployments must run migrations without `--seed` so predictable demo credentials are never installed or reset.

## References

- Laravel 12 deployment: https://laravel.com/docs/12.x/deployment
- Laravel 12 release support: https://laravel.com/docs/12.x/releases
- Tailwind Laravel guide: https://tailwindcss.com/docs/guides/laravel
- Bootstrap quick start: https://getbootstrap.com/docs/5.3/getting-started/introduction/
