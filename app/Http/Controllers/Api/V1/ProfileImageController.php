<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileImageController extends Controller
{
    # GET /api/v1/profile-images/{filename}
    # Sirve una foto de perfil desde storage/app/public/profile-images. Es pública porque un <img>
    # no puede enviar el token; el nombre lo genera Laravel al azar (40 caracteres) al subirla.
    # La ruta solo admite nombres con extensión jpg/jpeg/png/webp y sin barras, así que no permite
    # salir de la carpeta profile-images.
    #[OA\Get(
        path: '/api/v1/profile-images/{filename}',
        summary: 'Ver una foto de perfil',
        description: 'Devuelve el archivo de imagen de un perfil. Es la URL que devuelve el campo `image` del perfil. No requiere token.',
        tags: ['Perfil'],
        parameters: [
            new OA\Parameter(name: 'filename', in: 'path', required: true, description: 'Nombre del archivo (jpg, jpeg, png o webp)', schema: new OA\Schema(type: 'string', example: 'aB3dE5gH7jK9mN1pQ3sT5vW7yZ9bD1fH3jL5nP7r.jpg')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Imagen'),
            new OA\Response(response: 404, description: 'La imagen no existe'),
        ]
    )]
    public function show(string $filename): StreamedResponse
    {
        $path = 'profile-images/'.$filename;
        $disk = Storage::disk('public');

        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, ['Cache-Control' => 'public, max-age=86400']);
    }
}
