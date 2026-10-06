# AstroGann — Deployment (GoDaddy shared hosting / cPanel)

Status: **draft (Phase 0)**; §6 cron/queue completed in Phase 8 (2026-10-06); rest finalised in Phase 9.

Constraints: no S3, SES, Redis, Supervisor, possibly no SSH. Everything below works with cPanel only.

## 1. PHP version
cPanel → *Select PHP Version* (or *MultiPHP Manager*) → choose **PHP 8.2 or newer** (8.3/8.4 recommended, see
DECISIONS D-01). Enable extensions: `pdo_mysql, mbstring, openssl, tokenizer, xml, ctype, json, bcmath, fileinfo,
curl, gd, intl, zip`.

## 2. Upload the code
- Upload the contents of `backend/` to a folder **outside** `public_html`, e.g. `/home/<cpaneluser>/astrogann`.
- If Composer is not available on the server: run `composer install --no-dev --optimize-autoloader` locally and
  upload the resulting `vendor/` folder too.
- Front-end assets are pre-built and committed (`public/build`, D-30); no Node needed on the server.

## 3. Point the domain at `backend/public`
- Addon/sub-domain: cPanel → *Domains* → set *Document Root* to `/home/<cpaneluser>/astrogann/public`.
- Primary domain locked to `public_html`: copy the contents of `astrogann/public/` into `public_html/` and edit
  `public_html/index.php` so the two `require` paths point to `../astrogann/vendor/autoload.php` and
  `../astrogann/bootstrap/app.php`. Then set `PUBLIC_PATH` (see `.env`) so uploads land in `public_html/uploads`.
  (Exact steps finalised in Phase 9.)

## 4. Database
cPanel → *MySQL Databases*: create DB + user, grant ALL. Run migrations: with SSH `php artisan migrate --force`;
without SSH, temporarily use the one-off cron trick (`php artisan migrate --force` as a cron run once) — details in
Phase 9. **Never run the seeder in production.**

## 5. `.env` (production)
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

## 6. Cron (the only background process)
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

## 7. Folder permissions
- `storage/` and `bootstrap/cache/` → 755 (775 if the host requires), writable by the PHP user.
- `public/uploads/entries/` → 755, writable. Its `.htaccess` blocks script execution — keep it.
- `.env` → 640/600.

## 8. After each deploy
`php artisan config:cache && php artisan route:cache && php artisan view:cache` (or via one-off cron).
