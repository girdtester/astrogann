# AstroGann — REST API contract

Status: **implemented (Phase 7, 2026-10-06)**. This file is the contract for the Flutter app.
Try it with `docs/api.http` (VS Code REST Client / JetBrains HTTP client). Tests: `backend/tests/Feature/Api/ApiTest.php`.

Base URL: `{APP_URL}/api` — local `http://localhost:8000/api`.

## 1. Conventions

- **Auth:** Laravel Sanctum bearer token from `POST /api/auth/login` → header `Authorization: Bearer <token>`.
  Tokens do not expire on their own; they end on logout, on a new login of the same account (single session), or
  when the account is deactivated.
- **JSON always.** The server forces `Accept: application/json` on `/api/*`. Request bodies: JSON or form data;
  `POST /api/entries` must be `multipart/form-data`.
- **Envelope** for every response, success or error:

```json
{ "success": true, "message": "OK", "data": { }, "errors": null }
```

- **Errors:** `success=false`, `data=null`. Validation (422): `errors` = `{ field: [messages] }`. Other errors:
  `errors.code` is one of:

| HTTP | `errors.code` | Meaning / what the app should do |
|---|---|---|
| 401 | `unauthenticated` | No/unknown token → show login |
| 401 | `invalid_credentials` | Wrong email/password at login |
| 401 | `session_replaced` | Account logged in on another device → clear token, show `message` on the login screen |
| 403 | `account_inactive` | Account deactivated by the admin → clear token, show `message` |
| 403 | `forbidden` | Role/permission does not allow this |
| 404 | `not_found` | Unknown id, or inactive category |
| 422 | `invalid` | Business rule failed (e.g. extend to an earlier date); `message` explains |
| 429 | `too_many_requests` | Rate limit hit |
| 500 | `server_error` | Unexpected error |

- **Formats:** dates `YYYY-MM-DD`; entry times `HH:MM` (24 h); timestamps ISO-8601 with offset (`Asia/Kolkata`).
- **Rate limits:** login & settings 30/min per IP and 5 failed logins/min per email+IP; authenticated 60/min per user.
- **Pagination** (`GET /api/notifications`, `/api/admin/users`, `/api/admin/admins`): `?page=N&per_page=M`;
  `data = { items: [...], meta: { current_page, last_page, per_page, total } }`.
- **Single session:** a successful login ends every other web session and API token of that account (spec 3.8).

## 2. Endpoints

| # | Method | Path | Who | Purpose |
|---|---|---|---|---|
| 1 | POST | /api/auth/login | public | Login (single session) |
| 2 | POST | /api/auth/logout | any logged in | Logout |
| 3 | GET | /api/auth/me | any logged in | Current account + subscription |
| 4 | GET | /api/settings | public | App config (names, limits, contact details) |
| 5 | GET | /api/categories | any logged in | Active categories in tab order |
| 6 | GET | /api/categories/{id}/entries | any logged in | Latest 7 / 3 entries by subscription |
| 7 | POST | /api/entries | permission `entries.create` (Admin) | Add entry |
| 8 | GET | /api/notifications | any logged in | Notifications + unread count |
| 9 | POST | /api/notifications/{id}/read | any logged in | Mark one read |
| 10 | POST | /api/notifications/read-all | any logged in | Mark all read |
| 11 | GET | /api/subscription | role kind `user` | Subscription details |
| 12 | POST | /api/update-fcm-token | any logged in | Save/refresh the FCM token of this session |
| 13 | GET / POST | /api/admin/users | permission `users.manage` | List / create users (traders) |
| 14 | PATCH | /api/admin/users/{id} | permission `users.manage` | Update a user (incl. `status`) |
| 15 | GET / POST | /api/admin/admins | permission `staff.manage` | List / create staff (admins) |
| 16 | PATCH | /api/admin/admins/{id} | permission `staff.manage` | Update staff (incl. `status`, `role_id`, `permissions`) |
| 17 | POST | /api/admin/subscription | permission `subscriptions.manage` | activate / extend / expire |

Rows 1–2, 4–8, 11, 13–17 are the spec's endpoints (3.6) with their exact paths. Rows 3, 9, 10 are additions for the
app; row 12 comes from the architecture image (DECISIONS D-09). Super Admin has every permission; the other roles
and permissions are configurable (DECISIONS D-42).

### 2.1 POST /api/auth/login

