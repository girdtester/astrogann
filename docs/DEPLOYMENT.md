# ASTRO GANN — Deployment (GoDaddy shared hosting / cPanel)

Status: **final for the web + API (Phase 9, 2026-10-06)**. Works with cPanel only: no SSH, Node, Redis, Supervisor,
S3 or SES needed. The mobile app only needs the API URL (docs/MOBILE.md, later).

## 0. What you need
- cPanel login, a domain or sub-domain (e.g. `app.your-domain.com`), PHP **8.2+** (8.3/8.4 recommended — D-01).
- One MySQL database + user (cPanel → *MySQL Databases*).
- An e-mail account for sending (see §5).

## 0b. PHP-only hosting — no Node.js on the server (D-93)
The server only needs **PHP + MySQL**. Node.js/npm is a *build tool on your PC*: it turns the design files
(Tailwind CSS, date/time picker, live-notification script) into plain files in `backend/public/build/` — those
files are committed and uploaded like images. Verified 2026-10-06: with `node_modules` removed, login, styles,
scripts and the date/time pickers all load (HTTP 200).
- Upload: `backend/` with `vendor/` and `public/build/`. **Do not upload `node_modules/`** (not in git anyway).
- Never run `npm`/`node`/`vite` on the server. Make sure there is **no `public/hot` file** on the server.
- Only if you change CSS/JS or Tailwind classes in Blade views: on your PC run `npm run build`, then upload the
  new `public/build/` folder.
- The `mobile/` folder is the phone app source — it is not uploaded to the server either.

## 1. Build the upload package on your PC
From `E:\astrogann\backend`:
```
composer install --no-dev --optimize-autoloader
npm run build            (only if you changed CSS/views; public/build is already committed)
```
Zip the whole `backend` folder **including `vendor/` and `public/build/`**, **excluding** `node_modules/`, `.env`,
`storage/logs/*`, `public/uploads/entries/*` (except `.gitkeep`) and `tests/`.
Afterwards run `composer install` again locally to get the dev tools back.

## 2. PHP version and extensions
cPanel → *Select PHP Version* / *MultiPHP Manager* → PHP 8.2 or newer for the domain. Extensions:
`pdo_mysql, mbstring, openssl, tokenizer, xml, ctype, json, bcmath, fileinfo, curl, gd, intl, zip`.

## 3. Upload and point the domain
Upload the zip with cPanel *File Manager* into your home folder, e.g. `/home/<cpaneluser>/astrogann`, and extract.

**3a. Sub-domain / addon domain (recommended).** cPanel → *Domains* → set the document root of the domain to
`/home/<cpaneluser>/astrogann/public`. Done — nothing else to change.

**3b. Main domain that must use `public_html`.**
1. Move everything from `astrogann/public/` into `public_html/` (incl. `.htaccess`, `build/`, `images/`, `uploads/`).
2. Edit `public_html/index.php`: replace the two `__DIR__.'/../` paths with `__DIR__.'/../astrogann/` so it reads
   `../astrogann/vendor/autoload.php`, `../astrogann/bootstrap/app.php` and `../astrogann/storage/framework/maintenance.php`.
3. Create the file `/home/<cpaneluser>/astrogann/.public_path` containing one line: `/home/<cpaneluser>/public_html`
   (this makes uploads, asset URLs and cron jobs use `public_html` — see `bootstrap/app.php`).

## 4. Database
cPanel → *MySQL Databases*: create database + user, add the user to the database with **ALL PRIVILEGES**.
Run the migrations and the production-safe seeders once (cPanel *Terminal* if you have it, otherwise a one-off cron
line from §6 that you delete after it ran):
```
php artisan migrate --force
php artisan db:seed --class=AccessSeeder --force
php artisan db:seed --class=SettingsSeeder --force
```
**Never run `php artisan db:seed` without `--class` and never `migrate:fresh` in production** — the demo seeder
refuses to run when `APP_ENV=production`, but `migrate:fresh` deletes all data.
Then create the first Super Admin (one-off): `php artisan tinker` is usually not available, so use:
```
php artisan astrogann:create-superadmin "Your Name" ganndecoder@gmail.com
```
(it asks nothing; it prints a random password — change it after the first login on the profile page).

