<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name'              => 'Sarah Support',
                'email'             => 'sarah@support.com',
                'password'          => 'password',
                'email_verified_at' => now(),
            ],
            [
                'name'              => 'James Support',
                'email'             => 'james@support.com',
                'password'          => 'password',
                'email_verified_at' => now(),
            ],
            [
                'name'              => 'Linda Support',
                'email'             => 'linda@support.com',
                'password'          => 'password',
                'email_verified_at' => now(),
            ],
        ];

        foreach ($users as $user) {
            User::firstOrCreate(
                ['email' => $user['email']],
                $user
            );
        }
    }
}
