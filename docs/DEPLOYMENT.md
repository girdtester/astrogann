# AstroGann — Deployment (GoDaddy shared hosting / cPanel)

Status: **draft (Phase 0)** — completed in Phase 8/9 once the scheduler and assets exist.

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

## 6. Cron (the only background process)
cPanel → *Cron Jobs* → every minute (`* * * * *`):
```
/usr/local/bin/php /home/<cpaneluser>/astrogann/artisan schedule:run >> /dev/null 2>&1
```
(Use the PHP binary path cPanel shows, e.g. `/opt/cpanel/ea-php82/root/usr/bin/php`.)
The scheduler then runs `queue:work --stop-when-empty` every minute and the daily subscription jobs.

## 7. Folder permissions
- `storage/` and `bootstrap/cache/` → 755 (775 if the host requires), writable by the PHP user.
- `public/uploads/entries/` → 755, writable. Its `.htaccess` blocks script execution — keep it.
- `.env` → 640/600.

## 8. After each deploy
`php artisan config:cache && php artisan route:cache && php artisan view:cache` (or via one-off cron).
