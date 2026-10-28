<?php

return [
    'enabled' => (bool) env('LEGACY_MIGRATION_ENABLED', false),
    'target_database' => env('LEGACY_TARGET_DATABASE'),
    'media_root' => env('LEGACY_MEDIA_ROOT'),
    'report_directory' => env('LEGACY_REPORT_DIRECTORY', base_path('legacy-private/phase4/reports')),
    'backup_manifest' => env('LEGACY_BACKUP_MANIFEST'),
    'review_manifest' => env('LEGACY_REVIEW_MANIFEST', base_path('legacy-private/phase4/review-overrides.json')),
];
