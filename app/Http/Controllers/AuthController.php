<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Helpers\AuthHelper;
use App\Services\IdGenerator;
use App\Services\AvatarService;
use App\Services\ProfileCompletionService;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6'
        ]);

        $avatar = AvatarService::generateUserAvatar(
            $request->first_name,
            $request->last_name
        );

        $user = User::create([
            'user_id' => IdGenerator::generate(User::class, 'user_id', 'u_', 6),
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => $request->password,
            'avatar_text' => $avatar['text'],
            'avatar_color' => $avatar['color'],
        ]);

        // Load empty relations so calculation works correctly
        $user->load(['educations', 'experiences', 'links']);

        // Calculate completion
        $completion = ProfileCompletionService::calculate($user);

        // Save
        $user->profile_completion = $completion;
        $user->save();

        return response()->json([
            'message' => 'User registered successfully',
            'user' => [
                'user_id' => $user->user_id,
                'name' => $user->full_name,
                'email' => $user->email,
                'avatar_text' => $user->avatar_text,
                'avatar_color' => $user->avatar_color,
                'profile_completion' => $user->profile_completion,
            ]
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        // 🔥 Create token
        if ($user->role === 'admin') {
            $token = $user->createToken('admin_token')->plainTextToken;
        } else {
            $token = $user->createToken('user_token')->plainTextToken;
        }

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user,
            'role' => $user->role
        ]);
    }

    public function logout(Request $request)
    {
        $auth = $request->user();

        /*
    |--------------------------------------------------------------------------
    | USER LOGOUT
    |--------------------------------------------------------------------------
    */

        if (AuthHelper::authorize($auth, 'user')) {

            $auth->currentAccessToken()->delete();

            return response()->json([
                'message' => 'User logged out successfully'
            ]);
        }

        /*
    |--------------------------------------------------------------------------
    | ADMIN LOGOUT
    |--------------------------------------------------------------------------
    */

        if (AuthHelper::authorize($auth, 'admin')) {

            $auth->currentAccessToken()->delete();

            return response()->json([
                'message' => 'Admin logged out successfully'
            ]);
        }

        /*
    |--------------------------------------------------------------------------
    | UNAUTHORIZED
    |--------------------------------------------------------------------------
    */

        return response()->json([
            'message' => 'Unauthorized'
        ], 403);
    }
}
