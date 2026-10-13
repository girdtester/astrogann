# AstroGann — Specification

Source: `docs/architecture.png` (7 panels) + section 3 of the project brief (the written transcription).
Where the two disagree, the written spec wins and the difference is logged in `docs/DECISIONS.md` (D-06…D-16).
Keep this file in sync with decisions. Never delete content — mark superseded items with a date.

## 0. Product

AstroGann is a subscription-based platform where admins post image entries under market categories (Nifty 50, NSE,
etc.) and subscribed users/traders view the latest entries on web and, later, on Android and iOS.

## 1. High-level architecture (image panel 1)

- Clients: Web app (Laravel Blade — D-10), Android app, iOS app (Flutter — D-14), all over HTTPS.
- Application server (API backend): REST APIs, authentication (single session), business logic, subscription logic,
  notification service, email service, dynamic categories and entries.
- Database: MySQL/MariaDB (D-03, D-11) — users, categories, entries, subscriptions, logs.
- File storage: entry images in Laravel `public/uploads/entries/` (NOT S3 — D-12).
- Notification services: Firebase Cloud Messaging (push); e-mail for subscription expiry via SMTP (NOT SendGrid/SES — D-13).

## 2. Roles and permissions (image panel 2)

> 2026-10-06 (D-85): users can sign up themselves (web + app, switchable in Settings); new accounts have no
> subscription (latest 3 + Show More). (D-84) Forgot password works on web and app.
>
> 2026-10-06 (D-71/D-72): entries are no longer immutable — Super Admin (and roles granted `entries.edit` /
> `entries.delete` / `records.force_delete`) can edit and delete. The "Admin: no edit / no delete" rule is the
> default of the Admin role.
>
> 2026-10-06 (D-42): roles and permissions are now dynamic. The lists below are the **defaults** of the three
> system roles; the Super Admin can create staff roles, change the Admin role's permissions and give extra
> permissions to individual staff accounts. Entries remain immutable for everyone.

### Super Admin (full access)
- Login / dashboard
- Create / manage admins
- Create / manage users/traders
- Activate / deactivate users
- Manage subscriptions (activate, extend, expire) — D-08
- Manage categories (Nifty 50, NSE, etc.)
- View all entries
- View reports / logs
- System settings

### Admin (data entry only)
- Login
- View categories
- Add new entry (select category, date, time, image)
- View entries (own added entries only) — D-07
- NOT allowed: edit entries, delete entries, user management, subscription access, category management

### User / Trader (end user, "Paid / Expired")
- Login (web / mobile)
- View active categories
- See latest 7 entries if subscription is active
- See latest 3 entries if subscription is expired *(superseded 2026-10-13, D-106: the oldest 3 of the latest 7)*
- "Show More" button (contact admin for full access / payment)
- Receive push notifications for new entries
- Receive email 3 days before subscription expires
- Single login allowed (one device at a time, web or mobile)

## 3. End-to-end flow (image panel 3)

1. Super Admin creates Admin.
2. Super Admin manages categories.
3. (image) Super Admin creates end users and manages their subscription.
4. Admin adds a new entry (category, date, time, image).
5. System sends push notification to all subscribed users of that category.
6. Users view the latest 7 entries (if subscription active) on web / mobile.
7. Subscription ending in 3 days: system sends email reminder to the user.
8. Subscription expired: user can still log in but sees only the 3 latest entries plus "Show More" (contact admin).
9. Super Admin renews subscription: user gets full access (7 latest entries) again, push enabled again.

## 4. Subscription and access logic (image panel 7)

- On every entries request, check the user's subscription status (D-22: active = `status=active` and `end_date >= today`).
- Active: latest 7 entries of the selected category, push notifications, expiry e-mail, no restrictions.
- Not active: latest 3 entries *(superseded 2026-10-13, D-106: the oldest 3 of the latest 7, newest first)* of the selected category, "Show More" button (contact admin and make payment). After
  Super Admin activates/extends, restrictions are removed and push is enabled again.
- Expiry email: daily job finds subscriptions expiring in N (=3, setting) days and emails "Subscription will expire
  in 3 days". Never twice for the same subscription period (D-16 `reminder_sent_at`). The user keeps normal access
  until the expiry date (inclusive).
