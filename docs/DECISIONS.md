# AstroGann — Decisions Log

Dated log of every decision and every answered open question. Never delete entries; when a decision
changes, add a new entry and mark the old one `**Superseded YYYY-MM-DD by D-xx**`.

Format: `D-NN (YYYY-MM-DD) — Title` · Decision · Reason.

---

## Phase 0 — 2026-10-06

### Environment

**D-01 (2026-10-06) — Laravel 12, not 13.**
Installed PHP is 8.2.12 (XAMPP). Laravel 13 requires PHP ^8.3, so the latest Laravel the machine supports is
Laravel 12 (12.69.x at time of writing). We install Laravel 12.
*Recommendation to owner:* PHP 8.2 receives security fixes only until 2026-12-31. Upgrading XAMPP's PHP (and the
GoDaddy PHP selector) to 8.3 or 8.4 would allow a later move to Laravel 13. Not blocking.

**D-02 (2026-10-06) — Composer 2.4.4 is old but works.**
Recommend `composer self-update` (run from an Administrator terminal because Composer lives in `C:\ProgramData`).
Not blocking.

**D-03 (2026-10-06) — Database is MariaDB 10.4.32 (XAMPP), used through Laravel's `mysql` driver.**
The `mysql` driver works with both MariaDB and MySQL, which keeps us portable to whatever GoDaddy provides.
Avoid MySQL-8-only SQL (no `JSON_TABLE`, window functions are fine on MariaDB 10.4 but we do not depend on them).

**D-04 (2026-10-06) — Git repository is the project root `E:\astrogann`** (contains `backend/`, `mobile/`, `docs/`).
Initialised in Phase 0 so Phase 0 can be committed; Phase 1 adds the Laravel `.gitignore` inside `backend/`.

**D-05 (2026-10-06) — Timezone `Asia/Kolkata`.** The product targets Indian markets (Nifty 50, NSE). App
timezone, scheduler times and "today" for subscriptions all use IST. Configurable via `APP_TIMEZONE`.

### Image vs. written spec (differences found when comparing `docs/architecture.png` to section 3 of the brief)

**D-06 (2026-10-06) — The image has 7 panels, not 8.** Panels: 1 High Level Architecture, 2 User Roles & Flow,
3 System Flow, 4 Database Design, 5 API Flow, 6 Notification Flow, 7 Subscription & Access Logic. The image is
1536×1024 and was read in full; nothing is cropped. All 7 panels were transcribed into `docs/SPEC.md`.

**D-07 (2026-10-06) — Admin sees own entries only.** Image (Admin panel) says "View All Entries"; written spec says
"View entries (own added entries only)". Written spec wins → Admin sees only entries where `admin_id = self`.

**D-08 (2026-10-06) — Subscription actions are activate / extend / expire.** Image says "Activate / Extend /
Deactivate"; written spec says "activate, extend, expire". Written spec wins. (User *account* activate/deactivate is
a separate feature on the users screen.)

**D-09 (2026-10-06) — `POST /api/update-fcm-token` is kept as an extra endpoint.** The image's API table lists it;
the written 3.6 table does not. This is an omission, not a contradiction, and the endpoint is needed because FCM
tokens rotate after login. The written spec still holds: login accepts `fcm_token` too. Added to `docs/API.md`.

**D-10 (2026-10-06) — Web front end is Laravel Blade (Breeze), not React/Vue.** Image says "Web App (React / Vue /
etc)". The brief mandates Breeze (Blade). Blade it is.

**D-11 (2026-10-06) — Database is MySQL (not PostgreSQL).** Image says "MySQL / PostgreSQL". Brief says MySQL.

**D-12 (2026-10-06) — File storage is Laravel's public folder, not S3.** Images go to
`backend/public/uploads/entries/`, path stored relative (`uploads/entries/<random>.<ext>`), `.htaccess` denies script
execution. No `storage:link`.

**D-13 (2026-10-06) — Email is plain SMTP, not SendGrid/SES.** Local `MAIL_MAILER=log`; production SMTP via `.env`.

**D-14 (2026-10-06) — Mobile is Flutter (not React Native).**

