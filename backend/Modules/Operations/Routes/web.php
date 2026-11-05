<?php

use Illuminate\Support\Facades\Route;
use Modules\Operations\Http\Controllers\OperationsController;

Route::prefix('operations')->middleware(['auth'])->name('operations.')->group(function () {
    Route::get('/', [OperationsController::class, 'index'])->name('dashboard');
    Route::get('/onboarding', [OperationsController::class, 'onboarding'])->name('onboarding');
    Route::get('/onboarding-orders/create', [OperationsController::class, 'createOrder'])->name('onboarding-orders.create');
    Route::post('/onboarding-orders', [OperationsController::class, 'storeOrder'])->name('onboarding-orders.store');
    Route::post('/onboarding-orders/{order}/activation-request', [OperationsController::class, 'requestActivation'])->name('onboarding-orders.activation-request');
    Route::post('/onboarding-orders/{order}/complete-activation', [OperationsController::class, 'completeActivation'])->name('onboarding-orders.complete-activation');
    Route::post('/onboarding-orders/{order}/generate-contract', [OperationsController::class, 'generateContract'])->name('onboarding-orders.generate-contract');
    Route::get('/customers/{customer}/flow', [OperationsController::class, 'customerFlow'])->name('customers.flow');
});
