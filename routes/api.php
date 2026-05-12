<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyAuthController;

use App\Http\Controllers\UserController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\AdminController;

use App\Http\Controllers\JobController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\SavedJobController;

/*
|--------------------------------------------------------------------------
| AUTH ROUTES
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/

Route::get('/jobs', [JobController::class, 'index']);

Route::get('/jobs/{id}', [JobController::class, 'show']);

Route::get('/companies', [JobController::class, 'companiesWithJobs']);

/*
|--------------------------------------------------------------------------
| AUTHENTICATED ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | USER
    |--------------------------------------------------------------------------
    */

    Route::prefix('user')->group(function () {

        Route::get('/dashboard', [UserController::class, 'dashboard']);

        Route::get('/profile', [UserController::class, 'profile']);

        Route::patch('/profile', [UserController::class, 'updateProfile']);

        Route::get('/my-cv', [UserController::class, 'myCv']);

        Route::get('/saved-jobs', [SavedJobController::class, 'index']);

        Route::post('/saved-jobs/toggle', [SavedJobController::class, 'toggle']);

        Route::get('/applications', [ApplicationController::class, 'index']);

        Route::post('/applications', [ApplicationController::class, 'store']);

        Route::get('/application-status', [ApplicationController::class, 'status']);
    });

    /*
    |--------------------------------------------------------------------------
    | COMPANY
    |--------------------------------------------------------------------------
    */

    Route::prefix('company')->group(function () {

        Route::get('/dashboard', [CompanyController::class, 'dashboard']);

        Route::get('/candidates', [JobController::class, 'shortlistedCandidates']);

        Route::get('/view-cv/{user_id}', [CompanyController::class, 'viewCv']);

        Route::prefix('jobs')->group(function () {

            Route::post('/', [JobController::class, 'store']);

            Route::put('/{id}', [JobController::class, 'update']);

            Route::delete('/{id}', [JobController::class, 'destroy']);

            Route::get('/', [JobController::class, 'companyJobs']);

            Route::get('/{id}', [JobController::class, 'companyJobDetail']);
        });
    });

    /*
|--------------------------------------------------------------------------
| ADMIN
|--------------------------------------------------------------------------
*/

    Route::prefix('admin')->group(function () {

        /*
    |--------------------------------------------------------------------------
    | ANALYTICS
    |--------------------------------------------------------------------------
    */

        Route::get('/analytics', [AdminController::class, 'analytics']);

        /*
    |--------------------------------------------------------------------------
    | USERS MANAGEMENT
    |--------------------------------------------------------------------------
    */

        Route::get('/manage-users', [AdminController::class, 'users']);

        Route::delete('/manage-users/{user_id}', [AdminController::class, 'destroyUser']);

        /*
    |--------------------------------------------------------------------------
    | COMPANIES MANAGEMENT
    |--------------------------------------------------------------------------
    */

        Route::get('/manage-companies', [AdminController::class, 'companies']);

        Route::get('/manage-companies/{company_id}', [AdminController::class, 'companyDetails']);

        Route::delete('/manage-companies/{company_id}', [AdminController::class, 'destroyCompany']);

        /*
    |--------------------------------------------------------------------------
    | JOBS MANAGEMENT
    |--------------------------------------------------------------------------
    */

        Route::get('/manage-jobs', [AdminController::class, 'jobs']);

        Route::get('/manage-jobs/{job_id}', [AdminController::class, 'jobDetails']);

        Route::get(
            '/manage-jobs/{job_id}/suggested-candidates',
            [AdminController::class, 'suggestedCandidates']
        );

        Route::delete('/manage-jobs/{job_id}', [AdminController::class, 'destroyJob']);

        /*
|--------------------------------------------------------------------------
| APPLICATIONS MANAGEMENT
|--------------------------------------------------------------------------
*/

        Route::get('/manage-applications', [AdminController::class, 'applications']);

        Route::patch(
            '/manage-applications/{application_id}/status',
            [AdminController::class, 'updateApplicationStatus']
        );

        Route::delete(
            '/manage-applications/{application_id}',
            [AdminController::class, 'destroyApplication']
        );

        /*
    |--------------------------------------------------------------------------
    | CV
    |--------------------------------------------------------------------------
    */

        Route::get('/cv-collection', [AdminController::class, 'cvCollection']);

        Route::get('/view-cv/{user_id}', [AdminController::class, 'viewCv']);
    });

    /*
    |--------------------------------------------------------------------------
    | LOGOUT
    |--------------------------------------------------------------------------
    */

    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::post('/company/auth/logout', [CompanyAuthController::class, 'logout']);
});
