# Casa Paraiso Security Hardening Checklist

## Purpose And Status

This checklist is the release gate for the Laravel application and Android-backed mobile variant. The application baseline and the Supabase database cutover are implemented. Remaining production-host, Google-provider, retention, restore, and handover items must still be accepted before launch.

Database work follows the account-preservation and environment rules in `AGENTS.md`; this checklist does not override them.

## Implemented Application Controls

- [x] Require authentication, active-account status, verified email, and explicit role middleware for authenticated workspaces.
- [x] Keep Super Administrator identity protection separate from ordinary Admin authorization.
- [x] Protect state-changing forms with Laravel CSRF middleware and validate writes through Form Requests or equivalent controller validation.
- [x] Rotate sessions during authentication and invalidate sessions through the existing account-security workflows.
- [x] Use Laravel password hashing, password reset, email verification, and time-limited Google identity confirmation.
- [x] Apply named rate limiters to registration, password recovery/reset, password confirmation/update, Google identity callbacks, and account deletion flows.
- [x] Keep mobile Google OAuth in the system browser with device/server-bound state, SHA-256 PKCE, five-minute single-use exchange codes, and no bearer token in callback URLs.
- [x] Add `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, and `Cross-Origin-Opener-Policy` headers to browser responses.
- [x] Support HTTPS URL enforcement and HSTS through environment-controlled settings. HSTS is emitted only for secure requests when enabled.
- [x] Support explicit production host allow-listing through `TRUSTED_HOSTS`.
- [x] Keep environment files, local backups, and generated testing artifacts outside version control.
- [x] Keep production demo seeding disabled in `DatabaseSeeder`.
- [x] Restrict Admin Settings to Admin and Super Administrator; keep user provisioning exclusive to the protected Super Administrator.
- [x] Keep Supabase application data in the private `casa` schema and deny `PUBLIC`, `anon`, `authenticated`, and `service_role` access.
- [x] Separate `casa_migrator` schema ownership from the DML-only `casa_runtime` Laravel role; the runtime DDL denial is acceptance-tested.
- [x] Use the Sydney Supavisor session pooler on port 5432 with `verify-full`, the Supabase CA, and project-level SSL enforcement.
- [x] Disable the unused Supabase Data API and keep Supabase Auth, Storage, Realtime, and privileged keys out of the APK.
- [x] Keep hybrid sentiment model artifacts local and versioned; customer feedback is not sent to hosted AI services or exported without redaction.

## Production Environment Gate

Set and verify these values on the production host. Do not copy local secrets or commit the production `.env` file.

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-approved-domain.example
FORCE_HTTPS=true
HSTS_ENABLED=false
TRUSTED_HOSTS=your-approved-domain.example,www.your-approved-domain.example

SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_ENCRYPT=true

LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=aws-0-ap-southeast-2.pooler.supabase.com
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=casa_runtime.pnichczvgkdxnhcezqyn
DB_SEARCH_PATH=casa,public
DB_SSLMODE=verify-full
DB_SSLROOTCERT=/var/www/html/storage/app/private/supabase/prod-ca-2021.crt
```

- [ ] Generate a unique production `APP_KEY`; never reuse a published or sample key.
- [x] Use the least-privilege `casa_runtime` account scoped to the private `casa` schema.
- [ ] Configure the Render proof-of-concept secrets only in the hosting environment: `APP_KEY`, protected Super Administrator email, Supavisor runtime credentials, a stable `MOBILE_INSTANCE_ID`, and the Supabase CA secret file at `/etc/secrets/supabase-prod-ca-2021.crt`. Keep Google credentials unset and `MAIL_MAILER=array` for the password-login pilot.
- [ ] Verify Render HTTPS, `/up`, `/api/v1/meta`, password login, role reads, and logout before enabling HSTS. Keep HSTS disabled until every approved host works over HTTPS.
- [ ] Point the web document root to Laravel's `public` directory; application source, `.env`, storage data, and vendor internals must not be web-accessible.
- [ ] Confirm storage and bootstrap cache directories are writable without broad world-writable permissions.
- [ ] Run `composer install --no-dev --optimize-autoloader` and `php artisan optimize` on the Linux production environment after configuration is complete.
- [ ] Verify error pages reveal no stack traces, credentials, absolute paths, or customer data.

Laravel's deployment guidance requires the web server to direct requests to `public/index.php`, recommends production optimization, and warns that `APP_DEBUG` must be `false` in production: <https://laravel.com/docs/12.x/deployment>.

## Hosting And Data Protection Gate

- [x] Create checksumed, ignored MariaDB pre-cutover and Supabase post-cutover exports.
- [x] Keep the cutover MariaDB database frozen as a read-only rollback source.
- [ ] Agree the ongoing Supabase export/backup retention period and protected off-machine storage location.
- [ ] Complete one restore rehearsal in a non-production environment before launch.
- [ ] Store manual SQL exports outside the public web root with restricted access and an agreed retention/deletion schedule.
- [ ] Confirm production logs do not contain passwords, reset tokens, OAuth tokens, session IDs, full payment notes, or unnecessary customer data.
- [ ] Review Admin and Super Administrator accounts before release; deactivate unused accounts and remove stale OAuth credentials.
- [ ] Verify rate limiting, mail delivery, password reset, email verification, Google sign-in, logout, and session revocation on the production domain.
- [ ] Record the deployed commit, migration batch, build date, PHP version, and rollback/restore contact for each release.

## Content Security Policy Decision

A strict Content Security Policy is deferred until inline Alpine expressions and other inline behavior are migrated to a nonce-compatible policy and tested across all four workspaces. Do not deploy an untested CSP that breaks booking, navigation, modals, calendars, or payment workflows. The current header baseline still blocks MIME sniffing and framing while applying conservative browser permissions.

## Verification

Run application checks against the isolated test database:

```powershell
.\scripts\casa-docker.ps1 compose exec -T --user sail laravel.test php artisan test
.\scripts\casa-docker.ps1 compose exec -T laravel.test npm run build
.\scripts\casa-docker.ps1 compose exec -T --user sail laravel.test php artisan view:cache
.\scripts\casa-docker.ps1 compose exec -T --user sail laravel.test php artisan view:clear
.\scripts\casa-docker.ps1 compose exec -T laravel.test vendor/bin/pint --test
```

Representative coverage includes `SecurityHardeningTest`, `RoleWorkspaceTest`, `AuthenticatedWorkspaceSmokeTest`, authentication tests, and role-specific workflow suites.

Migration execution is deliberately separate. Identify the target database and exact account-preserving command first. Production schema changes must use `--database=migration_target` as `casa_migrator`; ordinary Laravel requests run as `casa_runtime`. Stop for direction only when no account-preserving approach exists.

After any production schema change, recheck Supabase security and performance advisors. The 2026-07-17 cutover closed all security and unindexed-foreign-key findings; immediate `unused_index` notices are expected until the new database receives representative workload statistics.

Laravel supports named route rate limiters and `throttle` middleware as used by this application: <https://laravel.com/docs/12.x/routing#rate-limiting>. Trusted proxy and host configuration is documented in Laravel's request guidance: <https://laravel.com/docs/12.x/requests#configuring-trusted-hosts>.
