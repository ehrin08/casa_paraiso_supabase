# Casa Paraiso Tech Stack

## Approved Stack

Casa Paraiso should be built as a production-ready Laravel monolith that stays compatible with Hostinger shared/web hosting and local XAMPP development.

- Backend: Laravel 12
- Runtime: PHP 8.2 or higher
- Views: Laravel Blade server-rendered templates
- Frontend styling: Tailwind CSS v4 through Vite
- Database: MariaDB/MySQL
- Database workflow: Laravel migrations and seeders
- Package management: Composer for PHP dependencies, npm for frontend build dependencies
- Production hosting: Hostinger shared/web hosting by default

## Frontend Decision

Use Tailwind CSS instead of Bootstrap for the MVP frontend.

Rationale:

- Tailwind supports a more custom commercial-grade visual identity for Casa Paraiso.
- Tailwind works well with Laravel Blade and Vite.
- Tailwind allows the team to build polished admin, staff, and customer interfaces without adopting a full SPA.
- The extra npm/Vite build step is acceptable because Node is only used during development and asset compilation.

Bootstrap is not selected for the MVP frontend because it prioritizes fastest CRUD scaffolding and built-in components over a more custom product interface.

## Deferred Frontend Options

Do not introduce these unless explicitly approved later:

- Inertia
- React
- Vue
- Livewire
- External UI kits
- Persistent Node.js runtime in production

Use targeted JavaScript only where it directly improves a workflow, such as appointment filters, availability checks, charts, confirmation dialogs, or report filtering.

## Database And Data Workflow

- Use Laravel migrations as the source of truth for schema changes.
- Use Laravel seeders for initial roles, default settings, sample services, and demo data.
- Keep schema compatible with MariaDB/MySQL for Hostinger.
- Provide SQL exports when needed for Hostinger/phpMyAdmin handover, but do not use manual phpMyAdmin edits as the primary development workflow.

## Deployment Notes

- Compile production frontend assets with `npm run build`.
- Deploy compiled assets from Laravel's public build output.
- Do not require Node.js to run on Hostinger production for the MVP.
- Keep `.env` credentials out of committed source files.
- Hostinger must point web requests to Laravel's `public/index.php` entrypoint or an equivalent safe shared-hosting configuration.
- Run Laravel production optimization commands during deployment when supported by the hosting environment.

## Pre-Scaffold Checks

Before scaffolding Laravel:

- Update Composer to the latest stable version.
- Confirm PHP is 8.2 or higher.
- Confirm required PHP extensions are enabled for Laravel, including `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `mbstring`, `openssl`, `pdo`, `pdo_mysql`, `session`, `tokenizer`, and `xml`.
- Confirm Node and npm are available for Vite and Tailwind asset builds.
- Confirm MySQL/MariaDB is available locally through XAMPP.

Current local environment observed during planning:

- PHP: 8.2.12
- Composer: 2.9.7
- Node: 24.15.0
- npm: 11.12.1

## Verification Commands After Scaffolding

Use these commands once the Laravel project exists:

```bash
composer install
npm install
npm run build
php artisan migrate --seed
php artisan test
```

## References

- Laravel 12 deployment: https://laravel.com/docs/12.x/deployment
- Laravel 12 release support: https://laravel.com/docs/12.x/releases
- Tailwind Laravel guide: https://tailwindcss.com/docs/guides/laravel
- Bootstrap quick start: https://getbootstrap.com/docs/5.3/getting-started/introduction/
