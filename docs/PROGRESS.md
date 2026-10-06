# AstroGann — Progress

Update at the end of every work session. Newest session on top.

## Current state

- **Current phase:** Phase 6 — User module (web) (next to start)
- **Last finished phase:** Phase 5 — Admin module (2026-10-06)

## Next steps
1. Phase 6: user tabs from active categories, 7/3 entries via EntryService::visibleFor, Show More with contact
   settings, subscription status page, notifications list + unread count (NotificationService).

## Known issues / notes
- PHP 8.2.12 → Laravel 12 (Laravel 13 needs PHP 8.3). PHP 8.2 security support ends 2026-12-31 (D-01).
- Composer 2.4.4 is old; `composer self-update` recommended (D-02).
- DB is MariaDB 10.4 (D-03).
- Image has 7 panels, not 8 (D-06).

## Session log

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
