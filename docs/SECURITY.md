# AstroGann — Security design

Status: **design (Phase 0)** — reviewed and ticked in Phase 9.

## 1. Single-session design (D-23)

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

- `users.role` ∈ superadmin/admin/user; no permission package.
- Middleware `role:<roles>` on route groups: `/superadmin/*` → superadmin, `/admin/*` → admin, `/user/*` → user,
  `/api/admin/*` → superadmin, `POST /api/entries` → admin.
- Gates/Policies for model actions: `EntryPolicy` (create: admin; view: admin owns it / superadmin any; update and
  delete: always false), `CategoryPolicy`, `UserPolicy`, `SubscriptionPolicy`.
- Inactive users (`status = inactive`) cannot log in; middleware `EnsureUserIsActive` also logs out an already
  logged-in user who gets deactivated.
- Tests assert 403 for every cross-role access, not just hidden buttons.

## 3. Rate limits

- Web login: 5 attempts/min per email+IP (Breeze `LoginRequest` throttle).
- API login: named limiter `api-login`, 5/min per email+IP.
- Authenticated API: `api` limiter 60/min per user id (fallback IP).
- Upload endpoint shares the authenticated limit.

## 4. Uploads

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
