<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Módulo Services
|--------------------------------------------------------------------------
| Rutas API para el módulo de servicios
| Estas rutas son públicas (sin autenticación) para el frontend Vue.js
*/

// Importar controladores
use Modules\Services\Http\Controllers\PlanController;
use Modules\Services\Http\Controllers\ServiceController;
use Modules\Services\Http\Controllers\PromotionController;

/*
|--------------------------------------------------------------------------
| RUTAS PÚBLICAS (para el frontend Vue.js)
|--------------------------------------------------------------------------
*/

Route::prefix('public')->name('public.')->group(function () {

    // ==================== PLANES PÚBLICOS ====================
    Route::prefix('plans')->group(function () {

        Route::get('/', [PlanController::class, 'apiIndex']);

        Route::get('/featured', [PlanController::class, 'apiFeatured']);

        Route::get('/by-service/{serviceId}', [PlanController::class, 'apiByService']);

        Route::get('/{id}', [PlanController::class, 'apiShow'])
            ->whereNumber('id');
    });

    // ==================== SERVICIOS PÚBLICOS ====================
    Route::prefix('services')->name('services.')->group(function () {
        Route::get('/', function () {
            $serviceController = app(ServiceController::class);
            return $serviceController->apiIndex();
        })->name('index');

        Route::get('/{id}', function ($id) {
            $serviceController = app(ServiceController::class);
            return $serviceController->apiShow($id);
        })->name('show');
    });

    // ==================== PROMOCIONES PÚBLICAS ====================
    Route::prefix('promotions')->name('promotions.')->group(function () {
        Route::get('/', function () {
            $promotionController = app(PromotionController::class);
            return $promotionController->apiIndex();
        })->name('index');

        Route::get('/active', function () {
            $promotionController = app(PromotionController::class);
            return $promotionController->apiActive();
        })->name('active');
    });
});

/*
|--------------------------------------------------------------------------
| RUTAS PROTEGIDAS (para administración interna)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    // Aquí van las rutas que requieren autenticación
    // Por ejemplo, para crear, actualizar, eliminar planes desde una app móvil
});
