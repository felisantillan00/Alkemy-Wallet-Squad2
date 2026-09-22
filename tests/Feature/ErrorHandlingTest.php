<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        # LOS USUARIOS REQUIEREN ROLES EXISTENTES (admin=1, user=2)
        $this->seed(RoleSeeder::class);
    }

    # CREA UN USUARIO COMUN CON CONTRASEÑA CONOCIDA PARA PROBAR EL LOGIN
    private function crearUsuario(): User
    {
        $rolUser = Role::where('role_name', 'user')->first();

        return User::factory()->create([
            'email'    => 'login@example.test',
            'password' => 'password123',
            'role_id'  => $rolUser->id,
        ]);
    }

    # HACE UN INTENTO DE LOGIN CON CONTRASEÑA INCORRECTA
    private function loginFallido(string $email = 'login@example.test'): TestResponse
    {
        return $this->postJson('/api/v1/auth/login', [
            'email'    => $email,
            'password' => 'incorrecta',
        ]);
    }

    # ---------- FORMATO UNIFICADO DE ERRORES ----------

    # SIN HEADER Accept: application/json IGUAL DEVUELVE 401 JSON Y NO REDIRIGE A LOGIN
    public function test_sin_accept_json_devuelve_401_json_sin_redirigir(): void
    {
        $response = $this->get('/api/v1/profile');

        $response->assertStatus(401)
            ->assertHeaderMissing('Location')
            ->assertJson(['success' => false]);
    }

    # UNA RUTA INEXISTENTE BAJO /api/v1 DEVUELVE 404 JSON
    public function test_ruta_inexistente_devuelve_404_json(): void
    {
        $response = $this->get('/api/v1/ruta-que-no-existe');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Recurso no encontrado.',
            ]);
    }

    # UN METODO HTTP NO PERMITIDO DEVUELVE 405 JSON
    public function test_metodo_no_permitido_devuelve_405_json(): void
    {
        $response = $this->get('/api/v1/auth/login');

        $response->assertStatus(405)
            ->assertJson(['success' => false]);
    }

    # LA VALIDACION DEVUELVE 422 CON EL FORMATO UNIFICADO Y EL DETALLE POR CAMPO
    public function test_validacion_devuelve_422_con_formato_unificado(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422)
            ->assertJson(['success' => false])
            ->assertJsonValidationErrors(['email', 'password']);
    }

    # UN ERROR INTERNO DEVUELVE 500 GENERICO SIN EXPONER DETALLES
    public function test_error_interno_no_expone_detalles(): void
    {
        # RUTA TEMPORAL SOLO PARA ESTE TEST, SIMULA UN ERROR DE BASE DE DATOS
        Route::get('/api/v1/test-error-interno', function () {
            throw new \RuntimeException('SQLSTATE[HY000]: detalle interno secreto');
        });

        $response = $this->get('/api/v1/test-error-interno');

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => 'Error interno del servidor.',
            ])
            ->assertDontSee('SQLSTATE')
            ->assertDontSee('secreto');
    }

    # ---------- LIMITE DE INTENTOS DE LOGIN ----------

    # AL SEXTO INTENTO FALLIDO RESPONDE 429 E INFORMA CUANDO REINTENTAR
    public function test_el_login_bloquea_al_sexto_intento_fallido_con_429(): void
    {
        $this->crearUsuario();

        for ($i = 0; $i < 5; $i++) {
            $this->loginFallido()->assertStatus(401);
        }

        $response = $this->loginFallido();

        $response->assertStatus(429)
            ->assertJson(['success' => false])
            ->assertJsonStructure(['retry_after'])
            ->assertHeader('Retry-After');
    }

    # BLOQUEADO, NI SIQUIERA CON LA CONTRASEÑA CORRECTA PUEDE ENTRAR
    public function test_bloqueado_no_entra_ni_con_la_contrasena_correcta(): void
    {
        $this->crearUsuario();

        for ($i = 0; $i < 5; $i++) {
            $this->loginFallido();
        }

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'login@example.test',
            'password' => 'password123',
        ]);

        $response->assertStatus(429);
    }

    # EL EMAIL SE NORMALIZA: MAYUSCULAS Y MINUSCULAS CUENTAN COMO EL MISMO
    public function test_el_limite_usa_el_email_normalizado(): void
    {
        $this->crearUsuario();

        for ($i = 0; $i < 5; $i++) {
            $this->loginFallido('LOGIN@Example.TEST')->assertStatus(401);
        }

        $this->loginFallido()->assertStatus(429);
    }

    # UN LOGIN EXITOSO REINICIA EL CONTADOR DE INTENTOS FALLIDOS
    public function test_un_login_exitoso_reinicia_el_contador(): void
    {
        $this->crearUsuario();

        for ($i = 0; $i < 4; $i++) {
            $this->loginFallido()->assertStatus(401);
        }

        $this->postJson('/api/v1/auth/login', [
            'email'    => 'login@example.test',
            'password' => 'password123',
        ])->assertStatus(200);

        # SI EL CONTADOR NO SE HUBIERA REINICIADO, EL SEGUNDO DE ESTOS YA DARIA 429
        for ($i = 0; $i < 5; $i++) {
            $this->loginFallido()->assertStatus(401);
        }
    }
}