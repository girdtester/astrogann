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
- [x] EntryCreated event → queued job → DB notifications (chunked) + PushService (log / fcm)
- [x] Expiry reminder Mailable (queued), never twice
- [x] Daily expire job
- [x] Scheduler: queue:work --stop-when-empty each minute; daily jobs
- [x] Failed-job handling; DEPLOYMENT.md cron

### Phase 9 — Security and QA
- [x] SECURITY.md review ticked (§7)
- [x] Full test run (119 tests)
- [x] 3.3 flow walk-through (`EndToEndFlowTest` + manual checks); 3.2 bullets ticked below
- [x] DEPLOYMENT.md final (GoDaddy, public_html variant, cron, mail, FCM, first Super Admin)

### Phase 10 — Mobile setup
- [x] flutter doctor; app in `mobile/` (android, ios, web)
- [x] Config file for base URL, API client, token storage, models, theme
- [x] Local CORS for Flutter web origin
- [x] docs/MOBILE.md

### Phase 11 — Mobile features
- [x] Login (device_type=mobile, fcm token), settings, dynamic tabs, entries, pull to refresh, Show More,
      subscription, notifications, logout, session_replaced handling, loading/empty/error states

### Phase 12 — Mobile push and build
- [x] PushService interface; NoPushService until Firebase files exist; skipped on web; exact steps in MOBILE.md §5 (D-82)
- [x] Widget tests (login, entries, Show More, staff refused, session replaced); Chrome walk-through
- [x] Build docs (Android debug/release, iOS)

### Phase 13 — Final report
- [x] Traceability table complete, defaults list, not-done list, what the owner must supply (below + chat report)

## Traceability (spec bullet → web / API / mobile) — working table, **superseded 2026-10-06 by the final table at the end of this file**

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
| 3.2 U | Push for new entries | (web: unread badge) | NotifyUsersOfNewEntry → SendPushToUsers → FcmPushService | | backend done; app Phase 12 |
| 3.2 U | Email 3 days before expiry | astrogann:send-expiry-reminders + SubscriptionExpiringMail | | n/a | done |
| 3.2 U | Single login | Login + EnsureActiveSession | session_replaced (D-52) | | web+API done |
| 3.3 | End-to-end flow 1–8 | EndToEndFlowTest (web staff steps) | EndToEndFlowTest (API trader steps) | | done |
| 3.4 | Subscription check per request, daily jobs | EntryService::visibleFor; astrogann:expire-subscriptions, astrogann:send-expiry-reminders | same service | | done |
| 3.7 | Notification flow | EntryCreated → jobs → user_notifications + push; /user/notifications | GET /api/notifications | | backend done |
| 3.8 | Single session | SessionService::startWeb, EnsureActiveSession, SingleSessionTest | SessionService::startApi, ApiTest | | web+API done |
| 3.9 | Dynamic tabs + settings | /user tabs, SettingsService | GET /api/categories, GET /api/settings | | web+API done |


## Final traceability — Phase 13 (2026-10-06)

