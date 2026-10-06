# AstroGann — Phases and traceability

Rules: do not skip, merge or reorder phases. At the end of each phase: migrate/test, fix, update docs, commit
"Phase N — name", short report, continue.

## Phase checklists

### Phase 0 — Read and plan
- [x] Read `docs/architecture.png` (7 panels) and the brief
- [x] Check PHP (8.2.12), Composer (2.4.4), MySQL (MariaDB 10.4.32, DB `astrogann` exists), Flutter (3.35.5)
- [x] Create CLAUDE.md, AGENTS.md, docs/SPEC, PHASES, PROGRESS, DECISIONS, DATABASE, API, SECURITY, TEST_ACCOUNTS, DEPLOYMENT
- [x] List open questions with defaults (DECISIONS D-17…D-20)
- [x] git init + commit

### Phase 1 — Project setup
- [x] Laravel 12 in `backend/`
- [x] `.env`: DB astrogann/root/empty, QUEUE_CONNECTION=database, SESSION_DRIVER=database, MAIL_MAILER=log, APP_TIMEZONE=Asia/Kolkata
- [x] Breeze (Blade) + Sanctum installed
- [x] App boots, DB connects, default migrations run
- [x] `.gitignore` sensible (keep `public/build` committed — D-30)

### Phase 2 — Database
- [x] Migrations for every table in DATABASE.md
- [x] Models, relations, casts, enums
- [x] Factories + seeders (4 accounts, categories, ≥10 entries/category with placeholder images, settings)
- [x] `migrate:fresh --seed` clean

### Phase 3 — Web auth, roles, single session
- [x] One login page; redirect by role to /superadmin, /admin, /user
- [x] Role middleware + Gates; inactive users blocked; registration disabled; login throttling
- [x] SessionService + EnsureActiveSession (web)
- [x] Feature tests: each seed account sees only its own area, 403 elsewhere; single-session replacement

### Phase 4 — Super Admin module
- [x] Dashboard counts
- [x] Admins CRUD (no hard delete of admins with entries — deactivate instead)
- [x] Users CRUD + activate/deactivate
- [x] Categories CRUD + status + sort_order
- [x] Subscriptions activate/extend/expire + payment record
- [x] All entries with filters
- [x] Reports/logs (activity_logs)
- [x] Settings screen (limits, reminder days, contact details, texts)
- [x] (owner request, D-42) Dynamic roles & permissions: Roles screen, extra per-user permissions, anti-escalation

### Phase 5 — Admin module
- [x] View categories
- [x] Add entry with validation, random filename, uploads .htaccess
- [x] Own entries only
- [x] Policies + tests for forbidden actions

### Phase 6 — User module (web)
- [x] Tabs from active categories (sort_order)
- [x] 7/3 entries limited in the query, image/date/time
- [x] Show More with contact details from settings
- [x] Subscription status + expiry date
- [x] Notifications list + unread count

### Phase 7 — REST API
- [x] All endpoints, Sanctum, envelope, Resources, Form Requests, rate limits
- [x] Single session shared with web
- [x] Feature tests per endpoint and role
- [x] docs/API.md complete + `docs/api.http` / Postman collection

### Phase 8 — Queues, notifications, email, scheduler
- [ ] EntryCreated event → queued job → DB notifications (chunked) + PushService (log / fcm)
- [ ] Expiry reminder Mailable (queued), never twice
- [ ] Daily expire job
- [ ] Scheduler: queue:work --stop-when-empty each minute; daily jobs
- [ ] Failed-job handling; DEPLOYMENT.md cron

### Phase 9 — Security and QA
- [ ] SECURITY.md review ticked
- [ ] Full test run
- [ ] 3.3 flow walk-through; 3.2 bullets ticked below

### Phase 10 — Mobile setup
- [ ] flutter doctor; app in `mobile/` (android, ios, web)
- [ ] Config file for base URL, API client, token storage, models, theme
- [ ] Local CORS for Flutter web origin
- [ ] docs/MOBILE.md

### Phase 11 — Mobile features
- [ ] Login (device_type=mobile, fcm token), settings, dynamic tabs, entries, pull to refresh, Show More,
      subscription, notifications, logout, session_replaced handling, loading/empty/error states

### Phase 12 — Mobile push and build
- [ ] PushService (Firebase) optional when config files missing; skipped on web
- [ ] Widget tests (login, entries); Chrome walk-through
- [ ] Build docs (Android debug/release, iOS)

### Phase 13 — Final report
- [ ] Traceability table complete, defaults list, not-done list, what the owner must supply

## Traceability (spec bullet → web / API / mobile) — filled in as phases complete

| Spec | Bullet | Web | API | Mobile | Status |
|---|---|---|---|---|---|
| 3.2 SA | Login / dashboard | /superadmin | POST /api/auth/login | n/a | done |
| 3.2 SA | Create / manage admins | /superadmin/admins (+ roles, extra perms) | GET/POST /api/admin/admins, PATCH /{id} | n/a | done |
| 3.2 SA | Create / manage users | /superadmin/users | GET/POST /api/admin/users, PATCH /{id} | n/a | done |
| 3.2 SA | Activate / deactivate users | /superadmin/users/{id}/status | PATCH /api/admin/users/{id} status | n/a | done |
| 3.2 SA | Subscriptions activate/extend/expire | /superadmin/subscriptions/{user} | POST /api/admin/subscription | n/a | done |
| 3.2 SA | Manage categories | /superadmin/categories | | n/a | web done |
| 3.2 SA | View all entries | /superadmin/entries | | n/a | web done |
| 3.2 SA | Reports / logs | /superadmin/logs | | n/a | web done |
| 3.2 SA | System settings | /superadmin/settings | | n/a | web done |
| D-42 | Roles & permissions | /superadmin/roles, staff edit form | | n/a | web done |
| 3.2 A | Login | /login → /admin | POST /api/auth/login | n/a | done |
| 3.2 A | View categories | /admin/categories | GET /api/categories | n/a | done |
| 3.2 A | Add entry | /admin/entries/create | POST /api/entries | n/a | done |
| 3.2 A | View own entries | /admin/entries | | n/a | web done |
| 3.2 A | No edit/delete/user/subscription/category mgmt | EntryPolicy + can: middleware, AdminModuleTest | | n/a | web done (default role perms, D-42) |
| 3.2 U | Login web/mobile | /login → /user | POST /api/auth/login | | web+API done |
| 3.2 U | View active categories | /user tabs | GET /api/categories | | web+API done |
| 3.2 U | Latest 7 if active | /user (EntryService::visibleFor) | GET /api/categories/{id}/entries | | web+API done |
| 3.2 U | Latest 3 if expired | /user | GET /api/categories/{id}/entries | | web+API done |
| 3.2 U | Show More (contact admin) | /user, /user/subscription | entries.show_more, GET /api/settings | | web+API done |
| 3.2 U | Push for new entries | | | | |
| 3.2 U | Email 3 days before expiry | | | | |
| 3.2 U | Single login | | | | |
| 3.3 | End-to-end flow 1–8 | | | | |
| 3.4 | Subscription check per request, daily jobs | | | | |
| 3.7 | Notification flow | | | | |
| 3.8 | Single session | | | | |
| 3.9 | Dynamic tabs + settings | /user tabs, SettingsService | GET /api/categories, GET /api/settings | | web+API done |
