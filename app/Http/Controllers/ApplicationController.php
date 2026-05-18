<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use App\Models\Job;
use App\Models\Application;
use App\Helpers\AuthHelper;
use App\Services\IdGenerator;
use App\Http\Resources\JobCardResource;
use App\Http\Resources\ApplicationStatusResource;

class ApplicationController extends Controller
{
    public function index(Request $request)
    {
        $auth = $request->user();

        $isUser = AuthHelper::authorize($auth, 'user');

        if (!$isUser) {

            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        /*
|--------------------------------------------------------------------------
| AUTH OPTIMIZATION
|--------------------------------------------------------------------------
*/

        $savedJobIds = $auth->savedJobs()
            ->pluck('jobs.id')
            ->toArray();

        $appliedJobIds = $auth->applications()
            ->pluck('job_id')
            ->toArray();

        /*
|--------------------------------------------------------------------------
| APPLIED JOBS
|--------------------------------------------------------------------------
*/

        $applications = Application::with([
            'job.company',
        ])
            ->where('user_id', $auth->id)
            ->latest()
            ->get();

        $jobs = $applications
            ->map(fn($application) => $application->job)
            ->filter()
            ->unique('id')
            ->values();

        /*
|--------------------------------------------------------------------------
| SHARE WITH RESOURCE
|--------------------------------------------------------------------------
*/

        $request->attributes->set('savedJobIds', $savedJobIds);

        $request->attributes->set('appliedJobIds', $appliedJobIds);

        return JobCardResource::collection($jobs);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'job_id' => 'required|exists:jobs,job_id',
        ]);

        $user = $request->user();

        if ($user->role !== 'user') {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $job = Job::where('job_id', $request->job_id)->first();

        try {
            $application = Application::create([
                'application_id' => IdGenerator::generate(
                    Application::class,
                    'application_id',
                    'a_',
                    10
                ),
                'job_id' => $job->id,
                'user_id' => $user->id,
                'company_id' => $job->company_id,
                'status' => 'applied',
                'is_shortlisted' => false,
                'applied_at' => now(),
            ]);
        } catch (QueryException $e) {

            Log::error($e->getMessage());

            if ($e->errorInfo[1] == 1062) {
                return response()->json([
                    'message' => 'You already applied for this job'
                ], 400);
            }

            return response()->json([
                'message' => 'Something went wrong'
            ], 500);
        }

        return response()->json([
            'message' => 'Application submitted successfully',
            'application' => $application
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function status(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'user') {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $query = Application::with([
            'job.company'
        ])
            ->where('user_id', $user->id);

        // FILTER
        if ($request->status && $request->status !== 'all') {

            $query->where(
                'status',
                strtolower($request->status)
            );
        }

        $applications = $query
            ->latest('applied_at')
            ->get();

        // STATS
        $stats = [
            'total' => $user->applications()->count(),

            'applied' => $user->applications()
                ->where('status', 'applied')
                ->count(),

            'shortlisted' => $user->applications()
                ->where('status', 'shortlisted')
                ->count(),

            'accepted' => $user->applications()
                ->where('status', 'accepted')
                ->count(),

            'rejected' => $user->applications()
                ->where('status', 'rejected')
                ->count(),
        ];

        return response()->json([

            'stats' => $stats,

            'applications' => ApplicationStatusResource::collection($applications),

        ]);
    }
}