Request:
```json
{ "email": "user.active@astrogann.test", "password": "password", "device_type": "mobile", "fcm_token": "optional" }
```
`device_type` is `mobile` or `web`. Response 200:
```json
{
  "success": true,
  "message": "Logged in. Any other session of this account has been logged out.",
  "data": {
    "token": "12|q9Xc...",
    "token_type": "Bearer",
    "session_id": 31,
    "user": {
      "id": 3, "name": "Active Trader", "email": "user.active@astrogann.test", "phone": "+91 90000 00001",
      "status": "active",
      "role": { "id": 3, "name": "User / Trader", "slug": "user", "kind": "user" },
      "created_at": "2026-10-06T11:55:00+05:30"
    },
    "subscription": { "status": "active", "is_active": true, "start_date": "2026-10-01", "end_date": "2026-11-05", "days_left": 30, "entries_limit": 7 }
  },
  "errors": null
}
```
For staff accounts `user.permissions` (list of keys) is included and `subscription` is `null`.
Errors: 422 validation, 401 `invalid_credentials`, 403 `account_inactive`, 429 `too_many_requests`.
**The mobile app accepts only `user.role.kind == "user"`** (DECISIONS D-31).

### 2.2 POST /api/auth/logout
No body. 200 `{ "success": true, "message": "Logged out.", "data": null, "errors": null }`.

### 2.3 GET /api/auth/me
200 `data = { "user": {...as login...}, "subscription": {...} | null }`.

### 2.4 GET /api/settings (public)
```json
{ "success": true, "message": "OK", "errors": null, "data": {
  "app_name": "AstroGann", "entries_limit_active": 7, "entries_limit_expired": 3, "reminder_days": 3,
  "show_more_text": "Your subscription is not active. Contact the admin to renew and see all the latest entries.",
  "contact": { "phone": "+91 90000 00000", "whatsapp": "+919000000000", "email": "support@astrogann.test" },
  "notice_text": null } }
```

### 2.5 GET /api/categories
```json
{ "success": true, "message": "OK", "errors": null, "data": [
  { "id": 1, "name": "Nifty 50", "code": "NIFTY50", "sort_order": 1 },
  { "id": 2, "name": "NSE", "code": "NSE", "sort_order": 2 } ] }
```
Only active categories, ordered by `sort_order` then name. Build the tabs from this list — never hard-code it.

### 2.6 GET /api/categories/{id}/entries
```json
{ "success": true, "message": "OK", "errors": null, "data": {
  "category": { "id": 1, "name": "Nifty 50", "code": "NIFTY50", "sort_order": 1 },
  "subscription_active": false,
  "limit": 3,
  "restricted": true,
  "entries": [
    { "id": 60, "category_id": 1, "image_url": "http://localhost:8000/uploads/entries/seed-nifty50-1.png",
      "entry_date": "2026-10-06", "entry_time": "09:15", "created_at": "2026-10-06T11:55:02+05:30" }
  ],
  "show_more": { "text": "Your subscription is not active…", "phone": "+91 90000 00000", "whatsapp": "+919000000000", "email": "support@astrogann.test" }
} }
```
Entries are newest first (`entry_date`, `entry_time`). `limit` is 7 for an active subscription, 3 otherwise
(values from settings). `show_more` is `null` when not restricted. Staff accounts always get the active limit.
404 `not_found` if the category does not exist or is inactive.

### 2.7 POST /api/entries (multipart/form-data)
Fields: `category_id` (active category), `entry_date` (`YYYY-MM-DD`, within the last year … +7 days),
`entry_time` (`HH:MM`), `image` (jpg/jpeg/png/webp, ≤ `ENTRY_IMAGE_MAX_KB`, default 5 MB).
201 `data` = one entry object (as in 2.6). 403 without `entries.create`. 422 on validation errors.
Creating an entry queues the notifications/push to subscribed users (Phase 8).

### 2.8 GET /api/notifications
```json
{ "success": true, "message": "OK", "errors": null, "data": {
  "items": [ { "id": 9, "type": "new_entry", "message": "New entry added in Nifty 50. Check latest update now!",
               "category_id": 1, "category_name": "Nifty 50", "is_read": false, "created_at": "2026-10-06T12:10:00+05:30" } ],
  "meta": { "current_page": 1, "last_page": 1, "per_page": 20, "total": 1 },
  "unread_count": 1 } }
```
`type` is `new_entry` or `expiry`. `POST /api/notifications/{id}/read` and `POST /api/notifications/read-all`
return `data = { "unread_count": n }`.

### 2.9 GET /api/subscription (users only)
```json
{ "success": true, "message": "OK", "errors": null,
  "data": { "status": "active", "is_active": true, "start_date": "2026-10-01", "end_date": "2026-11-05", "days_left": 30, "entries_limit": 7 } }
```
`status` is `active`, `expired` or `none`. `end_date` is inclusive. 403 `forbidden` for staff accounts.

### 2.10 POST /api/update-fcm-token
Body `{ "fcm_token": "..." }` (or `null` to clear). Stores it on the caller's active session. 200.

### 2.11 /api/admin/users and /api/admin/admins
- `GET` — `?q=search&status=active|inactive&page=&per_page=` → paginated user objects.
- `POST` — `{ name, email, password, password_confirmation, phone?, status? }`; admins additionally accept
  `role_id` (a staff role; default "Admin") and `permissions` (extra permission keys). 201 → user object.
