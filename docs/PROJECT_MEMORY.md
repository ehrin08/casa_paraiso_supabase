# Casa Paraiso Project Memory

> Last reviewed: 2026-07-21
> Review basis: current repository working tree plus verified Casa Paraiso Supabase cutover
> Role: fast orientation for agents and developers; not an independent source of truth

## Purpose

This document is the shortest reliable path into the Casa Paraiso codebase. It summarizes the current application shape, durable decisions, important invariants, and the files most likely to matter for a task.

It does not eliminate source inspection. Use it to avoid a broad repository scan, then verify the behavior you will change in the relevant implementation, tests, and authoritative planning document.

## Required Reading Sequence

1. Read [`AGENTS.md`](../AGENTS.md) for mandatory safety, environment, and development rules.
2. Read this memory for project orientation and task routing.
3. Read the authoritative planning documents and current source files relevant to the task.
4. Read representative tests before changing established behavior.

Database work is permitted when it follows the account-preservation rule in `AGENTS.md`; this memory does not override that rule.

## Authority Map

| Question | Authority | Use |
| --- | --- | --- |
| What operations and constraints are mandatory? | [`AGENTS.md`](../AGENTS.md) | Safety gates, environment rules, deployment boundaries, and development conventions |
| What is approved for the standalone mobile variant? | [`MOBILE_SUPABASE_PLAN.md`](MOBILE_SUPABASE_PLAN.md) | Capacitor architecture, API boundary, Supabase migration, Android delivery, and acceptance |
| What should the MVP do? | [`MVP_SCOPE.md`](MVP_SCOPE.md) | Product scope, users, features, exclusions, and acceptance |
| Which stack and hosting assumptions are approved? | [`TECH_STACK.md`](TECH_STACK.md) | Runtime, frontend, authentication, deployment, and verification |
| What data shape and state vocabulary are intended? | [`DATABASE_DESIGN.md`](DATABASE_DESIGN.md) | Tables, relationships, statuses, indexes, and data rules |
| How should each role move through the product? | [`SCREEN_FLOW.md`](SCREEN_FLOW.md) | Screens, workflows, navigation, and access expectations |
| How is local Docker work performed? | [`DOCKER_WORKFLOW.md`](DOCKER_WORKFLOW.md) | Sail services, dependency installation, and local commands |
| What is the approved build order and remaining delivery work? | [`IMPLEMENTATION_ROADMAP.md`](IMPLEMENTATION_ROADMAP.md) | Phases, dependencies, verification, and completion definition |
| How should the interface look and behave? | [`BRAND_UI_GUIDE.md`](BRAND_UI_GUIDE.md) | Brand, density, components, responsive behavior, and accessibility |
| What security checks gate a release? | [`SECURITY_HARDENING.md`](SECURITY_HARDENING.md) | Implemented controls plus production environment, hosting, backup, and recovery checks |
| What does the application currently do? | Current routes, controllers, services, models, migrations, configuration, and tests | Implemented behavior |
| Where should a new task begin? | This document | Fast navigation only |

When sources disagree, identify whether the question concerns intended or current behavior, inspect the corresponding authority, and record a durable discrepancy under Known Gaps. Do not silently guess.

## Standalone Mobile Variant

- This repository was separated from `casa_paraiso` so mobile and database migration work cannot accidentally affect the original project.
- The inherited Laravel/Blade/MariaDB implementation remains the functional baseline; its isolated MariaDB copy is now frozen as the read-only rollback source for the completed Supabase cutover.
- The approved target is a bundled Capacitor Android application using Vue 3 and TypeScript, with Laravel retained as the authenticated API and business-logic backend and Supabase PostgreSQL as the authoritative database after cutover.
- All five roles remain in scope: customer, receptionist, therapist, admin, and super administrator.
- The development/demo backend runs in the Docker Desktop Compose project `casa-paraiso-supabase-desktop` with a profile-gated Cloudflare Quick Tunnel. The hosted proof of concept is one free Singapore Render Docker web service defined by `render.yaml`; it uses Supabase Sydney only, sleeps after 15 idle minutes, and is intentionally password-login pilot capacity rather than always-on production.
- The implementation and migration sequence is authoritative in [`MOBILE_SUPABASE_PLAN.md`](MOBILE_SUPABASE_PLAN.md). Inherited Hostinger/MariaDB documents describe the baseline unless and until they are reconciled as implementation work lands.

## Current Project State

