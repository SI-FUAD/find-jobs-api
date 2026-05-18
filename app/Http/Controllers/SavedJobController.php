<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Job;
use App\Helpers\AuthHelper;
use App\Http\Resources\JobCardResource;

class SavedJobController extends Controller
{
    // ✅ Get all saved jobs
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
| SAVED JOBS
|--------------------------------------------------------------------------
*/

        $savedJobs = $auth->savedJobs()
            ->with([
                'company',
            ])
            ->orderByPivot('created_at', 'desc')
            ->get();

        /*
|--------------------------------------------------------------------------
| SHARE WITH RESOURCE
|--------------------------------------------------------------------------
*/

        $request->attributes->set('savedJobIds', $savedJobIds);

        $request->attributes->set('appliedJobIds', $appliedJobIds);

        return JobCardResource::collection($savedJobs);
    }

    // ✅ Save / Unsave (TOGGLE)
    public function toggle(Request $request)
    {
        $request->validate([
            'job_id' => 'required|exists:jobs,job_id'
        ]);

        $user = $request->user();

        if ($user->role !== 'user') {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $job = Job::where('job_id', $request->job_id)->first();

        // Check if already saved
        if ($user->savedJobs()->where('saved_jobs.job_id', $job->id)->exists()) {

            $user->savedJobs()->detach($job->id);

            return response()->json([
                'message' => 'Job removed from saved'
            ]);
        }

        // Save job
        $user->savedJobs()->attach($job->id);

        return response()->json([
            'message' => 'Job saved successfully'
        ]);
    }
}
