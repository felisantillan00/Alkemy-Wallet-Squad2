<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    # GET /api/v1/profile
    # Devuelve los datos del usuario autenticado.
    # El usuario sale del token: el cliente no envía user_id, por lo que
    # nadie puede consultar el perfil de otra persona.
    public function show(): JsonResponse
    {
        $user = auth('api')->user();

        return response()->json([
            'success' => true,
            'data' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
            ],
        ]);
    }
}