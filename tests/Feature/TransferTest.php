<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Movement;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class TransferTest extends TestCase
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

    # UNA TRANSFERENCIA VALIDA DESCUENTA EL ORIGEN, ACREDITA EL DESTINO Y CREA AMBOS MOVIMIENTOS
    public function test_transferencia_valida_mueve_el_saldo_y_crea_movimientos(): void
    {
        [, $origen, $token] = $this->crearUsuarioConCuenta(100.00);
        [, $destino] = $this->crearUsuarioConCuenta(20.00);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/transfers', [
                'destination_cbu' => $destino->cbu,
                'amount'          => 30.00,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => ['balance' => '70.00'],
            ]);

        $this->assertDatabaseHas('accounts', [
            'id'      => $origen->id,
            'balance' => 70.00,
        ]);

        $this->assertDatabaseHas('accounts', [
            'id'      => $destino->id,
            'balance' => 50.00,
        ]);

        $this->assertDatabaseHas('movements', [
            'account_id'      => $origen->id,
            'type'            => 'transfer_out',
            'amount'          => 30.00,
            'counterpart_cbu' => $destino->cbu,
        ]);

        $this->assertDatabaseHas('movements', [
            'account_id'      => $destino->id,
            'type'            => 'transfer_in',
            'amount'          => 30.00,
            'counterpart_cbu' => $origen->cbu,
        ]);

        $this->assertEquals(1, Movement::where('account_id', $origen->id)->count());
        $this->assertEquals(1, Movement::where('account_id', $destino->id)->count());
    }

    # SALDO INSUFICIENTE DEVUELVE 422 Y NO MODIFICA NINGUN SALDO NI MOVIMIENTO
    public function test_saldo_insuficiente_no_modifica_nada(): void
    {
        [, $origen, $token] = $this->crearUsuarioConCuenta(10.00);
        [, $destino] = $this->crearUsuarioConCuenta(20.00);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/transfers', [
                'destination_cbu' => $destino->cbu,
                'amount'          => 50.00,
            ]);

        $response->assertStatus(422);

        $this->assertDatabaseHas('accounts', ['id' => $origen->id, 'balance' => 10.00]);
        $this->assertDatabaseHas('accounts', ['id' => $destino->id, 'balance' => 20.00]);
        $this->assertEquals(0, Movement::count());
    }

    # UN CBU DE DESTINO INEXISTENTE DEVUELVE 422
    public function test_cbu_destino_inexistente_devuelve_422(): void
    {
        [, , $token] = $this->crearUsuarioConCuenta(100.00);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/transfers', [
                'destination_cbu' => '0000000000000000000000',
                'amount'          => 10.00,
            ]);

        $response->assertStatus(422);
        $this->assertEquals(0, Movement::count());
    }

    # NO SE PUEDE TRANSFERIR AL PROPIO CBU
    public function test_no_permite_transferir_a_la_propia_cuenta(): void
    {
        [, $origen, $token] = $this->crearUsuarioConCuenta(100.00);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/transfers', [
                'destination_cbu' => $origen->cbu,
                'amount'          => 10.00,
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('accounts', ['id' => $origen->id, 'balance' => 100.00]);
        $this->assertEquals(0, Movement::count());
    }

    # MONTO INVALIDO (<=0) DEVUELVE 422
    public function test_monto_invalido_devuelve_422(): void
    {
        [, , $token] = $this->crearUsuarioConCuenta(100.00);
        [, $destino] = $this->crearUsuarioConCuenta(20.00);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/transfers', [
                'destination_cbu' => $destino->cbu,
                'amount'          => 0,
            ]);

        $response->assertStatus(422);
        $this->assertEquals(0, Movement::count());
    }

    # SIN TOKEN DEVUELVE 401
    public function test_sin_token_devuelve_401(): void
    {
        [, $destino] = $this->crearUsuarioConCuenta(20.00);

        $response = $this->postJson('/api/v1/transfers', [
            'destination_cbu' => $destino->cbu,
            'amount'          => 10.00,
        ]);

        $response->assertStatus(401);
    }

    # user_id / account_id ENVIADOS NO AFECTAN EL ORIGEN DE LA TRANSFERENCIA
    public function test_no_permite_elegir_otra_cuenta_de_origen(): void
    {
        [, $origen, $token] = $this->crearUsuarioConCuenta(100.00);
        [, $otraCuenta] = $this->crearUsuarioConCuenta(100.00);
        [, $destino] = $this->crearUsuarioConCuenta(20.00);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/transfers', [
                'destination_cbu' => $destino->cbu,
                'amount'          => 30.00,
                'account_id'      => $otraCuenta->id,
                'user_id'         => 9999,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('accounts', ['id' => $origen->id, 'balance' => 70.00]);
        $this->assertDatabaseHas('accounts', ['id' => $otraCuenta->id, 'balance' => 100.00]);
    }
}
