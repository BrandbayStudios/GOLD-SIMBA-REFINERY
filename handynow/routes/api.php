<?php

use App\Http\Controllers\Api;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| Versioned JSON API for the HANDY NOW Android / iOS apps.
| Authenticate with POST /api/v1/auth/login and send "Authorization: Bearer {token}".
*/
Route::prefix('v1')->group(function () {
    Route::post('/auth/register', [Api\AuthController::class, 'register'])->middleware('throttle:6,1');
    Route::post('/auth/login', [Api\AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::get('/categories', [Api\CatalogController::class, 'categories']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [Api\AuthController::class, 'me']);
        Route::post('/auth/logout', [Api\AuthController::class, 'logout']);
        Route::get('/notifications', fn (Request $r) => response()->json(['data' => $r->user()->notifications()->take(50)->get()->map(fn ($n) => $n->data + ['id' => $n->id, 'read' => (bool) $n->read_at, 'at' => $n->created_at->toIso8601String()])]));

        Route::middleware('role:customer')->prefix('requests')->group(function () {
            Route::get('/', [Api\CustomerRequestController::class, 'index']);
            Route::post('/', [Api\CustomerRequestController::class, 'store']);
            Route::get('/{serviceRequest}', [Api\CustomerRequestController::class, 'show']);
            Route::get('/{serviceRequest}/live', [Api\CustomerRequestController::class, 'live']);
            Route::post('/{serviceRequest}/quotes/{quotation}/accept', [Api\CustomerRequestController::class, 'acceptQuote']);
            Route::post('/{serviceRequest}/quotes/{quotation}/reject', [Api\CustomerRequestController::class, 'rejectQuote']);
            Route::post('/{serviceRequest}/cancel', [Api\CustomerRequestController::class, 'cancel']);
            Route::post('/{serviceRequest}/pay', [Api\CustomerRequestController::class, 'pay']);
            Route::post('/{serviceRequest}/review', [Api\CustomerRequestController::class, 'review']);
        });

        Route::middleware('role:provider')->prefix('provider')->group(function () {
            Route::get('/jobs/available', [Api\ProviderJobController::class, 'available']);
            Route::get('/jobs', [Api\ProviderJobController::class, 'mine']);
            Route::post('/jobs/{serviceRequest}/quote', [Api\ProviderJobController::class, 'quote']);
            Route::post('/jobs/{serviceRequest}/status', [Api\ProviderJobController::class, 'status']);
            Route::post('/availability', [Api\ProviderJobController::class, 'availability']);
            Route::post('/location', [Api\ProviderJobController::class, 'location']);
        });
    });
});
