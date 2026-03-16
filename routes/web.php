<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TMDBController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// Public Routes
Route::get('/', [TMDBController::class, 'index'])->name('home');
Route::get('/search', [TMDBController::class, 'search'])->name('search')->middleware('throttle:30,1');
Route::get('/images/{type}/{id}', [TMDBController::class, 'images'])->name('images')->middleware('throttle:60,1');
Route::get('/proxy-image', [TMDBController::class, 'proxyImage'])->name('proxy.image')->middleware('throttle:120,1');
Route::get('/gallery/{type}/{id}', [TMDBController::class, 'gallery'])->name('gallery')->where(['type' => 'movie|tv', 'id' => '[0-9]+'])->middleware('throttle:60,1');
Route::post('/generate-quotes', [TMDBController::class, 'generateQuotes'])->name('generate.quotes')->middleware('throttle:10,1');
Route::get('/person/{id}/credits', [TMDBController::class, 'personCredits'])->name('person.credits')->where('id', '[0-9]+')->middleware('throttle:60,1');

// Particles API (public - for frontend)
Route::get('/api/particles/config', [AdminController::class, 'getActiveThemeConfig'])->name('api.particles.config')->middleware('throttle:60,1');

// Auth Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Admin Routes (protected) — Livewire Volt full-page components
// Volt::route('/url', 'component-name') → Controller'a gerek yok, component her şeyi halleder
Route::middleware('auth')->group(function () {
    Volt::route('/admin', 'admin.dashboard')->name('admin.dashboard');
    Volt::route('/admin/particles', 'admin.particles')->name('admin.particles');
    Volt::route('/admin/settings', 'admin.settings')->name('admin.settings');
    Volt::route('/admin/login-history', 'admin.login-history')->name('admin.login-history');
    Volt::route('/admin/cache', 'admin.cache-manager')->name('admin.cache');
});