- The Laravel 12 monolith contains the core MVP workspaces for authentication and roles, services, therapist and customer records, scheduling, calendar-based appointments, receptionist operations, transactions, therapist commissions, feedback and sentiment, automatic customer rewards, reports, and CSV export.
- Authenticated-workspace refinement is active in the current working tree, standardizing compact density, responsive filters, accessible overflow regions, shared page headings and stat strips, and fixed pagination.
- Customer appointments use a month calendar with a lazily loaded booking panel and a separate customer-owned, filterable appointment-history page, admin and receptionist appointments use weekly Bookings/Availability workspaces with lazy appointment-create panels, and therapists use a personal weekly calendar.
- Admin Availability now includes a dated weekly therapist-roster draft/publish workflow. `StaffScheduleWeek`, `StaffScheduleShift`, `WeeklyRoster`, and `WeeklyRosterController` are the entry points; published weeks inherit forward by weekday, while legacy recurring schedules remain the initial fallback and exceptions retain final precedence.
- Application behavior is covered primarily by Laravel feature tests under `tests/Feature`; factories exist for all business models.
- The mobile foundation now includes fixed Render endpoint bootstrap, Sanctum device-token authentication, and role workspace shells. `/api/v1/meta`, `POST /api/v1/auth/login`, `GET /api/v1/auth/me`, and `POST /api/v1/auth/logout` are implemented; metadata validation establishes server identity only, while the app keeps a 30-day mobile bearer token in native secure storage. Unauthenticated launches always keep the public Home page available while pairing validates in the background; only a Book/sign-in action reveals authentication, while a valid stored session still resumes its role workspace.
- Mobile Google sign-in uses `MobileGoogleAuthController`, `MobileGoogleOAuth`, and `GoogleIdentity` for a system-browser, state-bound PKCE flow. A five-minute single-use code returns through `casaparaiso://oauth/callback` and is exchanged for the same scoped Sanctum device token; the mobile verifier and final session use secure storage.
- The customer mobile workspace now provides the complete appointment-first slice: owned appointment history/details, status filtering, fixed pagination, pre-start cancellation, transactional booking with eligible therapist preferences, paid add-ons, available RFM vouchers and live month availability, one-per-completed-visit feedback with sentiment classification, and profile/contact/password management. Booking is a guided Treatment/Preferences/Time/Review sheet and a successful reservation uses the shared success sheet. The customer bottom bar exposes Appointments, Feedback, and Profile. `MobileCustomerAppointmentController`, `MobileCustomerBookingController`, `MobileCustomerFeedbackController`, `MobileCustomerProfileController`, `MobileAppointmentResource`, `MobileFeedbackResource`, and the matching `mobile/src/stores` and `mobile/src/views` customer modules are the primary entry points.
- The receptionist mobile workspace now covers the front-desk dashboard, appointment search/filter/detail and creation, therapist availability, appointment updates/outcomes/completion, customer contact/history editing, and manual transaction search/create/update. Its bottom bar exposes Today, Bookings, Customers, and Payments. `MobileReceptionDashboardController`, `MobileReceptionAppointmentController`, `MobileReceptionCustomerController`, `MobileReceptionTransactionController`, `MobileOperationalAppointmentResource`, `MobileTransactionResource`, and the matching reception store/views are the primary entry points.
- The therapist mobile workspace now covers a personal dashboard, assigned appointment search/detail, no-show and atomic completion actions, served-customer history, related feedback and payments, and personal commission history. Its bottom bar exposes Today, Schedule, Guests, and Earnings. `MobileStaffDashboardController`, `MobileStaffAppointmentController`, `MobileStaffCustomerController`, `MobileStaffFeedbackController`, `MobileStaffTransactionController`, `MobileStaffCommissionController`, `MobileStaffFeedbackResource`, `MobileStaffCommissionResource`, and the matching staff store/views are the primary entry points.
- Therapist QR attendance is available in the mobile Today workspace: therapists scan a short-lived server-signed QR from the on-site attendance station and the scan automatically records time in or time out. `AttendanceQr`, `AttendanceWorkflow`, `MobileAttendanceController`, `staff_attendances`, `staff_attendance_scan_requests`, and immutable `staff_attendance_events` are the entry points. Attendance uses the Asia/Manila calendar day; only admins can apply a reasoned correction.
- The Blade fallback exposes the same attendance workflow: `staff.attendance.show` opens the browser-camera scan page, `admin.attendance.station` and `reception.attendance.station` share the rotating QR display, and `admin.attendance.index` provides filtered history plus reasoned correction. There is no pending-scan queue or manual scan approval path; the web controllers reuse the same automatic verification service.
- Camera access is least-privilege: the Android scanner requests Capacitor camera permission only when the therapist opens the scanner, and the web attendance scanner explicitly triggers `getUserMedia` only after its Open camera scanner action. The browser security policy permits `camera=(self)` solely on `staff.attendance.show`; every other route blocks camera access.
- The admin and protected super-administrator mobile workspace now covers operational dashboards; booking, customer, and payment operations; therapist, service, recurring-schedule, exception, and dated weekly-roster management; feedback, rewards, commissions, and native-share CSV reports; business settings; and protected user access. Its dock exposes Today, Ops, Manage, and Insights; Control and sign-out are in the adjacent More sheet. `MobileAdminDashboardController`, the `MobileAdmin*` API controllers, `/api/v1/admin`, and the matching `mobile/src/stores/admin*` and `mobile/src/views/Admin*` modules are the primary entry points. Regular admins cannot access user provisioning or role/activation controls.
- The mobile interface now uses bundled Manrope/Cormorant Garamond fonts, semantic Casa design tokens, Phosphor icons, a shared app bar whose native status-bar height is applied as the Android top inset, and role docks. Workspace destinations are URL-backed at `/workspace/:workspace/:section?`; route queries preserve nested management tabs, customer feedback targets, and public treatment preselection. Android Back closes sheets, open filters, or focused fields first; it then returns a role workspace to Today/Appointments before exiting from that home tab. Sticky segmented subnavigation, responsive form/list rules, shared focus-managed sheets, confirmations, and success feedback remain standard. Browser prompts are no longer used for appointment outcomes or commission payouts.
- Authenticated mobile API reads now emit request timing/request IDs, cache dashboards for two minutes and stable option payloads for 15 minutes only on the server, and retain `Cache-Control: no-store` for clients. Successful mobile mutations invalidate those private versioned reads; the startup screen explicitly explains and retries Render free-tier wake-ups.
- Mobile role destinations stay mounted with Vue `KeepAlive`. Pinia reads use the shared memory-only stale-while-revalidate cache in `mobile/src/lib/mobileDataCache.ts`: operational data is fresh for 60 seconds, profiles/settings/options for five minutes, concurrent reads are deduplicated, cached content remains visible during refresh failures, mutations force invalidation, and logout/account changes clear every store and cache. Booking/payment selector options are requested only when their sheet opens. Each role preloads its common destinations after its home screen settles.
- The default fast mobile-development loop runs the Vue client in Chrome at `http://localhost:5173` with Vite hot reload against the configured Render endpoint; the Quick Tunnel remains available for browser/API inspection only. Chrome covers rapid UI and ordinary API iteration only; Capacitor-native behavior and release acceptance still require the approved Android emulator or a physical phone, and release builds remain bundled without `server.url`.
- `mobile/e2e/role-workspaces.spec.ts` runs deterministic Playwright workflows for all five roles at 320px small-phone, Pixel 7, 375px landscape, and 768px tablet viewports. It mocks the fixed Render backend and automatic startup flow, applies axe scans after every main tab, asserts navigation target size and viewport fit, verifies modal focus/dismissal, and maintains reference visual snapshots. Keep this suite alongside Vitest and live Quick Tunnel acceptance.
- The primary Compose project runs on Docker Desktop through the guarded `scripts/casa-docker.ps1` wrapper. MariaDB is the frozen 2026-07-17 rollback source; local PostgreSQL 17 is reserved for isolated tests and portability rehearsal; the prior dedicated WSL2 engine remains stopped as an additional rollback copy.
- The existing Supabase **Casa Paraiso** project (`pnichczvgkdxnhcezqyn`) in Sydney is now authoritative. Application data lives in the private `casa` schema; `casa_migrator` owns DDL/imports and `casa_runtime` has DML/sequence access only. SSL enforcement and `verify-full` CA validation are active, the Data API is disabled, and Supabase API roles have no application-schema access.
- `casa:transfer-to-postgres` is dry-run-first, preserves identity and business rows in foreign-key order, accepts only the exact migration-owned RFM baseline on an otherwise empty target, resets sequences, and validates every row/count/identity/sequence before commit. The production transfer preserved 21 accounts, passed independent validation and CRUD integrity, and produced checksumed pre/post cutover exports.
- Admin Settings persists editable business identity, address/landmark guidance, Facebook/Messenger/Google Maps links, contact details, and a payment-form default while displaying code-controlled operating and security safeguards. The public web and mobile landing pages read the same settings; the bundled app obtains public contact details from the unauthenticated /api/v1/public/business-profile endpoint.
- The Phase 11 application and Supabase database security baselines are implemented. Final Laravel hosting, live Google-provider validation, signing-key backup, and the non-technical handover/operations manual remain incomplete.
- CRUD audit and repair information is tracked separately in [`CRUD_REMEDIATION_CHECKLIST.md`](CRUD_REMEDIATION_CHECKLIST.md) and [`CRUD_DATA_REPAIR_PLAN.md`](CRUD_DATA_REPAIR_PLAN.md). Never copy its record-level findings here or infer approval to execute a repair.

