<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\Job;
use App\Models\Application;
use App\Helpers\AuthHelper;
use App\Services\IdGenerator;
use App\Http\Resources\JobCardResource;
use App\Http\Resources\JobViewResource;

class JobController extends Controller
{
    public function home(Request $request)
    {
        $auth = $request->user();

        $authType = AuthHelper::getAuthType($auth);

        $authData = null;

        /*
    |--------------------------------------------------------------------------
    | USER
    |--------------------------------------------------------------------------
    */

        if ($authType === 'user') {

            $authData = [
                'type' => 'user',

                'user_id' => $auth->user_id,

                'name' => $auth->full_name,

                'avatar_text' => $auth->avatar_text,

                'avatar_color' => $auth->avatar_color,
            ];
        }

        /*
    |--------------------------------------------------------------------------
    | COMPANY
    |--------------------------------------------------------------------------
    */

        if ($authType === 'company') {

            $authData = [
                'type' => 'company',

                'company_id' => $auth->company_id,

                'name' => $auth->name,

                'logo_color' => $auth->logo_color,
            ];
        }

        /*
    |--------------------------------------------------------------------------
    | ADMIN
    |--------------------------------------------------------------------------
    */

        if ($authType === 'admin') {

            $authData = [
                'type' => 'admin',

                'name' => $auth->full_name,
            ];
        }

        return response()->json([

            'auth_type' => $authType,

            'auth' => $authData,
        ]);
    }

    public function index(Request $request)
    {
        $auth = $request->user();

        $isUser = AuthHelper::authorize($auth, 'user');

        $query = Job::query()
            ->with('company')
            ->active();

        /*
    |--------------------------------------------------------------------------
    | SEARCH
    |--------------------------------------------------------------------------
    */

        if ($request->filled('search')) {

            $search = trim($request->search);

            $query->where(function ($q) use ($search) {

                $q->where('title', 'like', "%{$search}%");
            });
        }

        /*
    |--------------------------------------------------------------------------
    | LOCATION
    |--------------------------------------------------------------------------
    */

        if ($request->filled('location')) {

            $query->where(
                'location',
                'like',
                "%{$request->location}%"
            );
        }

        /*
    |--------------------------------------------------------------------------
    | LEVEL
    |--------------------------------------------------------------------------
    */

        if ($request->filled('level')) {

            $query->where('level', $request->level);
        }

        /*
    |--------------------------------------------------------------------------
    | EXPERIENCE
    |--------------------------------------------------------------------------
    */

        if ($request->filled('experience')) {

            $experience = (int) $request->experience;

            $query->where(function ($q) use ($experience) {

                $q->where('experience', 'like', "%{$experience}%")
                    ->orWhere('experience', 'like', "%Fresher%");
            });
        }

        /*
    |--------------------------------------------------------------------------
    | SALARY
    |--------------------------------------------------------------------------
    */

        if ($request->boolean('negotiable')) {

            $query->where('salary', 'Negotiable');
        }

        if (
            $request->filled('salary_min') &&
            $request->filled('salary_max')
        ) {

            $query->where('salary', '!=', 'Negotiable')
                ->whereBetween('salary', [
                    $request->salary_min,
                    $request->salary_max
                ]);
        }

        /*
    |--------------------------------------------------------------------------
    | AUTH OPTIMIZATION
    |--------------------------------------------------------------------------
    */

        $savedJobIds = [];
        $appliedJobIds = [];

        if ($isUser) {
            $savedJobIds = $auth->savedJobs()
                ->pluck('jobs.id')
                ->toArray();

            $appliedJobIds = $auth->applications()
                ->pluck('job_id')
                ->toArray();
        }

        $jobs = $query
            ->latest()
            ->paginate(12);

        $request->attributes->set('savedJobIds', $savedJobIds);
        $request->attributes->set('appliedJobIds', $appliedJobIds);

        return JobCardResource::collection($jobs);
    }

    // 🌍 PUBLIC - Get single job
    public function show(Request $request, string $job_id)
    {
        $auth = $request->user();

        $isUser = AuthHelper::authorize($auth, 'user');

        /*
    |--------------------------------------------------------------------------
    | JOB
    |--------------------------------------------------------------------------
    */

        $job = Job::query()
            ->with('company')
            ->where('job_id', $job_id)
            ->first();

        if (!$job) {

            return response()->json([
                'message' => 'Job not found'
            ], 404);
        }

        /*
    |--------------------------------------------------------------------------
    | AUTH OPTIMIZATION
    |--------------------------------------------------------------------------
    */

        $savedJobIds = [];

        $appliedJobIds = [];

        if ($isUser) {

            $savedJobIds = $auth->savedJobs()
                ->pluck('jobs.id')
                ->toArray();

            $appliedJobIds = $auth->applications()
                ->pluck('job_id')
                ->toArray();
        }

        /*
    |--------------------------------------------------------------------------
    | SHARE WITH RESOURCE
    |--------------------------------------------------------------------------
    */

        $request->attributes->set('savedJobIds', $savedJobIds);

        $request->attributes->set('appliedJobIds', $appliedJobIds);

        return new JobViewResource($job);
    }

