<?php

namespace App\Helpers;

use App\Models\User;
use App\Models\Company;

class AuthHelper
{
    /*
    |--------------------------------------------------------------------------
    | Get Auth Type
    |--------------------------------------------------------------------------
    */

    public static function getAuthType(mixed $auth): ?string
    {
        // Logged out
        if (!$auth) {
            return null;
        }

        // User
        if ($auth instanceof User && $auth->role === 'user') {
            return 'user';
        }

        // Admin
        if ($auth instanceof User && $auth->role === 'admin') {
            return 'admin';
        }

        // Company
        if ($auth instanceof Company) {
            return 'company';
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Authorize
    |--------------------------------------------------------------------------
    */

    public static function authorize(mixed $auth, string $role): bool
    {
        return self::getAuthType($auth) === $role;
    }
}
