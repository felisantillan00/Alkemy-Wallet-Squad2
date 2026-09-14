<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Movement;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class FixedTermInvestmentTest extends TestCase
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

    # UNA SIMULACION CON term_days USA INTERES SIMPLE, TNA 30% Y BASE 365
    public function test_simula_con_plazo_en_dias(): void
    {
        [, , $token] = $this->crearUsuarioConCuenta();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/investments/fixed-term/simulate', [
                'amount'    => 1000,
                'term_days' => 365,
            ]);

        # interes = 1000 * 0.30 * (365/365) = 300.00 ; total = 1300.00
        $response->assertStatus(200)
            ->assertJsonPath('data.plazo_dias', 365)
            ->assertJsonPath('data.monto_invertido', '1000.00')
            ->assertJsonPath('data.interes_ganado', '300.00')
            ->assertJsonPath('data.total_a_cobrar', '1300.00')
            ->assertJsonPath('data.tasa.tna', 0.30)
            ->assertJsonStructure(['data' => ['fecha_creacion', 'fecha_finalizacion', 'tasa' => ['tna', 'formula', 'nota']]]);
    }

    # UNA SIMULACION CON end_date CALCULA EL PLAZO EN DIAS EQUIVALENTE
    public function test_simula_con_fecha_de_finalizacion(): void
    {
        [, , $token] = $this->crearUsuarioConCuenta();

        $fecha = now()->addDays(30)->toDateString();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/investments/fixed-term/simulate', [
                'amount'   => 1000,
                'end_date' => $fecha,
            ]);

        # interes = 1000 * 0.30 * (30/365) = 24.657... -> 24.66 ; total = 1024.66
        $response->assertStatus(200)
            ->assertJsonPath('data.plazo_dias', 30)
            ->assertJsonPath('data.fecha_finalizacion', $fecha)
            ->assertJsonPath('data.interes_ganado', '24.66')
            ->assertJsonPath('data.total_a_cobrar', '1024.66');
    }

    # NO SE PUEDE ENVIAR term_days Y end_date AL MISMO TIEMPO
    public function test_no_permite_enviar_plazo_y_fecha_juntos(): void
    {
        [, , $token] = $this->crearUsuarioConCuenta();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/investments/fixed-term/simulate', [
                'amount'    => 1000,
                'term_days' => 60,
                'end_date'  => now()->addDays(60)->toDateString(),
            ]);

        $response->assertStatus(422);
    }

    # HAY QUE ENVIAR term_days O end_date
    public function test_requiere_plazo_o_fecha(): void
    {
        [, , $token] = $this->crearUsuarioConCuenta();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/investments/fixed-term/simulate', ['amount' => 1000]);

        $response->assertStatus(422);
    }

    # UN PLAZO MENOR A 30 DIAS DEVUELVE 422
    public function test_plazo_menor_al_minimo_devuelve_422(): void
    {
        [, , $token] = $this->crearUsuarioConCuenta();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/investments/fixed-term/simulate', [
                'amount'    => 1000,
                'term_days' => 29,
            ]);

        $response->assertStatus(422);
    }

    # UN PLAZO MAYOR A 365 DIAS DEVUELVE 422
    public function test_plazo_mayor_al_maximo_devuelve_422(): void
    {
        [, , $token] = $this->crearUsuarioConCuenta();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/investments/fixed-term/simulate', [
                'amount'    => 1000,
                'term_days' => 366,
            ]);

        $response->assertStatus(422);
    }

    # UN MONTO INVALIDO (<=0) DEVUELVE 422
    public function test_monto_invalido_devuelve_422(): void
    {
        [, , $token] = $this->crearUsuarioConCuenta();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/investments/fixed-term/simulate', [
                'amount'    => 0,
                'term_days' => 90,
            ]);

        $response->assertStatus(422);
    }

    # LA TASA NO PUEDE ELEGIRSE DESDE EL CLIENTE: SE IGNORA CUALQUIER VALOR ENVIADO
    public function test_la_tasa_enviada_por_el_cliente_se_ignora(): void
    {
        [, , $token] = $this->crearUsuarioConCuenta();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/investments/fixed-term/simulate', [
                'amount'    => 1000,
                'term_days' => 365,
                'tna'       => 0.99,
            ]);

        $response->assertStatus(200)->assertJsonPath('data.tasa.tna', 0.30);
    }

    # LA SIMULACION NO CAMBIA EL SALDO NI CREA MOVIMIENTOS
    public function test_la_simulacion_no_modifica_saldo_ni_crea_movimientos(): void
    {
        [, $cuenta, $token] = $this->crearUsuarioConCuenta(500.00);

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/investments/fixed-term/simulate', [
                'amount'    => 1000,
                'term_days' => 180,
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('accounts', ['id' => $cuenta->id, 'balance' => 500.00]);
        $this->assertEquals(0, Movement::where('account_id', $cuenta->id)->count());
    }

    # SIN TOKEN DEVUELVE 401
    public function test_sin_token_devuelve_401(): void
    {
        $response = $this->postJson('/api/v1/investments/fixed-term/simulate', [
            'amount'    => 1000,
            'term_days' => 90,
        ]);

        $response->assertStatus(401);
    }
}
