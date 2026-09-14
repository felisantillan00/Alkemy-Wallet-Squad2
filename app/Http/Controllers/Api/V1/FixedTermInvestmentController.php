<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\FixedTermSimulationRequest;
use Illuminate\Http\JsonResponse;

class FixedTermInvestmentController extends Controller
{
    # POST /api/v1/investments/fixed-term/simulate
    # Simula un plazo fijo a interés simple. Es puramente informativo:
    # no toca el saldo de la cuenta ni crea movimientos.
    public function simulate(FixedTermSimulationRequest $request): JsonResponse
    {
        $monto = (float) $request->validated()['amount'];
        $dias = $request->plazoEnDias();

        $tna = config('investments.fixed_term.tna');
        $baseDias = config('investments.fixed_term.day_base');

        $interes = $monto * $tna * ($dias / $baseDias);
        $total = $monto + $interes;

        $fechaCreacion = now()->startOfDay();
        $fechaFinalizacion = $request->fechaFinalizacion();

        return response()->json([
            'success' => true,
            'data' => [
                'fecha_creacion'     => $fechaCreacion->toDateString(),
                'fecha_finalizacion' => $fechaFinalizacion->toDateString(),
                'plazo_dias'         => $dias,
                'monto_invertido'    => number_format($monto, 2, '.', ''),
                'interes_ganado'     => number_format($interes, 2, '.', ''),
                'total_a_cobrar'     => number_format($total, 2, '.', ''),
                'tasa' => [
                    'tna'     => $tna,
                    'formula' => 'interés = monto × TNA × (días / ' . $baseDias . ')',
                    'nota'    => 'TNA = Tasa Nominal Anual, interés simple (no capitaliza). La tasa es fija y no puede elegirse desde el cliente.',
                ],
            ],
        ], 200);
    }
}
