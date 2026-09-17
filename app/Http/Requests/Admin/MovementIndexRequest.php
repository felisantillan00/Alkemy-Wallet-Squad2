<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class MovementIndexRequest extends FormRequest
{
    # La autorizacion la resuelve el middleware "admin" en las rutas.
    public function authorize(): bool
    {
        return true;
    }

    # Parametros de paginado, orden y filtros del listado administrativo.
    public function rules(): array
    {
        return [
            'per_page'   => ['sometimes', 'integer', 'min:1', 'max:100'],
            'order'      => ['sometimes', 'string', 'in:asc,desc'],
            'sort'       => ['sometimes', 'string', 'in:id,created_at,amount,type'],
            'account_id' => ['sometimes', 'integer', 'exists:accounts,id'],
            'user_id'    => ['sometimes', 'integer', 'exists:users,id'],
            'type'       => ['sometimes', 'string', 'in:deposit,transfer_out,transfer_in'],
        ];
    }

    public function messages(): array
    {
        return [
            'per_page.max'      => 'El máximo de elementos por página es 100.',
            'order.in'          => 'El orden debe ser asc o desc.',
            'sort.in'           => 'Solo se puede ordenar por id, created_at, amount o type.',
            'account_id.exists' => 'La cuenta indicada no existe.',
            'user_id.exists'    => 'El usuario indicado no existe.',
            'type.in'           => 'El tipo debe ser deposit, transfer_out o transfer_in.',
        ];
    }
}