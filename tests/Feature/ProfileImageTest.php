<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class ProfileImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Storage::fake('public');
    }

    # LA FOTO SUBIDA SE DEVUELVE COMO URL DE LA API (NO /storage) Y ESA URL SIRVE LA IMAGEN
    public function test_la_foto_subida_se_sirve_desde_la_api(): void
    {
        $user = User::factory()->create();
        $token = JWTAuth::fromUser($user);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->patchJson('/api/v1/profile', [
                'image' => UploadedFile::fake()->image('foto.jpg')->size(200),
            ]);

        $response->assertStatus(200);

        $url = $response->json('data.image');
        $this->assertStringContainsString('/api/v1/profile-images/', $url);
        $this->assertStringNotContainsString('/storage/', $url);

        $this->get(parse_url($url, PHP_URL_PATH))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    # UNA IMAGEN QUE NO EXISTE DEVUELVE 404
    public function test_imagen_inexistente_devuelve_404(): void
    {
        $this->get('/api/v1/profile-images/no-existe.jpg')->assertNotFound();
    }

    # LA RUTA NO PERMITE SALIR DE LA CARPETA NI PEDIR ARCHIVOS QUE NO SON IMAGENES
    public function test_la_ruta_no_permite_recorrer_directorios_ni_otras_extensiones(): void
    {
        Storage::disk('public')->put('profile-images/script.php', '<?php echo 1;');
        Storage::disk('public')->put('secreto.jpg', 'no debe salir');

        $this->get('/api/v1/profile-images/script.php')->assertNotFound();
        $this->get('/api/v1/profile-images/..%2Fsecreto.jpg')->assertNotFound();
        $this->get('/api/v1/profile-images/../secreto.jpg')->assertNotFound();
    }

    # UNA URL EXTERNA GUARDADA COMO IMAGEN (P. EJ. CREADA POR UN ADMIN) SE DEVUELVE TAL CUAL
    public function test_una_url_externa_se_devuelve_sin_modificar(): void
    {
        $user = User::factory()->create(['image' => 'https://example.test/foto.jpg']);
        $token = JWTAuth::fromUser($user);

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('data.image', 'https://example.test/foto.jpg');
    }

    # SIN FOTO, EL PERFIL DEVUELVE image = null
    public function test_sin_foto_el_perfil_devuelve_null(): void
    {
        $user = User::factory()->create(['image' => null]);
        $token = JWTAuth::fromUser($user);

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('data.image', null);
    }
}
