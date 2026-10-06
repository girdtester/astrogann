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

## Phase 3 — 2026-10-06

**D-38 (2026-10-06) — "Remember me" is removed from the login form.** A remember-me cookie would silently start a new
web session after the old one expired, outside the single-session rule. Users log in again after
`SESSION_LIFETIME` (default 120 min, adjustable in `.env`).

**D-39 (2026-10-06) — Self-service account changes are limited.** Breeze's email verification, registration and
"delete account" were removed. The profile page lets a user change their name and password only; email, role and
status are changed by the Super Admin. Password reset by email stays (uses SMTP in production).

**D-40 (2026-10-06) — `EnsureActiveSession` also enforces `status = active`.** It is appended to the `web` middleware
group (runs on every web request with a logged-in user) and added after `auth:sanctum` on API routes. A deactivated
user is logged out on the next request (web) or gets 403 `account_inactive` (API).

**D-41 (2026-10-06) — After login every role goes to its own dashboard** (`/superadmin`, `/admin`, `/user`), not to
the "intended" URL, so a stale link to another role's area cannot produce a confusing 403 right after login.

## Phase 4 — 2026-10-06

**D-42 (2026-10-06) — Dynamic roles & permissions (owner request during Phase 4).** The owner asked for roles and
permissions that the Super Admin can manage (create new roles, give permissions to roles, give extra permissions to
individual admins) instead of fixed roles. This **supersedes** brief section 2 "a role column on users" and spec
3.5 `users.role (superadmin/admin/user)`. Implementation, still first-party only (Gates + own tables, no package):
- Tables `roles` (name, slug, kind, description, is_system), `permissions` (key, group, label, description, sort),
  `permission_role`, `permission_user` (extra per-user grants); `users.role_id` FK replaces `users.role`.
- `kind` decides the panel: `superadmin` (implicitly every permission, home /superadmin), `staff` (back office,
  access = permissions, home /admin), `user` (subscriber/trader, home /user). System roles: Super Admin, Admin,
  User / Trader (cannot be deleted). Custom roles are always `staff`.
- Permission keys are defined in code (`App\Support\Permissions`) because each one guards a feature; they are synced
  into the DB by `AccessSeeder` (safe in production). Which role/user holds them is data.
- Every management route uses `can:<permission>`; navigation shows only permitted links.
- The built-in Admin role defaults to the spec's "data entry only" permissions (`categories.view`,
  `entries.create`, `entries.view_own`). The Super Admin may change them, so the spec's "NOT allowed" list for Admin
  is now the *default*, not a hard rule.
- Anti-escalation: an actor can only grant permissions it holds; can only assign roles whose permissions it holds;
  can only manage accounts whose permissions are a subset of its own; never its own account; never a Super Admin.
- Entries stay immutable for everyone (no permission exists to edit/delete them, D-19 unchanged).

**D-43 (2026-10-06) — Accounts with history are never hard-deleted.** Accounts with entries, subscriptions or
payments can only be deactivated; empty accounts can be deleted. Same for categories with entries.

**D-44 (2026-10-06) — Profile email locked;** account email is changed by staff holding `staff.manage` /
`users.manage`.

## Phase 5 — 2026-10-06

**D-45 (2026-10-06) — Entry date window.** `entry_date` must be within the last 365 days and at most 7 days ahead
(stops typos like 2062); `entry_time` is `HH:MM` 24h (`HH:MM:SS` from clients is trimmed). Adjust in
`StoreEntryRequest` if needed.

**D-46 (2026-10-06) — Upload checks:** `file|image|mimes:jpg,jpeg,png,webp|mimetypes:image/jpeg,image/png,image/webp|
max:ENTRY_IMAGE_MAX_KB|dimensions:max 8000x8000`; stored extension comes from the sniffed MIME type; random
40-char name; the file is removed again if the DB insert fails. Web upload route throttled to 30/min per user.

**D-47 (2026-10-06) — `EntryPolicy`.** create = `entries.create`; view = `entries.view_all` or (own and
`entries.view_own`); update/delete = always false. No edit/delete routes exist for entries anywhere.

## Phase 6 — 2026-10-06

**D-48 (2026-10-06) — Web tabs are plain links** (`/user?category={id}`), server-rendered; no JavaScript needed and
the selected tab is bookmarkable. Tabs = `CategoryService::activeTabs()` (status active, `sort_order`, then name).

