<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // System Admin
        User::firstOrCreate(
            ['email' => 'admin@taalom.com'],
            [
                'name'              => 'System Admin',
                'password'          => Hash::make('Admin@12345'),
                'role'              => UserRole::ADMIN,
                'status'            => UserStatus::ACTIVE,
                'email_verified_at' => now(),
            ],
        );

        // Demo Teacher
        User::firstOrCreate(
            ['email' => 'teacher@taalom.com'],
            [
                'name'              => 'Demo Teacher',
                'password'          => Hash::make('Teacher@12345'),
                'role'              => UserRole::TEACHER,
                'status'            => UserStatus::ACTIVE,
                'email_verified_at' => now(),
            ],
        );

        // Demo Student
        User::firstOrCreate(
            ['email' => 'student@taalom.com'],
            [
                'name'              => 'Demo Student',
                'password'          => Hash::make('Student@12345'),
                'role'              => UserRole::STUDENT,
                'status'            => UserStatus::ACTIVE,
                'email_verified_at' => now(),
            ],
        );
    }
}
