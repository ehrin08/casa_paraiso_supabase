# Casa Paraiso Mobile and Supabase Plan

## Goal

Deliver the complete Casa Paraiso system as a mobile-first Android application while preserving the existing Laravel business rules and all customer, receptionist, therapist, admin, and super-administrator workflows.

The release consists of a signed Capacitor APK with a bundled Vue frontend, a versioned Laravel JSON API, and a dedicated Supabase PostgreSQL database. The existing Blade application remains available as a fallback and regression reference.

## Implemented Foundation (2026-07-15)

- A dedicated Ubuntu 24.04 WSL2 Docker Engine and guarded PowerShell workflow isolate this repository from Docker Desktop and the inherited project.
- The isolated MariaDB demo database has an account-preserving, read-only-source clone workflow with source/destination account-count verification.
- `/api/v1/meta` implements URL-only pairing with exact Quick Tunnel host validation, stable server UUID binding, CORS restrictions, and rate limiting. The app accepts the bare tunnel URL or its APK download link; no pairing PIN or verification endpoint is required.
- Laravel Sanctum now provides `POST /api/v1/auth/login`, `GET /api/v1/auth/me`, and `POST /api/v1/auth/logout`. Email/password login issues a scoped, 30-day device token; inactive and unverified accounts are rejected, and tokens are revoked on logout and security-sensitive identity changes.
- `mobile/` is a bundled Vue 3/TypeScript/Tailwind/Pinia/Capacitor 8 Android project. It validates server metadata, accepts manual or `casaparaiso://pair` input, stores pairing state in Preferences, stores the bearer token in Android Keystore-backed secure storage, and routes authenticated users to a role-specific workspace shell without a remote `server.url`.
- The customer APK workspace now covers Appointments, Feedback, and Profile through its persistent bottom navigation. It lists owned appointments with fixed pagination and transactional cancellation; books from live availability with therapist preferences, paid add-ons, and RFM vouchers; submits one feedback record per eligible completed visit through the existing sentiment classifier; updates contact/profile fields while keeping email read-only; and changes an existing password with full device-token revocation. The matching `/api/v1/customer` and `/api/v1/auth/password` endpoints enforce role, ownership, validation, and stable error envelopes.
- The receptionist APK workspace now covers Today, Bookings, Customers, and Payments through persistent bottom navigation. It provides front-desk metrics and schedule context; appointment search, creation, editing, therapist availability, outcome and completion operations; customer contact/history search and editing; and manual transaction search, creation, and editing. The matching `/api/v1/reception` endpoints reuse the Laravel domain rules and enforce receptionist-only access.
- The therapist APK workspace now covers Today, Schedule, Guests, and Earnings through persistent bottom navigation. It provides personal metrics and agenda context; assigned appointment search/detail with no-show and atomic completion actions; served-customer treatment history; related feedback and payment records; and personal pending/paid commission totals and history. The matching `/api/v1/staff` endpoints enforce assignment ownership and keep customer, feedback, payment, and commission access read-only.
- The admin and super-administrator APK workspace now covers Today, Ops, Manage, Insights, and Control. It provides operational dashboards; booking, customer, and payment management; therapist, service, schedule, exception, and weekly-roster management; feedback, rewards, commissions, and native-share CSV reports; business settings; and protected super-administrator-only user access. The matching `/api/v1/admin` endpoints enforce admin access, while user provisioning and role/activation changes remain restricted to the configured protected super administrator.
- Local PostgreSQL 17 runs beside MariaDB on the dedicated engine. The Laravel suite passes on PostgreSQL, MySQL-only expressions are portable, and `casa:transfer-to-postgres` provides dry-run, guarded apply, full-row validation, and sequence verification. A local transfer preserved all 20 accounts and related business records and passed the read-only CRUD integrity audit.
- Mobile Google authentication now uses the system browser, server/device-bound state, SHA-256 PKCE, a five-minute single-use exchange code, secure verifier storage, and the `casaparaiso://oauth/callback` deep link. Only the final Laravel Sanctum token enters secure device storage; neither bearer nor Google authorization tokens appear in callback URLs.
- Release signing is configured for version `1.0.0` (code `1`). A 4096-bit release key is stored outside Git under the Windows user profile, and the repeatable build helper synchronizes Capacitor, assembles the universal APK, verifies Android v2/v3 signatures, writes a checksum, and optionally installs through ADB.
- `scripts/mobile-demo.ps1` owns tunnel start, rotation, environment hardening/restoration, metadata checks, temporary signed-APK browser delivery, URL-only optional ADB pairing, and shutdown.
- Pairing does not grant an authenticated application session; it only identifies the backend. The user accepted Android API 34 emulator validation for this delivery; dedicated Supabase provisioning/cutover, live Google-provider acceptance, and secure signing-key backup remain later milestones.

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
- Release APKs accept only HTTPS `*.trycloudflare.com` backend URLs and validate `/api/v1/meta` before saving the paired address.
- Store the paired URL in Capacitor Preferences; store no sensitive API response data locally and queue no offline mutations.
- Support secure storage, Browser, App/deep links and Android back, Preferences, Splash Screen, Status Bar, Keyboard, Network, Haptics, Filesystem, and Share.
- Add a demo helper that discovers the current quick-tunnel URL, validates the API, prints the current Google callback URI, and pairs a USB-connected phone through `adb`.
- Re-pair the APK and update the authorized Google callback whenever the quick-tunnel hostname changes.

