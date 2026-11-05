<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Customer\Http\Controllers\CommercialOpenWaWebhookController;
use Modules\Customer\Http\Controllers\CustomerAPIController;
use Modules\Customer\Http\Controllers\PublicLeadController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->prefix('customer')->name('customer.portal.')->group(function () {
    Route::get('dashboard', [CustomerAPIController::class, 'dashboard'])->name('dashboard');
    Route::post('restart-line', [CustomerAPIController::class, 'restartLine'])->name('restart-line');
    Route::post('upload-payment', [CustomerAPIController::class, 'uploadPayment'])->name('upload-payment');
    Route::post('payments/upload-voucher', [CustomerAPIController::class, 'uploadPayment'])->name('payments.upload-voucher');
    Route::get('invoices', [CustomerAPIController::class, 'invoices'])->name('invoices');
    Route::get('payments', [CustomerAPIController::class, 'payments'])->name('payments');
    Route::get('tickets', [CustomerAPIController::class, 'tickets'])->name('tickets');
    Route::post('tickets', [CustomerAPIController::class, 'createTicket'])->name('tickets.store');
    Route::put('profile', [CustomerAPIController::class, 'updateProfile'])->name('profile.update');
});

Route::prefix('public')->name('customer.public.')->group(function () {
    Route::post('leads', [PublicLeadController::class, 'store'])->name('leads.store');
    Route::post('leads/whatsapp-click', [PublicLeadController::class, 'whatsappClick'])->name('leads.whatsapp-click');
    Route::post('openwa/commercial/webhook', CommercialOpenWaWebhookController::class)->name('openwa.commercial.webhook');
    Route::post('openwa/bi/sync', [CustomerAPIController::class, 'syncBiClassification'])->name('openwa.bi.sync');
});
