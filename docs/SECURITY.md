# AstroGann — Security design

Status: **design (Phase 0)**; web parts of §1–§3 implemented in Phase 3 (2026-10-06) — reviewed and ticked in Phase 9.

## 1. Single-session design (D-23)

API part implemented Phase 7 (2026-10-06); revoked tokens answer `session_replaced` / `account_inactive` via `user_sessions.ended_reason` (D-52).

- Table `user_sessions` holds at most one `is_active = true` row per user.
- Login (web or API) → `SessionService::start()`:
  1. set `is_active = false` on all the user's `user_sessions` rows;
  2. delete all the user's Sanctum tokens (`personal_access_tokens`);
  3. delete the user's rows from the Laravel `sessions` table (kills other browser sessions);
  4. create the new row (`device_type`, `fcm_token`, `ip_address`, `user_agent`) and, for API, the token
     (`token_id` stored on the row); for web, put `user_session_id` into the regenerated session.
- Middleware `session.single` (`EnsureActiveSession`) runs on every authenticated web and API route:
  - web: session's `user_session_id` must point at the user's active row → else logout, invalidate, redirect to
    `/login` with "You were logged out because your account signed in on another device."
  - API: `currentAccessToken()->id` must equal the active row's `token_id` → else 401 `session_replaced`.
- Applies always to `user`; to `admin`/`superadmin` when `config('astrogann.single_session_staff')` is true (default).
- Logout deactivates the row and deletes the token / invalidates the session.

## 2. Roles

**Superseded in part 2026-10-06 by D-42 (dynamic roles & permissions):** roles are rows in `roles`, permissions are
catalogue keys held by roles (`permission_role`) or granted per user (`permission_user`). Panel guard
`role:<kinds>` (superadmin/staff/user) + per-route `can:<permission>`. Super Admin holds every permission.
Anti-escalation rules: see DECISIONS D-42. Original text kept below for history.

- `users.role` ∈ superadmin/admin/user; no permission package.
- Middleware `role:<roles>` on route groups: `/superadmin/*` → superadmin, `/admin/*` → admin, `/user/*` → user,
  `/api/admin/*` → superadmin, `POST /api/entries` → admin.
- Gates/Policies for model actions: `EntryPolicy` (create: admin; view: admin owns it / superadmin any; update and
  delete: always false), `CategoryPolicy`, `UserPolicy`, `SubscriptionPolicy`.
- Inactive users (`status = inactive`) cannot log in; ~~middleware `EnsureUserIsActive`~~ (superseded 2026-10-06,
  D-40: the check lives in `EnsureActiveSession`) also logs out an already logged-in user who gets deactivated.
- Tests assert 403 for every cross-role access, not just hidden buttons.

## 3. Rate limits

Implemented 2026-10-06 (D-54): `api-public` 30/min/IP, API login 5 failures/min per email+IP, `api` 60/min/user.

- Web login: 5 attempts/min per email+IP (Breeze `LoginRequest` throttle). Failed logins are logged
  (`auth.failed`). Forgot/reset password: 6/min. "Remember me" removed (D-38).
- API login: named limiter `api-login`, 5/min per email+IP.
- Authenticated API: `api` limiter 60/min per user id (fallback IP).
- Upload endpoint shares the authenticated limit.

## 4. Uploads

Implemented Phase 5 (2026-10-06): `StoreEntryRequest`, `EntryService::create`, `public/uploads/.htaccess` (D-46).

- Validation: `required|image|mimes:jpg,jpeg,png,webp|max:${ENTRY_IMAGE_MAX_KB}` (default 5120) plus
  `dimensions:max_width=8000,max_height=8000`.
- Stored at `public/uploads/entries/<Str::random(40)>.<guessed-ext>` (extension from the sniffed MIME, never the
  client filename). Only the relative path is saved in `entries.image_path`.
- `public/uploads/.htaccess`: disables PHP/CGI handlers and script execution, `Options -Indexes`.

## 5. Web hardening

- CSRF on all web POST forms (Laravel default). Public registration route removed.
- Mass assignment: explicit `$fillable` on every model; `role`/`status` never filled from user-facing forms
  except Super Admin forms with validated enum values.