## 5. `.env` (copy `.env.example` → `.env` in `astrogann/`, then edit)
```
APP_NAME="ASTRO GANN"
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...                      # php artisan key:generate --show (run on your PC) and paste
APP_URL=https://app.your-domain.com
APP_TIMEZONE=Asia/Kolkata

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=<cpaneluser>_astrogann
DB_USERNAME=<cpaneluser>_astro
DB_PASSWORD=...

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
CACHE_STORE=database
LOG_LEVEL=warning

MAIL_MAILER=smtp
MAIL_HOST=mail.your-domain.com          # cPanel → Email Accounts → Connect Devices shows the exact values
MAIL_PORT=465
MAIL_SCHEME=smtps
MAIL_USERNAME=no-reply@your-domain.com
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=no-reply@your-domain.com
MAIL_FROM_NAME="${APP_NAME}"

SINGLE_SESSION_STAFF=true
ENTRY_IMAGE_MAX_KB=5120
PUSH_DRIVER=log                         # switch to fcm when Firebase is set up (below)
FCM_PROJECT_ID=
FCM_CREDENTIALS=/home/<cpaneluser>/astrogann/storage/app/firebase-service-account.json
```
**E-mail sender (D-63).** Recommended: a mailbox on your own domain as above; replies automatically go to
ganndecoder@gmail.com (Reply-To = "Contact e-mail" setting). Alternative: Gmail SMTP (`smtp.gmail.com`, port 587,
`MAIL_SCHEME=null`, username ganndecoder@gmail.com, a 16-character Google *App Password*). Many GoDaddy shared
plans block outgoing SMTP to outside servers — if mails do not arrive, use the domain mailbox.

**Push (FCM)** — easiest (D-89): log in as Super Admin → Settings → *Push notifications (Firebase)* → upload the key
from Firebase console → Project settings → Service accounts → *Generate new private key*; press *Check connection*.
Nothing to edit in `.env`, no SSH. (Alternative: upload the JSON to `astrogann/storage/app/` and set
`PUSH_DRIVER=fcm`, `FCM_PROJECT_ID`, `FCM_CREDENTIALS`.) No package or SDK is needed (HTTP v1).

## 6. Cron (the only background process)
cPanel → *Cron Jobs* → **Once per minute** (`* * * * *`):
```
/usr/local/bin/php /home/<cpaneluser>/astrogann/artisan schedule:run >> /dev/null 2>&1
```
Use the PHP path for your version if cPanel shows one, e.g. `/opt/cpanel/ea-php83/root/usr/bin/php`.
The scheduler (`backend/routes/console.php`) then runs:

| When | Command | Purpose |
|---|---|---|
| every minute | `queue:work --stop-when-empty --max-time=50 --tries=3` | sends queued notifications, pushes, e-mails, then exits |
| 00:05 daily | `astrogann:expire-subscriptions` | marks past-due subscriptions expired |
| ~~09:00 daily~~ | ~~`astrogann:send-expiry-reminders`~~ | superseded 2026-10-06 by the two rows below (D-88) |
| 18:00 daily | `astrogann:collect-expiry-reminders` | saves all users expiring within N days (once per expiry date) |
| every minute | `astrogann:send-expiry-reminders` | e-mails the saved list one by one (`MAIL_PER_MINUTE`) + in-app + push |
| daily | `queue:prune-failed --hours=720`, `sanctum:prune-expired` | housekeeping |

