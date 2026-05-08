<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserCvResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'fullName' => trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? '')),

            'email' => $this->email ?? '',
            'phone' => $this->phone ?? '',

            'currentAddress' => $this->current_address ?? '',
            'permanentAddress' => $this->permanent_address ?? '',

            // Profile
            'careerTitle' => $this->career_title ?: 'Professional',
            'careerSummary' => $this->career_summary ?: '',

            // Personal Info
            'fatherName' => $this->father_name ?? '',
            'motherName' => $this->mother_name ?? '',
            'gender' => $this->gender ?? '',
            'maritalStatus' => $this->marital_status ?? '',
            'emergencyPhone' => $this->emergency_phone ?? '',

            // Avatar
            'avatar' => [
                'text' => $this->avatar_text ?? '',
                'color' => $this->avatar_color ?? '#2563eb',
            ],

            // ======================
            // CV RELATIONS (ARRAYS)
            // ======================

            'educations' => $this->educations->map(function ($e) {
                return [
                    'level' => $e->level ?? '',
                    'institute' => $e->institute ?? '',
                    'year' => $e->year ?? '',
                    'result' => $e->result ?? '',
                ];
            }),

            'experiences' => $this->experiences->map(function ($ex) {
                return [
                    'role' => $ex->role ?? '',
                    'company' => $ex->company ?? '',
                    'duration' => $ex->duration ?? '',
                    'skills' => $ex->skills ?? '',
                ];
            }),

            'certificates' => $this->certificates->map(function ($c) {
                return [
                    'name' => $c->name ?? '',
                    'organization' => $c->organization ?? '',
                    'year' => $c->year ?? '',
                ];
            }),

            'links' => $this->links->map(function ($l) {
                return [
                    'label' => $l->label ?? '',
                    'url' => $l->url ?? '',
                ];
            }),
        ];
    }
}
