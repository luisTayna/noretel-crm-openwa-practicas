<?php

use Illuminate\Support\Facades\Route;
use Modules\Network\Http\Controllers\NetworkDashboardController;
use Modules\Network\Http\Controllers\RouterController;
use Modules\Network\Http\Controllers\NodeController;
use Modules\Network\Http\Controllers\ServiceRequestController;
use Modules\Network\Http\Controllers\AutomationRuleController;
use Modules\Network\Http\Controllers\SSHController;

/*
|--------------------------------------------------------------------------
| Network Module Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->prefix('network')->name('network.')->group(function () {

    // Dashboard de Red
    Route::get('/dashboard', [NetworkDashboardController::class, 'index'])->name('dashboard');

    // Gestión de Routers
    Route::resource('routers', RouterController::class);

    // Acciones específicas de routers
    Route::post('routers/{router}/reboot', [RouterController::class, 'reboot'])->name('routers.reboot');
    Route::get('routers/{router}/status', [RouterController::class, 'status'])->name('routers.status');
    Route::get('routers/{router}/metrics', [RouterController::class, 'metrics'])->name('routers.metrics');
    Route::get('routers/{router}/logs', [RouterController::class, 'logs'])->name('routers.logs');
    Route::post('routers/{router}/assign-customer', [RouterController::class, 'assignCustomer'])->name('routers.assign-customer');
    Route::delete('routers/{router}/remove-customer/{customer}', [RouterController::class, 'removeCustomer'])->name('routers.remove-customer');

    // Gestión de Nodos
    Route::resource('nodes', NodeController::class);
    Route::get('nodes/{node}/routers', [NodeController::class, 'routers'])->name('nodes.routers');
    Route::get('nodes/{node}/coverage-map', [NodeController::class, 'coverageMap'])->name('nodes.coverage-map');

    // Solicitudes de Servicio
    Route::resource('service-requests', ServiceRequestController::class);
    Route::post('service-requests/{serviceRequest}/assign', [ServiceRequestController::class, 'assign'])->name('service-requests.assign');
    Route::post('service-requests/{serviceRequest}/complete', [ServiceRequestController::class, 'complete'])->name('service-requests.complete');
    Route::post('service-requests/{serviceRequest}/cancel', [ServiceRequestController::class, 'cancel'])->name('service-requests.cancel');
    Route::post('service-requests/{serviceRequest}/retry', [ServiceRequestController::class, 'retry'])->name('service-requests.retry');

    // Automatizaciones
    Route::resource('automation', AutomationRuleController::class);
    Route::post('automation/{rule}/toggle', [AutomationRuleController::class, 'toggle'])->name('automation.toggle');
    Route::post('automation/{rule}/execute', [AutomationRuleController::class, 'execute'])->name('automation.execute');
    Route::get('automation/{rule}/history', [AutomationRuleController::class, 'history'])->name('automation.history');

    // Reportes y Estadísticas
    Route::get('reports/network-health', [NetworkDashboardController::class, 'networkHealth'])->name('reports.network-health');
    Route::get('reports/router-performance', [NetworkDashboardController::class, 'routerPerformance'])->name('reports.router-performance');
    Route::get('reports/service-requests-summary', [NetworkDashboardController::class, 'serviceRequestsSummary'])->name('reports.service-requests-summary');

    // Terminal SSH
    Route::get('/routers/{router}/ssh', [SSHController::class, 'show'])
        ->name('ssh.show')
        ->middleware('permission:routers,ssh');
    Route::post('/routers/{router}/ssh/execute', [SSHController::class, 'execute'])
        ->name('ssh.execute')
        ->middleware('permission:routers,ssh');
});
