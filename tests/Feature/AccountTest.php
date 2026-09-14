<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        # LOS USUARIOS REQUIEREN UN role_id EXISTENTE
        $this->seed(RoleSeeder::class);
    }

    # LA CONSULTA DE CUENTA DEVUELVE CBU, TIPO, MONEDA Y BALANCE
    public function test_la_consulta_de_cuenta_incluye_tipo_y_moneda(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'balance'  => 100.00,
            'type'     => 'checking',
            'currency' => 'USD',
        ]);

        $token = JWTAuth::fromUser($user);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/account');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'cbu'      => $account->cbu,
                    'type'     => 'checking',
                    'currency' => 'USD',
                    'balance'  => 100.00,
                ],
            ]);
    }

    # UNA CUENTA SIN type/currency EXPLICITOS (COMO LAS EXISTENTES ANTES DE LA MIGRACION)
    # RECIBE LOS VALORES PREDETERMINADOS: savings Y ARS
    public function test_una_cuenta_sin_type_ni_currency_explicitos_usa_los_defaults(): void
    {
        $user = User::factory()->create();

        # Insert directo, sin pasar por la factory, simulando una fila "vieja" anterior a la migración
        $accountId = \DB::table('accounts')->insertGetId([
            'user_id'    => $user->id,
            'cbu'        => Account::generarCbuUnico(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $account = Account::find($accountId);

        $this->assertEquals('savings', $account->type);
        $this->assertEquals('ARS', $account->currency);
    }

    # SIN TOKEN DEVUELVE 401
    public function test_sin_token_devuelve_401(): void
    {
        $this->getJson('/api/v1/account')->assertStatus(401);
    }
}
