<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

use Illuminate\Support\Facades\Route;
use Modules\Contract\Http\Controllers\ContractController;
use Modules\Contract\Http\Controllers\InstallationController;
use Modules\Contract\Http\Controllers\RouteController;
use Modules\Contract\Http\Controllers\SLAController;
use Modules\Core\Http\Middleware\CheckPermission;

// Grupo de rutas protegidas por autenticación
Route::middleware('auth')->prefix('contract')->name('contract.')->group(function() {
    
    // Dashboard del módulo
    Route::get('dashboard', [ContractController::class, 'dashboard'])->name('dashboard');
    
    // Gestión de contratos - Con permisos granulares
    Route::prefix('contracts')->name('contracts.')->group(function() {
        // Rutas de creación
        Route::middleware('permission:contracts,create')->group(function() {
            Route::get('create', [ContractController::class, 'create'])->name('create');
            Route::post('', [ContractController::class, 'store'])->name('store');
        });

        // Rutas de visualización - Deben ir después de 'create' para evitar conflictos
        Route::middleware('permission:contracts,view')->group(function() {
            Route::get('', [ContractController::class, 'index'])->name('index');
            Route::get('near-expiration', [ContractController::class, 'nearExpiration'])->name('near-expiration');
            Route::get('expired', [ContractController::class, 'expired'])->name('expired');
            Route::get('{id}/pdf', [ContractController::class, 'pdf'])->name('pdf')->where('id', '[0-9]+');
            Route::get('{id}', [ContractController::class, 'show'])->name('show')->where('id', '[0-9]+');
        });

        // Rutas de edición
        Route::middleware('permission:contracts,edit')->group(function() {
            Route::get('{id}/edit', [ContractController::class, 'edit'])->name('edit');
            Route::put('{id}', [ContractController::class, 'update'])->name('update');
            Route::get('{id}/renew', [ContractController::class, 'showRenewForm'])->name('renew-form');
            Route::post('{id}/renew', [ContractController::class, 'renew'])->name('renew');
            Route::post('{id}/cancel', [ContractController::class, 'cancel'])->name('cancel');
        });

        // Rutas de eliminación
        Route::middleware('permission:contracts,delete')->group(function() {
            Route::delete('{id}', [ContractController::class, 'destroy'])->name('destroy');
        });
    });
    
    // Gestión de instalaciones - Con permisos granulares
    Route::prefix('installations')->name('installations.')->group(function() {
        // Rutas de creación
        Route::middleware('permission:installations,create')->group(function() {
            Route::get('create', [InstallationController::class, 'create'])->name('create');
            Route::post('', [InstallationController::class, 'store'])->name('store');
        });

        // Rutas de visualización
        Route::middleware('permission:installations,view')->group(function() {
            Route::get('', [InstallationController::class, 'index'])->name('index');
            Route::get('today', [InstallationController::class, 'today'])->name('today');
            Route::get('pending', [InstallationController::class, 'pending'])->name('pending');
            Route::get('late', [InstallationController::class, 'late'])->name('late');
            Route::get('{id}', [InstallationController::class, 'show'])->name('show')->where('id', '[0-9]+');
        });

        // Rutas de edición
        Route::middleware('permission:installations,edit')->group(function() {
            Route::get('{id}/edit', [InstallationController::class, 'edit'])->name('edit');
            Route::put('{id}', [InstallationController::class, 'update'])->name('update');
            Route::get('{id}/complete', [InstallationController::class, 'showCompleteForm'])->name('complete-form');
            Route::post('{id}/complete', [InstallationController::class, 'complete'])->name('complete');
            Route::post('{id}/cancel', [InstallationController::class, 'cancel'])->name('cancel');
            Route::post('{id}/photos', [InstallationController::class, 'addPhoto'])->name('add-photo');
            Route::delete('{id}/photos/{photoId}', [InstallationController::class, 'deletePhoto'])->name('delete-photo');
        });

        // Rutas de eliminación
        Route::middleware('permission:installations,delete')->group(function() {
            Route::delete('{id}', [InstallationController::class, 'destroy'])->name('destroy');
        });
    });
    
    // Gestión de rutas - Con permisos granulares
    Route::prefix('routes')->name('routes.')->group(function() {
        // Rutas de creación
        Route::middleware('permission:routes,create')->group(function() {
            Route::get('create', [RouteController::class, 'create'])->name('create');
            Route::post('', [RouteController::class, 'store'])->name('store');
        });

        // Rutas de visualización
        Route::middleware('permission:routes,view')->group(function() {
            Route::get('', [RouteController::class, 'index'])->name('index');
            Route::get('today', [RouteController::class, 'today'])->name('today');
            Route::get('active', [RouteController::class, 'active'])->name('active');
            Route::get('{id}', [RouteController::class, 'show'])->name('show')->where('id', '[0-9]+');
            Route::get('{id}/map', [RouteController::class, 'map'])->name('map');
        });

        // Rutas de edición
        Route::middleware('permission:routes,edit')->group(function() {
            Route::get('{id}/edit', [RouteController::class, 'edit'])->name('edit');
            Route::put('{id}', [RouteController::class, 'update'])->name('update');
            Route::post('{id}/status', [RouteController::class, 'changeStatus'])->name('change-status');
        });

        // Rutas de eliminación
        Route::middleware('permission:routes,delete')->group(function() {
            Route::delete('{id}', [RouteController::class, 'destroy'])->name('destroy');
        });
    });
    
    // Gestión de SLAs - Con permisos granulares
    Route::prefix('slas')->name('slas.')->group(function() {
        // Rutas de creación
        Route::middleware('permission:slas,create')->group(function() {
            Route::get('create', [SLAController::class, 'create'])->name('create');
            Route::post('', [SLAController::class, 'store'])->name('store');
        });

        // Rutas de visualización
        Route::middleware('permission:slas,view')->group(function() {
            Route::get('', [SLAController::class, 'index'])->name('index');
            Route::get('{id}', [SLAController::class, 'show'])->name('show')->where('id', '[0-9]+');
        });

        // Rutas de edición
        Route::middleware('permission:slas,edit')->group(function() {
            Route::get('{id}/edit', [SLAController::class, 'edit'])->name('edit');
            Route::put('{id}', [SLAController::class, 'update'])->name('update');
        });

        // Rutas de eliminación
        Route::middleware('permission:slas,delete')->group(function() {
            Route::delete('{id}', [SLAController::class, 'destroy'])->name('destroy');
        });
    });
    
    // API para uso interno del módulo
    Route::prefix('api')->name('api.')->group(function() {
        Route::get('contracts/search', [ContractController::class, 'searchForSelect2'])->name('contracts.search');
        Route::get('slas/plan-type/{type}', [SLAController::class, 'getSuitableForPlanType'])->name('slas.by-plan-type');
    });
});
