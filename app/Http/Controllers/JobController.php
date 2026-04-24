<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Job;
use App\Models\Application;

class JobController extends Controller
{
    private function generateJobId()
    {
        do {
            $id = 'j_' . rand(1000000000, 9999999999);
        } while (Job::where('job_id', $id)->exists());

        return $id;
    }

    public function index(Request $request)
    {
        $query = Job::with('company')
            ->where('deadline', '>=', now()); // ACTIVE JOBS ONLY

        // SEARCH by title
        if ($request->search) {
            $query->where('title', 'like', "%{$request->search}%");
        }

        // LOCATION FILTER
        if ($request->location) {
            $query->where('location', 'like', "%{$request->location}%");
        }

        // LEVEL FILTER
        if ($request->level) {
            $query->where('level', $request->level);
        }

        // EXPERIENCE FILTER (basic version)
        if ($request->experience) {
            $query->where('experience', 'like', "%{$request->experience}%");
        }

        return $query->get()->map(function ($job) {

            return [
                'job_id' => $job->job_id,
                'title' => $job->title,
                'location' => $job->location,
                'level' => $job->level,
                'experience' => $job->experience,
                'salary' => $job->salary,
                'vacancy' => $job->vacancy,
                'description' => $job->description,
                'datePosted' => $job->date_posted,
                'deadline' => $job->deadline,

                // RELATION (IMPORTANT)
                'company' => [
                    'company_id' => $job->company->company_id,
                    'name' => $job->company->name,
                    'logo_color' => $job->company->logo_color,
                ],

                // computed field
                'isExpired' => false,
            ];
        });
    }

    // 🌍 PUBLIC - Get single job
    public function show($job_id)
    {
        $job = Job::where('job_id', $job_id)->first();

        if (!$job) {
            return response()->json([
                'message' => 'Job not found'
            ], 404);
        }

        return response()->json($job);
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
            'job_id' => $this->generateJobId(),
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
    public function update(Request $request, $job_id)
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
    public function destroy(Request $request, $job_id)
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

    public function companyJobDetail(Request $request, $job_id)
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

    public function companiesWithJobs()
    {
        $today = now()->toDateString();

        // 1. Get active jobs with company
        $jobs = Job::with('company')
            ->whereDate('deadline', '>=', $today)
            ->get();

        // 2. Group by company
        $grouped = $jobs->groupBy('company_id');

        // 3. Format response
        $companies = $grouped->map(function ($jobs) {

            $company = $jobs->first()->company;

            return [
                'company_id' => $company->company_id,
                'company_name' => $company->name,
                'company_logo_color' => $company->logo_color,
                'active_jobs_count' => $jobs->count(),

                'jobs' => $jobs->map(function ($job) {
                    return [
                        'id' => $job->id,
                        'title' => $job->title,
                        'location' => $job->location,
                        'salary' => $job->salary,
                        'experience' => $job->experience,
                        'deadline' => $job->deadline,
                        'company_name' => $job->company->name,
                    ];
                })
            ];
        })->values();

        return response()->json([
            'companies' => $companies
        ]);
    }
}
