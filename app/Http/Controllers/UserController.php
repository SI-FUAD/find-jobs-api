<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

use App\Models\Education;
use App\Models\Experience;
use App\Models\Certificate;
use App\Models\Link;
use App\Services\ProfileCompletionService;
use App\Http\Resources\UserCvResource;

class UserController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user();

        $stats = $user->applications()
            ->selectRaw("
                COUNT(*) as total,
                SUM(status = 'applied') as applied,
                SUM(status = 'shortlisted') as shortlisted,
                SUM(status = 'accepted') as accepted,
                SUM(status = 'rejected') as rejected
            ")
            ->first();

        return response()->json([
            'user' => [
                'user_id' => $user->user_id,
                'name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'current_address' => $user->current_address,
                'profile_completion' => $user->profile_completion
            ],
            'stats' => [
                'saved_jobs' => $user->savedJobs()->count(),
                'total' => $stats->total,
                'applied' => $stats->applied,
                'shortlisted' => $stats->shortlisted,
                'accepted' => $stats->accepted,
                'rejected' => $stats->rejected,
            ]
        ]);
    }

    public function profile(Request $request)
    {
        $user = $request->user();

        $user->load([
            'educations' => fn($q) => $q->orderBy('position'),
            'experiences' => fn($q) => $q->orderBy('position'),
            'certificates' => fn($q) => $q->orderBy('position'),
            'links' => fn($q) => $q->orderBy('position'),
        ]);

        return response()->json($user);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'first_name' => 'nullable|string',
            'last_name' => 'nullable|string',
            'phone' => 'nullable|string',
            'emergency_phone' => 'nullable|string',

            'gender' => ['nullable', Rule::in(['Male', 'Female', 'Other'])],
            'marital_status' => ['nullable', Rule::in(['Single', 'Married'])],

            'father_name' => 'nullable|string',
            'mother_name' => 'nullable|string',
            'current_address' => 'nullable|string',
            'permanent_address' => 'nullable|string',

            'career_title' => 'nullable|string',
            'career_summary' => 'nullable|string',

            'educations' => 'sometimes|array',
            'experiences' => 'sometimes|array',
            'certificates' => 'sometimes|array',
            'links' => 'sometimes|array',
        ]);

        $this->validateRows($request->educations ?? [], ['level', 'institute', 'result', 'year'], 'educations');
        $this->validateRows($request->experiences ?? [], ['company', 'role', 'duration', 'skills'], 'experiences');
        $this->validateRows($request->certificates ?? [], ['name', 'organization', 'year'], 'certificates');
        $this->validateRows($request->links ?? [], ['label', 'url'], 'links');

        DB::transaction(function () use ($request, $user) {

            $user->update($request->only([
                'first_name',
                'last_name',
                'phone',
                'emergency_phone',
                'gender',
                'marital_status',
                'father_name',
                'mother_name',
                'current_address',
                'permanent_address',
                'career_title',
                'career_summary',
            ]));

            if ($request->has('educations')) {
                $this->syncData(Education::class, $request->educations, $user->id);
            }

            if ($request->has('experiences')) {
                $this->syncData(Experience::class, $request->experiences, $user->id);
            }

            if ($request->has('certificates')) {
                $this->syncData(Certificate::class, $request->certificates, $user->id);
            }

            if ($request->has('links')) {
                $this->syncData(Link::class, $request->links, $user->id);
            }
        });

        $user->load(['educations', 'experiences', 'certificates', 'links']);

        $completion = ProfileCompletionService::calculate($user);

        $user->profile_completion = $completion;
        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'profile_completion' => $completion,
            'user' => $user
        ]);
    }

    public function myCv(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'user') {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        // Load CV relations
        $user->load([
            'educations',
            'experiences',
            'links',
            'certificates'
        ]);

        return new UserCvResource($user);
    }

    private function validateRows(array $items, array $fields, string $section): void
    {
        foreach ($items as $index => $item) {

            $filled = collect($item)->filter(fn($v) => !empty($v))->count();

            if ($filled > 0 && $filled < count($fields)) {
                throw ValidationException::withMessages([
                    "$section.$index" => ["All fields must be filled for this entry."]
                ]);
            }
        }
    }

    private function syncData(string $model, array $items, int $userId): void
    {
        $model::where('user_id', $userId)->delete();

        foreach ($items as $index => $item) {

            $filled = collect($item)->filter(fn($v) => !empty($v))->count();

            if ($filled === 0) continue; // skip empty row

            $model::create([
                ...$item,
                'user_id' => $userId,
                'position' => $index + 1
            ]);
        }
    }
}
