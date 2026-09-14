<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        # CUENTA DEL ADMINISTRADOR
        Account::factory()->create([
            'user_id' => 2,
        ]);

         # CUENTA DEL USER TEST
        Account::factory()->create([
            'user_id' => 1,
        ]);
    }
}
