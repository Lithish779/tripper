<?php

use App\Http\Controllers\Api\CallbackController;
use App\Http\Controllers\Api\MockInsurerController;
use App\Http\Controllers\Api\PolicyRequestController;
use App\Http\Controllers\Api\ReconciliationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PolicyFlow REST API Routes
|--------------------------------------------------------------------------
*/

// Policy Request Intake & Management
Route::prefix('policy-requests')->group(function () {
    Route::post('/', [PolicyRequestController::class, 'store'])->name('policy-requests.store');
    Route::get('/', [PolicyRequestController::class, 'index'])->name('policy-requests.index');
    Route::post('/bulk-csv', [PolicyRequestController::class, 'bulkCsv'])->name('policy-requests.bulk-csv');
    Route::get('/{id}', [PolicyRequestController::class, 'show'])->name('policy-requests.show');
    Route::post('/{id}/retry', [PolicyRequestController::class, 'retry'])->name('policy-requests.retry');
    Route::post('/{id}/reconcile', [PolicyRequestController::class, 'reconcile'])->name('policy-requests.reconcile');
});

// Asynchronous Insurer Webhook Callback (Idempotent)
Route::post('/insurer/callback', [CallbackController::class, 'handle'])->name('insurer.callback');

// Batch Reconciliation Trigger
Route::post('/reconciliation/run', [ReconciliationController::class, 'run'])->name('reconciliation.run');

// Mock Insurer Gateway Simulation Endpoints
Route::prefix('mock-insurer')->group(function () {
    Route::post('/issue', [MockInsurerController::class, 'issue'])->name('mock-insurer.issue');
    Route::get('/policies/{client_request_id}', [MockInsurerController::class, 'status'])->name('mock-insurer.status');
    Route::post('/reset', [MockInsurerController::class, 'reset'])->name('mock-insurer.reset');
});