## PostgreSQL and Data Migration

- Create a dedicated Supabase project named `Casa Paraiso Mobile` in Singapore after confirming the current provider cost.
- Disable the Data API and use a restricted Laravel runtime database role plus a separate migration/import account.
- Connect the Laravel backend through the Supavisor session pooler with verified TLS.
- Add PostgreSQL 17 to local Docker development while retaining the inherited MariaDB service and volume for migration and rollback.
- Replace the confirmed MySQL-only `MINUTE(...) MOD` and `FIELD(...)` expressions with portable queries.
- Apply the pending account-preserving roster migration to MariaDB and take a consistent backup before transfer.
- Add a dry-run-first Artisan transfer command. It must require an empty target, preserve IDs and account credentials, insert in foreign-key order inside a transaction, reset PostgreSQL sequences, and never modify the source database.
- Transfer business and identity data. Exclude sessions, caches, queues, failed jobs, reset tokens, migration history, and the obsolete `transaction_adjustments` table.
- Validate table counts, foreign keys, sequences, password and Google identities, scheduling capacity, financial totals, rewards, and representative records before cutover.

Local implementation and verification are complete for the PostgreSQL service, portable queries, transfer command, full-row/source identity comparison, sequence alignment, and integrity audit. A freshly migrated target contains only five deterministic migration-owned RFM presets; the command recognizes that exact baseline as non-business data and replaces it inside the target transaction. The production run still requires a consistent MariaDB backup and an approved empty dedicated Supabase project in Singapore.

## Delivery and Acceptance

- Keep the inherited web application functional throughout the migration.
- Run the Laravel suite against PostgreSQL and web regression coverage against MariaDB.
- Add API authorization and contract tests for every role, authentication method, conflict path, export, CORS rule, and pairing flow.
- Add Vue unit/component tests, Playwright role workflows, accessibility checks, Android lint/tests, and a physical-device or explicitly approved emulator smoke test.
- Current verification covers 253 Laravel tests and 1,835 assertions, local full-row migration validation and CRUD integrity, 19 mobile unit tests, responsive Playwright role workflows at small-phone, Pixel 7, landscape, and tablet viewports with axe accessibility scans across every main tab, modal focus checks and visual snapshots, the mobile production build, Capacitor Android synchronization, Android release unit/lint gates, a universal release APK signed with Android v2/v3 schemes, and phone-sized Quick Tunnel acceptance for customer, receptionist, therapist, admin, and protected super-administrator workspaces. On 2026-07-15, the signed release was installed on an Android API 34 emulator and accepted with URL-only connection, password sign-in, live admin navigation, and persisted pairing/authentication. Dedicated Supabase cutover, live Google-provider acceptance, and signing-key backup are still required.
- Demonstrate customer booking/rewards/feedback; receptionist bookings/payments; therapist schedule/outcomes/commissions; admin operations/reports/export; and super-admin user access entirely through the APK.
- Build a signed universal APK version `1.0.0` with version code `1`. Keep the keystore outside Git and publish the APK checksum with the installation and demo runbook.

## Initial Boundaries

- Android only; iOS and Play Store/AAB publishing are deferred.
- Push notifications, biometrics, sensitive offline caching, and queued offline writes are deferred.
- The mobile app is online-only with clear connection, failure, and retry states.
- The inherited MariaDB database remains available for rollback until the Supabase migration is accepted.
