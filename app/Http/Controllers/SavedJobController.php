<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Job;
use App\Http\Resources\JobCardResource;

class SavedJobController extends Controller
{
    // ✅ Get all saved jobs
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'user') {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $savedJobs = $user->savedJobs()
            ->with([
                'company',
                'applications'
            ])
            ->orderByPivot('created_at', 'desc')
            ->get();

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