- A daily job marks subscriptions with `end_date < today` as expired.
- Subscription scope: one global subscription per user (D-17).

## 5. Database design (image panel 4 + written additions) — details in `docs/DATABASE.md`

- users: id, name, email (unique), password, role (superadmin/admin/user), status (active/inactive), timestamps
- categories: id, name, code, status (active/inactive), sort_order, timestamps
- entries: id, category_id (FK), admin_id (FK users), image_path, entry_date, entry_time, created_at
- subscriptions: id, user_id (FK), start_date, end_date, status (active/expired), created_by (FK users), timestamps
  (+ reminder_sent_at, notes — D-16)
- user_sessions: id, user_id (FK), device_type (web/mobile), fcm_token (nullable), is_active, timestamps
  (+ token_id, ip_address, user_agent, last_seen_at — D-16)
- user_notifications (spec name "notifications", renamed — D-21): id, user_id, category_id, type (new_entry/expiry),
  message, is_read, created_at
- subscription_payments: id, user_id (FK), amount, payment_date, transaction_ref, status, created_at
- activity_logs (D-24), settings (D-25)
- Laravel tables: jobs, job_batches, failed_jobs, sessions, personal_access_tokens, cache, cache_locks,
  password_reset_tokens
- Indexes on all FKs, entries(category_id, entry_date, entry_time), subscriptions(user_id, status, end_date).

## 6. API endpoints (image panel 5 + written 3.6) — contract in `docs/API.md`

| Method | Endpoint | Description |
|---|---|---|
| POST | /api/auth/login | Login (single session, logs out previous). Accepts device_type, optional fcm_token |
| POST | /api/auth/logout | Logout |
| GET | /api/categories | Active categories |
| GET | /api/categories/{id}/entries | Latest 7 or 3 entries based on subscription |
| POST | /api/entries | (Admin) add new entry |
| GET | /api/notifications | User notifications |
| GET | /api/subscription | User subscription details |
| POST | /api/admin/users | (Super Admin) create / manage users |
| POST | /api/admin/admins | (Super Admin) create / manage admins |
| POST | /api/admin/subscription | (Super Admin) activate / extend / expire subscription |
| GET | /api/settings | Public app config (written 3.9) |
| POST | /api/update-fcm-token | Save/update FCM token (image only — D-09) |

Envelope for every response: `{ "success": bool, "message": string, "data": any|null, "errors": object|null }`.
API Resources for output, Form Requests for input.

## 7. Notification flow (image panel 6)

Admin adds new entry → system identifies all subscribed users (D-17/D-18) → stores a `user_notifications` row per
user → sends FCM push (mobile, real time) → web shows the notification and unread count at next page load / login
(D-15). Example message: "New entry added in Nifty 50. Check latest update now!"

## 8. Single login session (written 3.8)

- A user can log in from any device (web, Android, iOS).
- A new login automatically logs out the previous session.
- Only one active login session per user at a time, web OR mobile.
- Implementation: D-23. Mandatory for `user`; config flag (default on) for admin/superadmin.

## 9. User screen layout and "everything dynamic" rule (written 3.9, web AND mobile)

- Entries screen is tab-based: one tab per active category; names, order (`sort_order`) and count come from the
  database/API, never from code.
- Inside a tab: entries newest first, each with image, entry date and entry time; 7 or 3 per subscription; then
  "Show More" when restricted.
- Adding/renaming/reordering/activating/deactivating a category changes the tabs on web and mobile with no code
  change and no app update.
- Nothing shown to users is hard-coded: category list, entry limits (7/3), reminder days (3), "Show More" text and
  contact-admin details (phone, WhatsApp, email), app name, notice text — all from `settings` / API.
  `GET /api/settings` serves the mobile app; web uses the same `SettingsService`.
- Web and mobile read the same data through the same services.

## 10. Deployment constraints (brief section 2)

GoDaddy shared hosting (cPanel): no S3/SES/Redis/Supervisor, maybe no SSH. Images in `public/uploads/entries/`,
SMTP mail, database queue driven by one cron (`schedule:run` every minute), FCM over HTTP. See `docs/DEPLOYMENT.md`.