Each run is short (jobs work in chunks of `NOTIFICATION_CHUNK`, default 200 users). Failed jobs are kept in the
`failed_jobs` table and logged as `job.failed` (Super Admin → Reports / Logs); retry with
`php artisan queue:retry all`. **One-off commands without SSH:** add a temporary cron line such as
`/usr/local/bin/php /home/<cpaneluser>/astrogann/artisan migrate --force`, let it run once, delete it.

### 6b. Cron by link (alternative, D-86)
If you prefer a link (or cPanel cron is not available), set a secret in `.env`:
```
CRON_TOKEN=<48 random letters/digits>     # e.g. php -r "echo bin2hex(random_bytes(24));"
MAIL_PER_MINUTE=20
```
then call **every minute**:
```
https://app.your-domain.com/cron/run/<CRON_TOKEN>
```
- cPanel → Cron Jobs → every minute: `wget -q -O /dev/null "https://app.your-domain.com/cron/run/<CRON_TOKEN>"`
- or a free service such as cron-job.org: create a job with that URL, schedule "every minute".
It answers `OK <time>` and runs the same schedule as §6. A wrong key returns 404. Keep the link secret.
Check results in Super Admin → Subscriptions ▾ → Expiry reminders and Reports / Logs.

## 7. Folder permissions
- `storage/` and `bootstrap/cache/` (recursive) → 755 directories / 644 files, writable by your user (775 if the host requires).
- `public/uploads/entries/` (or `public_html/uploads/entries/`) → 755, writable. Keep `uploads/.htaccess` — it blocks
  script execution and directory listing.
- `.env` and the Firebase JSON → 600.

## 7b. Images (D-91)
After the first deploy (and after copying old uploads) run once: `php artisan astrogann:make-thumbnails`.
Make sure `APP_URL` is the real https address — every image, link and e-mail URL is built from it.

## 8. After every deploy
```
php artisan migrate --force
php artisan db:seed --class=AccessSeeder --force     # adds any new permission keys
php artisan config:cache && php artisan route:cache && php artisan view:cache
```
(If you change `.env`, run `php artisan config:cache` again.)

## 9. Smoke test
1. `https://app.your-domain.com/up` → "Application up".
2. Log in as the Super Admin → Settings: check app name, limits, contact numbers and e-mail.
3. Create an Admin, a category, a test user with a subscription; log in as the Admin and add an entry.
4. Within a minute the test user's Notifications shows "New entry added in …" (proves cron + queue work).
5. `https://app.your-domain.com/api/settings` returns JSON — this is the URL the mobile app will use.

---

## Appendix — earlier draft (superseded 2026-10-06 by the guide above)

### (old) AstroGann — Deployment draft

Status: **draft (Phase 0)**; §6 cron/queue completed in Phase 8 (2026-10-06); rest finalised in Phase 9.

Constraints: no S3, SES, Redis, Supervisor, possibly no SSH. Everything below works with cPanel only.

#### 1. PHP version
cPanel → *Select PHP Version* (or *MultiPHP Manager*) → choose **PHP 8.2 or newer** (8.3/8.4 recommended, see
DECISIONS D-01). Enable extensions: `pdo_mysql, mbstring, openssl, tokenizer, xml, ctype, json, bcmath, fileinfo,
curl, gd, intl, zip`.

#### 2. Upload the code
- Upload the contents of `backend/` to a folder **outside** `public_html`, e.g. `/home/<cpaneluser>/astrogann`.
- If Composer is not available on the server: run `composer install --no-dev --optimize-autoloader` locally and
  upload the resulting `vendor/` folder too.
- Front-end assets are pre-built and committed (`public/build`, D-30); no Node needed on the server.

#### 3. Point the domain at `backend/public`
- Addon/sub-domain: cPanel → *Domains* → set *Document Root* to `/home/<cpaneluser>/astrogann/public`.
- Primary domain locked to `public_html`: copy the contents of `astrogann/public/` into `public_html/` and edit
  `public_html/index.php` so the two `require` paths point to `../astrogann/vendor/autoload.php` and
  `../astrogann/bootstrap/app.php`. Then set `PUBLIC_PATH` (see `.env`) so uploads land in `public_html/uploads`.
  (Exact steps finalised in Phase 9.)

