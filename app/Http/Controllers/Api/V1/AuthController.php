<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Account;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    # POST /api/v1/auth/register
    # Registra un usuario y le crea su cuenta con CBU unico y saldo inicial 0.00
    public function register(RegisterRequest $request): JsonResponse
    {
        $datos = $request->validated();

        # El rol se asigna desde el servidor: nadie puede autoasignarse admin
        $rolUsuario = Role::where('role_name', 'user')->first();

        # Transaccion: o se crean usuario y cuenta, o no se crea ninguno de los dos
        $user = DB::transaction(function () use ($datos, $rolUsuario) {

            $user = User::create([
                'name'     => $datos['name'],
                'email'    => $datos['email'],
                'password' => $datos['password'],
                'role_id'  => $rolUsuario?->id,
            ]);

            Account::create([
                'user_id' => $user->id,
                'cbu'     => Account::generarCbuUnico(),
            ]);

            return $user;
        });

        $user->load('account');

        return response()->json([
            'success' => true,
            'message' => 'Usuario registrado correctamente.',
            'data' => [
                'user' => [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                ],
                'account' => [
                    'cbu'     => $user->account->cbu,
                    'balance' => number_format((float) $user->account->balance, 2, '.', ''),
                ],
            ],
        ], 201);
    }
}