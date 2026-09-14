<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Movement;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class DepositTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        # LOS USUARIOS REQUIEREN UN role_id EXISTENTE
        $this->seed(RoleSeeder::class);
    }

    # HELPER: crea un usuario con su cuenta y devuelve el usuario y el token
    protected function crearUsuarioConCuenta(float $balanceInicial = 0.00): array
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['balance' => $balanceInicial]);

        $token = JWTAuth::fromUser($user);

        return [$user, $account, $token];
    }

    # UN DEPOSITO VALIDO AUMENTA EL SALDO Y CREA UN MOVIMIENTO
    public function test_deposito_valido_aumenta_saldo_y_crea_movimiento(): void
    {
        [$user, $account, $token] = $this->crearUsuarioConCuenta(100.00);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/deposits', ['amount' => 50.00]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => ['balance' => '150.00'],
            ]);

        $this->assertDatabaseHas('accounts', [
            'id'      => $account->id,
            'balance' => 150.00,
        ]);

        $this->assertDatabaseHas('movements', [
            'account_id' => $account->id,
            'type'       => 'deposit',
            'amount'     => 50.00,
        ]);

        $this->assertEquals(1, Movement::where('account_id', $account->id)->count());
    }

    # UN MONTO INVALIDO (<=0) DEVUELVE 422 Y NO CAMBIA NADA
    public function test_monto_invalido_no_modifica_saldo_ni_crea_movimientos(): void
    {
        [$user, $account, $token] = $this->crearUsuarioConCuenta(100.00);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/deposits', ['amount' => 0]);

        $response->assertStatus(422);

        $this->assertDatabaseHas('accounts', [
            'id'      => $account->id,
            'balance' => 100.00,
        ]);

        $this->assertEquals(0, Movement::where('account_id', $account->id)->count());
    }

    # SIN AMOUNT DEVUELVE 422
    public function test_amount_faltante_devuelve_422(): void
    {
        [$user, $account, $token] = $this->crearUsuarioConCuenta();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/deposits', []);

        $response->assertStatus(422);
    }

    # SIN TOKEN DEVUELVE 401
    public function test_sin_token_devuelve_401(): void
    {
        $response = $this->postJson('/api/v1/deposits', ['amount' => 50.00]);

        $response->assertStatus(401);
    }

    # ENVIAR account_id O user_id NO PERMITE DEPOSITAR EN OTRA CUENTA
    public function test_no_permite_elegir_otra_cuenta(): void
    {
        [$user, $account, $token] = $this->crearUsuarioConCuenta(100.00);
        [, $otraCuenta] = $this->crearUsuarioConCuenta(100.00);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/deposits', [
                'amount'     => 50.00,
                'account_id' => $otraCuenta->id,
                'user_id'    => 9999,
            ]);

        $response->assertStatus(201);

        # La cuenta propia aumenta...
        $this->assertDatabaseHas('accounts', [
            'id'      => $account->id,
            'balance' => 150.00,
        ]);

        # ...y la otra cuenta queda intacta
        $this->assertDatabaseHas('accounts', [
            'id'      => $otraCuenta->id,
            'balance' => 100.00,
        ]);
    }
}