<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AccountIndexRequest;
use App\Http\Requests\Admin\AdminAccountStoreRequest;
use App\Http\Requests\Admin\AdminAccountUpdateRequest;
use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AccountController extends Controller
{
    # GET /api/v1/admin/accounts
    # Lista paginada de cuentas. Solo accesible para administradores (middleware "admin").
     #[OA\Get(
        path: '/api/v1/admin/accounts',
        summary: 'Listar cuentas',
        description: 'Lista paginada de todas las cuentas con su titular, con ordenamiento. Solo para administradores. La respuesta usa el formato de paginación de Laravel.',
        tags: ['Admin: cuentas'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'Número de página', schema: new OA\Schema(type: 'integer', minimum: 1, default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, description: 'Elementos por página (1 a 100)', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 15)),
            new OA\Parameter(name: 'sort', in: 'query', required: false, description: 'Campo por el que se ordena', schema: new OA\Schema(type: 'string', enum: ['id', 'balance', 'type', 'currency', 'created_at'], default: 'id')),
            new OA\Parameter(name: 'order', in: 'query', required: false, description: 'Sentido del ordenamiento', schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'], default: 'asc')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Listado paginado de cuentas',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'current_page', type: 'integer', example: 1),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/AdminAccount')),
                        new OA\Property(property: 'first_page_url', type: 'string', example: 'http://localhost:8000/api/v1/admin/accounts?page=1'),
                        new OA\Property(property: 'from', type: 'integer', nullable: true, example: 1),
                        new OA\Property(property: 'last_page', type: 'integer', example: 2),
                        new OA\Property(property: 'last_page_url', type: 'string', example: 'http://localhost:8000/api/v1/admin/accounts?page=2'),
                        new OA\Property(property: 'next_page_url', type: 'string', nullable: true, example: 'http://localhost:8000/api/v1/admin/accounts?page=2'),
                        new OA\Property(property: 'path', type: 'string', example: 'http://localhost:8000/api/v1/admin/accounts'),
                        new OA\Property(property: 'per_page', type: 'integer', example: 15),
                        new OA\Property(property: 'prev_page_url', type: 'string', nullable: true, example: null),
                        new OA\Property(property: 'to', type: 'integer', nullable: true, example: 15),
                        new OA\Property(property: 'total', type: 'integer', example: 20),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 403, description: 'El usuario autenticado no es administrador', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenError')),
            new OA\Response(response: 422, description: 'Error de validación en los parámetros de consulta', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function index(AccountIndexRequest $request): JsonResponse
    {
        // Validamos los datos
        $datos = $request->validated();

        // Cantidad de items por pagina (por defecto: 15)
        $perPage = $datos['per_page'] ?? 15;

        // Dato con el cual ordenamos (por defecto: id)
        $sort = $datos['sort'] ?? 'id';

        // Orden que usamos para organizar los datos (por defecto: asc)
        $direction = $datos['order'] ?? 'asc';

        // Creamos la query
        $query = Account::with('user');

        // Buscamos las cuentas
        $cuentas = $query->orderBy($sort, $direction)
                        ->paginate($perPage)
                        ->appends($request->query());

        // Mostramos las cuentas y sus detalles.
        $cuentas->through(fn (Account $account) => [
            'id'       => $account->id,
            'cbu'      => $account->cbu,
            'type'     => $account->type,
            'currency' => $account->currency,
            'balance'  => $account->balance,
            'user'     => [
                'id'    => $account->user?->id,
                'name'  => $account->user?->name,
                'email' => $account->user?->email,
            ],
        ]);

        return response()->json($cuentas);
    }

    # GET /api/v1/admin/accounts/{account}
    # Detalle de una cuenta puntual. Solo accesible para administradores.
     #[OA\Get(
        path: '/api/v1/admin/accounts/{account}',
        summary: 'Ver detalle de una cuenta',
        description: 'Devuelve una cuenta con su titular. Solo para administradores.',
        tags: ['Admin: cuentas'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'account', in: 'path', required: true, description: 'ID de la cuenta', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Cuenta obtenida correctamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/AdminAccount'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 403, description: 'El usuario autenticado no es administrador', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenError')),
            new OA\Response(response: 404, description: 'La cuenta no existe', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundError')),
        ]
    )]
    public function show(Account $account): JsonResponse
    {
        $account->load('user');

        return response()->json([
            'success' => true,
            'data' => [
                'id'       => $account->id,
                'cbu'      => $account->cbu,
                'type'     => $account->type,
                'currency' => $account->currency,
                'balance'  => $account->balance,
                'user'     => [
                    'id'    => $account->user?->id,
                    'name'  => $account->user?->name,
                    'email' => $account->user?->email,
                ],
            ],
        ], 200);
    }

    # POST /api/v1/admin/accounts
    # Crea una cuenta para un usuario que todavia no tenga una.
    # El CBU se genera automaticamente y el balance siempre arranca en 0.00.
        #[OA\Post(
        path: '/api/v1/admin/accounts',
        summary: 'Crear cuenta',
        description: 'Crea una cuenta para un usuario que todavía no tenga una. El CBU se genera automáticamente y el saldo siempre arranca en 0: ni `cbu` ni `balance` se aceptan desde el cliente. Solo para administradores.',
        tags: ['Admin: cuentas'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['user_id', 'type', 'currency'],
                properties: [
                    new OA\Property(property: 'user_id', type: 'integer', description: 'ID de un usuario existente que no tenga cuenta', example: 3),
                    new OA\Property(property: 'type', type: 'string', enum: ['savings', 'checking'], example: 'savings'),
                    new OA\Property(property: 'currency', type: 'string', enum: ['ARS', 'USD'], example: 'ARS'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Cuenta creada correctamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/AdminAccount'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 403, description: 'El usuario autenticado no es administrador', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenError')),
            new OA\Response(response: 422, description: 'Error de validación: usuario inexistente, usuario que ya tiene cuenta, tipo o moneda inválidos', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function store(AdminAccountStoreRequest $request): JsonResponse
    {
        $datos = $request->validated();

        $account = Account::create([
            'user_id'  => $datos['user_id'],
            'cbu'      => Account::generarCbuUnico(),
            'type'     => $datos['type'],
            'currency' => $datos['currency'],
            # balance no se asigna: usa el default de la migracion (0.00)
        ]);

        # refresh() vuelve a leer el registro de la base de datos: sin esto,
        # 'balance' queda en null en memoria porque nunca se lo asignamos
        # explicitamente (aunque en la DB la columna sí tiene el default 0.00).
        $account->refresh();
        $account->load('user');

        return response()->json([
            'success' => true,
            'data' => [
                'id'       => $account->id,
                'cbu'      => $account->cbu,
                'type'     => $account->type,
                'currency' => $account->currency,
                'balance'  => $account->balance,
                'user'     => [
                    'id'    => $account->user?->id,
                    'name'  => $account->user?->name,
                    'email' => $account->user?->email,
                ],
            ],
        ], 201);
    }

    # PUT/PATCH /api/v1/admin/accounts/{account}
    # Actualiza type y/o currency. No permite reasignar user_id ni tocar balance/cbu.
     #[OA\Put(
        path: '/api/v1/admin/accounts/{account}',
        summary: 'Actualizar una cuenta',
        description: 'Actualiza el tipo y/o la moneda de una cuenta. No permite reasignar el titular (`user_id`) ni modificar `balance` o `cbu`. Solo para administradores.',
        tags: ['Admin: cuentas'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'account', in: 'path', required: true, description: 'ID de la cuenta', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                description: 'Ambos campos son opcionales: se actualizan solo los enviados.',
                properties: [
                    new OA\Property(property: 'type', type: 'string', enum: ['savings', 'checking'], example: 'checking'),
                    new OA\Property(property: 'currency', type: 'string', enum: ['ARS', 'USD'], example: 'USD'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Cuenta actualizada correctamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/AdminAccount'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 403, description: 'El usuario autenticado no es administrador', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenError')),
            new OA\Response(response: 404, description: 'La cuenta no existe', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundError')),
            new OA\Response(response: 422, description: 'Error de validación (tipo o moneda inválidos)', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    #[OA\Patch(
        path: '/api/v1/admin/accounts/{account}',
        summary: 'Actualizar una cuenta (parcial)',
        description: 'Mismo comportamiento que PUT /api/v1/admin/accounts/{account}.',
        tags: ['Admin: cuentas'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'account', in: 'path', required: true, description: 'ID de la cuenta', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'type', type: 'string', enum: ['savings', 'checking'], example: 'checking'),
                    new OA\Property(property: 'currency', type: 'string', enum: ['ARS', 'USD'], example: 'USD'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Cuenta actualizada correctamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/AdminAccount'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 403, description: 'El usuario autenticado no es administrador', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenError')),
            new OA\Response(response: 404, description: 'La cuenta no existe', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundError')),
            new OA\Response(response: 422, description: 'Error de validación', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function update(AdminAccountUpdateRequest $request, Account $account): JsonResponse
    {
        $account->fill($request->validated());
        $account->save();

        $account->load('user');

        return response()->json([
            'success' => true,
            'data' => [
                'id'       => $account->id,
                'cbu'      => $account->cbu,
                'type'     => $account->type,
                'currency' => $account->currency,
                'balance'  => $account->balance,
                'user'     => [
                    'id'    => $account->user?->id,
                    'name'  => $account->user?->name,
                    'email' => $account->user?->email,
                ],
            ],
        ], 200);
    }

    # DELETE /api/v1/admin/accounts/{account}
    # Elimina una cuenta solo si no tiene saldo ni movimientos asociados.
    # movements.account_id tiene onDelete('set null'): si se dejara borrar
    # una cuenta con movimientos, esos movimientos quedarian "huerfanos"
    # (account_id = null) en silencio. Por eso se bloquea explicitamente.
    #[OA\Delete(
        path: '/api/v1/admin/accounts/{account}',
        summary: 'Eliminar una cuenta',
        description: 'Elimina una cuenta solo si su saldo es 0 y no tiene movimientos asociados. Si tiene saldo o movimientos, responde 422 y no elimina nada. Solo para administradores.',
        tags: ['Admin: cuentas'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'account', in: 'path', required: true, description: 'ID de la cuenta', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Cuenta eliminada correctamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Cuenta eliminada correctamente.'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 403, description: 'El usuario autenticado no es administrador', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenError')),
            new OA\Response(response: 404, description: 'La cuenta no existe', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundError')),
            new OA\Response(
                response: 422,
                description: 'La cuenta tiene saldo o movimientos asociados',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'No se puede eliminar la cuenta: el saldo no es 0.'),
                    ]
                )
            ),
        ]
    )]
    public function destroy(Account $account): JsonResponse
    {
        if ((float) $account->balance !== 0.0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar la cuenta: el saldo no es 0.',
            ], 422);
        }

        if ($account->movements()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar la cuenta: tiene movimientos asociados.',
            ], 422);
        }

        $account->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cuenta eliminada correctamente.',
        ], 200);
    }
}