    // 🏢 COMPANY - Create job
    public function store(Request $request)
    {
        $company = $request->user();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'level' => 'required|string',
            'location' => 'required|string',
            'description' => 'required|string',
            'vacancy' => 'required|integer|min:1',
            'experience' => 'required|string',
            'salary' => 'nullable|string',
            'deadline' => 'required|date|after_or_equal:today'
        ]);

        $job = Job::create([
            'job_id' => IdGenerator::generate(Job::class, 'job_id', 'j_', 10),
            'company_id' => $company->id,
            'title' => $validated['title'],
            'level' => $validated['level'],
            'location' => $validated['location'],
            'description' => $validated['description'],
            'vacancy' => $validated['vacancy'],
            'experience' => $validated['experience'],
            'salary' => $validated['salary'] ?? 'Negotiable',
            'date_posted' => now(),
            'deadline' => $validated['deadline'],
        ]);

        return response()->json([
            'message' => 'Job created successfully',
            'data' => $job
        ], 201);
    }

    // 🏢 COMPANY - Update job
    public function update(Request $request, string $job_id)
    {
        $job = Job::where('job_id', $job_id)->first();

        if (!$job) {
            return response()->json(['message' => 'Job not found'], 404);
        }

        if ($job->company_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'level' => 'sometimes|required|string',
            'location' => 'sometimes|required|string',
            'description' => 'sometimes|required|string',
            'vacancy' => 'sometimes|required|integer|min:1',
            'experience' => 'sometimes|required|string',
            'salary' => 'nullable|string',
            'deadline' => 'sometimes|required|date|after_or_equal:today'
        ]);

        $job->update($validated);

        return response()->json([
            'message' => 'Job updated successfully',
            'job' => $job
        ]);
    }

    // 🏢 COMPANY - Delete job
    public function destroy(Request $request, string $job_id)
    {
        $job = Job::where('job_id', $job_id)->first();

        if (!$job) {
            return response()->json(['message' => 'Job not found'], 404);
        }

        // 🔒 Only owner can delete
        if ($job->company_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $job->delete();

        return response()->json([
            'message' => 'Job deleted'
        ]);
    }

    // 🏢 COMPANY - Get own jobs
    public function companyJobs(Request $request)
    {
        $jobs = Job::where('company_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($job) {
                return [
                    'job_id' => $job->job_id,
                    'title' => $job->title,
                    'location' => $job->location,
                    'level' => $job->level,
                    'vacancy' => $job->vacancy,
                    'deadline' => $job->deadline,
                    'isExpired' => now()->gt($job->deadline),
                    'created_at' => $job->created_at,
                ];
            });

        return response()->json($jobs);
    }

    public function companyJobDetail(Request $request, string $job_id)
    {
        $job = Job::where('job_id', $job_id)->first();

        if (!$job) {
            return response()->json(['message' => 'Job not found'], 404);
        }

        // 🔒 ownership check
        if ($job->company_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($job);
    }

    public function shortlistedCandidates(Request $request)
    {
        $company = $request->user();

        // 1. Get company jobs
        $jobs = Job::where('company_id', $company->id)->get();

        $result = $jobs->map(function ($job) {

            // 2. Get shortlisted applications for this job
            $applications = Application::with('user')
                ->where('job_id', $job->id) // DB ID
                ->where('is_shortlisted', true)
                ->get()
                ->map(function ($app) {
                    return [
                        'application_id' => $app->id,
                        'status' => $app->status,
                        'applied_at' => $app->created_at,
                        'updated_at' => $app->updated_at,

                        'user' => [
                            'user_id' => $app->user->user_id, // public id
                            'name' => $app->user->first_name . ' ' . $app->user->last_name,
                            'email' => $app->user->email,
                            'phone' => $app->user->phone,
                        ]
                    ];
                });

            return [
                'job' => [
                    'job_id' => $job->job_id,
                    'title' => $job->title,
                ],
                'candidates' => $applications
            ];
        });

        return response()->json($result);
    }

    public function companiesWithJobs(Request $request)
    {
        $auth = $request->user();

        $isUser = AuthHelper::authorize($auth, 'user');

        $companies = Company::query()

            ->when($request->filled('search'), function ($query) use ($request) {

                $query->where(
                    'name',
                    'like',
                    "%{$request->search}%"
                );
            })

            ->whereHas('jobs', function ($query) {

                $query->active();
            })

            ->with([
                'jobs' => function ($query) {

                    $query->active()
                        ->latest()
                        ->with('company');
                }
            ])

            ->paginate(6);

        /*
|--------------------------------------------------------------------------
| AUTH OPTIMIZATION
|--------------------------------------------------------------------------
*/

        $savedJobIds = [];
        $appliedJobIds = [];

        if ($isUser) {

            $savedJobIds = $auth->savedJobs()
                ->pluck('jobs.id')
                ->toArray();

            $appliedJobIds = $auth->applications()
                ->pluck('job_id')
                ->toArray();
        }

        /*
|--------------------------------------------------------------------------
| SHARE ARRAYS WITH RESOURCE
|--------------------------------------------------------------------------
*/

        $request->attributes->set('savedJobIds', $savedJobIds);

        $request->attributes->set('appliedJobIds', $appliedJobIds);

        /*
|--------------------------------------------------------------------------
| TRANSFORM
|--------------------------------------------------------------------------
*/

        $companies->getCollection()->transform(function ($company) {

            return [

                'companyId' => $company->company_id,

                'brandName' => $company->name,

                'brandColor' => $company->logo_color,

                'activeJobsCount' => $company->jobs->count(),

                'jobs' => JobCardResource::collection(
                    $company->jobs
                ),
            ];
        });

        return response()->json([

            'data' => $companies->items(),

            'links' => [
                'next' => $companies->nextPageUrl(),
                'prev' => $companies->previousPageUrl(),
            ],

            'pagination' => [
                'currentPage' => $companies->currentPage(),
                'lastPage' => $companies->lastPage(),
                'perPage' => $companies->perPage(),
                'total' => $companies->total(),
            ],
        ]);
    }
}