## Stack and Operating Boundaries

| Area | Current decision |
| --- | --- |
| Backend | Laravel 12 on PHP 8.2+; Blade fallback plus a versioned pairing and mobile-auth `/api/v1` surface |
| Authentication | Laravel Breeze/Socialite plus password login and Sanctum scoped device tokens; mobile Google PKCE exchange implemented |
| Views | Blade templates with reusable Blade components |
| Frontend | Existing Blade/Tailwind UI plus a bundled Vue 3/TypeScript/Tailwind/Pinia/Capacitor 8 Android pairing, sign-in, and role-shell app |
| Data | Existing Casa Paraiso Supabase PostgreSQL 17 project in Sydney, private `casa` schema; frozen MariaDB rollback source; local PostgreSQL for isolated tests |
| Primary local runtime | Docker Desktop `desktop-linux` through `scripts/casa-docker.ps1`, using Compose project `casa-paraiso-supabase-desktop`; bare Compose is intentionally avoided |
| Local runtime rollback | Preserved `CasaParaisoDocker` WSL2 engine through `scripts/casa-dedicated-docker.ps1`; never run concurrently with Docker Desktop |
| Local fallback | XAMPP/Apache with compatible PHP and MariaDB/MySQL |
| Delivery target | Signed Android APK; stable Render HTTPS endpoint for the hosted pilot, with Cloudflare Quick Tunnel retained for local demo builds |
| Timezone | `Asia/Manila` |
| Business window | Every day, 1:00 PM through 12:00 midnight, with 30-minute starts |

Production must not require Docker, a persistent Node.js process, a custom daemon, an external AI service, or a continuously running queue worker. Node is used for asset compilation. RFM and sentiment logic remain application-driven.

