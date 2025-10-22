<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\GoalController;

// Public authentication routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// ✅ Allow CORS preflight (OPTIONS) requests to pass without JWT check
Route::options('{any}', function () {
    return response()->json([], 200);
})->where('any', '.*');

// Protected routes (requires JWT authentication)
Route::group(['middleware' => ['jwt.auth']], function () {
    // Auth-related endpoints
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::get('/me', [AuthController::class, 'me']);

    // Categories - RESTful API resource
    Route::apiResource('categories', CategoryController::class);

    // Goals - RESTful API resource
    Route::apiResource('goals', GoalController::class);

    // Transactions - additional custom endpoints
    Route::get('/transactions/summary', [TransactionController::class, 'summary']);
    Route::get('/transactions/summary-current', [TransactionController::class, 'summaryCurrent']);
    Route::get('/transactions/insight', [TransactionController::class, 'insight']);

    // Transactions - standard CRUD operations
    Route::apiResource('transactions', TransactionController::class)
         ->where(['transaction' => '[0-9]+']);
});
