<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\User;
use App\Models\Company;
use App\Models\Job;
use App\Models\Application;

use App\Http\Resources\UserCvResource;

class AdminController extends Controller
{
    public function analytics(Request $request)
    {
        $admin = $request->user();

        if (!$admin->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $users = User::where('role', 'user')->get();
        $companies = Company::all();
        $jobs = Job::all();
        $applications = Application::all();

        $totalUsers = $users->count();
        $totalCompanies = $companies->count();
        $totalJobs = $jobs->count();
        $totalApplications = $applications->count();

        $uniqueAppliedUsers = $applications
            ->pluck('user_id')
            ->unique()
            ->count();

        $avgProfileCompletion = $totalUsers > 0
            ? round($users->avg('profile_completion'))
            : 0;

        $completedProfiles = $users
            ->where('profile_completion', 100)
            ->count();

        $uncompletedProfiles = $users
            ->where('profile_completion', '<', 100)
            ->count();

        $expiredJobs = $jobs
            ->filter(function ($job) {
                return $job->deadline &&
                    \Carbon\Carbon::parse($job->deadline)->isPast();
            })
            ->count();

        $activeJobs = $totalJobs - $expiredJobs;

        $avgApplicationsPerJob = $totalJobs > 0
            ? round($totalApplications / $totalJobs, 1)
            : 0;

        $applied = $applications
            ->where('status', 'applied')
            ->count();

        $shortlisted = $applications
            ->where('status', 'shortlisted')
            ->count();

        $accepted = $applications
            ->where('status', 'accepted')
            ->count();

        $rejected = $applications
            ->where('status', 'rejected')
            ->count();

        $pending = $applied + $shortlisted;
        $completedApplications = $accepted + $rejected;
        $acceptanceRate = $totalApplications > 0
            ? round(($accepted / $totalApplications) * 100)
            : 0;

        $rejectionRate = $totalApplications > 0
            ? round(($rejected / $totalApplications) * 100)
            : 0;

        $updatedApplications = Application::query()
            ->where('status', '!=', 'applied')
            ->count();

        return response()->json([

            'userInsights' => [
                'totalUsers' => $totalUsers,
                'uniqueAppliedUsers' => $uniqueAppliedUsers,
                'avgProfileCompletion' => $avgProfileCompletion,
                'completedProfiles' => $completedProfiles,
                'uncompletedProfiles' => $uncompletedProfiles,
            ],

            'companyInsights' => [
                'totalCompanies' => $totalCompanies,
                'avgJobsPerCompany' => $totalCompanies > 0
                    ? round($totalJobs / $totalCompanies, 1)
                    : 0,
            ],

            'jobInsights' => [
                'totalJobs' => $totalJobs,
                'activeJobs' => $activeJobs,
                'expiredJobs' => $expiredJobs,
                'avgApplicationsPerJob' => $avgApplicationsPerJob,
            ],

            'applicationInsights' => [
                'totalApplications' => $totalApplications,
                'applied' => $applied,
                'shortlisted' => $shortlisted,
                'accepted' => $accepted,
                'rejected' => $rejected,
                'pending' => $pending,
                'completedApplications' => $completedApplications,
                'acceptanceRate' => $acceptanceRate,
                'rejectionRate' => $rejectionRate,
                'updatedApplications' => $updatedApplications,
            ],

            'pieChartData' => [

                [
                    'name' => 'Applied',
                    'value' => $applied,
                ],

                [
                    'name' => 'Shortlisted',
                    'value' => $shortlisted,
                ],

                [
                    'name' => 'Accepted',
                    'value' => $accepted,
                ],

                [
                    'name' => 'Rejected',
                    'value' => $rejected,
                ],
            ],
        ]);
    }

    /**
     * ADMIN MANAGE USERS LIST
     */
    public function users(Request $request)
    {
        $admin = $request->user();

        if (!$admin->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $search = trim($request->query('search', ''));

        $query = User::query()
            ->where('role', 'user');

        /*
    |--------------------------------------------------------------------------
    | SEARCH
    |--------------------------------------------------------------------------
    */

        if ($search !== '') {

            $query->where(function ($q) use ($search) {

                $q->where('user_id', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhereRaw(
                        "LOWER(CONCAT(first_name, ' ', last_name)) LIKE ?",
                        ['%' . strtolower($search) . '%']
                    );
            });
        }

        /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

        $users = $query
            ->latest('id')
            ->paginate(20);

        return response()->json([
            'users' => $users->through(function ($user) {

                return [

                    'userId' => $user->user_id,

                    'firstName' => $user->first_name,

                    'lastName' => $user->last_name,

                    'fullName' => $user->full_name,

                    'email' => $user->email,

                    'phone' => $user->phone,

                    'profileCompletion' => $user->profile_completion,

                    'createdAt' => $user->created_at,
                ];
            }),

            'filters' => [
                'search' => $search,
            ]
        ]);
    }

    /**
     * ADMIN DELETE USER
     */
    public function destroyUser(Request $request, string $user_id)
    {
        $admin = $request->user();

        /*
    |--------------------------------------------------------------------------
    | AUTHORIZE
    |--------------------------------------------------------------------------
    */

        if (!$admin->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        /*
    |--------------------------------------------------------------------------
    | FIND USER
    |--------------------------------------------------------------------------
    */

        $user = User::where('user_id', $user_id)
            ->where('role', 'user')
            ->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        /*
    |--------------------------------------------------------------------------
    | PREVENT SELF DELETE
    |--------------------------------------------------------------------------
    */

        if ($admin->id === $user->id) {
            return response()->json([
                'message' => 'You cannot delete your own account'
            ], 422);
        }

        /*
    |--------------------------------------------------------------------------
    | DELETE USER DATA
    |--------------------------------------------------------------------------
    */

        DB::transaction(function () use ($user) {

            /*
        |--------------------------------------------------------------------------
        | DELETE RELATIONAL DATA
        |--------------------------------------------------------------------------
        */

            $user->savedJobs()->detach();

            $user->applications()->delete();

            $user->educations()->delete();

            $user->experiences()->delete();

            $user->certificates()->delete();

            $user->links()->delete();

            /*
        |--------------------------------------------------------------------------
        | DELETE TOKENS
        |--------------------------------------------------------------------------
        */

            $user->tokens()->delete();

            /*
        |--------------------------------------------------------------------------
        | DELETE USER
        |--------------------------------------------------------------------------
        */

            $user->delete();
        });

        /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

        return response()->json([
            'message' => 'User deleted successfully'
        ]);
    }

    /**
     * ADMIN MANAGE COMPANIES LIST
     */
    public function companies(Request $request)
    {
        $admin = $request->user();

        /*
    |--------------------------------------------------------------------------
    | AUTHORIZE
    |--------------------------------------------------------------------------
    */

        if (!$admin->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        /*
    |--------------------------------------------------------------------------
    | SEARCH
    |--------------------------------------------------------------------------
    */

        $search = trim($request->query('search', ''));

        $query = Company::query();

        if ($search !== '') {

            $query->where(function ($q) use ($search) {

                $q->where('company_id', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

        $companies = $query
            ->latest('id')
            ->paginate(20);

        return response()->json([

            'companies' => $companies->through(function ($company) {

                return [

                    'companyId' => $company->company_id,

                    'brandName' => $company->name,

                    'email' => $company->email,

                    'phone' => $company->phone,

                    'address' => $company->address,

                    'createdAt' => $company->created_at,
                ];
            }),

            'filters' => [
                'search' => $search,
            ]
        ]);
    }

    /**
     * ADMIN COMPANY DETAILS
     */
    public function companyDetails(Request $request, string $company_id)
    {
        $admin = $request->user();

        /*
    |--------------------------------------------------------------------------
    | AUTHORIZE
    |--------------------------------------------------------------------------
    */

        if (!$admin->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        /*
    |--------------------------------------------------------------------------
    | FIND COMPANY
    |--------------------------------------------------------------------------
    */

        $company = Company::where('company_id', $company_id)
            ->first();

        if (!$company) {
            return response()->json([
                'message' => 'Company not found'
            ], 404);
        }

        /*
    |--------------------------------------------------------------------------
    | JOB STATS
    |--------------------------------------------------------------------------
    */

        $totalJobs = Job::where('company_id', $company->id)
            ->count();

        $activeJobs = Job::where('company_id', $company->id)
            ->active()
            ->count();

        $expiredJobs = Job::where('company_id', $company->id)
            ->expired()
            ->count();

        /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

        return response()->json([

            'company' => [

                'companyId' => $company->company_id,

                'brandName' => $company->name,

                'email' => $company->email,

                'phone' => $company->phone,

                'address' => $company->address,

                'createdAt' => $company->created_at,
            ],

            'stats' => [

                'totalJobs' => $totalJobs,

                'activeJobs' => $activeJobs,

                'expiredJobs' => $expiredJobs,
            ]
        ]);
    }

    /**
     * ADMIN DELETE COMPANY
     */
    public function destroyCompany(Request $request, string $company_id)
    {
        $admin = $request->user();

        /*
    |--------------------------------------------------------------------------
    | AUTHORIZE
    |--------------------------------------------------------------------------
    */

        if (!$admin->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        /*
    |--------------------------------------------------------------------------
    | FIND COMPANY
    |--------------------------------------------------------------------------
    */

        $company = Company::where('company_id', $company_id)
            ->first();

        if (!$company) {
            return response()->json([
                'message' => 'Company not found'
            ], 404);
        }

        /*
    |--------------------------------------------------------------------------
    | DELETE COMPANY DATA
    |--------------------------------------------------------------------------
    */

        DB::transaction(function () use ($company) {

            /*
        |--------------------------------------------------------------------------
        | GET COMPANY JOB IDS
        |--------------------------------------------------------------------------
        */

            $jobIds = Job::where('company_id', $company->id)
                ->pluck('id');

            /*
        |--------------------------------------------------------------------------
        | DELETE APPLICATIONS
        |--------------------------------------------------------------------------
        */

            Application::whereIn('job_id', $jobIds)
                ->delete();

            /*
        |--------------------------------------------------------------------------
        | DELETE SAVED JOBS
        |--------------------------------------------------------------------------
        */

            DB::table('saved_jobs')
                ->whereIn('job_id', $jobIds)
                ->delete();

            /*
        |--------------------------------------------------------------------------
        | DELETE JOBS
        |--------------------------------------------------------------------------
        */

            Job::where('company_id', $company->id)
                ->delete();

            /*
        |--------------------------------------------------------------------------
        | DELETE TOKENS
        |--------------------------------------------------------------------------
        */

            $company->tokens()->delete();

            /*
        |--------------------------------------------------------------------------
        | DELETE COMPANY
        |--------------------------------------------------------------------------
        */

            $company->delete();
        });

        /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

        return response()->json([
            'message' => 'Company deleted successfully'
        ]);
    }

    /**
     * ADMIN MANAGE JOBS LIST
     */
    public function jobs(Request $request)
    {
        $admin = $request->user();

        /*
|--------------------------------------------------------------------------
| AUTHORIZE
|--------------------------------------------------------------------------
*/

        if (!$admin->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        /*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

        $search = trim($request->query('search', ''));

        $status = trim($request->query('status', 'all'));

        /*
|--------------------------------------------------------------------------
| QUERY
|--------------------------------------------------------------------------
*/

        $query = Job::query()
            ->with('company');

        /*
|--------------------------------------------------------------------------
| STATUS FILTER
|--------------------------------------------------------------------------
*/

        if ($status === 'active') {

            $query->active();
        }

        if ($status === 'expired') {

            $query->expired();
        }

        /*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

        if ($search !== '') {

            $query->where(function ($q) use ($search) {

                $q->where('job_id', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")

                    ->orWhereHas('company', function ($companyQuery) use ($search) {

                        $companyQuery->where('company_id', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        /*
|--------------------------------------------------------------------------
| STATS
|--------------------------------------------------------------------------
*/

        $totalJobs = Job::count();

        $activeJobs = Job::active()->count();

        $expiredJobs = Job::expired()->count();

        /*
|--------------------------------------------------------------------------
| RESPONSE
|--------------------------------------------------------------------------
*/

        $jobs = $query
            ->latest('id')
            ->paginate(20);

        return response()->json([

            'jobs' => $jobs->through(function ($job) {

                return [

                    'jobId' => $job->job_id,

                    'title' => $job->title,

                    'level' => $job->level,

                    'companyId' => $job->company?->company_id,

                    'companyName' => $job->company?->name,

                    'location' => $job->location,

                    'vacancy' => $job->vacancy,

                    'salary' => $job->salary,

                    'datePosted' => $job->date_posted,

                    'deadline' => $job->deadline,

                    'isExpired' => $job->deadline < today(),
                ];
            }),

            'stats' => [

                'totalJobs' => $totalJobs,

                'activeJobs' => $activeJobs,

                'expiredJobs' => $expiredJobs,
            ],

            'filters' => [

                'search' => $search,

                'status' => $status,
            ]
        ]);
    }

    /**
     * ADMIN JOB DETAILS
     */
    public function jobDetails(Request $request, string $job_id)
    {
        $admin = $request->user();

        /*
|--------------------------------------------------------------------------
| AUTHORIZE
|--------------------------------------------------------------------------
*/

        if (!$admin->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        /*
|--------------------------------------------------------------------------
| FIND JOB
|--------------------------------------------------------------------------
*/

        $job = Job::with([
            'company',
            'applications'
        ])
            ->where('job_id', $job_id)
            ->first();

        if (!$job) {
            return response()->json([
                'message' => 'Job not found'
            ], 404);
        }

        /*
|--------------------------------------------------------------------------
| APPLICATION STATS
|--------------------------------------------------------------------------
*/

        $applications = $job->applications;

        $totalApplicants = $applications->count();

        $applied = $applications
            ->where('status', 'applied')
            ->count();

        $shortlisted = $applications
            ->where('status', 'shortlisted')
            ->count();

        $accepted = $applications
            ->where('status', 'accepted')
            ->count();

        $rejected = $applications
            ->where('status', 'rejected')
            ->count();

        /*
|--------------------------------------------------------------------------
| RESPONSE
|--------------------------------------------------------------------------
*/

        return response()->json([

            'job' => [

                'jobId' => $job->job_id,

                'title' => $job->title,

                'level' => $job->level,

                'companyId' => $job->company?->company_id,

                'companyName' => $job->company?->name,

                'location' => $job->location,

                'description' => $job->description,

                'vacancy' => $job->vacancy,

                'salary' => $job->salary,

                'experience' => $job->experience,

                'datePosted' => $job->date_posted,

                'deadline' => $job->deadline,

                'isExpired' => $job->deadline < today(),
            ],

            'stats' => [

                'totalApplicants' => $totalApplicants,

                'applied' => $applied,

                'shortlisted' => $shortlisted,

                'accepted' => $accepted,

                'rejected' => $rejected,
            ]
        ]);
    }

    /**
     * ADMIN SUGGESTED CANDIDATES
     */
    public function suggestedCandidates(Request $request, string $job_id)
    {
        $admin = $request->user();

        /*
|--------------------------------------------------------------------------
| AUTHORIZE
|--------------------------------------------------------------------------
*/

        if (!$admin->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        /*
|--------------------------------------------------------------------------
| FIND JOB
|--------------------------------------------------------------------------
*/

        $job = Job::where('job_id', $job_id)
            ->first();

        if (!$job) {
            return response()->json([
                'message' => 'Job not found'
            ], 404);
        }

        /*
|--------------------------------------------------------------------------
| FIND MATCHING USERS
|--------------------------------------------------------------------------
*/

        $jobTitle = strtolower($job->title);

        $keywords = collect(
            preg_split('/[\s,\-\/]+/', $jobTitle)
        )
            ->filter()
            ->unique()
            ->values();

        $users = User::query()
            ->where('role', 'user')

            ->where(function ($query) use ($keywords) {

                foreach ($keywords as $keyword) {

                    $query->orWhere(
                        'career_title',
                        'like',
                        "%{$keyword}%"
                    );
                }
            })

            ->latest('id')

            ->limit(20)

            ->get();

        /*
|--------------------------------------------------------------------------
| RESPONSE
|--------------------------------------------------------------------------
*/

        return response()->json([

            'job' => [

                'jobId' => $job->job_id,

                'title' => $job->title,
            ],

            'suggestedCandidates' => $users->map(function ($user) {

                return [

                    'userId' => $user->user_id,

                    'firstName' => $user->first_name,

                    'lastName' => $user->last_name,

                    'fullName' => $user->full_name,

                    'careerTitle' => $user->career_title,

                    'profileCompletion' => $user->profile_completion,
                ];
            }),
        ]);
    }

    /**
     * ADMIN DELETE JOB
     */
    public function destroyJob(Request $request, string $job_id)
    {
        $admin = $request->user();

        /*
|--------------------------------------------------------------------------
| AUTHORIZE
|--------------------------------------------------------------------------
*/

        if (!$admin->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        /*
|--------------------------------------------------------------------------
| FIND JOB
|--------------------------------------------------------------------------
*/

        $job = Job::where('job_id', $job_id)
            ->first();

        if (!$job) {
            return response()->json([
                'message' => 'Job not found'
            ], 404);
        }

        /*
|--------------------------------------------------------------------------
| DELETE JOB DATA
|--------------------------------------------------------------------------
*/

        DB::transaction(function () use ($job) {

            /*
    |--------------------------------------------------------------------------
    | DELETE APPLICATIONS
    |--------------------------------------------------------------------------
    */

            $job->applications()->delete();

            /*
    |--------------------------------------------------------------------------
    | DELETE SAVED JOBS
    |--------------------------------------------------------------------------
    */

            DB::table('saved_jobs')
                ->where('job_id', $job->id)
                ->delete();

            /*
    |--------------------------------------------------------------------------
    | DELETE JOB
    |--------------------------------------------------------------------------
    */

            $job->delete();
        });

        /*
|--------------------------------------------------------------------------
| RESPONSE
|--------------------------------------------------------------------------
*/

        return response()->json([
            'message' => 'Job deleted successfully'
        ]);
    }

    /**
     * ADMIN MANAGE APPLICATIONS
     */
    public function applications(Request $request)
    {
        $admin = $request->user();

        /*
|--------------------------------------------------------------------------
| AUTHORIZE
|--------------------------------------------------------------------------
*/

        if (!$admin->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        /*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

        $search = trim($request->query('search', ''));

        $status = trim($request->query('status', 'all'));

        /*
|--------------------------------------------------------------------------
| QUERY
|--------------------------------------------------------------------------
*/

        $query = Application::query()
            ->with([
                'user',
                'job.company'
            ]);

        /*
|--------------------------------------------------------------------------
| STATUS FILTER
|--------------------------------------------------------------------------
*/

        if ($status === 'applied') {

            $query->applied();
        }

        if ($status === 'shortlisted') {

            $query->shortlisted();
        }

        if ($status === 'accepted') {

            $query->accepted();
        }

        if ($status === 'rejected') {

            $query->rejected();
        }

        if ($status === 'pending') {

            $query->whereIn('status', [
                'applied',
                'shortlisted'
            ]);
        }

        if ($status === 'completed') {

            $query->whereIn('status', [
                'accepted',
                'rejected'
            ]);
        }

        /*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

        if ($search !== '') {

            $query->where(function ($q) use ($search) {

                $q->where('application_id', 'like', "%{$search}%")

                    ->orWhereHas('user', function ($userQuery) use ($search) {

                        $userQuery->where('user_id', 'like', "%{$search}%");
                    })

                    ->orWhereHas('job', function ($jobQuery) use ($search) {

                        $jobQuery->where('job_id', 'like', "%{$search}%")
                            ->orWhere('title', 'like', "%{$search}%");
                    })

                    ->orWhereHas('company', function ($companyQuery) use ($search) {

                        $companyQuery->where('company_id', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        /*
|--------------------------------------------------------------------------
| STATS
|--------------------------------------------------------------------------
*/

        $totalApplications = Application::count();

        $applied = Application::applied()->count();

        $shortlisted = Application::shortlisted()->count();

        $accepted = Application::accepted()->count();

        $rejected = Application::rejected()->count();

        $pending = $applied + $shortlisted;

        $completedApplications = $accepted + $rejected;

        $acceptanceRate = $totalApplications > 0
            ? round(($accepted / $totalApplications) * 100)
            : 0;

        $rejectionRate = $totalApplications > 0
            ? round(($rejected / $totalApplications) * 100)
            : 0;

        $updatedApplications = Application::query()
            ->where('status', '!=', 'applied')
            ->count();

        /*
|--------------------------------------------------------------------------
| RESPONSE
|--------------------------------------------------------------------------
*/

        $applications = $query
            ->latest('id')
            ->paginate(20);

        return response()->json([

            'applications' => $applications->through(function ($application) {

                return [

                    'applicationId' => $application->application_id,

                    'jobId' => $application->job?->job_id,

                    'jobTitle' => $application->job?->title,

                    'companyId' => $application->company?->company_id,

                    'companyName' => $application->company?->name,

                    'userId' => $application->user?->user_id,

                    'fullName' => $application->user?->full_name,

                    'status' => $application->status,

                    'isShortlisted' => (bool) $application->is_shortlisted,

                    'appliedAt' => $application->applied_at,

                    'shortlistedAt' => $application->shortlisted_at,

                    'acceptedAt' => $application->accepted_at,

                    'rejectedAt' => $application->rejected_at,

                    'updatedAt' => $application->updated_at,
                ];
            }),

            'stats' => [

                'totalApplications' => $totalApplications,

                'applied' => $applied,

                'shortlisted' => $shortlisted,

                'accepted' => $accepted,

                'rejected' => $rejected,

                'pending' => $pending,

                'completedApplications' => $completedApplications,

                'acceptanceRate' => $acceptanceRate,

                'rejectionRate' => $rejectionRate,

                'updatedApplications' => $updatedApplications,
            ],

            'filters' => [

                'search' => $search,

                'status' => $status,
            ]
        ]);
    }

    /**
     * ADMIN UPDATE APPLICATION STATUS
     */
    public function updateApplicationStatus(
        Request $request,
        string $application_id
    ) {
        $admin = $request->user();

        /*
|--------------------------------------------------------------------------
| AUTHORIZE
|--------------------------------------------------------------------------
*/

        if (!$admin->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        /*
|--------------------------------------------------------------------------
| VALIDATE
|--------------------------------------------------------------------------
*/

        $validated = $request->validate([

            'status' => [
                'required',
                'in:shortlisted,accepted,rejected'
            ]
        ]);

        /*
|--------------------------------------------------------------------------
| FIND APPLICATION
|--------------------------------------------------------------------------
*/

        $application = Application::with([
            'user',
            'job'
        ])
            ->where('application_id', $application_id)
            ->first();

        if (!$application) {
            return response()->json([
                'message' => 'Application not found'
            ], 404);
        }

        /*
|--------------------------------------------------------------------------
| UPDATE STATUS
|--------------------------------------------------------------------------
*/

        $status = $validated['status'];

        /*
|--------------------------------------------------------------------------
| PREVENT INVALID STATUS TRANSITIONS
|--------------------------------------------------------------------------
*/

        if ($application->isRejected()) {

            return response()->json([
                'message' => 'Rejected applications cannot be updated'
            ], 422);
        }

        if ($application->isAccepted()) {

            return response()->json([
                'message' => 'Accepted applications cannot be updated'
            ], 422);
        }

        /*
|--------------------------------------------------------------------------
| APPLIED -> ONLY SHORTLIST OR REJECT
|--------------------------------------------------------------------------
*/

        if ($application->isApplied()) {

            if (!in_array($status, [
                'shortlisted',
                'rejected'
            ])) {

                return response()->json([
                    'message' => 'Invalid status transition'
                ], 422);
            }
        }

        /*
|--------------------------------------------------------------------------
| SHORTLISTED -> ONLY ACCEPT OR REJECT
|--------------------------------------------------------------------------
*/

        if ($application->isShortlisted()) {

            if (!in_array($status, [
                'accepted',
                'rejected'
            ])) {

                return response()->json([
                    'message' => 'Invalid status transition'
                ], 422);
            }
        }

        $application->status = $status;

        /*
|--------------------------------------------------------------------------
| SHORTLIST
|--------------------------------------------------------------------------
*/

        if ($status === 'shortlisted') {

            $application->is_shortlisted = true;

            $application->shortlisted_at = now();
        }

        /*
|--------------------------------------------------------------------------
| ACCEPT
|--------------------------------------------------------------------------
*/

        if ($status === 'accepted') {

            $application->accepted_at = now();
        }

        /*
|--------------------------------------------------------------------------
| REJECT
|--------------------------------------------------------------------------
*/

        if ($status === 'rejected') {

            $application->rejected_at = now();
        }

        $application->save();

        /*
|--------------------------------------------------------------------------
| RESPONSE
|--------------------------------------------------------------------------
*/

        return response()->json([

            'message' => 'Application status updated successfully',

            'application' => [

                'applicationId' => $application->application_id,

                'status' => $application->status,

                'isShortlisted' => (bool) $application->is_shortlisted,

                'shortlistedAt' => $application->shortlisted_at,

                'acceptedAt' => $application->accepted_at,

                'rejectedAt' => $application->rejected_at,
            ]
        ]);
    }

    /**
     * ADMIN DELETE APPLICATION
     */
    public function destroyApplication(
        Request $request,
        string $application_id
    ) {
        $admin = $request->user();

        /*
|--------------------------------------------------------------------------
| AUTHORIZE
|--------------------------------------------------------------------------
*/

        if (!$admin->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        /*
|--------------------------------------------------------------------------
| FIND APPLICATION
|--------------------------------------------------------------------------
*/

        $application = Application::where(
            'application_id',
            $application_id
        )->first();

        if (!$application) {
            return response()->json([
                'message' => 'Application not found'
            ], 404);
        }

        /*
|--------------------------------------------------------------------------
| DELETE APPLICATION
|--------------------------------------------------------------------------
*/

        $application->delete();

        /*
|--------------------------------------------------------------------------
| RESPONSE
|--------------------------------------------------------------------------
*/

        return response()->json([
            'message' => 'Application deleted successfully'
        ]);
    }

    /**
     * ADMIN CV COLLECTION
     */
    public function cvCollection(Request $request)
    {
        $admin = $request->user();

        /*
|--------------------------------------------------------------------------
| AUTHORIZE
|--------------------------------------------------------------------------
*/

        if (!$admin->isAdmin()) {

            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        /*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

        $search = trim($request->query('search', ''));

        /*
|--------------------------------------------------------------------------
| QUERY
|--------------------------------------------------------------------------
*/

        $query = User::query()
            ->where('role', 'user');

        /*
|--------------------------------------------------------------------------
| SEARCH BY CAREER TITLE
|--------------------------------------------------------------------------
*/

        if ($search !== '') {

            $query->where('career_title', 'like', "%{$search}%");
        }

        /*
|--------------------------------------------------------------------------
| RESPONSE
|--------------------------------------------------------------------------
*/

        $users = $query
            ->latest('id')
            ->paginate(20);

        return response()->json([

            'users' => $users->through(function ($user) {

                return [

                    'userId' => $user->user_id,

                    'firstName' => $user->first_name,

                    'lastName' => $user->last_name,

                    'fullName' => $user->full_name,

                    'careerTitle' => $user->career_title,

                    'profileCompletion' => $user->profile_completion,

                    'createdAt' => $user->created_at,
                ];
            }),

            'filters' => [

                'search' => $search,
            ]
        ]);
    }

    public function viewCv(Request $request, string $user_id)
    {
        $admin = $request->user();

        if (!$admin->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $user = User::where('user_id', $user_id)
            ->where('role', 'user')
            ->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        $user->load([
            'educations',
            'experiences',
            'certificates',
            'links'
        ]);

        return new UserCvResource($user);
    }
}
