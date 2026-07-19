# Casa Paraiso Mobile and Supabase Plan

## Goal

Deliver the complete Casa Paraiso system as a mobile-first Android application while preserving the existing Laravel business rules and all customer, receptionist, therapist, admin, and super-administrator workflows.

The release consists of a signed Capacitor APK with a bundled Vue frontend, a versioned Laravel JSON API, and a dedicated Supabase PostgreSQL database. The existing Blade application remains available as a fallback and regression reference.

## Implemented Foundation (2026-07-17)

- Docker Desktop's `desktop-linux` engine is now the primary local runtime. The guarded PowerShell workflow fixes the project name to `casa-paraiso-supabase-desktop`, while the former dedicated Ubuntu 24.04 WSL2 engine remains a stopped rollback copy.
- The isolated MariaDB demo database has an account-preserving, read-only-source clone workflow with source/destination account-count verification.
- `/api/v1/meta` exposes the stable server UUID binding, CORS restrictions, and rate limiting. Every Android/browser build validates the compiled HTTPS Render origin automatically; Quick Tunnel remains browser/API-only. No pairing PIN or verification endpoint is required.
- Laravel Sanctum now provides `POST /api/v1/auth/login`, `GET /api/v1/auth/me`, and `POST /api/v1/auth/logout`. Email/password login issues a scoped, 30-day device token; inactive and unverified accounts are rejected, and tokens are revoked on logout and security-sensitive identity changes.
- `mobile/` is a bundled Vue 3/TypeScript/Tailwind/Pinia/Capacitor 8 Android project. Every build validates, saves, and uses `VITE_BACKEND_URL` at startup, defaults to the stable Render endpoint, replaces any old tunnel state, has no manual server entry or pairing deep links, stores the bearer token in Android Keystore-backed secure storage, and routes authenticated users to a role-specific workspace shell without a remote `server.url`.
- The customer APK workspace now covers Appointments, Feedback, and Profile through its persistent bottom navigation. It lists owned appointments with fixed pagination and transactional cancellation; books from live availability with therapist preferences, paid add-ons, and RFM vouchers; submits one feedback record per eligible completed visit through the existing sentiment classifier; updates contact/profile fields while keeping email read-only; and changes an existing password with full device-token revocation. The matching `/api/v1/customer` and `/api/v1/auth/password` endpoints enforce role, ownership, validation, and stable error envelopes.
- The receptionist APK workspace now covers Today, Bookings, Customers, and Payments through persistent bottom navigation. It provides front-desk metrics and schedule context; appointment search, creation, editing, therapist availability, outcome and completion operations; customer contact/history search and editing; and manual transaction search, creation, and editing. The matching `/api/v1/reception` endpoints reuse the Laravel domain rules and enforce receptionist-only access.
- The therapist APK workspace now covers Today, Schedule, Guests, and Earnings through persistent bottom navigation. It provides personal metrics and agenda context; assigned appointment search/detail with no-show and atomic completion actions; served-customer treatment history; related feedback and payment records; and personal pending/paid commission totals and history. The matching `/api/v1/staff` endpoints enforce assignment ownership and keep customer, feedback, payment, and commission access read-only.
- The admin and super-administrator APK workspace now covers Today, Ops, Manage, Insights, and Control. It provides operational dashboards; booking, customer, and payment management; therapist, service, schedule, exception, and weekly-roster management; feedback, rewards, commissions, and native-share CSV reports; business settings; and protected super-administrator-only user access. The matching `/api/v1/admin` endpoints enforce admin access, while user provisioning and role/activation changes remain restricted to the configured protected super administrator.
- Local PostgreSQL 17 runs beside MariaDB on Docker Desktop for isolated tests and portability rehearsal. The Laravel suite passes on PostgreSQL, MySQL-only expressions are portable, and `casa:transfer-to-postgres` provides dry-run, guarded apply, in-transaction full-row validation, and sequence verification. The 2026-07-17 production cutover copied the current MariaDB source directly to the private `casa` schema in Supabase, preserving all 21 application accounts and related business records.
- Mobile Google authentication now uses the system browser, server/device-bound state, SHA-256 PKCE, a five-minute single-use exchange code, secure verifier storage, and the `casaparaiso://oauth/callback` deep link. Only the final Laravel Sanctum token enters secure device storage; neither bearer nor Google authorization tokens appear in callback URLs.
- Release signing is configured for version `1.0.1` (code `2`). A 4096-bit release key is stored outside Git under the Windows user profile, and the repeatable build helper requires the stable Render HTTPS origin, synchronizes Capacitor, assembles the universal APK, verifies Android v2/v3 signatures, writes a checksum, and optionally installs through ADB.
- `scripts/mobile-demo.ps1` owns browser/API-only Quick Tunnel start, rotation, environment hardening/restoration, metadata checks, and shutdown.
- Pairing does not grant an authenticated application session; it only identifies the backend. The user accepted Android API 34 emulator validation for this delivery. Supabase provisioning and cutover are complete; live Google-provider acceptance and secure signing-key backup remain later milestones.

