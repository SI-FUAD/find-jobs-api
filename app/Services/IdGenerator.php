<?php

namespace App\Services;

class IdGenerator
{
    public static function generate(string $model, string $column, string $prefix, int $length): string
    {
        do {
            $number = '';

            for ($i = 0; $i < $length; $i++) {
                $number .= rand(0, 9);
            }

            $id = $prefix . $number;
        } while ($model::where($column, $id)->exists());

        return $id;
    }
}
