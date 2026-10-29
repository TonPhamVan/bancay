<?php

use App\Http\Controllers\Frontend\ArticleController;
use App\Http\Controllers\Frontend\CartController;
use App\Http\Controllers\Frontend\CatalogController;
use App\Http\Controllers\Frontend\CheckoutController;
use App\Http\Controllers\Frontend\ContactController;
use App\Http\Controllers\Frontend\DiscoveryController;
use App\Http\Controllers\Frontend\LegacyRedirectController;
use App\Http\Middleware\StorefrontContext;
use Illuminate\Support\Facades\Route;

// Operational health endpoint is independent of public storefront presentation.
Route::get('/health', fn () => response()->json(['status' => 'ok']))->name('health');
Route::get('/robots.txt', [DiscoveryController::class, 'robots'])->name('store.robots');
Route::get('/sitemap.xml', [DiscoveryController::class, 'sitemap'])->name('store.sitemap');

Route::middleware(StorefrontContext::class)->name('store.')->group(function () {
    Route::get('/', [CatalogController::class, 'home'])->name('home');
    Route::get('/san-pham', [CatalogController::class, 'index'])->name('products.index');
    Route::get('/san-pham/{slug}', [CatalogController::class, 'show'])->name('products.show');
    Route::get('/danh-muc/{slug}', [CatalogController::class, 'category'])->name('categories.show');
    Route::get('/tim-kiem', [CatalogController::class, 'search'])->name('search');
    Route::get('/bai-viet', [ArticleController::class, 'index'])->name('articles.index');
    Route::get('/bai-viet/{slug}', [ArticleController::class, 'show'])->name('articles.show');
    Route::get('/chuyen-muc/{slug}', [ArticleController::class, 'index'])->name('article-categories.show');
    Route::get('/lien-he', ContactController::class)->name('contact');
    Route::get('/gio-hang', [CartController::class, 'index'])->block(15, 10)->name('cart.index');
    Route::post('/gio-hang', [CartController::class, 'store'])->middleware('throttle:60,1')->block(15, 10)->name('cart.store');
    Route::patch('/gio-hang/{variant}', [CartController::class, 'update'])->middleware('throttle:60,1')->block(15, 10)->name('cart.update');
    Route::delete('/gio-hang/{variant}', [CartController::class, 'destroy'])->block(15, 10)->name('cart.destroy');
    Route::delete('/gio-hang', [CartController::class, 'clear'])->block(15, 10)->name('cart.clear');
    Route::get('/thanh-toan', [CheckoutController::class, 'index'])->block(15, 10)->name('checkout.index');
    Route::post('/thanh-toan', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->block(15, 10)->name('checkout.store');
    Route::get('/don-hang/{number}', [CheckoutController::class, 'success'])->block(15, 10)->name('orders.show');
    Route::get('/tat-ca-san-pham', [LegacyRedirectController::class, 'listing'])->name('legacy.listing');
    Route::get('/{legacy}', LegacyRedirectController::class)->where('legacy', '[^/]+-(?:sp|dm|bv|cm)[0-9]{1,18}\.html')->name('legacy.entity');
});
