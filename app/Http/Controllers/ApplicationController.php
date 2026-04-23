<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Job;
use App\Models\Application;

class ApplicationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $applications = Application::with('job')
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        return response()->json($applications);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // 1. Validate
        $request->validate([
            'job_id' => 'required|exists:jobs,job_id',
        ]);

        // 2. Get logged-in user
        $user = $request->user();

        // 3. Find job
        $job = Job::where('job_id', $request->job_id)->first();

        if (!$job) {
            return response()->json([
                'message' => 'Job not found'
            ], 404);
        }

        // 4. Prevent duplicate application
        $alreadyApplied = Application::where('job_id', $job->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($alreadyApplied) {
            return response()->json([
                'message' => 'You already applied for this job'
            ], 400);
        }

        // 5. Create application
        $application = Application::create([
            'application_id' => 'a_' . rand(10000000, 99999999),
            'job_id' => $job->id,
            'user_id' => $user->id,
            'company_id' => $job->company_id,
            'status' => 'applied',
            'is_shortlisted' => false,
            'applied_at' => now(),
        ]);

        // 6. Response
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
}
