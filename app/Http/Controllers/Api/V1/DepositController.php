<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DepositRequest;
use App\Models\Movement;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class DepositController extends Controller
{
    # POST /api/v1/deposits
    # Deposita dinero en la cuenta del usuario autenticado.
    # Aumenta el saldo y crea exactamente un movimiento tipo deposit.
        #[OA\Post(
        path: '/api/v1/deposits',
        summary: 'Depositar dinero',
        description: 'Deposita en la cuenta del usuario autenticado: aumenta el saldo y crea un movimiento de tipo "deposit". La cuenta sale del token, no se puede depositar en la de otro usuario.',
        tags: ['Depósitos'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['amount'],
                properties: [
                    new OA\Property(property: 'amount', type: 'number', format: 'float', minimum: 0.01, example: 150.00),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Depósito realizado correctamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Depósito realizado correctamente.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'balance', type: 'string', description: 'Saldo resultante, con dos decimales', example: '150.00'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 422, description: 'Error de validación (monto faltante, no numérico o menor a 0.01)', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function store(DepositRequest $request): JsonResponse
    {
        $datos = $request->validated();

        $user = auth('api')->user();
        $user->load('account');
        $account = $user->account;

        # Transaccion: o se actualiza el saldo y se crea el movimiento, o no cambia nada
        $account = DB::transaction(function () use ($account, $datos) {

            $account->increment('balance', $datos['amount']);

            Movement::create([
                'account_id' => $account->id,
                'type'       => 'deposit',
                'amount'     => $datos['amount'],
            ]);

            return $account->fresh();
        });

        return response()->json([
            'success' => true,
            'message' => 'Depósito realizado correctamente.',
            'data' => [
                'balance' => number_format((float) $account->balance, 2, '.', ''),
            ],
        ], 201);
    }
}