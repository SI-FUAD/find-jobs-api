<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class AdminSeeder extends Seeder
{
    public function run()
    {
        User::updateOrCreate(
            ['email' => 'admin@findjobs.com'],
            [
                'user_id' => 'u_387254',
                'first_name' => 'Akbar',
                'last_name' => 'Ali',
                'password' => bcrypt('admin123'),
                'role' => 'admin'
            ]
        );
    }
}
