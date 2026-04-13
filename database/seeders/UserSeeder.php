<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // ======================
        // ADMIN
        // ======================
        User::updateOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]
        );

        // ======================
        // AUDITOR
        // ======================
        User::updateOrCreate(
            ['email' => 'auditor@gmail.com'],
            [
                'name' => 'Auditor',
                'password' => Hash::make('password'),
                'role' => 'auditor',
            ]
        );

        // ======================
        // ISSUER
        // ======================
        User::updateOrCreate(
            ['email' => 'issuer@gmail.com'],
            [
                'name' => 'Issuer',
                'password' => Hash::make('password'),
                'role' => 'issuer',
            ]
        );
    }
}