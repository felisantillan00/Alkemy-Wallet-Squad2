<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::factory()->create(['role_name' => 'admin']);
        $userRole = Role::factory()->create(['role_name' => 'user']);

        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);
        $this->regularUser = User::factory()->create(['role_id' => $userRole->id]);
    }

    public function test_admin_puede_listar_usuarios(): void
    {
        $response = $this->actingAs($this->admin, 'api')
                         ->getJson('/api/v1/admin/users');

        $response->assertStatus(200)
                 ->assertJsonStructure(['data']);
    }

    public function test_usuario_comun_no_puede_listar_usuarios_admin(): void
    {
        $response = $this->actingAs($this->regularUser, 'api')
                         ->getJson('/api/v1/admin/users');

        $response->assertStatus(403);
    }
}
