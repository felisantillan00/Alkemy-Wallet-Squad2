<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\MovementIndexRequest;
use App\Models\Movement;
use Illuminate\Http\JsonResponse;

class MovementController extends Controller
{
    # GET /api/v1/movements
    # Lista paginada de los movimientos de la cuenta autenticada, mas reciente primero por defecto.
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
