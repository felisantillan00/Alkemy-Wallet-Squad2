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
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;
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
    #[OA\Post(
        path: '/api/v1/auth/login',
        summary: 'Iniciar sesión',
        description: 'Devuelve un JWT para usar como Bearer Token. Máximo 5 intentos fallidos por minuto por email + IP; al superarlo responde 429.',
        tags: ['Autenticación'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'testuser@example.test'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Sesión iniciada correctamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Sesión iniciada correctamente.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'user',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 2),
                                        new OA\Property(property: 'name', type: 'string', example: 'Test User'),
                                        new OA\Property(property: 'email', type: 'string', example: 'testuser@example.test'),
                                    ]
                                ),
                                new OA\Property(property: 'access_token', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGciOi...'),
                                new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
                                new OA\Property(property: 'expires_in', type: 'integer', example: 3600),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Credenciales incorrectas',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Las credenciales son incorrectas.'),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Error de validación', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 429, description: 'Demasiados intentos fallidos', content: new OA\JsonContent(ref: '#/components/schemas/TooManyAttemptsError')),
        ]
    )]
    public function login(LoginRequest $request): JsonResponse
    {
         $credenciales = $request->validated();

        # Limitador por email normalizado + IP: maximo 5 intentos fallidos por minuto
        $clave = 'login:' . Str::lower(trim($credenciales['email'])) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($clave, 5)) {
            $segundos = RateLimiter::availableIn($clave);

            return response()->json([
                'success'     => false,
                'message'     => "Demasiados intentos fallidos. Podés reintentar en {$segundos} segundos.",
                'retry_after' => $segundos,
            ], 429)->header('Retry-After', $segundos);
        }

        # attempt() verifica el hash de la contraseña y emite el token
        $token = auth('api')->attempt($credenciales);

        if (! $token) {
            # Solo los intentos fallidos suman al contador (ventana de 60 segundos)
            RateLimiter::hit($clave, 60);

            return response()->json([
                'success' => false,
                'message' => 'Las credenciales son incorrectas.',
            ], 401);
        }

        # Login exitoso: se reinicia el contador
        RateLimiter::clear($clave);

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
    #[OA\Post(
        path: '/api/v1/auth/logout',
        summary: 'Cerrar sesión',
        description: 'Invalida el token actual.',
        tags: ['Autenticación'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Sesión cerrada correctamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Sesión cerrada correctamente.'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
        ]
    )]
    public function logout(): JsonResponse
    {
        auth('api')->logout();

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada correctamente.',
        ]);
    }
}