<?php

namespace App\Services;

use App\Models\User;

class ProfileCompletionService
{
    public static function calculate(User $user): int
    {
        $filled = 0;

        // ===== BASIC (13) =====
        $baseFields = [
            $user->email,
            $user->first_name,
            $user->last_name,
            $user->phone,
            $user->emergency_phone,
            $user->gender,
            $user->marital_status,
            $user->father_name,
            $user->mother_name,
            $user->current_address,
            $user->permanent_address,
            $user->career_title,
            $user->career_summary,
        ];

        foreach ($baseFields as $field) {
            if (!empty($field)) $filled++;
        }

        // ===== EDUCATION (max 3 × 4 = 12) =====
        $educations = $user->educations->take(3);

        foreach ($educations as $edu) {
            foreach (['level', 'institute', 'result', 'year'] as $field) {
                if (!empty($edu->$field)) $filled++;
            }
        }

        // ===== EXPERIENCE (1 × 4) =====
        $exp = $user->experiences->first();

        if ($exp) {
            foreach (['company', 'role', 'duration', 'skills'] as $field) {
                if (!empty($exp->$field)) $filled++;
            }
        }

        // ===== LINKS (2 × 2 = 4) =====
        $links = $user->links->take(2);

        foreach ($links as $link) {
            foreach (['label', 'url'] as $field) {
                if (!empty($link->$field)) $filled++;
            }
        }

        $TOTAL = 33;

        $percent = floor(($filled / $TOTAL) * 100 / 5) * 5;

        return min($percent, 100);
    }
}
