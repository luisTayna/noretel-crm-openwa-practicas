<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes - Para Vue.js Frontend
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->name('api.auth.')->group(function() {
    // Rutas públicas
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/login', [AuthController::class, 'login'])->name('login');

    // Rutas protegidas con Sanctum
    Route::middleware('auth:sanctum')->group(function() {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::put('/change-password', [AuthController::class, 'changePasswordApi'])->name('change-password');
    });
});
