# AstroGann — Progress

Update at the end of every work session. Newest session on top.

## Current state

- **Current phase:** Phase 1 — Project setup (next to start)
- **Last finished phase:** Phase 0 — Read and plan (2026-10-06)

## Next steps
1. Phase 1: `composer create-project laravel/laravel:^12.0 backend`, configure `.env`, install Breeze (Blade) +
   Sanctum (`php artisan install:api`), boot + DB check, commit.

## Known issues / notes
- PHP 8.2.12 → Laravel 12 (Laravel 13 needs PHP 8.3). PHP 8.2 security support ends 2026-12-31 (D-01).
- Composer 2.4.4 is old; `composer self-update` recommended (D-02).
- DB is MariaDB 10.4 (D-03).
- Image has 7 panels, not 8 (D-06).

## Session log

### 2026-10-06 — Phase 0
- Environment: PHP 8.2.12 (C:\xampp\php), Composer 2.4.4, MariaDB 10.4.32 (DB `astrogann` exists, root/no
  password), Node 22.14 / npm 9.6, Flutter 3.35.5 / Dart 3.9.2, git 2.43.
- Read the architecture image (7 panels) and the brief; wrote all docs in section 5; recorded D-01…D-34.
