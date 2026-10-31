<?php

use ExmentAdminCore\Admin\Facades\Admin;

/**
 * Exment - bootstrap
 *
 *  Bootstraper for Exment customize.
 */
Admin::navbar(function ($navbar) {
    $navbar->right(view('admin.store.navbar'));
});
Admin::js('/storefront/admin-ux.js?v='.substr(hash_file('sha256', public_path('storefront/admin-ux.js')), 0, 12), false);
