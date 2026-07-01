<?php

use App\Http\Controllers\Payments\WorldpayWebhookController;
use App\Http\Controllers\Payments\StripeSubscriptionWebhookController;
use App\Http\Controllers\Printers\PrinterBridgeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/webhooks/worldpay', [WorldpayWebhookController::class, 'store'])
    ->name('webhooks.worldpay');

Route::post('/webhooks/stripe/subscriptions', [StripeSubscriptionWebhookController::class, 'store'])
    ->name('webhooks.stripe.subscriptions');

Route::prefix('printer-bridge')
    ->name('printer-bridge.')
    ->group(function () {
        Route::post('/heartbeat', [PrinterBridgeController::class, 'heartbeat'])->name('heartbeat');
        Route::get('/jobs', [PrinterBridgeController::class, 'jobs'])->name('jobs.index');
        Route::get('/jobs/{job}', [PrinterBridgeController::class, 'show'])->name('jobs.show');
        Route::post('/jobs/{job}/printed', [PrinterBridgeController::class, 'printed'])->name('jobs.printed');
        Route::post('/jobs/{job}/failed', [PrinterBridgeController::class, 'failed'])->name('jobs.failed');
    });

Route::match(['GET', 'POST'], '/cloudprnt/poll', [PrinterBridgeController::class, 'cloudPrntPoll'])
    ->name('cloudprnt.poll');
