<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * La raíz del sitio redirige al frontend (public/app).
     */
    public function test_la_raiz_redirige_al_frontend(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/app/index.html');
    }

    /**
     * El frontend existe y apunta a la API del mismo dominio (URL relativa).
     */
    public function test_el_frontend_esta_publicado_y_usa_la_api_del_mismo_dominio(): void
    {
        $html = file_get_contents(public_path('app/index.html'));

        $this->assertStringContainsString('<meta name="api-base-url" content="/api/v1">', $html);
        $this->assertFileExists(public_path('app/js/app.js'));
        $this->assertFileExists(public_path('app/css/styles.css'));
    }
}
