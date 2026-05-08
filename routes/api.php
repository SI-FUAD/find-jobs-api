<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyAuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CompanyController;
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
    Route::get('/user/dashboard', [UserController::class, 'dashboard']);
    Route::get('/user/profile', [UserController::class, 'profile']);
    Route::patch('/user/profile', [UserController::class, 'updateProfile']);
    Route::get('/user/my-cv', [UserController::class, 'myCv']);

    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Company
    Route::get('/company/dashboard', [CompanyController::class, 'dashboard']);
    Route::get('/company/candidates', [JobController::class, 'shortlistedCandidates']);
    Route::get('/company/view-cv/{user_id}', [CompanyController::class, 'viewCv']);

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
    Route::get('/admin/view-cv/{user_id}', function (Request $request, string $user_id) {

        $admin = $request->user();
        if (!$admin->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }
        $user = App\Models\User::where('user_id', $user_id)
            ->where('role', 'user')
            ->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }
        $user->load([
            'educations',
            'experiences',
            'certificates',
            'links'
        ]);
        return new App\Http\Resources\UserCvResource($user);
    });

    // Company Jobs
    Route::prefix('company/jobs')->group(function () {
        Route::post('/', [JobController::class, 'store']);
        Route::put('/{id}', [JobController::class, 'update']);
        Route::delete('/{id}', [JobController::class, 'destroy']);
        Route::get('/', [JobController::class, 'companyJobs']);
        Route::get('/{id}', [JobController::class, 'companyJobDetail']);
    });

    Route::prefix('user')->group(function () {

        Route::get('/saved-jobs', [SavedJobController::class, 'index']);
        Route::post('/saved-jobs/toggle', [SavedJobController::class, 'toggle']);
        Route::get('/applications', [ApplicationController::class, 'index']);
        Route::post('/applications', [ApplicationController::class, 'store']);
        Route::get('/application-status', [ApplicationController::class, 'status']);
    });
});
