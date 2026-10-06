# ASTRO GANN — Performance & scale

Status: **measured 2026-10-06** (DECISIONS D-75). Re-run after big changes.

## How it was measured
- Separate database `astrogann_loadtest` (your real/local data untouched), filled with
  `DB_DATABASE=astrogann_loadtest php artisan migrate:fresh --seed` +
  `DB_DATABASE=astrogann_loadtest php artisan astrogann:generate-load --entries=200000 --users=50000` (local-only command).
- Data: **200,060 entries · 50,004 users · 50,002 subscriptions · 250,000 notifications · 50,000 log rows**.
- Server: `php artisan serve` on Windows/XAMPP (single PHP process, no OPcache tuning) — production Apache/PHP-FPM is
  usually faster. Times are the 2nd (warm) request, whole page incl. HTML rendering.

## Results

| Screen / endpoint | Before tuning | After |
|---|---|---|
| Super Admin dashboard | 0.31 s | 0.31 s (counts cached 60 s) |
| All Entries (default sort) | 0.46 s | **0.40 s** |
| All Entries sorted by Category | 10.6 s | **0.88 s** |
| All Entries sorted by Added by | 14.7 s | **0.88 s** |
| All Entries page 5,000 | 5.4 s | **0.43 s** |
| All Entries filtered (dates + category) | 0.31 s | 0.31 s |
| Users list / sort by name / search | 0.56–0.59 s | **0.43 s** |
| Users sorted by subscription expiry | 1.04 s | **0.66 s** |
| Users page 2,000 | — | 0.52 s |
| Subscriptions list | 1.25 s | **0.74 s** |
| Reports / Logs (+ date filter) | 0.33 s | 0.33 s |
| Admin: My Entries | 0.39 s | 0.32 s |
| User: tabs / notifications / subscription | 0.26–0.30 s | 0.26–0.30 s |
| API: settings / categories / entries / notifications / subscription | 0.25–0.28 s | 0.25–0.28 s |
| New-entry fan-out to 47,580 active traders | — | first 200 users in **0.13 s** right after saving; all 238 chunks in **17 s** |

## What makes it fast
- Indexes for every filter and sort (migration `2026_10_06_140000_scale_indexes_and_notification_entry`):
  entries `(category_id, entry_date, entry_time)`, `(entry_date, entry_time, id)`, `(admin_id, entry_date, entry_time)`,
  `created_at`; users `name`, `created_at`, `(role_id, status)`; subscriptions `(user_id, status, end_date)`, `end_date`;
  notifications `(user_id, id)`, unique `(user_id, entry_id)`; logs `(user_id, created_at)`.
- No `DATE(column)` in WHERE clauses (it disables indexes) — plain range comparisons instead.
- **Deferred pagination** (`App\Support\Sorting::paginateDeferred`): sort + offset run on ids only, then one page of
  full rows is loaded. Used for entries, users/traders and subscriptions.
- Role filter as `role_id IN (…)` instead of `EXISTS`.
- User screens: the 7/3 limit is a `LIMIT` on the `(category_id, entry_date, entry_time)` index — constant time no
  matter how many entries exist.
- Notification fan-out in chunks of 200 (`NOTIFICATION_CHUNK`), idempotent (unique user+entry), first chunk right
  after the admin's request, the rest via the cron-driven queue.
- Daily `astrogann:prune` keeps logs (365 days), read notifications (180 days) and ended sessions (90 days) in check
  (configurable: `RETAIN_*` in `.env`).
- Dashboard counts cached for 60 s.

## Clean up the test database
`"C:\xampp\mysql\bin\mysql.exe" -u root -e "DROP DATABASE astrogann_loadtest;"` (only the load-test DB).
