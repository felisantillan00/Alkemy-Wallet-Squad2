<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class AuthCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    # CON UN TOKEN VALIDO, /auth/check RESPONDE 200 (EL FRONTEND LO USA AL RECARGAR LA PAGINA)
    public function test_check_con_token_valido_devuelve_200(): void
    {
        $user = User::factory()->create();
        $token = JWTAuth::fromUser($user);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/auth/check');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonMissingPath('data.password');
    }

    # SIN TOKEN, /auth/check RESPONDE 401 EN JSON
    public function test_check_sin_token_devuelve_401(): void
    {
        $this->getJson('/api/v1/auth/check')
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    # CON UN TOKEN INVENTADO, /auth/check RESPONDE 401
    public function test_check_con_token_invalido_devuelve_401(): void
    {
        $this->withHeader('Authorization', 'Bearer token.inventado.xyz')
            ->getJson('/api/v1/auth/check')
            ->assertStatus(401);
    }
}
