<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\MovementIndexRequest;
use App\Models\Movement;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class MovementController extends Controller
{
    # GET /api/v1/movements
    # Lista paginada de los movimientos de la cuenta autenticada, mas reciente primero por defecto.
        #[OA\Get(
        path: '/api/v1/movements',
        summary: 'Listar movimientos propios',
        description: 'Lista paginada de los movimientos de la cuenta del usuario autenticado, del más reciente al más antiguo por defecto. Solo devuelve movimientos de la cuenta propia. La respuesta usa el formato de paginación de Laravel.',
        tags: ['Movimientos'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'Número de página (mínimo 1)', schema: new OA\Schema(type: 'integer', minimum: 1, default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, description: 'Elementos por página (1 a 100)', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 15)),
            new OA\Parameter(name: 'order', in: 'query', required: false, description: 'Orden por fecha de creación', schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'], default: 'desc')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Listado paginado de movimientos',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'current_page', type: 'integer', example: 1),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Movement')),
                        new OA\Property(property: 'first_page_url', type: 'string', example: 'http://localhost:8000/api/v1/movements?page=1'),
                        new OA\Property(property: 'from', type: 'integer', nullable: true, example: 1),
                        new OA\Property(property: 'last_page', type: 'integer', example: 3),
                        new OA\Property(property: 'last_page_url', type: 'string', example: 'http://localhost:8000/api/v1/movements?page=3'),
                        new OA\Property(property: 'next_page_url', type: 'string', nullable: true, example: 'http://localhost:8000/api/v1/movements?page=2'),
                        new OA\Property(property: 'path', type: 'string', example: 'http://localhost:8000/api/v1/movements'),
                        new OA\Property(property: 'per_page', type: 'integer', example: 15),
                        new OA\Property(property: 'prev_page_url', type: 'string', nullable: true, example: null),
                        new OA\Property(property: 'to', type: 'integer', nullable: true, example: 15),
                        new OA\Property(property: 'total', type: 'integer', example: 42),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 422, description: 'Error de validación (page, per_page u order inválidos)', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function index(MovementIndexRequest $request): JsonResponse
    {
        $datos = $request->validated();

        $user = auth('api')->user();
        $user->load('account');
        $cuenta = $user->account;

        $perPage = $datos['per_page'] ?? 15;
        $orden   = $datos['order'] ?? 'desc';

        $movimientos = Movement::where('account_id', $cuenta->id)
            ->orderBy('created_at', $orden)
            ->paginate($perPage)
            ->appends($request->query());

        $movimientos->through(fn (Movement $movimiento) => [
            'type'            => $movimiento->type,
            'amount'          => number_format((float) $movimiento->amount, 2, '.', ''),
            'date'            => $movimiento->created_at->toISOString(),
            'counterpart_cbu' => $movimiento->counterpart_cbu,
        ]);

        return response()->json($movimientos);
    }
}
