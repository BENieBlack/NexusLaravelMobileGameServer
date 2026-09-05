<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MasterImportController;
use Illuminate\Support\Facades\Route;

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

    // マスターデータインポート
    Route::prefix('master-import')->name('master-import.')->group(function () {
        Route::get('/', [MasterImportController::class, 'index'])->name('index');
        Route::get('/all-sheets', [MasterImportController::class, 'allSheets'])->name('all-sheets');
        Route::get('/spreadsheets', [MasterImportController::class, 'spreadsheets'])->name('spreadsheets');
        Route::get('/sheets', [MasterImportController::class, 'sheets'])->name('sheets');
        Route::get('/preview', [MasterImportController::class, 'preview'])->name('preview');
        Route::post('/execute', [MasterImportController::class, 'execute'])->name('execute');
    });
});