- Passwords: `Password::min(8)->letters()->numbers()` in production (`Password::defaults`), bcrypt hashing.
- Session cookie: `http_only=true`, `same_site=lax`, `secure` via `SESSION_SECURE_COOKIE=true` in production.
- `APP_DEBUG=false` in production; `.env` never committed; `.env.example` has no secrets.
- Activity log rows for: login, logout, failed login, session replaced, admin/user created/updated/status changed,
  category changes, subscription actions, payment recorded, entry created, settings changed.

## 6. Mobile

- Token stored with `shared_preferences` (works on web and devices) — documented trade-off; switch to
  `flutter_secure_storage` for device builds if desired.
- On `session_replaced` the app clears the token and shows the reason on the login screen.


## 6b. Edit / delete (2026-10-06, D-71/D-72)
`EntryPolicy` update/delete = permission (`entries.edit` / `entries.delete`) AND reach (all with `entries.view_all`,
else own). Routes use `can:update,entry` / `can:delete,entry`; the request classes re-check. Deleting records with
history needs `records.force_delete`. All edits/deletes are activity-logged.

## 6c. Direct URL access (2026-10-06, D-76)
User tabs use category codes, never ids; inactive/unknown categories cannot be opened by editing the URL (redirect,
no details). API: inactive category → 404. Notifications are scoped to their owner (others → 404). Sort/filter
parameters are whitelisted (D-77). Uploaded image names are random 40-character strings, uploads folder has no
directory listing.

## 7. Phase 9 review — 2026-10-06

| Area | Result | Evidence |
|---|---|---|
| Authorization on every route | ✅ every route is public-by-list or behind `auth`/`auth:sanctum`; every management/API-admin route has `role:` + `can:` | `SecurityAuditTest` (route table scan, fails on any new unguarded route) |
| Single session | ✅ web + API, staff flag, `session_replaced` / `account_inactive` codes | `SingleSessionTest`, `ApiTest`, `EndToEndFlowTest` |
| Privilege escalation (dynamic roles) | ✅ grant ⊆ own permissions, no self-management, Super Admin untouchable | `RolesPermissionsTest` |
| CSRF | ✅ all web POST/PUT/PATCH/DELETE; live `POST /login` without token → 419 | manual curl |
| Mass assignment | ✅ explicit `$fillable` on every model; profile accepts `name` only; role/status only via AccountService | `ProfileTest` |
| XSS | ✅ no `{!! !!}` in app views; Blade escaping | `SecurityAuditTest::test_no_html_is_rendered_unescaped…` |
| Uploads | ✅ image+mimes+mimetypes+size+dimensions, random name, sniffed extension, `.htaccess` no-exec | `AdminModuleTest::test_upload_validation` |
| Rate limits | ✅ web login 5/min, API login 5 failures/min + 30/min/IP, API 60/min/user, uploads 30/min | `RoleAccessTest`, `ApiTest` |
| Passwords | ✅ bcrypt; `Password::defaults()` = min 8 (+letters+numbers in production) on all forms | — |
| Session cookie | ✅ http-only, SameSite=Lax, `SESSION_SECURE_COOKIE=true` in production `.env` (DEPLOYMENT §5) | config/session.php |
| Security headers | ✅ X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy, HSTS on HTTPS | `SecurityHeaders`, `SecurityAuditTest` |
| Secrets | ✅ `.env` ignored; only a throwaway test RSA key in `tests/Fixtures` (D-60); Firebase JSON goes to `storage/app` | `git ls-files` check |
| Activity log | ✅ login, logout, failed login, session replaced, password change/reset, accounts, roles/permissions, categories, subscriptions, payments, entries, settings, reminders, failed jobs | `activity_logs` |
| Error output | ✅ API: envelope without stack traces when `APP_DEBUG=false`; web: branded error pages | `ApiExceptionRenderer` |
| Bug found & fixed in review | rejecting a cookie-authenticated (TransientToken) API request crashed with 500 → now only real tokens are deleted | `EndToEndFlowTest` |
