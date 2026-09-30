<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Movement;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

# El panel administrativo del frontend edita con PATCH; antes la API solo aceptaba PUT y devolvía 405.
class AdminPatchTest extends TestCase
{
    use RefreshDatabase;

    private array $headers;
    private User $objetivo;

    protected function setUp(): void
    {
        parent::setUp();

        $rolAdmin = Role::factory()->create(['role_name' => 'admin']);
        $rolUser = Role::factory()->create(['role_name' => 'user']);

        $admin = User::factory()->create(['role_id' => $rolAdmin->id]);
        $this->objetivo = User::factory()->create(['role_id' => $rolUser->id]);

        $this->headers = ['Authorization' => 'Bearer '.JWTAuth::fromUser($admin)];
    }

    public function test_admin_puede_editar_un_usuario_con_patch(): void
    {
        $this->withHeaders($this->headers)
            ->patchJson("/api/v1/admin/users/{$this->objetivo->id}", ['name' => 'Editado por PATCH'])
            ->assertStatus(200)
            ->assertJsonPath('data.user.name', 'Editado por PATCH');

        $this->assertDatabaseHas('users', ['id' => $this->objetivo->id, 'name' => 'Editado por PATCH']);
    }

    public function test_admin_sigue_pudiendo_editar_un_usuario_con_put(): void
    {
        $this->withHeaders($this->headers)
            ->putJson("/api/v1/admin/users/{$this->objetivo->id}", ['name' => 'Editado por PUT'])
            ->assertStatus(200)
            ->assertJsonPath('data.user.name', 'Editado por PUT');
    }

    public function test_admin_puede_editar_un_movimiento_con_patch_y_con_put(): void
    {
        $cuenta = Account::factory()->create(['user_id' => $this->objetivo->id]);
        $movimiento = Movement::factory()->create(['account_id' => $cuenta->id, 'amount' => 10]);

        $this->withHeaders($this->headers)
            ->patchJson("/api/v1/admin/movements/{$movimiento->id}", ['amount' => 25])
            ->assertStatus(200);
        $this->assertDatabaseHas('movements', ['id' => $movimiento->id, 'amount' => 25]);

        $this->withHeaders($this->headers)
            ->putJson("/api/v1/admin/movements/{$movimiento->id}", ['amount' => 40])
            ->assertStatus(200);
        $this->assertDatabaseHas('movements', ['id' => $movimiento->id, 'amount' => 40]);
    }

    public function test_un_usuario_comun_no_puede_editar_con_patch(): void
    {
        $token = JWTAuth::fromUser($this->objetivo);

        $this->withHeader('Authorization', "Bearer $token")
            ->patchJson("/api/v1/admin/users/{$this->objetivo->id}", ['name' => 'Intruso'])
            ->assertStatus(403);
    }
}
