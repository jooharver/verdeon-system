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
                'wallet_address' => '0xE4134309b6713bB8E9CcEe00C5bA8F0A455e47e8',
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
                'wallet_address' => '0x09d9239faC77dC9AA0cb5aA0f2Ff605602D3f3A7',
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
                'wallet_address' => '0x70C1E7D12328b01f2E611d572294Ca9518d9CD73',
            ]
        );
    }
}