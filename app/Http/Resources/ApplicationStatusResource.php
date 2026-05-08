<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [

            // Application
            'applicationId' => $this->application_id,

            'status' => ucfirst($this->status),

            'isShortlisted' => (bool) $this->is_shortlisted,

            'dateApplied' => $this->applied_at,

            'dateUpdated' => $this->updated_at,

            // Job
            'job' => [
                'jobId' => $this->job->job_id,

                'title' => $this->job->title,

                'location' => $this->job->location,

                'experience' => $this->job->experience,

                'salary' => $this->job->salary,

                'vacancy' => $this->job->vacancy,

                'description' => str($this->job->description)
                    ->limit(140),

                'deadline' => $this->job->deadline,

                'isExpired' => now()->gt($this->job->deadline),
            ],

            // Company
            'company' => [
                'companyId' => $this->job->company->company_id,

                'brandName' => $this->job->company->name,

                'brandColor' => $this->job->company->logo_color,
            ],
        ];
    }
}
