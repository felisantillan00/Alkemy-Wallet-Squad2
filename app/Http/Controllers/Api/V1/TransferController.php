<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransferRequest;
use App\Models\Account;
use App\Models\Movement;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferController extends Controller
{
    # POST /api/v1/transfers
    # Transfiere dinero desde la cuenta autenticada hacia la cuenta de otro CBU.
    # Descuenta el origen, acredita el destino y crea un movimiento en cada cuenta.
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
