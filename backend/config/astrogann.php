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

    // Push notifications. DECISIONS D-28.
    'push' => [
        'driver' => env('PUSH_DRIVER', 'log'), // log | fcm
        'fcm_project_id' => env('FCM_PROJECT_ID'),
        'fcm_credentials' => env('FCM_CREDENTIALS'), // absolute path to the service-account JSON
    ],

    // Users per chunk when fanning out notifications (keeps shared-hosting runs short).
    'notification_chunk' => (int) env('NOTIFICATION_CHUNK', 200),

    // Defaults for the settings table. Keys marked public are served by GET /api/settings.
    'settings_defaults' => [
        'app_name' => 'AstroGann',
        'entries_limit_active' => '7',
        'entries_limit_expired' => '3',
        'reminder_days' => '3',
        'show_more_text' => 'Your subscription is not active. Contact the admin to renew and see all the latest entries.',
        'contact_phone' => '+91 90000 00000',
        'contact_whatsapp' => '+919000000000',
        'contact_email' => 'support@astrogann.test',
        'notice_text' => '',
    ],

    'public_settings' => [
        'app_name', 'entries_limit_active', 'entries_limit_expired', 'reminder_days',
        'show_more_text', 'contact_phone', 'contact_whatsapp', 'contact_email', 'notice_text',
    ],
];