**D-49 (2026-10-06) — A user with no subscription at all is treated like an expired one** (3 entries + Show More).

**D-50 (2026-10-06) — "Show More" is a button that reveals the contact options** (tel:, WhatsApp `wa.me/<digits>`,
mailto:) with the `show_more_text` setting; on the subscription page it is open by default when not active.

**D-51 (2026-10-06) — Notifications page:** opening a notification marks it read and jumps to its category tab;
"Mark all as read" button; unread count badge on the "Notifications" nav link (every page load).

## Phase 7 — 2026-10-06

**D-52 (2026-10-06) — `user_sessions.ended_reason` (logout / replaced / deactivated).** A new login still revokes
every old token (spec 3.8), so a revoked token would normally only produce "unauthenticated". The API exception
renderer reads the token id from the bearer string (`{id}|{secret}`), finds the ended session and answers
`401 session_replaced` or `403 account_inactive`, so the app can show the right message. New migration
`2026_10_06_130000_add_ended_reason_to_user_sessions`.

**D-53 (2026-10-06) — API additions beyond spec 3.6** (paths of the spec unchanged): `GET /api/auth/me`,
`POST /api/notifications/{id}/read`, `POST /api/notifications/read-all`, `GET /api/admin/users`,
`PATCH /api/admin/users/{id}`, `GET /api/admin/admins`, `PATCH /api/admin/admins/{id}` (the "manage" part of
"create / manage"). Admin endpoints are guarded by permissions (D-42), not by the role name.

**D-54 (2026-10-06) — Rate limits:** `api-public` 30/min per IP (login, settings); login additionally 5 failed
attempts/min per email+IP; authenticated `api` 60/min per user.

**D-55 (2026-10-06) — Staff calling `GET /api/categories/{id}/entries` get the active limit** (they are not
subscribers). Traders follow the subscription rule.

**D-56 (2026-10-06) — `ForceJsonResponse` on the api group** + `ApiExceptionRenderer`: every `/api/*` error is the
envelope with `errors.code` (table in docs/API.md §1).

## Phase 8 — 2026-10-06

**D-57 (2026-10-06) — Notification pipeline.** `EntryService::create` fires `EntryCreated` → listener
`QueueEntryNotifications` queues `NotifyUsersOfNewEntry` (after commit). The job takes one chunk of eligible users
(`NOTIFICATION_CHUNK`, default 200, ordered by id), bulk-inserts `user_notifications`, queues `SendPushToUsers` for
that chunk and re-dispatches itself for the next chunk. Push goes to the active session's `fcm_token` only (single
session ⇒ one device). Invalid tokens reported by FCM are cleared. Jobs: 3 tries with backoff, `failed()` writes an
activity log row (`job.failed`); failed jobs stay in `failed_jobs` (pruned after 30 days).

**D-58 (2026-10-06) — Expiry reminder window.** Daily 09:00 the reminder command takes active subscriptions ending
between today and today+`reminder_days` that have `reminder_sent_at = NULL`, claims each atomically, queues the
e-mail (`SubscriptionExpiringMail`), stores an `expiry` in-app notification and a push. A window instead of
"exactly 3 days" means a missed cron day or a short new period still gets exactly one reminder. Extending resets
the flag.

**D-59 (2026-10-06) — Scheduler** (`routes/console.php`): `queue:work --stop-when-empty --max-time=50` every minute
(withoutOverlapping), `astrogann:expire-subscriptions` 00:05, `astrogann:send-expiry-reminders` 09:00,
`queue:prune-failed` and `sanctum:prune-expired` daily. Production = one cPanel cron running `schedule:run`.

