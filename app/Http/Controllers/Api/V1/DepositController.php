<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DepositRequest;
use App\Models\Movement;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DepositController extends Controller
{
    # POST /api/v1/deposits
    # Deposita dinero en la cuenta del usuario autenticado.
    # Aumenta el saldo y crea exactamente un movimiento tipo deposit.
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