`config/casa.php` is the fallback source for business identity and the code-controlled source for business hours, booking interval, commission rate, fixed pagination size, initial package seed metadata and curated inclusion labels, security feature switches, and the paid/voucher add-on catalog. The public landing catalog reads active `Service` records for current names, descriptions, durations, and prices. `config/sentiment.php` stores the code-controlled English, Tagalog, and Taglish sentiment lexicons and phrase rules. `ApplicationSetting` stores the editable business identity/contact fields and payment-form default. Schema remains migration-driven.

## Roles and Access Boundaries

All authenticated workspaces require `auth`, `active`, and `verified` middleware. Role middleware aliases are registered in `bootstrap/app.php`.

| Role | Primary access |
| --- | --- |
| Guest | Public landing page, customer registration, email/password login, Google sign-in, and password recovery |
| Customer | Own profile, availability, booking with an optional eligible RFM add-on voucher, month calendar and separate filterable appointment history, pre-start cancellation, and eligible feedback |
| Receptionist | Restricted front-desk dashboard plus appointment management, customer contact/history, and payment recording; availability is read-only |
| Staff/Therapist | Personal dashboard/calendar plus assigned appointments, operational customer records, related transactions/feedback, and own commission history |
| Admin | Dashboard and management of appointments, customers, staff, schedules, services, transactions, commissions, fixed customer rewards, feedback, reports, and limited application settings |
| Super administrator | All admin access plus protected user provisioning, role assignment, activation, and deactivation |

Important identity rules:

- Public registration always creates an active customer and customer profile.
- A new verified Google identity defaults to customer unless its email matches the configured protected super-administrator identity.
- Pre-authorized users retain their assigned role when linking Google.
- Inactive users are logged out and cannot use authenticated workspaces.
- Only the protected super administrator can access `admin.users.*`; the protected account cannot be edited through user management.
- Receptionist users have neither staff nor customer profiles and land at `reception.dashboard`.
- Google-only users must reconfirm the linked Google identity before creating a local password. Passwordless non-Google accounts use password reset.

Key identity entry points are `routes/auth.php`, the shared profile routes in `routes/web.php`, `app/Http/Controllers/Auth`, `app/Models/User.php`, `app/Http/Middleware`, and `app/Services/PasswordSetupConfirmation.php`.

## Architecture and Domain Map

### HTTP and Application Entry Points

- `routes/web.php` owns public, shared profile, admin, staff, and customer workspaces. Read its middleware groups before changing access.
- `routes/auth.php` owns registration, login, Google OAuth, verification, password reset, confirmation, and logout.
- `routes/api.php` owns the versioned mobile surface: URL-only pairing metadata, Sanctum-protected mobile authentication/password change, customer appointment/booking/feedback/profile endpoints, receptionist dashboard/appointment/customer/transaction endpoints, and therapist dashboard/assigned-appointment/customer/transaction/feedback/commission endpoints.
- `app/Http/Controllers/{Admin,Reception,Staff,Customer}` separates role-specific request handling.
- `app/Http/Requests` contains workflow validation; business invariants that require transactions, locks, or cross-record checks live in services.
- `app/Models` contains state vocabulary, casts, and relationships.
- `app/Services` contains reusable scheduling, completion, sentiment, RFM, numbering, identity-confirmation, session, and conflict logic.
- `app/Services/MobilePairing.php` owns HTTPS endpoint configuration and instance-identity validation for URL-only pairing; runtime flags separate hosted pairing from local demo APK delivery.

### Core Records and Relationships

- `User` has at most one `StaffProfile` or `CustomerProfile`; role changes provision, restore, or soft-delete the corresponding profile.
- `Service` is assigned to therapists through `StaffService` and connects to appointments, transactions, and feedback.
- `StaffProfile` owns service eligibility, recurring `StaffWeeklySchedule` rows, date-specific `StaffScheduleException` rows, and assigned/preferred appointments.
- `Appointment` connects customer, service, assigned therapist, optional preferred therapist, paid add-on snapshots, an optional RFM add-on voucher, status logs, transactions, and at most one feedback record.
- `Transaction` connects a customer to a service and optionally an appointment; it records amount, payment state, payment metadata, and recorder.
- `TherapistCommission` stores one primary earning per eligible transaction plus signed reconciliation adjustments and external payout metadata.
- `ApplicationSetting` stores the singleton-style editable business profile and default payment method; it falls back safely to configuration before its migration is applied.
- `Feedback` belongs to one completed appointment, customer, and service.
- `RfmSegment` and `PromotionRule` classify customer transaction behavior; `PromotionSuggestion` stores the generated snapshot and review outcome.

### Domain Services

