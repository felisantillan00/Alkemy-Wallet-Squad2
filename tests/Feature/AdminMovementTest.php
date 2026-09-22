<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Movement;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMovementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $regularUser;
    protected Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::factory()->create(['role_name' => 'admin']);
        $userRole = Role::factory()->create(['role_name' => 'user']);

        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);
        $this->regularUser = User::factory()->create(['role_id' => $userRole->id]);

        $this->account = Account::factory()->create(['user_id' => $this->regularUser->id]);
    }

    public function test_admin_puede_listar_movimientos_paginados(): void
    {
        Movement::factory()->count(20)->create(['account_id' => $this->account->id]);

        $response = $this->actingAs($this->admin, 'api')
                         ->getJson('/api/v1/admin/movements');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data',
                     'current_page',
                     'first_page_url',
                     'last_page'
                 ]);
    }

    public function test_usuario_comun_no_puede_acceder_a_movimientos_admin(): void
    {
        $response = $this->actingAs($this->regularUser, 'api')
                         ->getJson('/api/v1/admin/movements');

        $response->assertStatus(403);
    }

    public function test_peticion_sin_token_devuelve_401(): void
    {
        $response = $this->getJson('/api/v1/admin/movements');

        $response->assertStatus(401);
    }
}
