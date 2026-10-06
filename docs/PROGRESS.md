# AstroGann — Progress

Update at the end of every work session. Newest session on top.

## Current state

- **Current phase:** Phase 10 — Mobile setup — **WAITING for the owner's go-ahead** (D-67)
- **Last finished phase:** Phase 9 — Security and QA (2026-10-06). Web + API are complete.

## Next steps
1. Ask the owner the mobile questions (see the Phase 9 report in the chat / below) and wait for the answer.
2. Then Phase 10: `flutter doctor`, create `mobile/` (Android + iOS + web), run with `flutter run -d chrome`
   against http://localhost:8000, local-only CORS, docs/MOBILE.md.

## Open items for the owner
- Production: GoDaddy cPanel access/domain, SMTP mailbox (or Gmail App Password), Firebase project files.
- PHP 8.2 security support ends 2026-12-31 → choose PHP 8.3/8.4 on the server (D-01).

## Known issues / notes
- PHP 8.2.12 → Laravel 12 (Laravel 13 needs PHP 8.3). PHP 8.2 security support ends 2026-12-31 (D-01).
- Composer 2.4.4 is old; `composer self-update` recommended (D-02).
- DB is MariaDB 10.4 (D-03).
- Image has 7 panels, not 8 (D-06).

## Session log

### 2026-10-06 — Owner requests: scale, filters/sorting, pickers, live notifications, URL security, readability, buttons
- D-73 immediate + idempotent notifications, renew nudge for expired users (setting), live web badge/toast/tab title.
- D-74 flatpickr date/time pickers; D-75 indexes, deferred pagination, prune job, dashboard cache, load test
  (docs/PERFORMANCE.md: all screens < 1 s with 2 lakh entries / 50k users); D-76 `?tab=<code>` instead of ids;
  D-77 sorting/filters/page size on all lists; D-78 readable dates/times + clearer column names + API display
  strings; D-79 real buttons.
- 131 tests pass. Browser checks: pickers (06 Oct 2026 / 1:43 PM → 2026-10-06 / 13:43), entry list buttons and
  times, user tabs by code, notification created ~1 s after the admin saved, badge + "(1)" title + toast.

### 2026-10-06 — Owner change requests (after Phase 9)
- Light theme (D-69), grouped top menu with dropdowns + grouped mobile menu (D-70), entry edit/delete with
  `entries.edit` / `entries.delete` on web and API (D-71), deleting records with history with
  `records.force_delete` (D-72). Permission catalogue synced locally (`db:seed --class=AccessSeeder`).
- Tests rewritten for the new rules + API edit/delete test — 122 tests pass. Checked in Chrome: light theme,
  Accounts dropdown, All Entries with Edit/Delete.

### 2026-10-06 — Phase 9
- Security review (SECURITY.md §7): SecurityHeaders middleware, password change/reset logging, route-guard audit
  test, CSRF/headers checked live, `.public_path` hook for public_html hosting, `astrogann:create-superadmin`.
- Found & fixed: API rejection of a cookie-authenticated request crashed (TransientToken::delete) → 500.
- EndToEndFlowTest walks spec 3.3 steps 1–8 + renewal + single session across web/API. 119 tests pass.
- DEPLOYMENT.md finalised.

### 2026-10-06 — Branding (owner details)
- ASTRO GANN name, cropped transparent logo + favicons, dark olive/light-blue/yellow theme, no red (D-61);
  contact e-mail + two phone/WhatsApp numbers with one button each (D-62); Reply-To on mails (D-63); branded
  error pages; demo images regenerated without red. 111 tests pass. Local settings rows updated.
- Browser check: login page verified dark with logo; later screenshots timed out (Chrome window likely in the
  background) — the user page was verified via rendered HTML instead (both numbers, WhatsApp links, e-mail, logo).

### 2026-10-06 — Phase 8
- EntryCreated event + listener; jobs NotifyUsersOfNewEntry (chunked, self-continuing) and SendPushToUsers;
  PushService interface with LogPushService / FcmPushService (HTTP v1, JWT via openssl_sign, Http::pool);
  SubscriptionExpiringMail (markdown, queued); commands astrogann:send-expiry-reminders / astrogann:expire-subscriptions;
  scheduler incl. queue drain each minute; failed-job logging.
- NotificationsAndSchedulerTest (12 tests) — 111 tests pass. Live: admin posted an entry via API → 1 job queued →
  one `schedule:run` drained it → active trader got "New entry added in Nifty 50…", expired trader nothing.

