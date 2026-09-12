<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
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

    # POST /api/v1/auth/login
    # Valida las credenciales y devuelve un JWT utilizable como Bearer Token
    public function login(LoginRequest $request): JsonResponse
    {
        $credenciales = $request->validated();

        # attempt() verifica el hash de la contraseña y emite el token
        $token = auth('api')->attempt($credenciales);

        if (! $token) {
            return response()->json([
                'success' => false,
                'message' => 'Las credenciales son incorrectas.',
            ], 401);
        }

        $user = auth('api')->user();

        return response()->json([
            'success' => true,
            'message' => 'Sesión iniciada correctamente.',
            'data' => [
                'user' => [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                ],
                'access_token' => $token,
                'token_type'   => 'Bearer',
                                'expires_in'   => auth('api')->factory()->getTTL() * 60,
            ],
        ]);
    }

    # POST /api/v1/auth/logout
    # Invalida el token actual
    public function logout(): JsonResponse
    {
        auth('api')->logout();

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada correctamente.',
        ]);
    }
}