#### 4. Database
cPanel → *MySQL Databases*: create DB + user, grant ALL. Run migrations: with SSH `php artisan migrate --force`;
without SSH, temporarily use the one-off cron trick (`php artisan migrate --force` as a cron run once) — details in
Phase 9. **Never run the seeder in production.**

#### 5. `.env` (production)
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain
APP_TIMEZONE=Asia/Kolkata
DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
CACHE_STORE=database
MAIL_MAILER=smtp
MAIL_HOST=smtpout.secureserver.net   # or mail.<your-domain> for cPanel mailboxes
MAIL_PORT=465
MAIL_SCHEME=smtps
MAIL_USERNAME=no-reply@your-domain
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=no-reply@your-domain
MAIL_FROM_NAME="AstroGann"
PUSH_DRIVER=fcm
FCM_PROJECT_ID=...
FCM_CREDENTIALS=/home/<cpaneluser>/astrogann/storage/app/firebase-service-account.json
```
Generate `APP_KEY` locally (`php artisan key:generate --show`) and paste it.

**E-mail sender (D-63).** Two working options on GoDaddy:
1. *Recommended:* create a mailbox on your own domain in cPanel (e.g. `no-reply@your-domain`), use its SMTP
   details above and set `MAIL_FROM_ADDRESS=no-reply@your-domain`. Replies still go to ganndecoder@gmail.com
   (Reply-To is set automatically from the "Contact e-mail" setting).
2. Send through Gmail: `MAIL_HOST=smtp.gmail.com`, `MAIL_PORT=587`, `MAIL_SCHEME=null` (STARTTLS),
   `MAIL_USERNAME=ganndecoder@gmail.com`, `MAIL_PASSWORD=<16-char Google App Password>` (needs 2-step
   verification on the Google account). Many GoDaddy shared plans block outgoing SMTP to external servers —
   if mails do not arrive, use option 1.

#### 6. Cron (the only background process)
cPanel → *Cron Jobs* → every minute (`* * * * *`):
```
/usr/local/bin/php /home/<cpaneluser>/astrogann/artisan schedule:run >> /dev/null 2>&1
```
(Use the PHP binary path cPanel shows, e.g. `/opt/cpanel/ea-php82/root/usr/bin/php`.)
The scheduler then runs (see `backend/routes/console.php`):

| When | Command | Purpose |
|---|---|---|
| every minute | `queue:work --stop-when-empty --max-time=50 --tries=3` | sends queued notifications, pushes, e-mails, then exits |
| 00:05 daily | `astrogann:expire-subscriptions` | marks past-due subscriptions expired |
| 09:00 daily | `astrogann:send-expiry-reminders` | "expires in N days" e-mail + in-app notice + push (once per period) |
| daily | `queue:prune-failed --hours=720`, `sanctum:prune-expired` | housekeeping |

Each run is short (jobs work in chunks of `NOTIFICATION_CHUNK`, default 200 users), so it fits shared-hosting
CPU/time limits. Failed jobs land in the `failed_jobs` table and an activity-log row `job.failed`; retry them with
`php artisan queue:retry all` (via SSH or a one-off cron line).

Check it works: cPanel → Cron Jobs → set an e-mail for cron output temporarily, or look at
`storage/logs/laravel.log`; the Super Admin "Reports / Logs" screen shows `subscription.*` and `job.failed` rows.

#### 7. Folder permissions
- `storage/` and `bootstrap/cache/` → 755 (775 if the host requires), writable by the PHP user.
- `public/uploads/entries/` → 755, writable. Its `.htaccess` blocks script execution — keep it.
- `.env` → 640/600.

#### 8. After each deploy
`php artisan config:cache && php artisan route:cache && php artisan view:cache` (or via one-off cron).
