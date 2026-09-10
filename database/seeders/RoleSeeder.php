<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        # ROL ADMIN
        Role::factory()->create([
            'role_name' => 'admin',
            'role_description' => 'Role for unrestricted administrators'
        ]);

         # ROL USER
        Role::factory()->create([
            'role_name' => 'user',
            'role_description' => 'Limited Access User',
        ]);
    }
}
