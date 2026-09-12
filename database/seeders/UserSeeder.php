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
        # USUARIO NORMAL 
        User::factory()->create([
            'name' => 'TestUser',
            'email' => 'testuser@gmail.com',
            'password' => Hash::make('password123'),
            'role_id' => 2,
        ]);

        # USUARIO ADMINISTRADOR
        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@gmail.com',
            'password' => Hash::make('administrador'),
            'role_id' => 1,
        ]);
    }
}
