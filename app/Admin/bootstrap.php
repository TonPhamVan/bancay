<?php

use ExmentAdminCore\Admin\Admin;
use ExmentAdminCore\Admin\Form;

/**
 * Laravel-admin - admin builder based on Laravel.
 *
 * @author z-song <https://github.com/z-song>
 *
 * Bootstraper for Admin.
 *
 * Here you can remove builtin form field:
 * ExmentAdminCore\Admin\Form::forget(['map', 'editor']);
 *
 * Or extend custom form field:
 * ExmentAdminCore\Admin\Form::extend('php', PHPEditor::class);
 *
 * Or require js and css assets:
 * Admin::css('/packages/prettydocs/css/styles.css');
 * Admin::js('/packages/prettydocs/js/main.js');
 */
Form::forget(['map', 'editor']);
Admin::js('/storefront/admin-variant-matrix.js?v='.substr(hash_file('sha256', public_path('storefront/admin-variant-matrix.js')), 0, 12), false);
