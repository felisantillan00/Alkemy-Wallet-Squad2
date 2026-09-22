<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MovementIndexRequest;
use App\Http\Requests\Admin\StoreMovementRequest;
use App\Http\Requests\Admin\UpdateMovementRequest;
use App\Models\Movement;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/**
 * CRUD administrativo de movimientos.
 *
 * IMPORTANTE: este CRUD opera sobre el HISTORIAL de movimientos y NO recalcula
 * el saldo de la cuenta. Crear, editar o eliminar un movimiento desde acá no
 * modifica accounts.balance.
 *
 * Es intencional: los saldos solo se mueven por las operaciones reales de la
 * billetera (POST /deposits y POST /transfers), que aplican sus validaciones de
 * negocio dentro de una transaccion. Este CRUD es una herramienta de correccion
 * del historial, no un atajo para acreditar dinero.
 */
class MovementController extends Controller
{
    # GET /api/v1/admin/movements
    # Lista paginada de todos los movimientos, con orden y filtros por cuenta, usuario o tipo.
      #[OA\Get(
        path: '/api/v1/admin/movements',
        summary: 'Listar movimientos',
        description: 'Lista paginada de todos los movimientos, con filtros por cuenta, usuario o tipo, y ordenamiento. Solo para administradores. La respuesta usa el formato de paginación de Laravel.',
        tags: ['Admin: movimientos'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'Número de página', schema: new OA\Schema(type: 'integer', minimum: 1, default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, description: 'Elementos por página (1 a 100)', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 15)),
            new OA\Parameter(name: 'sort', in: 'query', required: false, description: 'Campo por el que se ordena', schema: new OA\Schema(type: 'string', enum: ['id', 'created_at', 'amount', 'type'], default: 'id')),
            new OA\Parameter(name: 'order', in: 'query', required: false, description: 'Sentido del ordenamiento', schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'], default: 'desc')),
            new OA\Parameter(name: 'account_id', in: 'query', required: false, description: 'Filtra por cuenta', schema: new OA\Schema(type: 'integer', example: 1)),
            new OA\Parameter(name: 'user_id', in: 'query', required: false, description: 'Filtra por usuario (resuelto a través de su cuenta)', schema: new OA\Schema(type: 'integer', example: 2)),
            new OA\Parameter(name: 'type', in: 'query', required: false, description: 'Filtra por tipo de movimiento', schema: new OA\Schema(type: 'string', enum: ['deposit', 'transfer_out', 'transfer_in'])),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Listado paginado de movimientos',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'current_page', type: 'integer', example: 1),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/AdminMovement')),
                        new OA\Property(property: 'first_page_url', type: 'string', example: 'http://localhost:8000/api/v1/admin/movements?page=1'),
                        new OA\Property(property: 'from', type: 'integer', nullable: true, example: 1),
                        new OA\Property(property: 'last_page', type: 'integer', example: 4),
                        new OA\Property(property: 'last_page_url', type: 'string', example: 'http://localhost:8000/api/v1/admin/movements?page=4'),
                        new OA\Property(property: 'next_page_url', type: 'string', nullable: true, example: 'http://localhost:8000/api/v1/admin/movements?page=2'),
                        new OA\Property(property: 'path', type: 'string', example: 'http://localhost:8000/api/v1/admin/movements'),
                        new OA\Property(property: 'per_page', type: 'integer', example: 15),
                        new OA\Property(property: 'prev_page_url', type: 'string', nullable: true, example: null),
                        new OA\Property(property: 'to', type: 'integer', nullable: true, example: 15),
                        new OA\Property(property: 'total', type: 'integer', example: 50),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 403, description: 'El usuario autenticado no es administrador', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenError')),
            new OA\Response(response: 422, description: 'Error de validación en los parámetros de consulta', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function index(MovementIndexRequest $request): JsonResponse
    {
        $datos = $request->validated();

        $perPage   = $datos['per_page'] ?? 15;
        $sort      = $datos['sort'] ?? 'id';
        $direction = $datos['order'] ?? 'desc';

        $query = Movement::with('account.user');

        // Filtro por cuenta.
        if (isset($datos['account_id'])) {
            $query->where('account_id', $datos['account_id']);
        }

        // Filtro por usuario: se resuelve a traves de la cuenta.
        if (isset($datos['user_id'])) {
            $query->whereHas('account', fn ($q) => $q->where('user_id', $datos['user_id']));
        }

        // Filtro por tipo de movimiento.
        if (isset($datos['type'])) {
            $query->where('type', $datos['type']);
        }

        $movimientos = $query->orderBy($sort, $direction)
            ->paginate($perPage)
            ->appends($request->query());

        $movimientos->through(fn (Movement $movimiento) => $this->formatear($movimiento));

        return response()->json($movimientos);
    }

    # POST /api/v1/admin/movements
    # Crea un movimiento en el historial. NO modifica el saldo de la cuenta.
    #[OA\Post(
        path: '/api/v1/admin/movements',
        summary: 'Crear movimiento',
        description: 'Crea un movimiento en el historial. **No modifica el saldo de la cuenta**: los saldos solo se mueven por POST /deposits y POST /transfers. Solo para administradores.',
        tags: ['Admin: movimientos'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['account_id', 'type', 'amount'],
                properties: [
                    new OA\Property(property: 'account_id', type: 'integer', description: 'ID de una cuenta existente', example: 1),
                    new OA\Property(property: 'type', type: 'string', enum: ['deposit', 'transfer_out', 'transfer_in'], example: 'deposit'),
                    new OA\Property(property: 'amount', type: 'number', format: 'float', description: 'Mayor que 0', example: 150.00),
                    new OA\Property(property: 'counterpart_cbu', type: 'string', nullable: true, description: 'CBU de 22 dígitos de una cuenta existente', example: '0000003100000000000002'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Movimiento creado exitosamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Movimiento creado exitosamente. El saldo de la cuenta no fue modificado.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'movement', ref: '#/components/schemas/AdminMovement'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 403, description: 'El usuario autenticado no es administrador', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenError')),
            new OA\Response(response: 422, description: 'Error de validación (cuenta o CBU inexistente, tipo inválido, monto menor o igual a 0)', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function store(StoreMovementRequest $request): JsonResponse
    {
        $movimiento = Movement::create($request->validated());

        $movimiento->load('account.user');

        return response()->json([
            'message' => 'Movimiento creado exitosamente. El saldo de la cuenta no fue modificado.',
            'data' => [
                'movement' => $this->formatear($movimiento),
            ],
        ], 201);
    }

    # GET /api/v1/admin/movements/{id}
    # Detalle de un movimiento.
      #[OA\Get(
        path: '/api/v1/admin/movements/{id}',
        summary: 'Ver detalle de un movimiento',
        description: 'Devuelve un movimiento con su cuenta, moneda y usuario asociado. Solo para administradores.',
        tags: ['Admin: movimientos'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID del movimiento', schema: new OA\Schema(type: 'integer', example: 12)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Movimiento obtenido correctamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'movement', ref: '#/components/schemas/AdminMovement'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 403, description: 'El usuario autenticado no es administrador', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenError')),
            new OA\Response(response: 404, description: 'El movimiento no existe', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundError')),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $movimiento = Movement::with('account.user')->findOrFail($id);

        return response()->json([
            'data' => [
                'movement' => $this->formatear($movimiento),
            ],
        ]);
    }

    # PUT /api/v1/admin/movements/{id}
    # Edita un movimiento del historial. NO recalcula el saldo de la cuenta.
       #[OA\Put(
        path: '/api/v1/admin/movements/{id}',
        summary: 'Actualizar un movimiento',
        description: 'Edita un movimiento del historial. Actualización parcial: enviá solo los campos a modificar. **No recalcula el saldo de la cuenta**. Solo para administradores.',
        tags: ['Admin: movimientos'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID del movimiento', schema: new OA\Schema(type: 'integer', example: 12)),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                description: 'Todos los campos son opcionales: se actualizan solo los enviados.',
                properties: [
                    new OA\Property(property: 'account_id', type: 'integer', description: 'ID de una cuenta existente', example: 1),
                    new OA\Property(property: 'type', type: 'string', enum: ['deposit', 'transfer_out', 'transfer_in'], example: 'transfer_out'),
                    new OA\Property(property: 'amount', type: 'number', format: 'float', description: 'Mayor que 0', example: 200.00),
                    new OA\Property(property: 'counterpart_cbu', type: 'string', nullable: true, description: 'CBU de 22 dígitos de una cuenta existente', example: '0000003100000000000002'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Movimiento actualizado exitosamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Movimiento actualizado exitosamente. El saldo de la cuenta no fue recalculado.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'movement', ref: '#/components/schemas/AdminMovement'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 403, description: 'El usuario autenticado no es administrador', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenError')),
            new OA\Response(response: 404, description: 'El movimiento no existe', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundError')),
            new OA\Response(response: 422, description: 'Error de validación', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function update(UpdateMovementRequest $request, int $id): JsonResponse
    {
        $movimiento = Movement::findOrFail($id);

        $movimiento->update($request->validated());

        $movimiento->load('account.user');

        return response()->json([
            'message' => 'Movimiento actualizado exitosamente. El saldo de la cuenta no fue recalculado.',
            'data' => [
                'movement' => $this->formatear($movimiento),
            ],
        ]);
    }

    # DELETE /api/v1/admin/movements/{id}
    # Elimina un movimiento del historial. NO devuelve el importe al saldo.
       #[OA\Delete(
        path: '/api/v1/admin/movements/{id}',
        summary: 'Eliminar un movimiento',
        description: 'Elimina un movimiento del historial. **No devuelve el importe al saldo de la cuenta**. Solo para administradores.',
        tags: ['Admin: movimientos'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID del movimiento', schema: new OA\Schema(type: 'integer', example: 12)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Movimiento eliminado exitosamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Movimiento eliminado exitosamente. El saldo de la cuenta no fue recalculado.'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 403, description: 'El usuario autenticado no es administrador', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenError')),
            new OA\Response(response: 404, description: 'El movimiento no existe', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundError')),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        $movimiento = Movement::findOrFail($id);

        $movimiento->delete();

        return response()->json([
            'message' => 'Movimiento eliminado exitosamente. El saldo de la cuenta no fue recalculado.',
        ]);
    }

    /**
     * Devuelve el movimiento con su cuenta, moneda y CBU asociado,
     * como pide el criterio de aceptacion del ticket.
     */
    private function formatear(Movement $movimiento): array
    {
        return [
            'id'              => $movimiento->id,
            'type'            => $movimiento->type,
            'amount'          => number_format((float) $movimiento->amount, 2, '.', ''),
            'date'            => $movimiento->created_at->toISOString(),
            'counterpart_cbu' => $movimiento->counterpart_cbu,
            'account' => [
                'id'       => $movimiento->account->id,
                'cbu'      => $movimiento->account->cbu,
                'type'     => $movimiento->account->type,
                'currency' => $movimiento->account->currency,
                'user' => [
                    'id'    => $movimiento->account->user?->id,
                    'name'  => $movimiento->account->user?->name,
                    'email' => $movimiento->account->user?->email,
                ],
            ],
        ];
    }
}