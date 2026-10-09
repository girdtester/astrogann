<?php

/*
 * AstroGann application configuration. Values shown to users live in the `settings`
 * table (see SettingsService); the `settings_defaults` below are only fall-backs and seed values.
 */
return [

    // Single active session for admin / superadmin too (always on for role "user"). DECISIONS D-23.
    'single_session_staff' => (bool) env('SINGLE_SESSION_STAFF', true),

    // Entry image upload rules. DECISIONS D-27.
    'entry_image_max_kb' => (int) env('ENTRY_IMAGE_MAX_KB', 5120),
    'entry_image_mimes' => ['jpg', 'jpeg', 'png', 'webp'],
    'entry_upload_dir' => 'entries',

    // Image processing for uploads (D-91).
    'images' => [
        'max_px' => (int) env('IMAGE_MAX_PX', 2560),     // longest side of the stored image (D-101: sharper zoom)
        'thumb_px' => (int) env('IMAGE_THUMB_PX', 640),  // width of the list / app-card preview
        'quality' => (int) env('IMAGE_QUALITY', 90),     // JPEG / WebP quality (only when resized)
    ],

    // Push notifications. DECISIONS D-28.
    'push' => [
        'driver' => env('PUSH_DRIVER', 'log'), // log | fcm
        'fcm_project_id' => env('FCM_PROJECT_ID'),
        'fcm_credentials' => env('FCM_CREDENTIALS'), // absolute path to the service-account JSON (fallback)
        // Where the key uploaded in Super Admin → Settings is stored (D-89). Empty = storage/app/private/firebase/.
        'key_path' => env('FCM_KEY_PATH'),
    ],

    // Daily clean-up (astrogann:prune) so big tables stay fast (DECISIONS D-75).
    'retention' => [
        'activity_logs_days' => (int) env('RETAIN_LOGS_DAYS', 365),
        'read_notifications_days' => (int) env('RETAIN_READ_NOTIFICATIONS_DAYS', 180),
        'ended_sessions_days' => (int) env('RETAIN_ENDED_SESSIONS_DAYS', 90),
    ],

    // E-mails sent per minute by the queue (D-87). Gmail ≈ 500/day, GoDaddy cPanel ≈ 250/hour — keep it modest.
    'mail_per_minute' => (int) env('MAIL_PER_MINUTE', 20),

    // Secret for the URL cron https://your-domain/cron/run/{CRON_TOKEN} (D-86). At least 32 characters; empty = disabled.
    'cron_token' => env('CRON_TOKEN'),

    // When the expiry-reminder list is collected: "18:00" (daily, default) or "every_minute" for testing (D-99).
    'reminder_collect_at' => env('REMINDER_COLLECT_AT', '18:00'),

    // Users per chunk when fanning out notifications (keeps shared-hosting runs short).
    'notification_chunk' => (int) env('NOTIFICATION_CHUNK', 200),

    // Defaults for the settings table. Keys marked public are served by GET /api/settings.
    'settings_defaults' => [
        'app_name' => 'ASTRO GANN',
        'entries_limit_active' => '7',
        'entries_limit_expired' => '3',
        'reminder_days' => '3',
        'show_more_text' => 'Your subscription is not active. Contact the admin to renew and see all the latest entries.',
        'contact_phone' => '+91 75583 77920, +91 81493 43315',
        'contact_whatsapp' => '+917558377920, +918149343315',
        'contact_email' => 'ganndecoder@gmail.com',
        'notice_text' => '',
        'allow_registration' => '1',   // D-85: users can sign up themselves (web + app)
        'live_check_seconds' => '20',  // D-95/D-96: base interval of the adaptive live check (backs off to 120 s when idle)
        'notify_expired_users' => '1', // D-73: expired / unsubscribed users get a "renew" in-app notification
        'push_enabled' => '0',         // D-89: switched on automatically when the Firebase key is uploaded
        'push_expired_users' => '1',   // D-89: expired / unsubscribed users also get a push ("renew" text)
    ],

    'public_settings' => [
        'app_name', 'entries_limit_active', 'entries_limit_expired', 'reminder_days',
        'show_more_text', 'contact_phone', 'contact_whatsapp', 'contact_email', 'notice_text', 'allow_registration', 'live_check_seconds',
    ],
];
