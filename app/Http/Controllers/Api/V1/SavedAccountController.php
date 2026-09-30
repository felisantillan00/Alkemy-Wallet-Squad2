<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveCvuRequest;
use App\Http\Requests\SavedAccountDestroyRequest;
use App\Http\Requests\SavedAccountIndexRequest;
use App\Models\Account;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
class SavedAccountController extends Controller
{
    # POST /api/v1/cvu/{cvu}/users/{idUser}
    # Guarda el CVU de una cuenta de tercero en la lista del usuario autenticado.
    #[OA\Post(
        path: '/api/v1/cvu/{cvu}/users/{idUser}',
        summary: 'Guardar un CVU de tercero',
        description: 'Agrega el CVU de otra cuenta a la lista de guardados del usuario autenticado. El {idUser} debe coincidir con el usuario del token.',
        tags: ['CVU de terceros'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'cvu', in: 'path', required: true, description: 'CVU de 22 dígitos de una cuenta existente, distinta de la propia', schema: new OA\Schema(type: 'string', example: '0000003100000000000002')),
            new OA\Parameter(name: 'idUser', in: 'path', required: true, description: 'ID del usuario autenticado', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(
                response: 201,
                description: 'CVU guardado correctamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'CVU guardado correctamente.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'cvu', type: 'string', example: '0000003100000000000002'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 403, description: 'El {idUser} no corresponde al usuario autenticado', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenError')),
            new OA\Response(response: 422, description: 'Error de validación: CVU inexistente, CVU propio o CVU ya guardado', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function store(SaveCvuRequest $request, string $cvu, string $idUser): JsonResponse    {
        $cuentaGuardada = Account::where('cvu', $cvu)->first();

        $user = auth('api')->user();
        $user->savedAccounts()->attach($cuentaGuardada->id);

        return response()->json([
            'success' => true,
            'message' => 'CVU guardado correctamente.',
            'data' => [
                'cvu' => $cuentaGuardada->cvu,
            ],
        ], 201);
    }

    # GET /api/v1/cvu/users/{idUser}
    # Lista los CVUs guardados por el usuario autenticado.
        #[OA\Get(
        path: '/api/v1/cvu/users/{idUser}',
        summary: 'Listar CVUs guardados',
        description: 'Devuelve los CVUs que el usuario autenticado tiene guardados, con el nombre del titular. El {idUser} debe coincidir con el usuario del token.',
        tags: ['CVU de terceros'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'idUser', in: 'path', required: true, description: 'ID del usuario autenticado', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Listado de CVUs guardados',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'cvu', type: 'string', example: '0000003100000000000002'),
                                    new OA\Property(property: 'titular', type: 'string', nullable: true, example: 'María López'),
                                ],
                                type: 'object'
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 403, description: 'El {idUser} no corresponde al usuario autenticado', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenError')),
        ]
    )]
    public function index(SavedAccountIndexRequest $request, string $idUser): JsonResponse
    {
        $user = auth('api')->user();

        $guardados = $user->savedAccounts()
            ->with('user')
            ->get()
            ->map(fn (Account $cuenta) => [
                'cvu'     => $cuenta->cvu,
                'titular' => $cuenta->user?->name,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => $guardados,
        ], 200);
    }

    # DELETE /api/v1/cvu/{cvu}/users/{idUser}
    # Quita un CVU de la lista de guardados del usuario autenticado, sin tocar la cuenta ni al usuario tercero.
        #[OA\Delete(
        path: '/api/v1/cvu/{cvu}/users/{idUser}',
        summary: 'Remover un CVU guardado',
        description: 'Quita un CVU de la lista de guardados del usuario autenticado. No modifica la cuenta ni al usuario tercero. El {idUser} debe coincidir con el usuario del token.',
        tags: ['CVU de terceros'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'cvu', in: 'path', required: true, description: 'CVU presente en la lista de guardados', schema: new OA\Schema(type: 'string', example: '0000003100000000000002')),
            new OA\Parameter(name: 'idUser', in: 'path', required: true, description: 'ID del usuario autenticado', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'CVU eliminado de la lista correctamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'CVU eliminado de tu lista correctamente.'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 403, description: 'El {idUser} no corresponde al usuario autenticado', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenError')),
            new OA\Response(
                response: 404,
                description: 'El CVU no está en la lista de guardados',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Ese CVU no está en tu lista de guardados.'),
                    ]
                )
            ),
        ]
    )]
    public function destroy(SavedAccountDestroyRequest $request, string $cvu, string $idUser): JsonResponse
    {
        $user = auth('api')->user();
        $cuenta = Account::where('cvu', $cvu)->first();

        if (! $cuenta || ! $user->savedAccounts()->where('account_id', $cuenta->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Ese CVU no está en tu lista de guardados.',
            ], 404);
        }

        $user->savedAccounts()->detach($cuenta->id);

        return response()->json([
            'success' => true,
            'message' => 'CVU eliminado de tu lista correctamente.',
        ], 200);
    }
}
