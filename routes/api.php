<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyAuthController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\SavedJobController;

// User Auth
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Company Auth
Route::prefix('company/auth')->group(function () {
    Route::post('/register', [CompanyAuthController::class, 'register']);
    Route::post('/login', [CompanyAuthController::class, 'login']);
});

// Public Jobs
Route::get('/jobs', [JobController::class, 'index']);
Route::get('/jobs/{id}', [JobController::class, 'show']);
Route::get('/companies', [JobController::class, 'companiesWithJobs']);

Route::middleware('auth:sanctum')->group(function () {

    // User
    Route::get('/user/profile', function (Request $request) {
        return $request->user();
    });

    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Company
    Route::get('/company/dashboard', [CompanyAuthController::class, 'dashboard']);
    Route::post('/company/auth/logout', [CompanyAuthController::class, 'logout']);

    // Admin Analytics (basic test)
    Route::get('/admin/analytics', function (Request $request) {

        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'message' => 'Welcome Admin',
            'user' => $request->user()
        ]);
    });

    // Company Jobs
    Route::prefix('company/jobs')->group(function () {
        Route::post('/', [JobController::class, 'store']);
        Route::put('/{id}', [JobController::class, 'update']);
        Route::delete('/{id}', [JobController::class, 'destroy']);
        Route::get('/', [JobController::class, 'myJobs']);
    });

    // Applications
    Route::apiResource('applications', ApplicationController::class);

    Route::prefix('user')->group(function () {

        Route::get('/saved-jobs', [SavedJobController::class, 'index']);
        Route::post('/saved-jobs/toggle', [SavedJobController::class, 'toggle']);
    });
});