**D-60 (2026-10-06) — FCM test key.** `tests/Fixtures/fcm-test-private-key.pem` is a throwaway RSA key generated
only for the FCM driver test (XAMPP's OpenSSL cannot generate keys without an `openssl.cnf`). It is not a real
credential.

## Branding (owner details, 2026-10-06)

**D-61 (2026-10-06) — Name, theme, logo.** App name **ASTRO GANN** (setting `app_name` + `APP_NAME`). Dark theme
with olive green (primary), light blue (info/links) and yellow (warnings/attention) — `tailwind.config.js` uses
"dark-semantic" palettes (low numbers = dark tints, high numbers = bright tones), so existing class names render on
the dark background. **No red anywhere** (owner rule): the `red` palette is remapped to yellow as a safety net,
every red class was replaced, demo placeholder images use olive/blue/yellow, the CSS bundle was checked for red
values. Branded dark error pages (`resources/views/errors`). Logo: original kept in `docs/brand/logo-original.png`;
`public/images/logo.png` (512), `logo-192.png`, `favicon-64.png`, `favicon-32.png` are cropped to the circle with
a transparent background. Note: the owner's logo artwork itself contains some red squares — it is used unchanged.

**D-62 (2026-10-06) — Contact details.** `contact_email` = ganndecoder@gmail.com (the owner's professional
address, "use this for all work"). `contact_phone` and `contact_whatsapp` hold **comma-separated lists**
(+91 75583 77920, +91 81493 43315 — both for calls and WhatsApp); the web "Show More" shows one Call and one
WhatsApp button per number and the API returns `contact.phones[]` / `contact.whatsapp_links[]` (plus the original
strings). The owner's personal e-mail is intentionally not stored anywhere in the project.

**D-63 (2026-10-06) — Mail sender.** `MAIL_FROM_ADDRESS` = ganndecoder@gmail.com locally/in `.env.example`; every
reminder sets **Reply-To = contact_email**. For production deliverability see DEPLOYMENT.md §5 (a @gmail.com
From address sent through GoDaddy's servers may land in spam; a mailbox on the own domain with Reply-To Gmail is
the safer choice).

## Phase 9 — 2026-10-06

**D-64 (2026-10-06) — `SecurityHeaders` middleware** on every response (X-Frame-Options SAMEORIGIN, nosniff,
Referrer-Policy, Permissions-Policy, HSTS when HTTPS).

**D-65 (2026-10-06) — `.public_path` marker file** (next to `artisan`) relocates Laravel's public folder for hosts
whose web root must be `public_html`; works for web and cron because it is read in `bootstrap/app.php`.

**D-66 (2026-10-06) — `php artisan astrogann:create-superadmin {name} {email} [--password=]`** creates the first
production Super Admin (non-interactive, cron-friendly; prints a random password).

**D-67 (2026-10-06) — Owner instruction:** after the web part is complete, stop and ask before starting the mobile
app (Phase 10).

## Owner change requests — 2026-10-06 (after Phase 9)

**D-69 (2026-10-06) — Light theme.** Owner: "all olive background → white is better for visibility". Supersedes the
dark part of D-61: white cards on a very light grey page, olive green primary, light-blue info/links, yellow
attention; still **no red** (red/rose/pink/orange palettes remapped to yellow). Normal Tailwind palette semantics.

**D-70 (2026-10-06) — Grouped top menu.** Owner: the top bar must fit the screen without horizontal scrolling. Menu
= Dashboard · Accounts ▾ (Admins/Staff, Users/Traders, Roles & Permissions) · Subscriptions · Entries ▾ (Add Entry,
All/My Entries, Categories) · Reports/Logs · Settings. Each item still only appears with its permission; a group
with one visible item becomes a plain link. Desktop dropdowns from 1024 px; below that a hamburger with grouped
sections. Built in `App\Support\Navigation::menu()`.

**D-71 (2026-10-06) — Entries are editable/deletable (supersedes D-19 and D-47).** Owner: "Super Admin can do
everything like edit and delete, but we restrict as well". New permissions `entries.edit` and `entries.delete`
(Super Admin has them; the default Admin role does not; grant them per role or per account). Reach: all entries with
`entries.view_all`, otherwise only own. Edit = category/date/time/replace image (old file removed, no new
notification); delete removes the image file. Web: `/admin/entries/{id}/edit|update|destroy`; API:
`POST /api/entries/{id}` (edit, multipart) and `DELETE /api/entries/{id}`. Logged as `entry.updated` /
`entry.deleted`.

**D-72 (2026-10-06) — Deleting records with history (refines D-43).** New permission `records.force_delete`
(Super Admin has it): users with subscriptions/payments can be deleted (their subscriptions, payments, sessions and
notifications go with them); staff with entries can be deleted (entries are re-assigned to the person deleting);
categories with entries can be deleted (entries and image files are deleted). Without this permission the old rule
stays (deactivate instead). Super Admin accounts are never deletable in the app.
