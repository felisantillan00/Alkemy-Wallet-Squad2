<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\FixedTermSimulationRequest;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
class FixedTermInvestmentController extends Controller
{
    # POST /api/v1/investments/fixed-term/simulate
    # Simula un plazo fijo a interés simple. Es puramente informativo:
    # no toca el saldo de la cuenta ni crea movimientos.
       #[OA\Post(
        path: '/api/v1/investments/fixed-term/simulate',
        summary: 'Simular un plazo fijo',
        description: "Simulación informativa a interés simple: no modifica el saldo ni crea movimientos.\n\nIndicá **term_days** o **end_date**, nunca ambos. El plazo debe ser de entre 30 y 365 días.\n\nLa tasa es fija (TNA 30%) y se toma del servidor: cualquier tasa enviada por el cliente se ignora. Fórmula: interés = monto × TNA × (días / 365).",
        tags: ['Plazo fijo'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['amount'],
                properties: [
                    new OA\Property(property: 'amount', type: 'number', format: 'float', minimum: 0.01, example: 10000.00),
                    new OA\Property(property: 'term_days', type: 'integer', nullable: true, minimum: 30, maximum: 365, description: 'Plazo en días corridos. Excluyente con end_date.', example: 90),
                    new OA\Property(property: 'end_date', type: 'string', format: 'date', nullable: true, description: 'Fecha de finalización, posterior a hoy. Excluyente con term_days.', example: '2026-12-31'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Simulación calculada correctamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'fecha_creacion', type: 'string', format: 'date', example: '2026-09-22'),
                                new OA\Property(property: 'fecha_finalizacion', type: 'string', format: 'date', example: '2026-12-21'),
                                new OA\Property(property: 'plazo_dias', type: 'integer', example: 90),
                                new OA\Property(property: 'monto_invertido', type: 'string', example: '10000.00'),
                                new OA\Property(property: 'interes_ganado', type: 'string', example: '739.73'),
                                new OA\Property(property: 'total_a_cobrar', type: 'string', example: '10739.73'),
                                new OA\Property(
                                    property: 'tasa',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'tna', type: 'number', format: 'float', example: 0.30),
                                        new OA\Property(property: 'formula', type: 'string', example: 'interés = monto × TNA × (días / 365)'),
                                        new OA\Property(property: 'nota', type: 'string', example: 'TNA = Tasa Nominal Anual, interés simple (no capitaliza). La tasa es fija y no puede elegirse desde el cliente.'),
                                    ]
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 422, description: 'Error de validación: monto inválido, plazo y fecha juntos, ninguno de los dos, o plazo fuera del rango de 30 a 365 días', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
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