## Architecture

- Create an isolated `mobile/` application using Vue 3, TypeScript, Vite, Tailwind CSS, Vue Router, Pinia, Axios, Vitest, and Playwright.
- Use Capacitor 8 with Android application ID `com.casaparaiso.mobile`, minimum API 24, and compile/target API 36.
- Bundle the frontend into the APK through Capacitor `webDir`; release builds must not depend on a remotely hosted web UI.
- Keep Laravel as the sole business-logic and data-access authority. Mobile API controllers must call existing services, policies, validation, and database transactions.
- Use Supabase only for managed PostgreSQL. Do not use Supabase Auth, Data API, Storage, Realtime, Edge Functions, or privileged keys in the APK.
- Preserve the Casa Paraiso brand palette, typography, and official logo. Mobile screens use single-column layouts, agenda-first calendars, card-based lists, safe areas, and at least 48dp touch targets.

## API and Authentication

- Install Laravel Sanctum and expose a versioned `/api/v1` API.
- Provide API resources for authentication, profile, dashboard, services, appointments, availability, customers, staff, schedules, rosters, transactions, commissions, promotions, feedback, reports, settings, and user access.
- Keep server-controlled pagination at 15 records. Serialize money as two-decimal strings and timestamps with Asia/Manila offsets.
- Use stable JSON error codes with HTTP `401`, `403`, `409`, `422`, `429`, and `503` semantics.
- Store per-device bearer tokens in Android Keystore-backed secure storage. Tokens expire after 30 days and are revoked on logout, password reset, account deactivation, or security-sensitive identity changes.
- Preserve email/password registration, verification, recovery, and Google authentication.
- Google login uses the system browser, PKCE, a five-minute single-use exchange code, and the `casaparaiso://oauth/callback` deep link. Bearer tokens must never appear in callback URLs.

## Tunnel Pairing and Native Features

- Add `GET /api/v1/meta` with a stable Casa Paraiso application identity, API version, timezone, server time, and supported authentication methods.
- All Android builds accept only their compiled HTTPS Render origin and validate `/api/v1/meta` before saving it. Quick Tunnel URLs are not accepted by the APK.
- Store the paired URL in Capacitor Preferences; store no sensitive API response data locally and queue no offline mutations.
- Support secure storage, Browser, App/deep links and Android back, Preferences, Splash Screen, Status Bar, Keyboard, Network, Haptics, Filesystem, and Share.
- Add a browser/API demo helper that discovers the current Quick Tunnel URL, validates the API, and prints the current Google callback URI.
- Update the authorized Google callback whenever the Quick Tunnel hostname changes; Android endpoint configuration remains the stable Render URL.

## PostgreSQL and Data Migration

