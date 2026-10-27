<?php

use Exceedone\Exment\Model\LoginUser;

return [
    'defaults' => ['guard' => 'admin', 'passwords' => 'admin'],
    'guards' => ['admin' => ['driver' => 'session', 'provider' => 'admin']],
    'providers' => ['admin' => ['driver' => 'exment-auth', 'model' => LoginUser::class]],
    // Exment manages administrator password reset; no duplicate App User/customer login.
    'password_timeout' => 10800,
];
