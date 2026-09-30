<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

# El panel administrativo del frontend crea y edita usuarios con este contrato: rol por nombre
# ("role"), sin password_confirmation, con edad (1 a 120) e imagen opcionales.
class AdminUserPanelTest extends TestCase
{
    use RefreshDatabase;

    private array $headers;
    private Role $rolAdmin;
    private Role $rolUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rolAdmin = Role::factory()->create(['role_name' => 'admin']);
        $this->rolUser = Role::factory()->create(['role_name' => 'user']);

        $admin = User::factory()->create(['role_id' => $this->rolAdmin->id]);
        $this->headers = ['Authorization' => 'Bearer '.JWTAuth::fromUser($admin)];
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'name'     => 'Usuario Panel',
            'email'    => 'panel@example.test',
            'password' => 'password123',
            'age'      => '30',
            'role'     => 'admin',
        ], $extra);
    }

    public function test_crear_con_el_payload_del_panel(): void
    {
        $this->withHeaders($this->headers)
            ->postJson('/api/v1/admin/users', $this->payload())
            ->assertStatus(201)
            ->assertJsonPath('data.user.role', 'admin');

        $creado = User::where('email', 'panel@example.test')->first();
        $this->assertEquals($this->rolAdmin->id, $creado->role_id);
        $this->assertTrue(Hash::check('password123', $creado->password));
        $this->assertNotNull($creado->account);
    }

    public function test_crear_sin_edad_ni_imagen(): void
    {
        $this->withHeaders($this->headers)
            ->postJson('/api/v1/admin/users', $this->payload(['age' => null, 'role' => 'user']))
            ->assertStatus(201)
            ->assertJsonPath('data.user.age', null)
            ->assertJsonPath('data.user.role', 'user');
    }

    public function test_crear_sigue_aceptando_role_id_y_confirmacion(): void
    {
        $this->withHeaders($this->headers)
            ->postJson('/api/v1/admin/users', [
                'name'                  => 'Con role_id',
                'email'                 => 'roleid@example.test',
                'password'              => 'password123',
                'password_confirmation' => 'password123',
                'age'                   => 40,
                'image'                 => 'https://example.test/foto.jpg',
                'role_id'               => $this->rolUser->id,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.user.role', 'user');
    }

    public function test_si_se_envia_confirmacion_debe_coincidir(): void
    {
        $this->withHeaders($this->headers)
            ->postJson('/api/v1/admin/users', $this->payload(['password_confirmation' => 'otra-distinta']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_un_rol_invalido_o_ausente_devuelve_422(): void
    {
        $this->withHeaders($this->headers)
            ->postJson('/api/v1/admin/users', $this->payload(['role' => 'superadmin']))
            ->assertStatus(422);

        $sinRol = $this->payload();
        unset($sinRol['role']);

        $this->withHeaders($this->headers)
            ->postJson('/api/v1/admin/users', $sinRol)
            ->assertStatus(422)
            ->assertJsonValidationErrors('role_id');
    }

    public function test_la_edad_va_de_1_a_120(): void
    {
        $this->withHeaders($this->headers)
            ->postJson('/api/v1/admin/users', $this->payload(['age' => 17, 'email' => 'joven@example.test']))
            ->assertStatus(201);

        foreach ([0, 121] as $edad) {
            $this->withHeaders($this->headers)
                ->postJson('/api/v1/admin/users', $this->payload(['age' => $edad, 'email' => "e{$edad}@example.test"]))
                ->assertStatus(422)
                ->assertJsonValidationErrors('age');
        }
    }

    public function test_editar_con_el_payload_del_panel(): void
    {
        $objetivo = User::factory()->create(['role_id' => $this->rolUser->id, 'age' => 33]);

        $this->withHeaders($this->headers)
            ->patchJson("/api/v1/admin/users/{$objetivo->id}", [
                'name'  => 'Editado',
                'email' => $objetivo->email,
                'age'   => null,
                'role'  => 'admin',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.user.role', 'admin')
            ->assertJsonPath('data.user.age', null);

        $this->assertDatabaseHas('users', [
            'id'      => $objetivo->id,
            'name'    => 'Editado',
            'age'     => null,
            'role_id' => $this->rolAdmin->id,
        ]);
    }

    public function test_editar_la_contrasena_sin_confirmacion_la_hashea(): void
    {
        $objetivo = User::factory()->create(['role_id' => $this->rolUser->id]);

        $this->withHeaders($this->headers)
            ->patchJson("/api/v1/admin/users/{$objetivo->id}", ['password' => 'nueva-clave-123'])
            ->assertStatus(200)
            ->assertJsonMissingPath('data.user.password');

        $this->assertTrue(Hash::check('nueva-clave-123', $objetivo->fresh()->password));
    }

    public function test_editar_con_confirmacion_distinta_devuelve_422(): void
    {
        $objetivo = User::factory()->create(['role_id' => $this->rolUser->id]);

        $this->withHeaders($this->headers)
            ->patchJson("/api/v1/admin/users/{$objetivo->id}", [
                'password'              => 'nueva-clave-123',
                'password_confirmation' => 'no-coincide',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }
}
