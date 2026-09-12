<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class ProfileTest extends TestCase
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

    # ACTUALIZA NAME Y EMAIL Y DEVUELVE EL PERFIL SIN CONTRASEÑA
    public function test_actualiza_name_y_email(): void
    {
        [$user, , $token] = $this->crearUsuarioConCuenta();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->patchJson('/api/v1/profile', [
                'name'  => 'Nuevo Nombre',
                'email' => 'nuevo@example.com',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Nuevo Nombre')
            ->assertJsonPath('data.email', 'nuevo@example.com')
            ->assertJsonMissingPath('data.password');

        $this->assertDatabaseHas('users', [
            'id'    => $user->id,
            'name'  => 'Nuevo Nombre',
            'email' => 'nuevo@example.com',
        ]);
    }

    # PERMITE ACTUALIZAR SOLO LA EDAD, DEJANDO EL RESTO INTACTO
    public function test_actualiza_solo_la_edad(): void
    {
        [$user, , $token] = $this->crearUsuarioConCuenta();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->patchJson('/api/v1/profile', ['age' => 30]);

        $response->assertStatus(200)->assertJsonPath('data.age', 30);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'age' => 30, 'name' => $user->name]);
    }

    # UNA EDAD FUERA DE RANGO DEVUELVE 422 Y NO MODIFICA NADA
    public function test_edad_invalida_devuelve_422_y_no_modifica_nada(): void
    {
        [$user, , $token] = $this->crearUsuarioConCuenta();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->patchJson('/api/v1/profile', ['name' => 'Otro Nombre', 'age' => 121]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => $user->name]);
    }

    # SUBE UNA IMAGEN VALIDA Y DEVUELVE SU URL
    public function test_sube_una_imagen_valida(): void
    {
        Storage::fake('public');

        [$user, , $token] = $this->crearUsuarioConCuenta();
        $imagen = UploadedFile::fake()->image('foto.jpg')->size(500);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->patchJson('/api/v1/profile', ['image' => $imagen]);

        $response->assertStatus(200);
        $user->refresh();

        $this->assertNotNull($user->image);
        Storage::disk('public')->assertExists($user->image);
    }

    # UNA IMAGEN CON FORMATO INVALIDO DEVUELVE 422
    public function test_imagen_con_formato_invalido_devuelve_422(): void
    {
        Storage::fake('public');

        [, , $token] = $this->crearUsuarioConCuenta();
        $archivo = UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->patchJson('/api/v1/profile', ['image' => $archivo]);

        $response->assertStatus(422);
    }

    # UNA IMAGEN DE MAS DE 2MB DEVUELVE 422
    public function test_imagen_demasiado_grande_devuelve_422(): void
    {
        Storage::fake('public');

        [, , $token] = $this->crearUsuarioConCuenta();
        $imagen = UploadedFile::fake()->image('foto.jpg')->size(3000);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->patchJson('/api/v1/profile', ['image' => $imagen]);

        $response->assertStatus(422);
    }

    # NO SE PUEDE USAR UN EMAIL QUE YA USA OTRO USUARIO
    public function test_no_permite_email_duplicado(): void
    {
        [, , $token] = $this->crearUsuarioConCuenta();
        [$otroUsuario] = $this->crearUsuarioConCuenta();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->patchJson('/api/v1/profile', ['email' => $otroUsuario->email]);

        $response->assertStatus(422);
    }

    # REENVIAR EL PROPIO EMAIL NO CUENTA COMO DUPLICADO
    public function test_permite_reenviar_el_propio_email(): void
    {
        [$user, , $token] = $this->crearUsuarioConCuenta();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->patchJson('/api/v1/profile', ['email' => $user->email, 'name' => 'Nombre Actualizado']);

        $response->assertStatus(200);
    }

    # UN user_id AJENO ENVIADO EN EL BODY SE IGNORA: SOLO SE MODIFICA EL USUARIO AUTENTICADO
    public function test_user_id_ajeno_en_el_body_se_ignora(): void
    {
        [$user, , $token] = $this->crearUsuarioConCuenta();
        [$otroUsuario] = $this->crearUsuarioConCuenta();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->patchJson('/api/v1/profile', [
                'name'    => 'Nombre Propio',
                'user_id' => $otroUsuario->id,
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Nombre Propio']);
        $this->assertDatabaseHas('users', ['id' => $otroUsuario->id, 'name' => $otroUsuario->name]);
    }

    # SIN TOKEN LA ACTUALIZACION DEVUELVE 401
    public function test_actualizacion_sin_token_devuelve_401(): void
    {
        $response = $this->patchJson('/api/v1/profile', ['name' => 'X']);

        $response->assertStatus(401);
    }

    # EL DELETE HACE UN SOFT DELETE: EL USUARIO DEJA DE EXISTIR PARA LOGIN/CONSULTAS
    # PERO LA CUENTA, SU BALANCE Y SUS MOVIMIENTOS QUEDAN INTACTOS
    public function test_destroy_hace_soft_delete_y_no_afecta_la_cuenta(): void
    {
        [$user, $cuenta, $token] = $this->crearUsuarioConCuenta(250.00);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->deleteJson('/api/v1/profile');

        $response->assertStatus(200)->assertJson(['success' => true]);

        # El usuario sigue en la tabla, pero soft-deleted
        $this->assertSoftDeleted('users', ['id' => $user->id]);

        # La cuenta sigue existiendo, con el mismo user_id y el mismo saldo
        $this->assertDatabaseHas('accounts', [
            'id'      => $cuenta->id,
            'user_id' => $user->id,
            'balance' => 250.00,
        ]);
    }

    # DESPUES DE ELIMINAR EL PERFIL, EL TOKEN YA NO SIRVE PARA RUTAS PROTEGIDAS
    public function test_token_deja_de_servir_despues_de_eliminar_el_perfil(): void
    {
        [, , $token] = $this->crearUsuarioConCuenta();

        $this->withHeader('Authorization', "Bearer $token")
            ->deleteJson('/api/v1/profile')
            ->assertStatus(200);

        # En producción cada request resuelve el guard desde cero; en el test forzamos
        # lo mismo porque el guard JWT cachea el usuario resuelto dentro del mismo proceso.
        $this->app->make('auth')->forgetGuards();

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/profile')
            ->assertStatus(401);
    }

    # SIN TOKEN EL DELETE DEVUELVE 401
    public function test_destroy_sin_token_devuelve_401(): void
    {
        $response = $this->deleteJson('/api/v1/profile');

        $response->assertStatus(401);
    }
}
