<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Plazo fijo
    |--------------------------------------------------------------------------
    |
    | Parámetros de la simulación de plazo fijo (WAL-014). La tasa NO es
    | configurable por el cliente: siempre se usa este valor, nunca uno
    | recibido en el request.
    |
    | - tna: Tasa Nominal Anual, como fracción (0.30 = 30%).
    | - day_base: base de días del año usada para prorratear la tasa (365).
    | - min_days / max_days: plazo permitido, en días corridos.
    |
    | Fórmula de interés simple: interés = monto × tna × (días / day_base)
    |
    */

    'fixed_term' => [
        'tna' => 0.30,
        'day_base' => 365,
        'min_days' => 30,
        'max_days' => 365,
    ],

];
