<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        $isUser = $user && $user->role === 'user';

        return [

            // IDs
            'jobId' => $this->job_id,

            // Job
            'title' => $this->title,
            'location' => $this->location,
            'level' => $this->level,
            'experience' => $this->experience,
            'salary' => $this->salary,
            'vacancy' => $this->vacancy,

            // Short card description
            'description' => str($this->description)
                ->limit(140),

            // Dates
            'datePosted' => $this->date_posted,
            'deadline' => $this->deadline,

            // Status
            'isExpired' => now()->gt($this->deadline),

            // Company
            'companyId' => $this->company->company_id,
            'companyName' => $this->company->name,
            'brandColor' => $this->company->logo_color,

            // Auth state
            'auth' => [
                'canInteract' => $isUser,

                'isSaved' => $isUser
                    ? $user->savedJobs()
                    ->where('jobs.id', $this->id)
                    ->exists()
                    : false,

                'hasApplied' => $isUser
                    ? $this->applications()
                    ->where('user_id', $user->id)
                    ->exists()
                    : false,
            ],
        ];
    }
}
