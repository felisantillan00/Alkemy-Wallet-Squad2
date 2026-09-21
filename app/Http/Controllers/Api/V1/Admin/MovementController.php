<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MovementIndexRequest;
use App\Http\Requests\Admin\StoreMovementRequest;
use App\Http\Requests\Admin\UpdateMovementRequest;
use App\Models\Movement;
use Illuminate\Http\JsonResponse;

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