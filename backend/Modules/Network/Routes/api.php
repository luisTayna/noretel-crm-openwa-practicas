<?php

use Illuminate\Support\Facades\Route;
use Modules\Network\Http\Controllers\RouterController;
use Modules\Network\Http\Controllers\Api\ServiceRequestApiController;

/*
|--------------------------------------------------------------------------
| Network API Routes
|--------------------------------------------------------------------------
*/

// Rutas públicas para clientes (frontend Vue.js)
Route::middleware(['auth:sanctum'])->prefix('customer')->group(function () {

    // Solicitudes de servicio
    Route::post('/service-request', [ServiceRequestApiController::class, 'create'])->name('api.service-request.create');
    Route::get('/service-requests', [ServiceRequestApiController::class, 'myRequests'])->name('api.service-requests.my');
    Route::get('/service-request/{ticket}', [ServiceRequestApiController::class, 'show'])->name('api.service-request.show');

    // Estado del router del cliente
    Route::get('/my-router-status', [RouterController::class, 'myRouterStatus'])->name('api.my-router-status');
});

// Rutas administrativas
Route::middleware(['auth:sanctum'])->prefix('admin/network')->group(function () {

    // Routers
    Route::get('/routers', [RouterController::class, 'index'])->name('api.routers.index');
    Route::get('/routers/{router}', [RouterController::class, 'show'])->name('api.routers.show');
    Route::post('/routers/{router}/reboot', [RouterController::class, 'reboot'])->name('api.routers.reboot');
    Route::get('/routers/{router}/status', [RouterController::class, 'status'])->name('api.routers.status');
    Route::get('/routers/{router}/metrics', [RouterController::class, 'metrics'])->name('api.routers.metrics');

    // Solicitudes de servicio
    Route::get('/service-requests', [ServiceRequestApiController::class, 'index'])->name('api.service-requests.index');
    Route::get('/service-requests/pending', [ServiceRequestApiController::class, 'pending'])->name('api.service-requests.pending');
    Route::post('/service-requests/{serviceRequest}/assign', [ServiceRequestApiController::class, 'assign'])->name('api.service-requests.assign');

    // Dashboard en tiempo real
    Route::get('/dashboard/stats', [RouterController::class, 'dashboardStats'])->name('api.dashboard.stats');
    Route::get('/dashboard/alerts', [RouterController::class, 'alerts'])->name('api.dashboard.alerts');
});

// Webhook para routers (opcional - para recibir eventos de routers)
Route::post('/network/webhook/router-event', [RouterController::class, 'webhook'])->name('api.network.webhook');


