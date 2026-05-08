<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Company;
use App\Services\IdGenerator;
use App\Services\AvatarService;
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
            'company_id' => IdGenerator::generate(Company::class, 'company_id', 'c_', 6),
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'password' => $request->password,
            'logo_color' => AvatarService::generateCompanyAvatar($request->name),
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
}
