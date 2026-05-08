<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Job;
use App\Models\User;
use App\Models\Application;
use App\Http\Resources\UserCvResource;

class CompanyController extends Controller
{
    public function dashboard(Request $request)
    {
        $company = $request->user();

        return response()->json([
            'company_id' => $company->company_id,
            'name' => $company->name,
            'email' => $company->email,
            'phone' => $company->phone,
            'address' => $company->address,

            'jobs_count' => Job::where('company_id', $company->id)->count(),

            'active_jobs_count' => Job::where('company_id', $company->id)
                ->where('deadline', '>=', now())
                ->count(),

            'expired_jobs_count' => Job::where('company_id', $company->id)
                ->where('deadline', '<', now())
                ->count(),
        ]);
    }

    public function viewCv(Request $request, string $user_id)
    {
        $company = $request->user();

        // 1. Get user
        $user = User::where('user_id', $user_id)->first();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        // 2. Check access (FIXED)
        $hasAccess = Application::where('user_id', $user->id)
            ->where('company_id', $company->id) // 🔥 FIX HERE
            ->where('is_shortlisted', true)
            ->exists();

        if (!$hasAccess) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        // 3. Load CV
        $user->load([
            'educations',
            'experiences',
            'links',
            'certificates'
        ]);

        return new UserCvResource($user);
    }
}