**D-15 (2026-10-06) — Web notifications are shown at next page load, not pushed in real time.** Image (panel 6)
says "Show notification on Web (real-time or next login)". Written 3.7 says web sees it at next login, stored in DB
with an unread count. We do the simpler option: unread count + list rendered on every web page load (so "next
login" or next page view). No websockets (not available on shared hosting).

**D-16 (2026-10-06) — `user_sessions` and `subscriptions` get extra columns beyond the image.** Written spec already
adds `is_active`/`updated_at` to `user_sessions` and `sort_order` to `categories`. Implementation additionally needs:
- `user_sessions.token_id` (nullable FK → `personal_access_tokens.id`), `ip_address`, `user_agent`, `last_seen_at`
  — to tie the single active session to a Sanctum token / web session and show it in logs.
- `subscriptions.reminder_sent_at` (nullable timestamp) — guarantees the expiry e-mail is never sent twice for the
  same subscription period (reset to null on extend).
- `subscriptions.notes` (nullable) — free text for the Super Admin.
These are additive and do not contradict the spec.

### Open questions from section 4 (defaults taken — owner may change)

**D-17 (2026-10-06) — Q1 Subscription scope: one global subscription per user.** A user with an active subscription
can see 7 entries in every active category; a new-entry push goes to every user with an active subscription.
Future-proofing: all checks go through `SubscriptionService::isActiveFor(User $user, ?Category $category = null)` and
`SubscriptionService::eligibleUsersFor(Category $category)`. Adding a nullable `subscriptions.category_id` later only
changes those two methods, not callers.

**D-18 (2026-10-06) — Q2 Expired users do NOT receive push notifications** (and do not get a DB notification row for
new entries either, matching panel 7: "Push notifications enabled again" after renewal).

**D-19 (2026-10-06) — Q3 Entries are immutable for everyone**, including Super Admin. No edit/delete routes exist.
Super Admin can only view/filter.

**D-20 (2026-10-06) — Q4 Payments are recorded manually by Super Admin** in `subscription_payments` (optionally while
activating/extending a subscription). No payment gateway.

### Design decisions taken in Phase 0

**D-21 (2026-10-06) — Custom notifications table is named `user_notifications`** (model `UserNotification`) with the
exact columns from the spec (`user_id, category_id, type, message, is_read, created_at`). We do NOT use Laravel's
database notification channel (its schema is polymorphic with a JSON `data` column and `read_at`, which does not
match the spec), and we do not create Laravel's `notifications` table, so there is no clash. Laravel Notifications /
Mailables are still used for e-mail.

**D-22 (2026-10-06) — Subscription history model.** A user can have many `subscriptions` rows (history). At most one
row per user has `status = active`. "Current subscription" = the latest row. Actions:
- *activate*: expires any active row, creates a new row (`start_date`, `end_date`, `status=active`, `created_by`).
- *extend*: moves `end_date` of the current row forward (re-activates it if it was expired) and resets
  `reminder_sent_at`.
- *expire*: sets `status = expired` (and `end_date = today` if it was in the future).
A subscription is treated as active when `status = active` AND `end_date >= today` — so access is correct even if
the daily expire job has not yet run. `end_date` is inclusive (the user keeps access through that whole day).

**D-23 (2026-10-06) — Single session via `user_sessions` + middleware.** On login (web or API):
deactivate all the user's `user_sessions` rows, delete all their Sanctum tokens, delete their rows from the Laravel
`sessions` table (other web sessions), then create the new row. Web stores `user_session_id` in the session; API
stores the Sanctum token id in `user_sessions.token_id`. Middleware `EnsureActiveSession` (web + API) rejects requests
whose session/token is not the active one: web → logout + redirect to login with message; API → HTTP 401 envelope
with `errors.code = "session_replaced"`. Mandatory for `user`; for `admin`/`superadmin` controlled by
`config('astrogann.single_session_staff')` (env `SINGLE_SESSION_STAFF`, default `true`).

**D-24 (2026-10-06) — activity_logs columns:** `id, user_id (nullable), action, subject_type, subject_id,
description, ip_address, user_agent, created_at`. "subject" from the brief is split into a nullable polymorphic pair
plus a human-readable description, so reports can filter by type.

**D-25 (2026-10-06) — settings table is key/value** (`id, key unique, value text, timestamps`), read through a cached
`SettingsService`. Keys: `app_name`, `entries_limit_active` (7), `entries_limit_expired` (3), `reminder_days` (3),
`show_more_text`, `contact_phone`, `contact_whatsapp`, `contact_email`, `notice_text`. Exposed publicly (only the
whitelisted public keys) by `GET /api/settings`.

**D-26 (2026-10-06) — Entry ordering** is `entry_date DESC, entry_time DESC, id DESC` everywhere ("latest" = by the
entry's own date/time, not upload time). `entries` has `created_at` only (no `updated_at`), as specified.

**D-27 (2026-10-06) — Uploads:** allowed `jpg, jpeg, png, webp`; max size `ENTRY_IMAGE_MAX_KB` (default 5120 KB);
validated with `image` + `mimes` + real MIME sniffing; stored under a random 40-char name; folder has an
`.htaccess` that disables PHP/script execution; a custom filesystem disk `uploads` with root `public_path('uploads')`.

**D-28 (2026-10-06) — Push:** `PushService` interface with `LogPushService` (default, writes to the log) and
`FcmPushService` (FCM HTTP v1 API, OAuth2 service-account JWT signed with PHP's `openssl_sign` — no extra package).
Driver chosen by `PUSH_DRIVER=log|fcm`. The legacy FCM server-key API was shut down by Google in 2024, so v1 is
the only option.

**D-29 (2026-10-06) — Queues/cron on shared hosting:** database queue, no daemon. One cron line runs
`php artisan schedule:run` every minute; the scheduler runs `queue:work --stop-when-empty --max-time=50` every
minute (without overlapping) plus the daily jobs. Jobs chunk user lists (e.g. 200 per chunk) so each run is short.

**D-30 (2026-10-06) — Built front-end assets are committed.** Breeze uses Vite/Tailwind. GoDaddy shared hosting has
no Node, so `backend/public/build` is removed from `.gitignore` and committed after `npm run build`.

**D-31 (2026-10-06) — API is used by every role, the mobile app only by `user`.** Admin and Super Admin can call
their API endpoints (`/api/entries`, `/api/admin/*`) with a token; the Flutter app refuses to log in non-`user`
accounts with a clear message.

**D-32 (2026-10-06) — `/api/admin/*` "create / manage" semantics.** Exact paths are kept. `POST` creates. Management
is added under the same prefixes: `GET /api/admin/users`, `PATCH /api/admin/users/{id}` (incl. `status`), same for
`/api/admin/admins`. `POST /api/admin/subscription` takes `action = activate|extend|expire`.

**D-33 (2026-10-06) — Flutter packages (needs owner approval only if you object).** The "first-party only" rule is
for Laravel. The Flutter app will use: `http`, `shared_preferences` (token storage that works on web + devices),
`firebase_core` + `firebase_messaging` (Phase 12), `intl`, `url_launcher` (WhatsApp/phone/email for "Show More").
All are official Flutter/Firebase/Dart-team packages.

**D-34 (2026-10-06) — Further additive columns.** `users.phone` (nullable, trader contact shown to Super Admin),
`subscription_payments.subscription_id` (nullable FK, links a payment to the period it paid for) and
`subscription_payments.recorded_by` (nullable FK users, the Super Admin who recorded it). Additive only.

## Phase 1 — 2026-10-06

**D-35 (2026-10-06) — Installed versions.** Laravel 12.69.3, Breeze (Blade stack, PHPUnit), Sanctum via
`php artisan install:api`. `config/app.php` timezone now reads `APP_TIMEZONE`.

**D-36 (2026-10-06) — Tests run on SQLite in-memory** (Laravel default in `phpunit.xml`) so `php artisan test` never
touches the real `astrogann` database. Code must stay portable (no MySQL-only SQL in app code).

**D-37 (2026-10-06) — `php artisan db:show` fails on XAMPP MariaDB** (`performance_schema.session_status` missing).
Harmless; use `php artisan tinker` / `migrate:status` to check the connection instead.
