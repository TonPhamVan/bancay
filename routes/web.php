<?php

use Illuminate\Support\Facades\Route;

// Operational foundation only. Storefront and commerce HTTP endpoints come later.
Route::get('/health', fn () => response()->json(['status' => 'ok']))->name('health');