| Domain | Main services | Responsibility |
| --- | --- | --- |
| Effective schedules | `ScheduleWindowResolver` | Resolve recurring windows, date exceptions, business-hour clipping, merging, and unavailable subtraction |
| Eligibility protection | `StaffScheduleConflictGuard` | Block staff, service, or availability changes that invalidate future confirmed visits |
| Appointment lifecycle | `AppointmentWorkflow` | Validate starts, choose/lock eligible staff, prevent overlap, schedule, transition status, and log changes |
| Paid add-ons | `AppointmentAddons` | Validate the code-backed catalog, snapshot paid selections, calculate price/duration, and prevent duplication with a voucher |
| Customer availability | `AppointmentAvailability` | Build bookable month/day slots from effective staff capacity |
| Calendar reads | `AppointmentCalendar` | Produce role-scoped admin, staff, and customer calendar payloads |
| Service completion | `AppointmentCompletion` | Atomically create one transaction and complete an eligible confirmed visit |
| Appointment administration | `AppointmentManagement` | Share confirmed create/update invariants across Admin and Receptionist controllers |
| Transaction writes | `TransactionWorkflow` | Normalize Admin/Receptionist payment writes and invoke commission synchronization atomically |
| Therapist commissions | `TherapistCommissionSynchronizer` | Create or recalculate pending earnings and immutable-payout adjustments |
| Identifiers | `AppointmentNumberGenerator`, `TransactionNumber` | Allocate collision-resistant business identifiers |
| Sentiment | `SentimentClassifier` | Combine rating defaults with code-controlled English, Tagalog, and Taglish keyword, phrase, and nearby-negation rules; `casa:reclassify-sentiment` safely previews or applies historical updates |
| Customer rewards | `RfmPromotionGenerator`, `RfmAddonVoucher` | Match five fixed RFM-backed presets after a completed paid transaction, issue one eligible reward, validate ownership/expiry, reserve it during booking, and release it after cancellation/no-show |
| Identity safety | `PasswordSetupConfirmation`, `UserSessionRevoker` | Handle short-lived Google confirmation and session invalidation |

## Critical Workflows and Invariants

### Scheduling and Booking

- `config/casa.php` defines a hard 1:00 PM-to-midnight business window in `Asia/Manila`; starts must align to 30-minute intervals.
- Customer self-booking requires at least 30 minutes of lead time; exactly 30 minutes is valid. Admin, Receptionist, and therapist-assisted scheduling retain the future-start rule without this customer lead time.
- `ends_next_day` represents a schedule or exception ending exactly at midnight. Never model midnight as an earlier same-day end.
- Effective availability is recurring availability plus date-specific availability, minus unavailable exceptions, clipped to business hours and confirmed overlaps.
- Unavailable exceptions take precedence. A schedule, service assignment, staff role, active state, or bookable-state change must not orphan a future confirmed appointment.
- Every new appointment is confirmed transactionally and reserves therapist capacity.
- Appointment forms and detail views expose one appointment time. `scheduled_start_at` is canonical, and the legacy `requested_start_at` column is synchronized for schema compatibility.
- Customer booking uses `AppointmentWorkflow::autoBook` to confirm transactionally, honor an eligible preference when possible, and assign an eligible therapist without overlap. Admin-created bookings also require an eligible therapist and schedule.
- Confirmed appointments reserve capacity. Final scheduling must go through `AppointmentWorkflow`, not direct model updates.
- Appointment states are `confirmed`, `completed`, `cancelled`, and `no_show`. Terminal states do not reopen. The pending-state retirement migration converts legacy pending rows to cancelled records with a system status log.
- Role calendars are read-only JSON projections; mutations remain ordinary Laravel form routes.
- Receptionist calendars expose all bookings and therapist coverage but never availability-edit links.
- The mobile therapist schedule is operationally scoped rather than fully read-only: a therapist may mark an assigned confirmed visit no-show or finish an assigned arrived visit through `AppointmentCompletion`. Therapists cannot create, cancel, or reschedule appointments, edit standalone payments, or access another therapist's operational records.

### Completion and Transactions

- Only a confirmed appointment whose start time has arrived can use the finish workflow.
- `AppointmentCompletion` locks the appointment, rejects duplicate transactions, creates the transaction, and marks the appointment completed in one database transaction.
- Payment states are `unpaid`, `partial`, `paid`, `refunded`, and `void`. Payment method and paid timestamp are cleared when the state does not represent received funds.
- Admin manages transactions. Staff transaction views are operational and read-only.

### Therapist Commissions

- The system rate is `casa.commissions.therapist_rate = 0.22`; new earnings snapshot that rate.
- Only fully paid transactions linked to completed appointments with an assigned therapist earn commission.
- Pending earnings recalculate with source changes. Paid rows are immutable, and later corrections create signed pending adjustments.
- Admin records external settlement; no money is transferred. Therapists see only their own commission records, and Receptionists have no commission access.

### Settings and Security

- `admin.settings.index` and `admin.settings.update` are available only to Admin and Super Administrator. User provisioning and role/activation changes remain exclusive to the protected Super Administrator.
- `ApplicationSetting::updateCurrent` preserves the single settings row across web and mobile writers. The non-production demo seeder creates the configured protected super-administrator account only when that email is absent and never replaces an existing account at that address.
- Editable settings are business name, contact email, phone, formal address, landmark guidance, Facebook/Messenger/Google Maps links, and the default payment method used to prefill new Admin and Receptionist forms. The default never settles a transaction by itself.
- `AddSecurityHeaders` supplies the browser header baseline. `AppServiceProvider` registers named guest/user sensitive rate limiters and can force HTTPS in production.
- `casa.security` reads `FORCE_HTTPS`, `HSTS_ENABLED`, and `TRUSTED_HOSTS`. Production release checks live in `SECURITY_HARDENING.md`; HSTS must wait until HTTPS is verified.
- Mobile startup requires the HTTPS Render origin and configured UUID. All builds receive `VITE_BACKEND_URL`, replace stale Quick Tunnel state at startup, and expose no manual pairing UI or deep links. Metadata responses are non-cacheable and rate-limited; exact Capacitor/Vite CORS origins apply, including `X-Request-ID` for timed authenticated API requests. The metadata identity grants no session; password authentication is the current hosted-pilot flow.

