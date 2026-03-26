<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// ゲストユーザー用ルート（未ログイン）
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

// 認証済みユーザー用ルート
Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        return redirect('/dashboard');
    });
    
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
});