| Spec | Requirement | Web | API | Mobile | Status |
|---|---|---|---|---|---|
| 3.1 | Web + Android + iOS clients over HTTPS | Blade app | REST `/api/*` | Flutter app (android/ios/web) | done |
| 3.1 | Auth, single session, business/subscription logic, notifications, e-mail, dynamic categories | Services in `app/Services` | same services | — | done |
| 3.1 | Files in Laravel public folder (not S3); SMTP (not SES); FCM | `public/uploads/entries` | — | — | done (FCM needs owner's files) |
| 3.2 SA | Login / dashboard | /login → /superadmin | POST /api/auth/login | n/a (staff refused) | done |
| 3.2 SA | Create / manage admins | Accounts ▾ Admins/Staff (+ roles, extra permissions) | /api/admin/admins | n/a | done |
| 3.2 SA | Create / manage users | Accounts ▾ Users | /api/admin/users | n/a | done |
| 3.2 SA | Activate / deactivate users | Users → Activate/Deactivate | PATCH /api/admin/users/{id} | n/a | done |
| 3.2 SA | Subscriptions activate / extend / expire | Subscriptions → Manage (+ payments, e-mails) | POST /api/admin/subscription | n/a | done |
| 3.2 SA | Manage categories | Entries ▾ Categories | — | n/a | done |
| 3.2 SA | View all entries | Entries ▾ All Entries (filters, sort, edit/delete) | POST/DELETE /api/entries/{id} | n/a | done |
| 3.2 SA | Reports / logs | Reports / Logs | — | n/a | done |
| 3.2 SA | System settings | Settings | GET /api/settings (public part) | reads settings | done |
| 3.2 A | Login, view categories, add entry, view own entries | /admin, Categories, Add Entry, My Entries | /api/auth/login, /api/categories, POST /api/entries | n/a | done |
| 3.2 A | No edit/delete/user/subscription/category management | default Admin role permissions (D-42, D-71) | can: + policies | n/a | done (configurable) |
| 3.2 U | Login (web / mobile) | /login → /user | POST /api/auth/login | Login screen | done |
| 3.2 U | View active categories | tabs `?tab=<code>` | GET /api/categories | tab bar from API | done |
| 3.2 U | Latest 7 entries if active | /user | GET /api/categories/{id}/entries | Entries tab | done |
| 3.2 U | Latest 3 entries if expired | /user | same | Entries tab | done |
| 3.2 U | "Show More" (contact admin) | Show More panel | `show_more` + settings | Show More card (Call/WhatsApp/Email) | done |
| 3.2 U | Push notifications for new entries | live badge/toast (web) | jobs → FcmPushService | PushService (Firebase files pending) | backend done; device push needs owner's Firebase files |
| 3.2 U | E-mail 3 days before expiry | — | `astrogann:send-expiry-reminders` (SMTP) | — | done |
| 3.2 U | Single login (one device) | EnsureActiveSession | session_replaced | auto-logout with reason | done |
| 3.3 | End-to-end flow 1–8 | EndToEndFlowTest | EndToEndFlowTest | widget tests + Chrome | done |
| 3.4 | Subscription check per request; 7/3; renew restores; expiry mail; daily expire | EntryService, commands | same | same API | done |
| 3.5 | Database tables + indexes | migrations (+ roles/permissions, settings, logs) | — | — | done |
| 3.6 | API endpoints, envelope, Resources, Form Requests | — | 21 routes, docs/API.md, docs/api.http | ApiClient | done |
| 3.7 | Notification flow (DB + push + web at next view) | unread badge, toast, list | GET /api/notifications | Notifications tab + badge | done |
| 3.8 | Single session web OR mobile; staff flag | SessionService | SessionService | handles session_replaced | done |
| 3.9 | Tab layout, everything dynamic, settings endpoint | /user | /api/settings, /api/categories | tabs/texts/limits from API | done |
| Owner | Dynamic roles & permissions | Roles & Permissions | permission-guarded | — | done (D-42) |
| Owner | Branding, light theme, grouped menu, buttons, readable dates, pickers | all screens | `display` fields | same colours/strings | done (D-61…D-79) |
| Owner | Scale to lakhs, sorting/filters | all lists | — | — | done (docs/PERFORMANCE.md) |
| Owner | Subscription change e-mails | — | SubscriptionChangedMail | notification type `subscription` | done (D-81) |
| Owner | Forgot password everywhere | /forgot-password | POST /api/auth/forgot-password | Forgot password screen | done (D-84) |
| Owner | Self sign-up (3 entries until subscribed) | /register | POST /api/auth/register | Create account screen | done (D-85) |
| Owner | Cron link + saved expiry list + one-by-one e-mails | Subscriptions ▾ Expiry reminders | /cron/run/{token} | — | done (D-86…D-88) |
