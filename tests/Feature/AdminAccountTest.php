<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Movement;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class AdminAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        # LOS USUARIOS REQUIEREN UN role_id EXISTENTE
        $this->seed(RoleSeeder::class);
    }

    # HELPER: CREA UN ADMIN AUTENTICADO Y DEVUELVE SU TOKEN
    private function tokenAdmin(): string
    {
        $rolAdmin = Role::where('role_name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $rolAdmin->id]);

        return JWTAuth::fromUser($admin);
    }

    # HELPER: CREA UN USUARIO COMUN AUTENTICADO Y DEVUELVE SU TOKEN
    private function tokenUsuarioComun(): string
    {
        $rolUser = Role::where('role_name', 'user')->first();
        $user = User::factory()->create(['role_id' => $rolUser->id]);

        return JWTAuth::fromUser($user);
    }

    # ---------- INDEX ----------

    # EL LISTADO ESTA PAGINADO Y DEVUELVE LAS CUENTAS CON SU USUARIO
    public function test_index_devuelve_cuentas_paginadas_con_su_usuario(): void
    {
        $token = $this->tokenAdmin();

        $usuario = User::factory()->create();
        Account::factory()->for($usuario)->create([
            'type'     => 'checking',
            'currency' => 'USD',
        ]);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/admin/accounts');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'current_page',
                'data' => [
                    '*' => ['id', 'cbu', 'type', 'currency', 'balance', 'user' => ['id', 'name', 'email']],
                ],
                'per_page',
                'total',
            ]);
    }

    # UN USUARIO COMUN NO PUEDE LISTAR CUENTAS (403)
    public function test_index_rechaza_a_un_usuario_comun(): void
    {
        $token = $this->tokenUsuarioComun();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/admin/accounts');

        $response->assertStatus(403);
    }

    # SIN TOKEN, EL LISTADO DEVUELVE 401
    public function test_index_sin_token_devuelve_401(): void
    {
        $this->getJson('/api/v1/admin/accounts')->assertStatus(401);
    }

    # ---------- SHOW ----------

    # EL DETALLE DEVUELVE LOS DATOS COMPLETOS DE LA CUENTA
    public function test_show_devuelve_el_detalle_de_una_cuenta(): void
    {
        $token = $this->tokenAdmin();

        $usuario = User::factory()->create();
        $account = Account::factory()->for($usuario)->create([
            'type'     => 'savings',
            'currency' => 'ARS',
            'balance'  => 250.50,
        ]);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson("/api/v1/admin/accounts/{$account->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id'       => $account->id,
                    'cbu'      => $account->cbu,
                    'type'     => 'savings',
                    'currency' => 'ARS',
                    'balance'  => 250.50,
                    'user' => [
                        'id'    => $usuario->id,
                        'name'  => $usuario->name,
                        'email' => $usuario->email,
                    ],
                ],
            ]);
    }

    # ---------- STORE ----------

    # CREA UNA CUENTA PARA UN USUARIO QUE TODAVIA NO TIENE UNA, CON BALANCE 0.00 Y CBU GENERADO
    public function test_store_crea_una_cuenta_para_un_usuario_sin_cuenta(): void
    {
        $token = $this->tokenAdmin();
        $usuario = User::factory()->create();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/admin/accounts', [
                'user_id'  => $usuario->id,
                'type'     => 'checking',
                'currency' => 'USD',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'type'     => 'checking',
                    'currency' => 'USD',
                    'balance'  => 0.00,
                    'user' => ['id' => $usuario->id],
                ],
            ]);

        $this->assertDatabaseHas('accounts', [
            'user_id'  => $usuario->id,
            'type'     => 'checking',
            'currency' => 'USD',
            'balance'  => 0.00,
        ]);
    }

    # NO PERMITE CREAR UNA SEGUNDA CUENTA PARA UN USUARIO QUE YA TIENE UNA
    public function test_store_rechaza_un_usuario_que_ya_tiene_cuenta(): void
    {
        $token = $this->tokenAdmin();
        $usuario = User::factory()->create();
        Account::factory()->for($usuario)->create();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/admin/accounts', [
                'user_id'  => $usuario->id,
                'type'     => 'savings',
                'currency' => 'ARS',
            ]);

        $response->assertStatus(422);
        $this->assertEquals(1, Account::where('user_id', $usuario->id)->count());
    }

    # RECHAZA type Y currency FUERA DE LOS VALORES PERMITIDOS
    public function test_store_rechaza_type_y_currency_invalidos(): void
    {
        $token = $this->tokenAdmin();
        $usuario = User::factory()->create();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/admin/accounts', [
                'user_id'  => $usuario->id,
                'type'     => 'crypto',
                'currency' => 'EUR',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'currency']);
    }

    # RECHAZA user_id INEXISTENTE
    public function test_store_rechaza_un_user_id_inexistente(): void
    {
        $token = $this->tokenAdmin();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/admin/accounts', [
                'user_id'  => 999999,
                'type'     => 'savings',
                'currency' => 'ARS',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    # UN USUARIO COMUN NO PUEDE CREAR CUENTAS
    public function test_store_rechaza_a_un_usuario_comun(): void
    {
        $token = $this->tokenUsuarioComun();
        $usuario = User::factory()->create();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/admin/accounts', [
                'user_id'  => $usuario->id,
                'type'     => 'savings',
                'currency' => 'ARS',
            ]);

        $response->assertStatus(403);
    }

    # ---------- UPDATE ----------

    # ACTUALIZA type Y currency CORRECTAMENTE
    public function test_update_modifica_type_y_currency(): void
    {
        $token = $this->tokenAdmin();
        $usuario = User::factory()->create();
        $account = Account::factory()->for($usuario)->create([
            'type'     => 'savings',
            'currency' => 'ARS',
        ]);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->putJson("/api/v1/admin/accounts/{$account->id}", [
                'type'     => 'checking',
                'currency' => 'USD',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id'       => $account->id,
                    'type'     => 'checking',
                    'currency' => 'USD',
                ],
            ]);

        $this->assertDatabaseHas('accounts', [
            'id'       => $account->id,
            'type'     => 'checking',
            'currency' => 'USD',
        ]);
    }

    # UN user_id ENVIADO EN EL BODY SE IGNORA: LA CUENTA NO SE REASIGNA A OTRO USUARIO
    public function test_update_no_reasigna_la_cuenta_a_otro_usuario(): void
    {
        $token = $this->tokenAdmin();
        $usuarioOriginal = User::factory()->create();
        $otroUsuario = User::factory()->create();
        $account = Account::factory()->for($usuarioOriginal)->create();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->putJson("/api/v1/admin/accounts/{$account->id}", [
                'user_id'  => $otroUsuario->id,
                'type'     => 'checking',
                'currency' => 'USD',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('accounts', [
            'id'      => $account->id,
            'user_id' => $usuarioOriginal->id,
        ]);
        $this->assertDatabaseMissing('accounts', [
            'id'      => $account->id,
            'user_id' => $otroUsuario->id,
        ]);
    }

    # UN balance ENVIADO EN EL BODY SE IGNORA: NO SE PUEDE MODIFICAR DESDE ESTE ENDPOINT
    public function test_update_no_modifica_el_balance(): void
    {
        $token = $this->tokenAdmin();
        $usuario = User::factory()->create();
        $account = Account::factory()->for($usuario)->create(['balance' => 300.00]);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->putJson("/api/v1/admin/accounts/{$account->id}", [
                'balance'  => 999999.99,
                'type'     => 'checking',
                'currency' => 'USD',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('accounts', [
            'id'      => $account->id,
            'balance' => 300.00,
        ]);
    }

    # RECHAZA type/currency INVALIDOS EN UPDATE
    public function test_update_rechaza_type_invalido(): void
    {
        $token = $this->tokenAdmin();
        $usuario = User::factory()->create();
        $account = Account::factory()->for($usuario)->create();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->putJson("/api/v1/admin/accounts/{$account->id}", [
                'type' => 'crypto',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    # ---------- DESTROY ----------

    # ELIMINA UNA CUENTA SIN SALDO Y SIN MOVIMIENTOS
    public function test_destroy_elimina_una_cuenta_sin_saldo_ni_movimientos(): void
    {
        $token = $this->tokenAdmin();
        $usuario = User::factory()->create();
        $account = Account::factory()->for($usuario)->create(['balance' => 0.00]);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->deleteJson("/api/v1/admin/accounts/{$account->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('accounts', ['id' => $account->id]);
    }

    # NO ELIMINA UNA CUENTA CON SALDO DISTINTO DE 0 (422, MENSAJE CLARO)
    public function test_destroy_bloquea_una_cuenta_con_saldo(): void
    {
        $token = $this->tokenAdmin();
        $usuario = User::factory()->create();
        $account = Account::factory()->for($usuario)->create(['balance' => 500.00]);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->deleteJson("/api/v1/admin/accounts/{$account->id}");

        $response->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertDatabaseHas('accounts', ['id' => $account->id]);
    }

    # NO ELIMINA UNA CUENTA CON MOVIMIENTOS ASOCIADOS, AUNQUE EL SALDO SEA 0 (422, MENSAJE CLARO)
    public function test_destroy_bloquea_una_cuenta_con_movimientos(): void
    {
        $token = $this->tokenAdmin();
        $usuario = User::factory()->create();
        $account = Account::factory()->for($usuario)->create(['balance' => 0.00]);

        Movement::factory()->for($account)->create([
            'type'   => 'deposit',
            'amount' => 100.00,
        ]);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->deleteJson("/api/v1/admin/accounts/{$account->id}");

        $response->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertDatabaseHas('accounts', ['id' => $account->id]);
    }

    # UN USUARIO COMUN NO PUEDE ELIMINAR CUENTAS
    public function test_destroy_rechaza_a_un_usuario_comun(): void
    {
        $token = $this->tokenUsuarioComun();
        $usuario = User::factory()->create();
        $account = Account::factory()->for($usuario)->create();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->deleteJson("/api/v1/admin/accounts/{$account->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('accounts', ['id' => $account->id]);
    }
}
