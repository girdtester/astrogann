# AstroGann — Database

Engine: MariaDB 10.4 locally (XAMPP), MySQL/MariaDB on GoDaddy. Connection `mysql`, database `astrogann`,
charset `utf8mb4`. Status: **implemented (Phase 2, 2026-10-06)** — migrations `0001_01_01_000000_create_users_table.php` (role/status/phone
added) and `2026_10_06_120000_create_astrogann_tables.php`. `php artisan migrate:fresh --seed` runs cleanly.

## Enums (PHP backed enums in `app/Enums`)

| Enum | Values |
|---|---|
| ~~`Role`~~ → `RoleKind` (D-42) | `superadmin`, `staff`, `user` (panel of a role; roles themselves are rows) |
| `ActiveStatus` (users + categories) | `active`, `inactive` |
| `SubscriptionStatus` | `active`, `expired` |
| `DeviceType` | `web`, `mobile` |
| `NotificationType` | `new_entry`, `expiry` |
| `PaymentStatus` | `paid`, `pending`, `failed`, `refunded` |

Stored as `string` columns (not native ENUM) so values can be added without ALTER on shared hosting.

## Tables

### users
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | varchar(255) | |
| email | varchar(255) | unique |
| email_verified_at | timestamp null | Laravel default, unused |
| password | varchar(255) | bcrypt |
| ~~role~~ | ~~varchar(20)~~ | **superseded 2026-10-06 (D-42)** by `role_id` |
| role_id | FK → roles.id | restrict on delete |
| status | varchar(20) | index; `active/inactive` |
| phone | varchar(30) null | optional contact for traders |
| remember_token | varchar(100) null | |
| created_at, updated_at | timestamp | |

### roles (D-42)
| id PK | name varchar(100) | slug varchar(100) unique | kind varchar(20) idx (`superadmin/staff/user`) | description null | is_system bool | timestamps |

### permissions (D-42)
| id PK | key varchar(100) unique | group varchar(50) | label varchar(150) | description null | sort int | timestamps |

Rows mirror `App\Support\Permissions::catalog()`; synced by `AccessSeeder`.

### permission_role, permission_user (D-42)
Composite PKs (`role_id, permission_id`) / (`user_id, permission_id`), cascade on delete, index on `permission_id`.
`permission_user` = extra permissions granted to one staff account on top of its role.

### categories
| id PK | name varchar(100) | code varchar(50) unique | status varchar(20) idx | sort_order int default 0 idx | timestamps |

### entries
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| category_id | FK → categories.id | restrict on delete |
| admin_id | FK → users.id | restrict on delete |
| image_path | varchar(255) | relative, e.g. `uploads/entries/abc.jpg` |
| entry_date | date | |
| entry_time | time | |
| created_at | timestamp | no `updated_at` (immutable) |

Indexes: `admin_id`, composite `(category_id, entry_date, entry_time)`.

### subscriptions
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| user_id | FK → users.id | cascade on delete |
| start_date, end_date | date | end_date inclusive |
| status | varchar(20) | `active/expired` |
| created_by | FK → users.id null | the Super Admin; null on delete |
| reminder_sent_at | timestamp null | D-16 |
| notes | varchar(255) null | D-16 |
| created_at, updated_at | timestamp | |

Indexes: `created_by`, composite `(user_id, status, end_date)`, `(status, end_date)` for the daily jobs.

### subscription_payments
| id PK | user_id FK users (cascade) idx | subscription_id FK null (null on delete) | amount decimal(10,2) | payment_date date | transaction_ref varchar(100) null | status varchar(20) | recorded_by FK users null | created_at |

### user_sessions
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| user_id | FK users (cascade) | |
| device_type | varchar(10) | `web/mobile` |
| fcm_token | varchar(512) null | |
| is_active | boolean | |
| token_id | bigint null | Sanctum `personal_access_tokens.id` for API logins |
| ip_address | varchar(45) null | |
| user_agent | varchar(512) null | |
| last_seen_at | timestamp null | |
| created_at, updated_at | timestamp | |

Indexes: `(user_id, is_active)`, `token_id`.

### user_notifications (spec "notifications" — D-21)
| id PK | user_id FK (cascade) | category_id FK null (null on delete) | type varchar(20) | message varchar(500) | is_read bool default false | created_at |

Indexes: `(user_id, is_read)`, `category_id`.

### activity_logs (D-24)
| id PK | user_id FK null (null on delete) idx | action varchar(100) idx | subject_type varchar null | subject_id bigint null | description varchar(500) null | ip_address varchar(45) null | user_agent varchar(512) null | created_at idx |

### settings (D-25)
| id PK | key varchar(100) unique | value text null | timestamps |

### Laravel tables
`sessions` (database session driver), `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`,
`password_reset_tokens`, `personal_access_tokens` (Sanctum).

## Relations (Eloquent)

- User hasMany Entry (`admin_id`), hasMany Subscription, hasOne latestSubscription, hasMany SubscriptionPayment,
  hasMany UserSession, hasMany UserNotification, hasMany ActivityLog.
- Category hasMany Entry. Entry belongsTo Category, belongsTo User as `admin`.
- Subscription belongsTo User, belongsTo User as `creator` (`created_by`), hasMany SubscriptionPayment.
- UserNotification belongsTo User, belongsTo Category.

## Seed data (local only — `DemoSeeder`, refuses in production)

- `SettingsSeeder` (safe in production, never overwrites): the 9 keys from `config/astrogann.php` `settings_defaults`.
- `DemoSeeder`: 4 accounts (docs/TEST_ACCOUNTS.md), active subscription (+30 days, with a payment) and expired
  subscription (ended 10 days ago); categories Nifty 50, NSE, Bank Nifty, Sensex, Index (active, sort 1–5) and
  Commodities (inactive); 12 entries per active category (one per day, newest today) with GD-generated PNGs in
  `public/uploads/entries/seed-*.png` (git-ignored).

## Filesystem

Disk `uploads` (config/filesystems.php): root `public_path('uploads')`, URL `APP_URL/uploads`. `entries.image_path` is
relative to `public/` (e.g. `uploads/entries/x.png`) so `asset($path)` yields the URL.