- `PATCH /{id}` — any of `{ name, email, phone, status, password + password_confirmation }`; admins also `role_id`,
  `permissions`. 200 → user object. 404 if the id is not of that kind. 422 `invalid` if the caller may not manage
  that account or grant those permissions (anti-escalation, D-42).

### 2.12 POST /api/admin/subscription
```json
{ "user_id": 3, "action": "activate", "start_date": "2026-10-06", "end_date": "2026-11-05", "notes": "Monthly",
  "payment": { "amount": 1999, "payment_date": "2026-10-06", "transaction_ref": "UPI-123", "status": "paid" } }
```
- `activate`: `end_date` required, `start_date` defaults to today; closes any active period, starts a new one.
- `extend`: `end_date` required and later than the current end (and not in the past); re-activates if expired.
- `expire`: no dates; ends access now.
- `payment` is optional (manual record, D-20).
200 → `{ "user_id": 3, "status": "...", "is_active": ..., "start_date": ..., "end_date": ..., "days_left": ..., "entries_limit": ... }`.

## 3. Example calls (curl)

```bash
B=http://localhost:8000/api
TOKEN=$(curl -s -X POST $B/auth/login -H 'Content-Type: application/json' \
  -d '{"email":"user.active@astrogann.test","password":"password","device_type":"mobile"}' | jq -r .data.token)
curl -s $B/settings
curl -s $B/categories -H "Authorization: Bearer $TOKEN"
curl -s $B/categories/1/entries -H "Authorization: Bearer $TOKEN"
curl -s $B/notifications -H "Authorization: Bearer $TOKEN"
curl -s $B/subscription -H "Authorization: Bearer $TOKEN"
# Admin adds an entry
ATOKEN=$(curl -s -X POST $B/auth/login -d email=admin@astrogann.test -d password=password -d device_type=web | jq -r .data.token)
curl -s -X POST $B/entries -H "Authorization: Bearer $ATOKEN" \
  -F category_id=1 -F entry_date=2026-10-06 -F entry_time=15:30 -F image=@chart.png
```

---

## Appendix — Phase 0 draft (superseded 2026-10-06 by the contract above)

### (old) AstroGann — REST API contract

Status: **draft (Phase 0)** — finalised with examples and a Postman/.http collection in Phase 7.
This file is the contract for the Flutter app. Base URL: `{APP_URL}/api` (local `http://localhost:8000/api`).

#### Conventions

- Auth: Laravel Sanctum bearer token — `Authorization: Bearer <token>`. Send `Accept: application/json`.
- Every response uses one envelope:

```json
{ "success": true, "message": "OK", "data": { }, "errors": null }
```

- Errors: `success=false`, `data=null`, `errors` is an object. Validation (422): `errors` = field → [messages].
- Special error codes in `errors.code`:
  - `session_replaced` (401) — the account logged in somewhere else; the app must log out and show a message.
  - `unauthenticated` (401), `forbidden` (403), `account_inactive` (403), `not_found` (404), `too_many_requests` (429).
- Dates `YYYY-MM-DD`, times `HH:MM` (24h), timestamps ISO-8601 in `Asia/Kolkata`.
- Rate limits: login 5/min per email+IP; authenticated API 60/min per user.

#### Endpoints

| # | Method | Path | Auth / role | Purpose |
|---|---|---|---|---|
| 1 | POST | /api/auth/login | public | Login; single session; body `email, password, device_type (web|mobile), fcm_token?` |
| 2 | POST | /api/auth/logout | any | Revoke token, deactivate session |
| 3 | GET | /api/settings | public | App config: app_name, limits, reminder_days, show_more_text, contact_*, notice_text |
| 4 | GET | /api/categories | any | Active categories ordered by sort_order |
| 5 | GET | /api/categories/{id}/entries | user (others see full) | Latest 7 (active) or 3 (expired) entries + `restricted`, `show_more` |
| 6 | POST | /api/entries | admin | multipart: `category_id, entry_date, entry_time, image` |
| 7 | GET | /api/notifications | any | Paginated notifications + `unread_count` |
| 8 | GET | /api/subscription | user | Current subscription: status, start/end date, days_left, is_active |
| 9 | POST | /api/update-fcm-token | any | Body `fcm_token` — updates the active session (D-09) |
| 10 | GET/POST | /api/admin/users | superadmin | List / create users (traders) |
| 11 | PATCH | /api/admin/users/{id} | superadmin | Update user incl. `status` |
| 12 | GET/POST | /api/admin/admins | superadmin | List / create admins |
| 13 | PATCH | /api/admin/admins/{id} | superadmin | Update admin incl. `status` |
| 14 | POST | /api/admin/subscription | superadmin | `user_id, action (activate|extend|expire), start_date?, end_date?, payment{amount,payment_date,transaction_ref,status}?` |

Request/response examples are added per endpoint in Phase 7.
