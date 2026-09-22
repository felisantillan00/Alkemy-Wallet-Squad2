<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransferRequest;
use App\Models\Account;
use App\Models\Movement;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class TransferController extends Controller
{
    # POST /api/v1/transfers
    # Transfiere dinero desde la cuenta autenticada hacia la cuenta de otro CBU.
    # Descuenta el origen, acredita el destino y crea un movimiento en cada cuenta.
     #[OA\Post(
        path: '/api/v1/transfers',
        summary: 'Transferir dinero a otra cuenta',
        description: 'Transfiere desde la cuenta del usuario autenticado hacia la cuenta del CBU indicado. Descuenta el origen, acredita el destino y crea un movimiento en cada cuenta ("transfer_out" y "transfer_in"). Es atómica: si algo falla, no cambia ningún saldo.',
        tags: ['Transferencias'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['destination_cbu', 'amount'],
                properties: [
                    new OA\Property(property: 'destination_cbu', type: 'string', description: 'CBU de una cuenta existente, distinta de la propia', example: '0000003100000000000002'),
                    new OA\Property(property: 'amount', type: 'number', format: 'float', minimum: 0.01, description: 'No puede superar el saldo disponible', example: 100.00),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Transferencia realizada correctamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Transferencia realizada correctamente.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'balance', type: 'string', description: 'Saldo resultante de la cuenta de origen', example: '50.00'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 422, description: 'Error de validación: CBU inexistente, CBU propio, monto inválido o saldo insuficiente', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function store(TransferRequest $request): JsonResponse
    {
        $datos = $request->validated();

        $user = auth('api')->user();
        $user->load('account');
        $cuentaOrigen = $user->account;

        $cuentaDestino = Account::where('cbu', $datos['destination_cbu'])->first();

        # Transaccion: o se mueven ambos saldos y se crean ambos movimientos, o no cambia nada
        $cuentaOrigen = DB::transaction(function () use ($cuentaOrigen, $cuentaDestino, $datos) {

            $origen = Account::whereKey($cuentaOrigen->id)->lockForUpdate()->first();
            $destino = Account::whereKey($cuentaDestino->id)->lockForUpdate()->first();

            # Revalidamos el saldo con el lock tomado, por si cambió entre la validacion y la transaccion
            if ($origen->balance < $datos['amount']) {
                throw ValidationException::withMessages([
                    'amount' => 'Saldo insuficiente para realizar la transferencia.',
                ]);
            }

            $origen->decrement('balance', $datos['amount']);
            $destino->increment('balance', $datos['amount']);

            Movement::create([
                'account_id'      => $origen->id,
                'type'            => 'transfer_out',
                'amount'          => $datos['amount'],
                'counterpart_cbu' => $destino->cbu,
            ]);

            Movement::create([
                'account_id'      => $destino->id,
                'type'            => 'transfer_in',
                'amount'          => $datos['amount'],
                'counterpart_cbu' => $origen->cbu,
            ]);

            return $origen->fresh();
        });

        return response()->json([
            'success' => true,
            'message' => 'Transferencia realizada correctamente.',
            'data' => [
                'balance' => number_format((float) $cuentaOrigen->balance, 2, '.', ''),
            ],
        ], 201);
    }
}
