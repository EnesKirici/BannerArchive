<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TMDBController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// Public Routes (bfcache enabled)
Route::middleware(\App\Http\Middleware\EnableBfCache::class)->group(function () {
    Route::get('/', [TMDBController::class, 'index'])->name('home');
    Route::get('/search', [TMDBController::class, 'search'])->name('search')->middleware('throttle:search');
    Route::get('/images/{type}/{id}', [TMDBController::class, 'images'])->name('images')->middleware('throttle:browse');
    Route::get('/proxy-image', [TMDBController::class, 'proxyImage'])->name('proxy.image')->middleware('throttle:download');
    Route::get('/gallery/{type}/{id}', [TMDBController::class, 'gallery'])->name('gallery')->where(['type' => 'movie|tv', 'id' => '[0-9]+'])->middleware('throttle:browse');
    Route::post('/generate-quotes', [TMDBController::class, 'generateQuotes'])->name('generate.quotes')->middleware('throttle:quotes');
    Route::get('/person/{id}/credits', [TMDBController::class, 'personCredits'])->name('person.credits')->where('id', '[0-9]+')->middleware('throttle:browse');

    // Particles API (public - for frontend)
    Route::get('/api/particles/config', [AdminController::class, 'getActiveThemeConfig'])->name('api.particles.config')->middleware('throttle:60,1');

    // Tools (Public)
    Volt::route('/tools/image-converter', 'image-converter')->name('tools.image-converter');
});

// Auth Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Admin Routes (protected) — Livewire Volt full-page components
// Volt::route('/url', 'component-name') → Controller'a gerek yok, component her şeyi halleder
Route::middleware(['auth', 'admin'])->group(function () {
    Volt::route('/admin', 'admin.dashboard')->name('admin.dashboard');
    Volt::route('/admin/particles', 'admin.particles')->name('admin.particles');
    Volt::route('/admin/settings', 'admin.settings')->name('admin.settings');
    Volt::route('/admin/login-history', 'admin.login-history')->name('admin.login-history');
    Volt::route('/admin/cache', 'admin.cache-manager')->name('admin.cache');
    Volt::route('/admin/blocked-ips', 'admin.blocked-ips')->name('admin.blocked-ips');
    Volt::route('/admin/activity-logs', 'admin.activity-logs')->name('admin.activity-logs');
});