### Feedback and Sentiment

- A customer may submit one feedback record for an eligible completed appointment.
- Ratings 4–5 default positive, 3 neutral, and 1–2 negative. English, Tagalog, and mixed Taglish keyword/phrase polarity plus nearby negation may refine the label; negative written evidence overrides high ratings while 1–2 star ratings remain negative. No external AI service is used.
- Sentiment labels are `positive`, `neutral`, and `negative`.
- Classifier version `2.0.0` stores deterministic evidence plus normalized topic findings for care quality, therapist service, cleanliness/ambience, scheduling/wait time, value/pricing, and pain/comfort. `casa:reclassify-sentiment` previews and idempotently applies derived metadata/topic synchronization.
- Admin sees full feedback insights, including default 30-day rates, period totals, service/topic breakdowns, and a filterable negative-attention queue in Blade and mobile API/workspace surfaces; staff access is limited to related operational feedback with topic chips and no aggregate insights.

### Automatic Customer Rewards

- RFM uses only `paid` transactions attached to `completed` appointments.
- Recency is based on the latest paid timestamp, frequency is the qualifying transaction count, and monetary value is the qualifying amount total.
- Five `casa.customer_rewards.presets` define the fixed priority and thresholds. Admin can only enable a preset, choose its future add-on, and set a 30/60/90/180-day or no-expiry validity period.
- A transaction-derived generation key prevents duplicate issuance. Generation runs only when a qualifying transaction is created or transitions to `paid` for a completed appointment.
- User-facing reward states are available, reserved, used, dismissed, and expired. Only available, unexpired snapshots are customer-selectable during booking; booking reserves the selected voucher.
- A customer can have one available reward or one reward reserved against a confirmed appointment. Cancellation or no-show releases the reservation with its original expiry.
- Rewards grant a complimentary add-on only. They never change the service price, transaction amount, or therapist commission basis; a 30-Minute Back Massage reward still extends reserved capacity by 30 minutes.
- Paid add-ons are independently selectable by customers, Admins, and Receptionists. Their snapshot prices increase the appointment payment default; only 30-Minute Back Massage adds 30 minutes to the reserved capacity.

### Reports and Exports

- `Admin\ReportController` builds filtered appointment, transaction, customer, promotion, and feedback reports from current records.
- CSV export stays on its normal GET route and must not require a background worker or direct database access by business users.

## Shared UI Contract

- Keep the application server-rendered. Alpine.js may manage local disclosure, modal, and calendar state; URLs, filters, sorting, and pagination remain Laravel requests.
- Turbo Drive applies only to safe same-origin GET links and filter forms. One desktop copy of each role's common destinations uses `data-turbo-preload`; Turbo snapshots may preview cached pages while Laravel revalidates. State-changing forms, exports, OAuth, calendar feeds, and specialized panel links keep their normal request behavior.
- The shared detail-panel loader keeps fetched HTML in memory for 60 seconds, displays cached content immediately, refreshes stale panels in the background, and clears the cache after non-GET submissions or logout. Authenticated HTML and API responses expose `Server-Timing` application, database, and query-count metrics.
- Use `page-heading` for authenticated page titles and `stat-strip` for compact detail/calendar context. Reserve `metric-card` for dashboards and analytics.
- Use `list-toolbar` for result totals and responsive filter disclosure, and `table-shell` for labeled, keyboard-focusable horizontal overflow.
- `AppServiceProvider` registers `pagination.compact`. The fixed page size is `casa.pagination.per_page = 15`; preserve query state with `withQueryString()` and never accept request-provided page size.
- Multiple lists need distinct page keys and useful fragments. Calendars, active queues, selector collections, and bounded previews remain unpaginated.
- Appointment workspaces retain their calendar views: customer month, admin/receptionist weekly Bookings/Availability, and therapist personal week. Customer appointment history is a separate paginated card page.
- Complex management CRUD uses its normal create/edit page routes: staff, services, schedules, transactions, and appointments. Calendar booking links open those existing create pages through the shared panel loader and pass only validated customer, therapist, service, and date preselection query values; appointment calendar index responses do not embed selector-heavy forms. Keep modals for Admin service completion, feedback/notes, and confirmation prompts.
- Customer booking opens `/customer/appointments/create` through the shared panel loader, with the same route retained as a full-page fallback. Appointment history opens visit details in panels. Both booking presentations show eligible customer rewards with their expiry and keep the unchanged package price visible.
- Preserve 44px interaction targets, visible focus, accessible names, labeled overflow regions, keyboard calendar navigation, and reduced-motion support.

Primary UI sources are `docs/BRAND_UI_GUIDE.md`, `docs/TECH_STACK.md`, `resources/views/components`, `resources/views/layouts`, `resources/css/app.css`, `resources/js/app.js`, `config/casa.php`, and `AppServiceProvider`.

## Task Routing Table

