<?php

// Keep overrides small; the pinned Exment package supplies all other defaults.
return [
    'locale' => env('EXMENT_LOCALE', 'en'),
    'timezone' => env('APP_TIMEZONE', 'Asia/Ho_Chi_Minh'),
    'directory' => app_path('Exment'),
    'bootstrap' => app_path('Exment/bootstrap.php'),
    'mail_setting_env_force' => true,
    'api' => false,
    'outside_api' => false,
    'system_update_display_disabled' => true,
    'debugmode_request' => false,
    'debugmode_sql' => false,
];
