# AstroGann — REST API contract

Status: **draft (Phase 0)** — finalised with examples and a Postman/.http collection in Phase 7.
This file is the contract for the Flutter app. Base URL: `{APP_URL}/api` (local `http://localhost:8000/api`).

## Conventions

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

## Endpoints

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