| Task area | Read first | Inspect next | Representative tests |
| --- | --- | --- | --- |
| Login, registration, Google, passwords, profile deletion | `MVP_SCOPE.md`, `GOOGLE_AUTH_SETUP.md` | `routes/auth.php`, Auth controllers, `User`, profile controller, identity services | `Auth/AuthenticationTest`, `Auth/RegistrationTest`, `Auth/PasswordSetupTest`, `ProfileTest` |
| Roles, activation, or account provisioning | `SCREEN_FLOW.md`, `AGENTS.md` | `routes/web.php`, middleware, `Admin/UserManagementController`, profile models, conflict guard | `RoleAccessTest`, `SuperAdminUserManagementTest`, `IdentityAndEligibilityRemediationTest` |
| Services, staff, customers, or assignments | `DATABASE_DESIGN.md`, `SCREEN_FLOW.md` | Admin/staff controllers and requests, related models, factories, and migrations | `AdminServiceManagementTest`, `AdminStaffManagementTest`, `DatabaseFoundationTest` |
| Weekly schedules, exceptions, or availability | `DATABASE_DESIGN.md`, `SCREEN_FLOW.md` | `ScheduleWindowResolver`, `StaffScheduleConflictGuard`, schedule models/controllers | `AdminStaffScheduleManagementTest`, `CalendarSchedulingTest`, `AutomatedAppointmentQueueTest` |
| Appointment booking or lifecycle | `MVP_SCOPE.md`, `SCREEN_FLOW.md` | `Appointment`, `AppointmentWorkflow`, availability/calendar/completion services, role controllers | `AppointmentCrudRemediationTest`, `CalendarSchedulingTest`, `AutomatedAppointmentQueueTest` |
| Transactions or payment states | `DATABASE_DESIGN.md`, `SCREEN_FLOW.md` | `Transaction`, `AppointmentCompletion`, `TransactionNumber`, transaction controllers/requests | `TransactionRemediationTest`, `PhaseFiveToTenWorkflowTest` |
| Receptionist or therapist commissions | `MVP_SCOPE.md`, `DATABASE_DESIGN.md`, `SCREEN_FLOW.md` | Reception controllers/routes, `TherapistCommission`, `TransactionWorkflow`, commission synchronizer | `ReceptionistWorkspaceTest`, `TherapistCommissionTest` |
| Admin Settings or security hardening | `SCREEN_FLOW.md`, `SECURITY_HARDENING.md`, `TECH_STACK.md` | `ApplicationSetting`, Admin setting controller/request/view, security middleware, providers, auth routes, environment example | `AdminSettingsTest`, `SecurityHardeningTest`, `AuthenticatedWorkspaceSmokeTest` |
| Feedback, sentiment, RFM, promotions, or reports | `MVP_SCOPE.md`, roadmap phases 8–10 | Related models/services, admin/customer/staff controllers, report export | `InsightRemediationTest`, `PhaseFiveToTenWorkflowTest` |
| Schema, factories, seeders, or data integrity | `DATABASE_DESIGN.md`, `AGENTS.md` | `database/migrations`, factories, `DatabaseSeeder`, audit command; use additive/account-preserving operations | `DatabaseFoundationTest`, `SeederSafetyTest`, `CrudIntegrityCommandTest` |
| PostgreSQL, Supabase roles/TLS, or data transfer | `MOBILE_SUPABASE_PLAN.md`, `DOCKER_WORKFLOW.md`, `AGENTS.md` | `compose.yaml`, `config/database.php`, `TransferDatabaseToPostgres`, additive migrations, portable query call sites | `TransferDatabaseToPostgresTest`, `DatabaseTestIsolationTest`, `CrudIntegrityCommandTest`, full Laravel suite on local PostgreSQL |
| Authenticated UI, lists, calendars, or accessibility | `BRAND_UI_GUIDE.md`, `TECH_STACK.md` | Shared components/layouts, CSS/JS, pagination view, provider, relevant role view | `CompactWorkspacePaginationTest`, `InteractiveListControlsTest`, `ModalInfrastructureTest`, `RoleWorkspaceTest` |
| Public content, packages, or business hours | `BRAND_UI_GUIDE.md`, `MVP_SCOPE.md` | landing route/view, `Service`, `config/casa.php`, and service seeding | `LandingServiceCatalogTest`, `AdminServiceManagementTest`, `DatabaseFoundationTest` |
| Docker, Hostinger, or handover | `TECH_STACK.md`, `DOCKER_WORKFLOW.md`, roadmap phase 11 | `compose.yaml`, Composer/npm manifests, `.env.example`, public entry point | Build/test commands and clean-checkout review |
| Mobile pairing, authentication, role workflows, tunnel, or Android shell | `MOBILE_SUPABASE_PLAN.md`, `DOCKER_WORKFLOW.md` | `routes/api.php`, `MobilePairing`, `MobileGoogleOAuth`, mobile API controllers/resources, API middleware, `mobile/src`, `scripts/mobile-demo.ps1` | `MobilePairingApiTest`, `MobileAuthApiTest`, `MobileGoogleAuthApiTest`, role API tests, mobile unit tests, signed release build |
| Android release signing or installation | `PHONE_INSTALLATION.md`, `MOBILE_SUPABASE_PLAN.md` | `scripts/build-mobile-release.ps1`, Android Gradle config and manifest, external user-profile signing directory | mobile build/tests, `assembleRelease`, `apksigner verify`, APK metadata/checksum |

