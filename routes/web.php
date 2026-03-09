<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TMDBController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// Public Routes
Route::get('/', [TMDBController::class, 'index'])->name('home');
Route::get('/search', [TMDBController::class, 'search'])->name('search');
Route::get('/images/{type}/{id}', [TMDBController::class, 'images'])->name('images');
Route::get('/proxy-image', [TMDBController::class, 'proxyImage'])->name('proxy.image');
Route::post('/generate-quotes', [TMDBController::class, 'generateQuotes'])->name('generate.quotes');

// Particles API (public - for frontend)
Route::get('/api/particles/config', [AdminController::class, 'getActiveThemeConfig'])->name('api.particles.config');

// Auth Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
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
