<?php

namespace App\Services;

class AvatarService
{
    private static $colors = [
        "#2563eb",
        "#4f46e5",
        "#7c3aed",
        "#059669",
        "#0d9488",
        "#ea580c",
        "#dc2626",
    ];

    public static function generateColor(string $text): string
    {
        $hash = 0;

        for ($i = 0; $i < strlen($text); $i++) {
            $hash += ord($text[$i]);
        }

        return self::$colors[$hash % count(self::$colors)];
    }

    public static function generateUserAvatar(string $firstName, string $lastName): array
    {
        $initials =
            strtoupper(substr($firstName, 0, 1)) .
            strtoupper(substr($lastName, 0, 1));

        return [
            'text' => $initials,
            'color' => self::generateColor($initials),
        ];
    }

    public static function generateCompanyAvatar(string $name): string
    {
        return self::generateColor($name);
    }
}
