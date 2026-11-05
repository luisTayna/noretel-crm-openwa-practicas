<?php

/*
|--------------------------------------------------------------------------
| Web Routes - Customer Module
|--------------------------------------------------------------------------
*/

use Illuminate\Support\Facades\Route;
use Modules\Customer\Http\Controllers\AddressController;
use Modules\Customer\Http\Controllers\CustomerController;
use Modules\Customer\Http\Controllers\CustomerMergeController;
use Modules\Customer\Http\Controllers\DocumentController;
use Modules\Customer\Http\Controllers\EmergencyContactController;
use Modules\Customer\Http\Controllers\HistoricalCustomerController;
use Modules\Customer\Http\Controllers\InteractionController;
use Modules\Customer\Http\Controllers\LeadController;

Route::prefix('customer')->middleware(['auth'])->name('customer.')->group(function() {
    // Dashboard & Clasificación BI
    Route::get('/', 'CustomerDashboardController@index')->name('dashboard');
    Route::get('/bi-classification', [CustomerController::class, 'biDashboard'])->name('bi-classification');
    Route::post('/bi-classification/run', [CustomerController::class, 'runBiClassification'])->name('bi-classification.run');
    
    // Customers - Con permisos granulares
    Route::prefix('customers')->name('customers.')->group(function() {
        // Rutas de creación
        Route::middleware('permission:customers,create')->group(function() {
            Route::get('/create', [CustomerController::class, 'create'])->name('create');
            Route::get('/historical/create', [HistoricalCustomerController::class, 'create'])->name('historical.create');
            Route::post('/historical', [HistoricalCustomerController::class, 'store'])->name('historical.store');
            Route::post('/', [CustomerController::class, 'store'])->name('store');
        });
        // Rutas de visualización
        Route::middleware('permission:customers,view')->group(function() {
            Route::get('/', [CustomerController::class, 'index'])->name('index');
            Route::get('/search/suggestions', [CustomerController::class, 'searchSuggestions'])->name('search-suggestions');
            Route::get('/{id}', [CustomerController::class, 'show'])->name('show')->where('id', '[0-9]+');
        });
        
        // Rutas de edición
        Route::middleware('permission:customers,edit')->group(function() {
            Route::get('/{id}/edit', [CustomerController::class, 'edit'])->name('edit');
            Route::put('/{id}', [CustomerController::class, 'update'])->name('update');
            Route::post('/{id}/activate', [CustomerController::class, 'activate'])->name('activate');
            Route::post('/{id}/deactivate', [CustomerController::class, 'deactivate'])->name('deactivate');
        });
        
        // Rutas de eliminación
        Route::middleware('permission:customers,delete')->group(function() {
            Route::get('/{id}/merge', [CustomerMergeController::class, 'index'])->name('merge');
            Route::post('/{id}/merge/preview', [CustomerMergeController::class, 'preview'])->name('merge.preview');
            Route::post('/{id}/merge', [CustomerMergeController::class, 'store'])->name('merge.store');
            Route::delete('/{id}', [CustomerController::class, 'destroy'])->name('destroy');
        });
    });
    
    // Documents - Con permisos granulares
    Route::prefix('documents')->name('documents.')->group(function() {
        // Rutas de creación
        Route::middleware('permission:customers,create')->group(function() {
            Route::get('/create', [DocumentController::class, 'create'])->name('create');
            Route::post('/', [DocumentController::class, 'store'])->name('store');
            Route::post('/{id}/versions', [DocumentController::class, 'uploadVersion'])->name('upload-version');
        });
        // Rutas de visualización
        Route::middleware('permission:customers,view')->group(function() {
            Route::get('/', [DocumentController::class, 'index'])->name('index');
            Route::get('/{id}', [DocumentController::class, 'show'])->name('show');
            Route::get('/{id}/download', [DocumentController::class, 'download'])->name('download');
            Route::get('/{id}/version/{versionId}/download', [DocumentController::class, 'downloadVersion'])->name('download-version');
        });
        
        // Rutas de edición
        Route::middleware('permission:customers,edit')->group(function() {
            Route::get('/{id}/edit', [DocumentController::class, 'edit'])->name('edit');
            Route::put('/{id}', [DocumentController::class, 'update'])->name('update');
            Route::post('/{id}/status', [DocumentController::class, 'changeStatus'])->name('change-status');
        });
        
        // Rutas de eliminación
        Route::middleware('permission:customers,delete')->group(function() {
            Route::delete('/{id}', [DocumentController::class, 'destroy'])->name('destroy');
        });
    });
    
    // Interactions - Con permisos granulares
    Route::prefix('interactions')->name('interactions.')->group(function() {
        // Rutas de creación
        Route::middleware('permission:customers,create')->group(function() {
            Route::get('/create', [InteractionController::class, 'create'])->name('create');
            Route::post('/', [InteractionController::class, 'store'])->name('store');
        });
        // Rutas de visualización
        Route::middleware('permission:customers,view')->group(function() {
            Route::get('/', [InteractionController::class, 'index'])->name('index');
            Route::get('/{id}', [InteractionController::class, 'show'])->name('show');
        });
        
        // Rutas de edición
        Route::middleware('permission:customers,edit')->group(function() {
            Route::get('/{id}/edit', [InteractionController::class, 'edit'])->name('edit');
            Route::put('/{id}', [InteractionController::class, 'update'])->name('update');
            Route::post('/{id}/follow-up', [InteractionController::class, 'markForFollowUp'])->name('mark-follow-up');
            Route::post('/{id}/unfollow-up', [InteractionController::class, 'unmarkForFollowUp'])->name('unmark-follow-up');
        });
        
        // Rutas de eliminación
        Route::middleware('permission:customers,delete')->group(function() {
            Route::delete('/{id}', [InteractionController::class, 'destroy'])->name('destroy');
        });
    });
    
    // Leads - Con permisos granulares
    Route::prefix('leads')->name('leads.')->group(function() {
        // Rutas de creación
        Route::middleware('permission:customers,create')->group(function() {
            Route::get('/create', [LeadController::class, 'create'])->name('create');
            Route::post('/', [LeadController::class, 'store'])->name('store');
        });
        // Rutas de visualización
        Route::middleware('permission:customers,view')->group(function() {
            Route::get('/', [LeadController::class, 'index'])->name('index');
            Route::get('/{id}', [LeadController::class, 'show'])->name('show');
        });
        
        // Rutas de edición
        Route::middleware('permission:customers,edit')->group(function() {
            Route::get('/{id}/edit', [LeadController::class, 'edit'])->name('edit');
            Route::put('/{id}', [LeadController::class, 'update'])->name('update');
            Route::get('/{id}/convert', [LeadController::class, 'showConvertForm'])->name('convert-form');
            Route::post('/{id}/convert', [LeadController::class, 'convert'])->name('convert');
            Route::post('/{id}/status', [LeadController::class, 'changeStatus'])->name('change-status');
        });
        
        // Rutas de eliminación
        Route::middleware('permission:customers,delete')->group(function() {
            Route::delete('/{id}', [LeadController::class, 'destroy'])->name('destroy');
        });
    });
    
    // Address Routes
    Route::prefix('addresses')->name('addresses.')->group(function() {
        // Routes de creación
        Route::middleware('permission:customers,create')->group(function() {
            Route::post('/', [AddressController::class, 'store'])->name('store');
        });
        
        // Routes de edición
        Route::middleware('permission:customers,edit')->group(function() {
            Route::put('/{id}', [AddressController::class, 'update'])->name('update');
        });
        
        // Routes de eliminación
        Route::middleware('permission:customers,delete')->group(function() {
            Route::delete('/{id}', [AddressController::class, 'destroy'])->name('destroy');
        });
    });
    
    // Routes para contactos de emergencia
    Route::prefix('emergencyContacts')->name('emergencyContacts.')->group(function() {
        // Rutas de creación
        Route::middleware('permission:customers,create')->group(function() {
            Route::post('/', [EmergencyContactController::class, 'store'])->name('store');
        });
        
        // Rutas de edición
        Route::middleware('permission:customers,edit')->group(function() {
            Route::put('/{id}', [EmergencyContactController::class, 'update'])->name('update');
        });
        
        // Rutas de eliminación
        Route::middleware('permission:customers,delete')->group(function() {
            Route::delete('/{id}', [EmergencyContactController::class, 'destroy'])->name('destroy');
        });
    });
});