## Verification and Database Safety

Read-only orientation:

```powershell
php artisan route:list --except-vendor
git status --short
```

Standard application checks:

```powershell
.\scripts\casa-docker.ps1 compose exec -T laravel.test npm run build
.\scripts\casa-docker.ps1 compose exec -T --user sail laravel.test php artisan test
.\scripts\casa-docker.ps1 compose exec -T --user sail laravel.test php artisan casa:transfer-to-postgres --validate
Set-Location mobile; npm run build; npm test
Set-Location mobile; npm run test:e2e
```

Fast interactive mobile development uses `.\scripts\mobile-demo.ps1 -Action Start`, followed by `Set-Location mobile; npm run dev` in a second terminal and Chrome at `http://localhost:5173`. Stop the demo helper after the session.

Sentiment reclassification is dry-run by default: `.\scripts\casa-docker.ps1 compose exec -T --user sail laravel.test php artisan casa:reclassify-sentiment`; use `--apply` only after reviewing its transition counts and taking the appropriate database backup/export.

Run dependency installation only when dependencies changed or the environment is new; follow `AGENTS.md` and `DOCKER_WORKFLOW.md`.

Run in-container Artisan commands through `.\scripts\casa-docker.ps1 compose exec -T --user sail laravel.test ...` so the guarded Docker Desktop project is used and CLI-created logs and cache artifacts remain writable by the `sail` web process. The single and daily log channels create files with mode `0664`.

Database migrations, seeders, imports, and targeted data repairs are permitted in the intended environment. Preserve existing accounts: never use `migrate:fresh`, `db:wipe`, table drops, truncation, or bulk delete/reseed work that erases `users`, `customer_profiles`, `staff_profiles`, or authentication-support records. Prefer additive migrations and idempotent seeders; if an account-preserving route is unavailable, stop and ask the user. Tests must remain isolated from non-test databases.

## Known Gaps

- Pairing, password and Google device-token authentication, secure token/PKCE storage, complete five-role workspaces, and repeatable release signing are implemented. Google Cloud credentials and live provider acceptance remain pending.
- Supabase provisioning and the account-preserving Sydney cutover are complete, including restricted roles, private schema, verified TLS, disabled Data API, full-row/sequence validation, checksumed exports, live role smoke tests, and clean security/unindexed-foreign-key advisor results. Keep MariaDB read-only until acceptance; ongoing export retention and a restore rehearsal remain pending.
- The Quick Tunnel remains a browser/API-only development aid; APK download and ADB/deep-link pairing are retired. Render builds use the compiled stable endpoint; `render.yaml` now declares private `APP_URL`, `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, and `GOOGLE_REDIRECT_URI` variables for live Google-provider configuration.
- The automated five-workspace smoke suite passes. Pairing, password sign-in, customer booking options, live availability, slot selection, feedback history, profile display/update, navigation, and sign-out were verified through a phone-sized in-app browser against a Cloudflare Quick Tunnel on 2026-07-15. Receptionist dashboard, booking creation/options and lists, customer search/detail/edit fields, payment lists, and payment-entry options were likewise live-verified without mutating records. Therapist dashboard, assigned schedule and detail actions, served-customer history, related-review empty state, commission totals/history, and related payments were also live-verified without mutating records.
- Admin Today, Ops, Manage, Insights, Control, native report sharing entry points, and the protected super-administrator User Access boundary were live-verified through the phone-sized bundled client and Quick Tunnel on 2026-07-15. The universal version `1.0.0` release APK builds with API 36, verifies with Android v2/v3 signatures, and was installed on an Android API 34 emulator. The pending `1.0.1`/code `2` Render release requires the actual service URL at build time plus hosted HTTPS/wake-up acceptance before it supersedes that artifact; signing-key backup remains outstanding.
- Supabase database and live tunnel security checks are complete for the current configuration. Final live Google-provider acceptance and signing-key backup remain pending.
- Record-level CRUD repair status belongs only in its dedicated repair documents and must be rechecked read-only before any separately approved action.

## Maintenance Contract

Update this document in the same change when any of these change:

- roles, access boundaries, route families, or authentication flows;
- modules, major controllers, models, relationships, migrations, or commands;
- domain services, critical workflows, business invariants, or status values;
- shared UI conventions, configuration sources, deployment constraints, or verification commands;
- completed milestones or durable known gaps; or
- the authoritative planning-document set.

Do not update this document for:

- cosmetic copy or isolated styling changes;
- temporary debugging information, active-task notes, or generated artifacts;
- record-level database findings or credentials; or
- internal refactors that do not change behavior or help future task routing.

For every memory update:

1. Verify the affected statements against current source and tests.
2. Update only the sections affected by the change.
3. Set `Last reviewed` to the actual review date.
4. Check all referenced paths and remove obsolete entry points.
5. Keep the document near 200–300 lines; if it exceeds 350 lines, move detail into the appropriate authoritative document and retain a concise pointer.
6. If authority and implementation differ, record the durable discrepancy rather than presenting either as reconciled.
7. Never use a commit hash as the freshness marker and never store secrets.
