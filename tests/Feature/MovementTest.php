<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Movement;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class MovementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        # LOS USUARIOS REQUIEREN UN role_id EXISTENTE
        $this->seed(RoleSeeder::class);
    }

    # HELPER: crea un usuario con su cuenta y devuelve el usuario, la cuenta y el token
    protected function crearUsuarioConCuenta(float $balanceInicial = 0.00): array
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['balance' => $balanceInicial]);

        $token = JWTAuth::fromUser($user);

        return [$user, $account, $token];
    }

    # SOLO DEVUELVE LOS MOVIMIENTOS DE LA CUENTA AUTENTICADA
    public function test_solo_devuelve_movimientos_de_la_cuenta_propia(): void
    {
        [, $cuenta, $token] = $this->crearUsuarioConCuenta();
        [, $otraCuenta] = $this->crearUsuarioConCuenta();

        Movement::factory()->for($cuenta, 'account')->create(['type' => 'deposit', 'amount' => 50]);
        Movement::factory()->for($otraCuenta, 'account')->create(['type' => 'deposit', 'amount' => 999]);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/movements');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.amount', '50.00');
    }

    # CADA ELEMENTO INCLUYE TIPO, MONTO, FECHA Y CBU CONTRAPARTE
    public function test_cada_elemento_incluye_los_campos_esperados(): void
    {
        [, $cuenta, $token] = $this->crearUsuarioConCuenta();
        [, $destino] = $this->crearUsuarioConCuenta();

        Movement::factory()->for($cuenta, 'account')->create([
            'type'            => 'transfer_out',
            'amount'          => 30,
            'counterpart_cbu' => $destino->cbu,
        ]);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/movements');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.type', 'transfer_out')
            ->assertJsonPath('data.0.amount', '30.00')
            ->assertJsonPath('data.0.counterpart_cbu', $destino->cbu)
            ->assertJsonStructure(['data' => [['type', 'amount', 'date', 'counterpart_cbu']]]);
    }

    # LAS OPERACIONES RECHAZADAS (422) NO DEJAN MOVIMIENTOS Y NO APARECEN EN EL LISTADO
    public function test_operaciones_rechazadas_no_aparecen_en_el_listado(): void
    {
        [, $cuenta, $token] = $this->crearUsuarioConCuenta(10.00);

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/deposits', ['amount' => 0])
            ->assertStatus(422);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/movements');

        $response->assertStatus(200)->assertJsonCount(0, 'data');
    }

    # LA RESPUESTA ESTA PAGINADA CON LA ESTRUCTURA NATIVA DE LARAVEL, 15 POR DEFECTO
    public function test_respuesta_paginada_con_15_por_defecto(): void
    {
        [, $cuenta, $token] = $this->crearUsuarioConCuenta();

        Movement::factory()->for($cuenta, 'account')->count(20)->create();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/movements');

        $response->assertStatus(200)
            ->assertJsonCount(15, 'data')
            ->assertJsonStructure(['data', 'links', 'current_page', 'last_page', 'per_page', 'total'])
            ->assertJsonPath('total', 20)
            ->assertJsonPath('per_page', 15);
    }

    # PERMITE ELEGIR CANTIDAD POR PAGINA, HASTA UN MAXIMO DE 100
    public function test_per_page_respeta_el_valor_solicitado_y_el_maximo(): void
    {
        [, $cuenta, $token] = $this->crearUsuarioConCuenta();

        Movement::factory()->for($cuenta, 'account')->count(10)->create();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/movements?per_page=5');

        $response->assertStatus(200)->assertJsonCount(5, 'data');

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/movements?per_page=101')
            ->assertStatus(422);
    }

    # POR DEFECTO ORDENA DESCENDENTE (MAS RECIENTE PRIMERO) Y PERMITE ASCENDENTE
    public function test_ordena_por_fecha_descendente_por_defecto_y_permite_ascendente(): void
    {
        [, $cuenta, $token] = $this->crearUsuarioConCuenta();

        $viejo = Movement::factory()->for($cuenta, 'account')->create([
            'amount'     => 1,
            'created_at' => now()->subDays(2),
        ]);
        $nuevo = Movement::factory()->for($cuenta, 'account')->create([
            'amount'     => 2,
            'created_at' => now(),
        ]);

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/movements')
            ->assertJsonPath('data.0.amount', '2.00');

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/movements?order=asc')
            ->assertJsonPath('data.0.amount', '1.00');
    }

    # PAGINA Y ORDEN INVALIDOS DEVUELVEN 422
    public function test_pagina_y_orden_invalidos_devuelven_422(): void
    {
        [, , $token] = $this->crearUsuarioConCuenta();

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/movements?page=0')
            ->assertStatus(422);

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/movements?order=invalido')
            ->assertStatus(422);
    }

    # SIN TOKEN DEVUELVE 401
    public function test_sin_token_devuelve_401(): void
    {
        $this->getJson('/api/v1/movements')->assertStatus(401);
    }
}
