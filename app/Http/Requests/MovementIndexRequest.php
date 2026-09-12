<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MovementIndexRequest extends FormRequest
{
    # CUALQUIER USUARIO AUTENTICADO PUEDE CONSULTAR SUS PROPIOS MOVIMIENTOS
    public function authorize(): bool
    {
        return true;
    }

    # REGLAS DE VALIDACION DE PAGINACION Y ORDEN
    public function rules(): array
    {
        return [
            'page'     => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'order'    => ['sometimes', 'in:asc,desc'],
        ];
    }

    # MENSAJES DE ERROR EN ESPAÑOL
    public function messages(): array
    {
        return [
            'page.integer'     => 'La página debe ser un número entero.',
            'page.min'         => 'La página debe ser mayor o igual a 1.',
            'per_page.integer' => 'La cantidad por página debe ser un número entero.',
            'per_page.min'     => 'La cantidad por página debe ser mayor o igual a 1.',
            'per_page.max'     => 'La cantidad por página no puede superar 100.',
            'order.in'         => 'El orden debe ser "asc" o "desc".',
        ];
    }
}
