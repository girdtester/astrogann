# AstroGann — Local test accounts

**LOCAL DEVELOPMENT ONLY. Never seed these on production.** The seeder refuses to create them when
`APP_ENV=production`.

All passwords: `password`

The app is branded ASTRO GANN (D-61); these `.test` accounts are demo data only.

| Role | Email | State |
|---|---|---|
| Super Admin | superadmin@astrogann.test | active |
| Admin | admin@astrogann.test | active |
| User / Trader | user.active@astrogann.test | active subscription (ends ~30 days from seed date) |
| User / Trader | user.expired@astrogann.test | expired subscription (ended ~10 days before seed date) |

Created by `php artisan migrate:fresh --seed` (Phase 2). Seed data also includes categories (Nifty 50, NSE, Bank
Nifty, Sensex, Index…) and ≥10 entries per category with placeholder images so the 7/3 limit can be tested.
