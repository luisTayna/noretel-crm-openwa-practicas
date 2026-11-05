<?php

use Illuminate\Support\Facades\Route;
use Modules\Services\Http\Controllers\AdditionalServiceController;
use Modules\Services\Http\Controllers\PlanController;
use Modules\Services\Http\Controllers\PromotionController;
use Modules\Services\Http\Controllers\ServiceController;

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

Route::prefix('services')->middleware('auth')->name('services.')->group(function() {
    // Dashboard
    Route::get('/', [ServiceController::class, 'index'])->name('dashboard');

    Route::prefix('services')->name('services.')->group(function() {
        // Rutas de creación
        Route::middleware('permission:services,create')->group(function() {
            Route::get('/create', [ServiceController::class, 'create'])->name('create');
            Route::post('/', [ServiceController::class, 'store'])->name('store');
        });
        // Rutas de visualización
        Route::middleware('permission:services,view')->group(function() {
            Route::get('/', [ServiceController::class, 'index'])->name('index');
            Route::get('/{id}', [ServiceController::class, 'show'])->name('show')->where('id', '[0-9]+');
        });

        // Rutas de edición
        Route::middleware('permission:services,edit')->group(function() {
            Route::get('/{id}/edit', [ServiceController::class, 'edit'])->name('edit');
            Route::put('/{id}', [ServiceController::class, 'update'])->name('update');
            Route::match(['post', 'patch'], '/{id}/activate', [ServiceController::class, 'activate'])->name('activate');
            Route::match(['post', 'patch'], '/{id}/deactivate', [ServiceController::class, 'deactivate'])->name('deactivate');
        });

        // Rutas de eliminación
        Route::middleware('permission:services,delete')->group(function() {
            Route::delete('/{id}', [ServiceController::class, 'destroy'])->name('destroy');
        });
    });

    Route::prefix('plans')->name('plans.')->group(function() {
        // Rutas de creación
        Route::middleware('permission:plans,create')->group(function() {
            Route::get('/create', [PlanController::class, 'create'])->name('create');
            Route::post('/', [PlanController::class, 'store'])->name('store');
        });
        // Rutas de visualización
        Route::middleware('permission:plans,view')->group(function() {
            Route::get('/', [PlanController::class, 'index'])->name('index');
            Route::get('/{id}', [PlanController::class, 'show'])->name('show')->where('id', '[0-9]+');
        });

        // Rutas de edición
        Route::middleware('permission:plans,edit')->group(function() {
            Route::get('/{id}/edit', [PlanController::class, 'edit'])->name('edit');
            Route::put('/{id}', [PlanController::class, 'update'])->name('update');
            Route::match(['post', 'patch'], '/{id}/activate', [PlanController::class, 'activate'])->name('activate');
            Route::match(['post', 'patch'], '/{id}/deactivate', [PlanController::class, 'deactivate'])->name('deactivate');
        });

        // Rutas de eliminación
        Route::middleware('permission:plans,delete')->group(function() {
            Route::delete('/{id}', [PlanController::class, 'destroy'])->name('destroy');
        });
    });

    Route::prefix('additional-services')->name('additional-services.')->group(function() {
        // Rutas de creación
        Route::middleware('permission:additional-services,create')->group(function() {
            Route::get('/create', [AdditionalServiceController::class, 'create'])->name('create');
            Route::post('/', [AdditionalServiceController::class, 'store'])->name('store');
        });
        // Rutas de visualización
        Route::middleware('permission:additional-services,view')->group(function() {
            Route::get('/', [AdditionalServiceController::class, 'index'])->name('index');
            Route::get('/{id}', [AdditionalServiceController::class, 'show'])->name('show')->where('id', '[0-9]+');
        });

        // Rutas de edición
        Route::middleware('permission:additional-services,edit')->group(function() {
            Route::get('/{id}/edit', [AdditionalServiceController::class, 'edit'])->name('edit');
            Route::put('/{id}', [AdditionalServiceController::class, 'update'])->name('update');
        });

        // Rutas de eliminación
        Route::middleware('permission:additional-services,delete')->group(function() {
            Route::delete('/{id}', [AdditionalServiceController::class, 'destroy'])->name('destroy');
        });
    });
    Route::prefix('promotions')->name('promotions.')->group(function() {
        // Rutas de creación
        Route::middleware('permission:promotions,create')->group(function() {
            Route::get('/create', [PromotionController::class, 'create'])->name('create');
            Route::post('/', [PromotionController::class, 'store'])->name('store');
        });
        // Rutas de visualización
        Route::middleware('permission:promotions,view')->group(function() {
            Route::get('/', [PromotionController::class, 'index'])->name('index');
            Route::get('/{id}', [PromotionController::class, 'show'])->name('show')->where('id', '[0-9]+');
        });

        // Rutas de edición
        Route::middleware('permission:promotions,edit')->group(function() {
            Route::get('/{id}/edit', [PromotionController::class, 'edit'])->name('edit');
            Route::put('/{id}', [PromotionController::class, 'update'])->name('update');
            Route::match(['post', 'patch'], '/{id}/activate', [PromotionController::class, 'activate'])->name('activate');
            Route::match(['post', 'patch'], '/{id}/deactivate', [PromotionController::class, 'deactivate'])->name('deactivate');
        });

        // Rutas de eliminación
        Route::middleware('permission:promotions,delete')->group(function() {
            Route::delete('/{id}', [PromotionController::class, 'destroy'])->name('destroy');
        });
    });

    // Servicios
    Route::group(['middleware' => ['permission:roles,create']], function () {
        // // Servicios
        // Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
        // Route::get('/services/create', [ServiceController::class, 'create'])->name('services.create')->middleware('core.permission:services,create');
        // Route::post('/services', [ServiceController::class, 'store'])->name('services.store')->middleware('core.permission:services,create');
        // Route::get('/services/{id}', [ServiceController::class, 'show'])->name('services.show');
        // Route::get('/services/{id}/edit', [ServiceController::class, 'edit'])->name('services.edit')->middleware('core.permission:services,edit');
        // Route::put('/services/{id}', [ServiceController::class, 'update'])->name('services.update')->middleware('core.permission:services,edit');
        // Route::delete('/services/{id}', [ServiceController::class, 'destroy'])->name('services.destroy')->middleware('core.permission:services,delete');
        // Route::patch('/services/{id}/activate', [ServiceController::class, 'activate'])->name('services.activate')->middleware('core.permission:services,edit');
        // Route::patch('/services/{id}/deactivate', [ServiceController::class, 'deactivate'])->name('services.deactivate')->middleware('core.permission:services,edit');

        // // Planes
        // Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');
        // Route::get('/plans/create', [PlanController::class, 'create'])->name('plans.create')->middleware('core.permission:services,create');
        // Route::post('/plans', [PlanController::class, 'store'])->name('plans.store')->middleware('core.permission:services,create');
        // Route::get('/plans/{id}', [PlanController::class, 'show'])->name('plans.show');
        // Route::get('/plans/{id}/edit', [PlanController::class, 'edit'])->name('plans.edit')->middleware('core.permission:services,edit');
        // Route::put('/plans/{id}', [PlanController::class, 'update'])->name('plans.update')->middleware('core.permission:services,edit');
        // Route::delete('/plans/{id}', [PlanController::class, 'destroy'])->name('plans.destroy')->middleware('core.permission:services,delete');
        // Route::patch('/plans/{id}/activate', [PlanController::class, 'activate'])->name('plans.activate')->middleware('core.permission:services,edit');
        // Route::patch('/plans/{id}/deactivate', [PlanController::class, 'deactivate'])->name('plans.deactivate')->middleware('core.permission:services,edit');

        // Servicios adicionales
    //     Route::get('/additional-services', [AdditionalServiceController::class, 'index'])->name('additional-services.index');
    //     Route::get('/additional-services/create', [AdditionalServiceController::class, 'create'])->name('additional-services.create')->middleware('core.permission:services,create');
    //     Route::post('/additional-services', [AdditionalServiceController::class, 'store'])->name('additional-services.store')->middleware('core.permission:services,create');
    //     Route::get('/additional-services/{id}', [AdditionalServiceController::class, 'show'])->name('additional-services.show');
    //     Route::get('/additional-services/{id}/edit', [AdditionalServiceController::class, 'edit'])->name('additional-services.edit')->middleware('core.permission:services,edit');
    //     Route::put('/additional-services/{id}', [AdditionalServiceController::class, 'update'])->name('additional-services.update')->middleware('core.permission:services,edit');
    //     Route::delete('/additional-services/{id}', [AdditionalServiceController::class, 'destroy'])->name('additional-services.destroy')->middleware('core.permission:services,delete');

    //     // Promociones
    //     Route::get('/promotions', [PromotionController::class, 'index'])->name('promotions.index');
    //     Route::get('/promotions/create', [PromotionController::class, 'create'])->name('promotions.create')->middleware('core.permission:services,create');
    //     Route::post('/promotions', [PromotionController::class, 'store'])->name('promotions.store')->middleware('core.permission:services,create');
    //     Route::get('/promotions/{id}', [PromotionController::class, 'show'])->name('promotions.show');
    //     Route::get('/promotions/{id}/edit', [PromotionController::class, 'edit'])->name('promotions.edit')->middleware('core.permission:services,edit');
    //     Route::put('/promotions/{id}', [PromotionController::class, 'update'])->name('promotions.update')->middleware('core.permission:services,edit');
    //     Route::delete('/promotions/{id}', [PromotionController::class, 'destroy'])->name('promotions.destroy')->middleware('core.permission:services,delete');
    //     Route::patch('/promotions/{id}/activate', [PromotionController::class, 'activate'])->name('promotions.activate')->middleware('core.permission:services,edit');
    //     Route::patch('/promotions/{id}/deactivate', [PromotionController::class, 'deactivate'])->name('promotions.deactivate')->middleware('core.permission:services,edit');
    });
});
