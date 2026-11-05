<?php

use Illuminate\Http\Request;

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

Route::middleware('auth:api')->get('/billing', function (Request $request) {
    return $request->user();
});

Route::prefix('billing/yape')->group(function () {
    Route::get('health', 'YapeValidationController@health');
    Route::get('pending-receiver-intents', 'YapeValidationController@pendingReceiverIntents');
    Route::post('openwa/webhook', 'YapeValidationController@ingestOpenWaWebhook');
    Route::post('client-evidences', 'YapeValidationController@ingestClientEvidence');
    Route::post('receiver-events', 'YapeValidationController@ingestReceiverEvent');
});
