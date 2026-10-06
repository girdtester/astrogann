# AstroGann — agent guide

**Before any work: read this file, then `docs/PROGRESS.md`, then the docs relevant to the task.** Resume from the
first unfinished phase in `docs/PHASES.md`. Never restart finished phases.

## Project
Subscription platform: admins post image entries under market categories (Nifty 50, NSE, …); subscribed
users/traders view the latest entries on web and on the Flutter app (Android/iOS). Three roles in one `users` table:
`superadmin`, `admin`, `user`. Full spec: `docs/SPEC.md`.

## Layout
- `backend/` — Laravel 12 (web via Blade/Breeze + REST API via Sanctum)
- `mobile/` — Flutter app (user role only), Phases 10–12
- `docs/` — all project documentation (shared)

## Stack
PHP 8.2 (XAMPP `C:\xampp\php\php.exe`), Laravel 12, MariaDB/MySQL (DB `astrogann`, root, empty password,
127.0.0.1:3306), Breeze (Blade), Sanctum, database queue/session/cache, SMTP mail (log locally), FCM HTTP v1 push
behind `PushService`. Production: GoDaddy shared hosting (no S3/Redis/Supervisor/SSH) — see `docs/DEPLOYMENT.md`.
Laravel first-party packages only unless the owner approves.

## How to run (from `backend/`)
- Serve: `php artisan serve` → http://localhost:8000
- Queue (local): `php artisan queue:work` (production: scheduler runs `queue:work --stop-when-empty`)
- Scheduler (local): `php artisan schedule:work`
- Tests: `php artisan test`
- Reset local DB (local only, destroys data): `php artisan migrate:fresh --seed`
- Assets: `npm install && npm run build` (commit `public/build`)
- Mobile (from `mobile/`): `flutter run -d chrome` against http://localhost:8000

## Conventions
- Business logic in `app/Services/*Service.php` (SubscriptionService, EntryService, SessionService,
  SettingsService, NotificationService, ActivityLogService, PushService). Web and API controllers are thin and call
  the same services. No duplicated logic.
- ~~Roles via `role` column~~ (superseded 2026-10-06, D-42): dynamic roles & permissions — `roles`/`permissions`
  tables, `App\Support\Permissions` catalogue, `PermissionService`; routes use `role:<kind>` + `can:<permission>`.
  New feature => add a permission key, guard the route, run `php artisan db:seed --class=AccessSeeder`. No package.
- Validation in Form Requests; API output via API Resources; API envelope `{success, message, data, errors}`.
- Enums in `app/Enums`. Explicit `$fillable` on every model.
- Nothing user-visible is hard-coded: categories, limits (7/3), reminder days, contact info, texts come from DB
  (`settings` via `SettingsService`).
- ~~Entries are immutable~~ (superseded 2026-10-06, D-71): edit/delete via `entries.edit` / `entries.delete`
  permissions (EntryPolicy); deleting records with history needs `records.force_delete` (D-72).
- UI: light theme, olive/light-blue/yellow, **never red** (D-69); grouped top menu (D-70).
- Uploads to `public/uploads/entries/` via disk `uploads`; store relative path only.
- Lists: `App\Support\Sorting` (whitelisted sort, `paginateDeferred` for big tables), `<x-sort-th>`, `<x-list-toolbar>`;
  row actions with `<x-action>`; dates/times via `App\Support\Fmt`; date/time inputs via `<x-field type="date|time">`
  (flatpickr). Never `whereDate()` on big tables. User URLs never contain category ids (`?tab=<code>`).
- Performance numbers and how to re-measure: docs/PERFORMANCE.md.
- Timezone Asia/Kolkata.

## Doc rules
- Update `docs/PROGRESS.md` and the relevant doc **in the same change** as the code.
- Never delete doc content; mark it superseded with a date.
- Every decision/assumption → `docs/DECISIONS.md` (dated, numbered).
- Never run destructive DB commands (migrate:fresh, drops) once real data exists without telling the owner first.
- Commit at the end of each phase with the phase name.

## Docs index
- [docs/SPEC.md](docs/SPEC.md) — full specification
- [docs/PHASES.md](docs/PHASES.md) — phases, checklists, traceability
- [docs/PROGRESS.md](docs/PROGRESS.md) — current state, next steps, known issues
- [docs/DECISIONS.md](docs/DECISIONS.md) — decision log
- [docs/DATABASE.md](docs/DATABASE.md) — schema
- [docs/API.md](docs/API.md) — REST contract (mobile)
- [docs/SECURITY.md](docs/SECURITY.md) — single session, roles, limits, uploads
- [docs/TEST_ACCOUNTS.md](docs/TEST_ACCOUNTS.md) — local seed accounts
- [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) — GoDaddy deployment
- [docs/BACKLOG.md](docs/BACKLOG.md) — work waiting for the owner (B-01 push on phones: what to supply, steps, test plan)
- [docs/PERFORMANCE.md](docs/PERFORMANCE.md) — load test results (2 lakh entries / 50k users)
- [docs/MOBILE.md](docs/MOBILE.md) — Flutter app: run in Chrome, API URL in one place, Firebase, builds
- [docs/architecture.png](docs/architecture.png) — source architecture image
