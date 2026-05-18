<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Helpers\AuthHelper;

class JobViewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $auth = $request->user();

        $isUser = AuthHelper::authorize($auth, 'user');

        $savedJobIds = $request->attributes->get('savedJobIds', []);

        $appliedJobIds = $request->attributes->get('appliedJobIds', []);

        return [

            /*
            |--------------------------------------------------------------------------
            | JOB
            |--------------------------------------------------------------------------
            */

            'jobId' => $this->job_id,

            'title' => $this->title,

            'location' => $this->location,

            'level' => $this->level,

            'experience' => $this->experience,

            'salary' => $this->salary,

            'vacancy' => $this->vacancy,

            'description' => $this->description,

            'datePosted' => $this->date_posted,

            'deadline' => $this->deadline,

            'isExpired' => now()->gt($this->deadline),

            /*
            |--------------------------------------------------------------------------
            | COMPANY
            |--------------------------------------------------------------------------
            */

            'company' => [

                'companyId' => $this->company->company_id,

                'name' => $this->company->name,

                'brandColor' => $this->company->logo_color,
            ],

            /*
            |--------------------------------------------------------------------------
            | AUTH
            |--------------------------------------------------------------------------
            */

            'auth' => [

                'showActions' => !$auth || $isUser,

                'canInteract' => $isUser,

                'isSaved' => in_array($this->id, $savedJobIds),

                'hasApplied' => in_array($this->id, $appliedJobIds),
            ],
        ];
    }
}
