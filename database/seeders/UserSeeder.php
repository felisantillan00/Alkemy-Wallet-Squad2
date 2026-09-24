<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        # USUARIO NORMAL DE PRUEBA (no tiene privilegios, la contraseña fija no es un riesgo)
        User::factory()->create([
            'name' => 'TestUser',
            'email' => 'testuser@example.test',
            'password' => Hash::make('password123'),
            'role_id' => 2,
        ]);

        # USUARIO ADMINISTRADOR DE PRUEBA
        # La contraseña NO se hardcodea: sale de ADMIN_SEED_PASSWORD (.env, no versionado) o,
        # si no está seteada, se genera al azar y se muestra una sola vez por consola.
        $passwordAdmin = env('ADMIN_SEED_PASSWORD') ?: Str::random(16);

        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => Hash::make($passwordAdmin),
            'role_id' => 1,
        ]);

        if (! env('ADMIN_SEED_PASSWORD')) {
            $this->command?->warn("Admin de prueba (admin@example.test) creado con contraseña generada: {$passwordAdmin}");
            $this->command?->warn('Guardala ahora: no se vuelve a mostrar. Para fijarla, definí ADMIN_SEED_PASSWORD en tu .env.');
        }
    }
}
