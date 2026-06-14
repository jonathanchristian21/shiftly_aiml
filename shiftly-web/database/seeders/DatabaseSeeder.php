<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create manager user
        User::firstOrCreate(
            ['email' => 'manager@shiftly.com'],
            [
                'name' => 'Manager',
                'password' => Hash::make('password'),
                'role' => 'manager',
                'employee_id' => null,
            ]
        );

        // Employee users will be created automatically during CSV import
        // Departments will be created automatically during CSV import
    }
}