- Use the existing **Casa Paraiso** Supabase project (`pnichczvgkdxnhcezqyn`) in Sydney (`ap-southeast-2`). This approved existing target replaces the earlier Singapore/project-creation plan; the other Supabase project remains untouched.
- Keep application objects in the private `casa` schema owned by `casa_migrator`. Laravel runs as `casa_runtime`, which has schema usage, table DML, and sequence usage but no schema DDL, role/database creation, or RLS bypass.
- Keep `PUBLIC`, `anon`, `authenticated`, and `service_role` denied on `casa`. Supabase Auth remains unused, and the Data API is disabled with zero exposed schemas.
- Connect both Laravel database roles through the Sydney Supavisor session pooler on port `5432` with `sslmode=verify-full`, Supabase's CA certificate, and project-level SSL enforcement.
- Add PostgreSQL 17 to local Docker development while retaining the inherited MariaDB service and volume for migration and rollback.
- Replace the confirmed MySQL-only `MINUTE(...) MOD` and `FIELD(...)` expressions with portable queries.
- Apply the pending account-preserving roster migration to MariaDB and take a consistent backup before transfer.
- Add a dry-run-first Artisan transfer command. It must require an empty target, preserve IDs and account credentials, insert in foreign-key order inside a transaction, reset PostgreSQL sequences, complete row/count/identity/sequence validation before commit, and never modify the source database.
- Transfer business and identity data. Exclude sessions, caches, queues, failed jobs, reset tokens, migration history, and the obsolete `transaction_adjustments` table.
- Validate table counts, foreign keys, sequences, password and Google identities, scheduling capacity, financial totals, rewards, and representative records before cutover.

The production cutover completed on 2026-07-17 from the current Docker Desktop MariaDB source, not the stale local PostgreSQL rehearsal copy. A consistent MariaDB dump and SHA-256 checksum were created during maintenance; migrations ran as `casa_migrator`; dry-run, transactional apply, and independent post-commit validation all passed; and a checksumed post-cutover Supabase export was created. The command accepts only the five deterministic migration-owned RFM presets on an otherwise empty target and now rolls back the complete import if any row, count, identity, or sequence validation differs before commit.

## Delivery and Acceptance

- Keep the inherited web application functional throughout the migration.
- Run the Laravel suite against PostgreSQL and web regression coverage against MariaDB.
- Add API authorization and contract tests for every role, authentication method, conflict path, export, CORS rule, and pairing flow.
- Add Vue unit/component tests, Playwright role workflows, accessibility checks, Android lint/tests, and a physical-device or explicitly approved emulator smoke test.
- Current verification covers 255 Laravel tests and 1,845 assertions on the isolated local PostgreSQL test database, 19 mobile unit tests, web and mobile production builds, Capacitor Android synchronization, successful Android debug assembly, production full-row transfer validation and CRUD integrity, and live HTTPS smoke tests for administrator, staff, and customer login/logout plus booking, transaction, feedback, promotion, report, and tunnel metadata reads. Responsive Playwright role workflows, axe scans, Android release gates, the signed universal APK, and the 2026-07-15 Android API 34 emulator acceptance remain valid. Live Google-provider acceptance and signing-key backup are still required.
- Demonstrate customer booking/rewards/feedback; receptionist bookings/payments; therapist schedule/outcomes/commissions; admin operations/reports/export; and super-admin user access entirely through the APK.
- Build a signed universal APK version `1.0.1` with version code `2`, passing `-BackendUrl https://<actual-service>.onrender.com`. Keep the keystore outside Git and publish the APK checksum with the installation runbook.

## Initial Boundaries

- Android only; iOS and Play Store/AAB publishing are deferred.
- Push notifications, biometrics, sensitive offline caching, and queued offline writes are deferred.
- The mobile app is online-only with clear connection, failure, and retry states.
- The frozen MariaDB database remains a read-only rollback source until the Supabase cutover is accepted. Before any new Supabase business write, rollback is an environment switch; afterward it requires a reviewed reverse-data migration.
