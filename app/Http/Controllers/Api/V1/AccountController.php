<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
class AccountController extends Controller
{
        #[OA\Get(
        path: '/api/v1/account',
        summary: 'Consultar cuenta propia',
        description: 'Devuelve CBU, tipo, moneda y saldo de la cuenta del usuario autenticado. La cuenta se obtiene del token: no se puede consultar la de otro usuario.',
        tags: ['Cuenta'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Cuenta obtenida correctamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Account'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
        ]
    )]
    public function show()
    {
        /** @var \App\Models\User $user */

        # GET /api/v1/account
        # Devuelve el cbu y el balance de la cuenta del usuario autenticado.
        # La cuenta se obtiene desde el usuario autenticado.
        
        # Obtenemos los datos del usuario autenticado.
        $user = auth('api')->user();
        
        # Cargamos la relacion con account.
        $user->load('account');

        # Devolvemos el cbu, tipo, moneda y balance de la cuenta.
        return response()->json([
            'success' => true,
            'data' => [
                'cbu' => $user->account->cbu,
                'type' => $user->account->type,
                'currency' => $user->account->currency,
                'balance' => $user->account->balance,
            ],
        ], 200);
    }
}
