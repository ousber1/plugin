<?php

return [
    /*
    | Supported interface locales. Arabic is the default and the fallback for
    | any missing content translation.
    */
    'locales' => [
        'ar' => ['name' => 'العربية', 'dir' => 'rtl', 'short' => 'AR'],
        'fr' => ['name' => 'Français', 'dir' => 'ltr', 'short' => 'FR'],
        'en' => ['name' => 'English', 'dir' => 'ltr', 'short' => 'EN'],
    ],
    'default_locale' => 'ar',

    /*
    | Private media (lesson videos, protected attachments, library files,
    | store downloads). "local" stores files under storage/app/private and
    | streams them with HTTP byte ranges. Set MEDIA_DISK=s3_private to use
    | private object storage with short-lived signed URLs.
    */
    'media_disk' => env('MEDIA_DISK', 'local'),
    'signed_url_minutes' => (int) env('MEDIA_SIGNED_URL_MINUTES', 10),

    'video' => [
        // Maximum accepted MP4 size in megabytes (enforced server-side).
        'max_mb' => (int) env('VIDEO_MAX_MB', 2048),
        // Size of each upload chunk sent by the browser. Keep this below
        // PHP upload_max_filesize / post_max_size on your host.
        'chunk_mb' => (int) env('VIDEO_CHUNK_MB', 5),
        'mimes' => ['video/mp4', 'application/mp4'],
        // Abandoned partial uploads older than this are removed by
        // `php artisan academy:prune-uploads` (scheduled daily).
        'stale_upload_hours' => 24,
    ],

    'attachments' => [
        'max_mb' => (int) env('ATTACHMENT_MAX_MB', 50),
        'mimes' => 'pdf,zip,docx,doc,xlsx,xls,pptx,ppt,txt,csv,png,jpg,jpeg,webp,mp3',
    ],

    'images' => [
        'max_kb' => 4096,
        'mimes' => 'jpg,jpeg,png,webp',
    ],

    'ai' => [
        // Model used by the optional AI quiz generator.
        'model' => env('AI_QUIZ_MODEL', 'claude-opus-5-5'),
        'max_input_chars' => 60000,
    ],
];
