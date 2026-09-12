<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class SavedAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        # LOS USUARIOS REQUIEREN UN role_id EXISTENTE
        $this->seed(RoleSeeder::class);
    }

    # HELPER: crea un usuario con su cuenta y devuelve el usuario, la cuenta y el token
    protected function crearUsuarioConCuenta(): array
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $token = JWTAuth::fromUser($user);

        return [$user, $account, $token];
    }

    # GUARDAR UN CBU DE TERCERO VALIDO PERSISTE LA RELACION Y DEVUELVE 201
    public function test_guarda_un_cbu_de_tercero_valido(): void
    {
        [$user, , $token] = $this->crearUsuarioConCuenta();
        [, $tercero] = $this->crearUsuarioConCuenta();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/v1/cbu/{$tercero->cbu}/users/{$user->id}");

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => ['cbu' => $tercero->cbu],
            ]);

        $this->assertDatabaseHas('saved_accounts', [
            'user_id'    => $user->id,
            'account_id' => $tercero->id,
        ]);
    }

    # NO SE PUEDE GUARDAR EL PROPIO CBU
    public function test_no_permite_guardar_el_propio_cbu(): void
    {
        [$user, $cuenta, $token] = $this->crearUsuarioConCuenta();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/v1/cbu/{$cuenta->cbu}/users/{$user->id}");

        $response->assertStatus(422);
        $this->assertDatabaseMissing('saved_accounts', ['user_id' => $user->id]);
    }

    # UN CBU INEXISTENTE DEVUELVE 422 Y NO MODIFICA LA LISTA
    public function test_cbu_inexistente_devuelve_422(): void
    {
        [$user, , $token] = $this->crearUsuarioConCuenta();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/cbu/0000000000000000000000/users/' . $user->id);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('saved_accounts', ['user_id' => $user->id]);
    }

    # NO SE PUEDE DUPLICAR EL MISMO CBU PARA EL MISMO USUARIO
    public function test_no_permite_duplicar_el_mismo_cbu(): void
    {
        [$user, , $token] = $this->crearUsuarioConCuenta();
        [, $tercero] = $this->crearUsuarioConCuenta();

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/v1/cbu/{$tercero->cbu}/users/{$user->id}")
            ->assertStatus(201);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/v1/cbu/{$tercero->cbu}/users/{$user->id}");

        $response->assertStatus(422);
        $this->assertEquals(
            1,
            $user->savedAccounts()->where('account_id', $tercero->id)->count()
        );
    }

    # UN USUARIO NO PUEDE MODIFICAR LA LISTA DE OTRO USUARIO
    public function test_no_permite_modificar_la_lista_de_otro_usuario(): void
    {
        [, , $token] = $this->crearUsuarioConCuenta();
        [$otroUsuario] = $this->crearUsuarioConCuenta();
        [, $tercero] = $this->crearUsuarioConCuenta();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/v1/cbu/{$tercero->cbu}/users/{$otroUsuario->id}");

        $response->assertStatus(403);
        $this->assertDatabaseMissing('saved_accounts', ['user_id' => $otroUsuario->id]);
    }

    # SIN TOKEN DEVUELVE 401
    public function test_sin_token_devuelve_401(): void
    {
        [$user] = $this->crearUsuarioConCuenta();
        [, $tercero] = $this->crearUsuarioConCuenta();

        $response = $this->postJson("/api/v1/cbu/{$tercero->cbu}/users/{$user->id}");

        $response->assertStatus(401);
    }
}
