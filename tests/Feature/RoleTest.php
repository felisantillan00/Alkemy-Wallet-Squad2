<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class RoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        # LOS USUARIOS REQUIEREN ROLES EXISTENTES (admin=1, user=2)
        $this->seed(RoleSeeder::class);
    }

    # EL REGISTRO PUBLICO SIEMPRE ASIGNA EL ROL "user", SIN IMPORTAR LO QUE ENVIE EL CLIENTE
    public function test_el_registro_asigna_el_rol_user_por_defecto(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name'                  => 'Usuario Nuevo',
            'email'                 => 'nuevo@example.test',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);

        $user = User::where('email', 'nuevo@example.test')->first();
        $this->assertEquals('user', $user->role->role_name);
    }

    # UN INTENTO DE AUTOASIGNARSE ADMIN EN EL REGISTRO SE IGNORA
    public function test_el_registro_ignora_un_intento_de_autoasignarse_admin(): void
    {
        $rolAdmin = Role::where('role_name', 'admin')->first();

        $response = $this->postJson('/api/v1/auth/register', [
            'name'                  => 'Usuario Malicioso',
            'email'                 => 'malicioso@example.test',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'role'                  => 'admin',
            'role_id'               => $rolAdmin->id,
            'is_admin'              => true,
        ]);

        $response->assertStatus(201);

        $user = User::where('email', 'malicioso@example.test')->first();
        $this->assertEquals('user', $user->role->role_name);
    }

    # EL SEEDER CREA UN ADMIN Y UN USUARIO COMUN, CON LOS ROLES CORRECTOS
    public function test_el_seeder_crea_un_admin_y_un_usuario_comun(): void
    {
        $this->seed(UserSeeder::class);

        $admin = User::where('email', 'admin@example.test')->first();
        $comun = User::where('email', 'testuser@example.test')->first();

        $this->assertNotNull($admin);
        $this->assertEquals('admin', $admin->role->role_name);

        $this->assertNotNull($comun);
        $this->assertEquals('user', $comun->role->role_name);
    }

    # UN ADMINISTRADOR AUTENTICADO PUEDE ACCEDER A UNA RUTA ADMINISTRATIVA
    public function test_un_admin_puede_acceder_a_la_ruta_administrativa(): void
    {
        $rolAdmin = Role::where('role_name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $rolAdmin->id]);
        $token = JWTAuth::fromUser($admin);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/admin/users');

        $response->assertStatus(200);
    }

    # UN USUARIO COMUN RECIBE 403 JSON AL INTENTAR ACCEDER A UNA RUTA ADMINISTRATIVA
    public function test_un_usuario_comun_recibe_403_json_en_ruta_administrativa(): void
    {
        $rolUser = Role::where('role_name', 'user')->first();
        $user = User::factory()->create(['role_id' => $rolUser->id]);
        $token = JWTAuth::fromUser($user);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/admin/users');

        $response->assertStatus(403)
            ->assertJson(['success' => false]);
    }

    # SIN TOKEN, LA RUTA ADMINISTRATIVA DEVUELVE 401 (LA AUTENTICACION SE VERIFICA ANTES DEL ROL)
    public function test_sin_token_la_ruta_administrativa_devuelve_401(): void
    {
        $response = $this->getJson('/api/v1/admin/users');

        $response->assertStatus(401);
    }
}
