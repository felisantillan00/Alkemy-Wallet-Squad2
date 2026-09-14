<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AccountController extends Controller
{
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