### 2026-10-06 — Phase 7
- REST API: 19 routes (spec 3.6 paths + D-53 additions), Sanctum + `session.single` + rate limits, envelope via
  `ApiResponse` and `ApiExceptionRenderer`, `ForceJsonResponse`, API Resources (User, Category, Entry,
  Notification), reused Form Requests and services (no duplicated logic). `user_sessions.ended_reason` (D-52).
- ApiTest (21 tests: auth, single session across web/API, deactivation, rate limit, content 7/3, notifications,
  admin entry upload, super admin users/admins/subscription, permission checks, custom staff role) — 99 tests pass.
  Live curl check incl. `session_replaced`. docs/API.md finalised; docs/api.http collection.

### 2026-10-06 — Phase 6
- User/Trader web: tab screen (`/user`), latest 7/3 entries with image/date/time and lightbox, notice text,
  subscription strip, Show More with contact details; `/user/subscription`; `/user/notifications` with unread
  badge, mark read / mark all. `NotificationService`. Login page shows the app name instead of the Laravel logo;
  navigation tightened.
- UserModuleTest (tabs order/hidden, 7 vs 3 newest-first, no-subscription, limits from settings, rename/reorder,
  renewal, notifications isolation) — 78 tests pass. Checked in Chrome: expired user (3 entries + Show More
  contacts), Super Admin dashboard, roles and subscription screens.

### 2026-10-06 — Phase 5
- Staff dashboard (/admin) listing the screens the account's permissions allow; read-only categories; add entry
  with image preview; own entries with filters; `EntryPolicy`; `StoreEntryRequest` (D-45, D-46).
- AdminModuleTest (upload, validation incl. disguised PHP/GIF/PDF/oversize, own-only, immutability, no management
  access, staff without permission, trader blocked) — 67 tests pass. Live upload via curl as admin: 302, file
  stored under a random name and served (200 image/png); /superadmin/users → 403.

### 2026-10-06 — Phase 4 (+ owner request: dynamic roles & permissions)
- Owner asked mid-phase for manageable roles/permissions, so D-42 was implemented before finishing Phase 4: `roles`,
  `permissions`, `permission_role`, `permission_user`, `users.role_id`; `RoleKind` enum; `PermissionService`
  (catalogue sync, role CRUD, per-user grants, anti-escalation); `AccessSeeder`; gates per permission; routes use
  `can:`; permission-driven navigation; Roles screen. Local DB re-created with `migrate:fresh --seed` (demo data only).
- Super Admin module: dashboard counts + recent activity; staff (admins) and users CRUD with activate/deactivate,
  role + extra permissions; categories CRUD/status/order; subscriptions activate/extend/expire + payments; all
  entries with filters; logs with filters; settings. Services: AccountService, CategoryService, SubscriptionService,
  EntryService. Shared Blade components (page, card, field, select, badge, flash, link-button).
- Tests: SuperAdminModuleTest, RolesPermissionsTest — 58 tests pass. Assets rebuilt.

### 2026-10-06 — Phase 3
- One login page → role dashboards; `role` middleware + gates (`superadmin`, `admin`, `trader`); registration,
  email verification, remember-me and self-delete removed; SessionService + EnsureActiveSession (web);
  ActivityLogService, SettingsService, ApiResponse envelope; role-based navigation (`App\Support\Navigation`).
- Tests: RoleAccessTest, SingleSessionTest (+ updated Breeze tests) — 38 pass. Live curl check of the 4 seed
  accounts: each gets 200 on its own area, 403 on the others.
- Test helpers `signIn()` / `apiSignIn()` in `tests/TestCase.php` (plain `actingAs()` is rejected by the
  single-session middleware).

### 2026-10-06 — Phase 2
- Migrations for all tables, 6 enums, 8 models with relations/casts, factories, SettingsSeeder + DemoSeeder,
  `config/astrogann.php`, `uploads` disk, `public/uploads/.htaccess`, PlaceholderImage (GD). 28 tests pass.

### 2026-10-06 — Phase 1
- Laravel 12.69.3 in `backend/`, `.env` configured (MySQL astrogann, database queue/session/cache, log mail,
  Asia/Kolkata), Breeze Blade + Sanctum installed, default migrations run on MariaDB, 25 Breeze tests pass,
  `php artisan serve` returns 200 on /login. `public/build` committed (D-30).

### 2026-10-06 — Phase 0
- Environment: PHP 8.2.12 (C:\xampp\php), Composer 2.4.4, MariaDB 10.4.32 (DB `astrogann` exists, root/no
  password), Node 22.14 / npm 9.6, Flutter 3.35.5 / Dart 3.9.2, git 2.43.
- Read the architecture image (7 panels) and the brief; wrote all docs in section 5; recorded D-01…D-34.
