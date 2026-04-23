<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\Job;
use Illuminate\Support\Facades\Hash;

class CompanyAuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:companies',
            'phone' => 'required',
            'address' => 'required',
            'password' => 'required|min:6'
        ]);

        $company = Company::create([
            'company_id' => 'c_' . rand(100000, 999999),
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'password' => $request->password
        ]);

        return response()->json([
            'message' => 'Company registered successfully',
            'company' => $company
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $company = Company::where('email', $request->email)->first();

        if (!$company || !Hash::check($request->password, $company->password)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        // 🔥 Token
        $token = $company->createToken('company_token')->plainTextToken;

        return response()->json([
            'message' => 'Company login successful',
            'token' => $token,
            'company' => $company
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Company logged out'
        ]);
    }

    public function dashboard(Request $request)
    {
        $company = $request->user();
        $jobs_count = Job::where('company_id', $company->company_id)->count();
        $active_jobs_count = Job::where('company_id', $company->company_id)->where('deadline', '>=', now())->count();
        $expired_jobs_count = Job::where('company_id', $company->company_id)->where('deadline', '<', now())->count();

        return response()->json([
            'company_id' => $company->company_id,
            'name' => $company->name,
            'email' => $company->email,
            'phone' => $company->phone,
            'address' => $company->address,
            'jobs_count' => $jobs_count,
            'active_jobs_count' => $active_jobs_count,
            'expired_jobs_count' => $expired_jobs_count
        ]);
    }
}
