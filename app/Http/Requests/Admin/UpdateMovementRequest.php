<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMovementRequest extends FormRequest
{
    # La autorizacion la resuelve el middleware "admin" en las rutas.
    public function authorize(): bool
    {
        return true;
    }

    # Los campos son opcionales: se envian solo los que se quieren modificar.
    public function rules(): array
    {
        return [
            'account_id'      => ['sometimes', 'integer', 'exists:accounts,id'],
            'type'            => ['sometimes', 'string', 'in:deposit,transfer_out,transfer_in'],
            'amount'          => ['sometimes', 'numeric', 'gt:0'],
            'counterpart_cvu' => ['nullable', 'string', 'size:22', 'exists:accounts,cvu'],
        ];
    }

    public function messages(): array
    {
        return [
            'account_id.exists'      => 'La cuenta indicada no existe.',
            'type.in'                => 'El tipo debe ser deposit, transfer_out o transfer_in.',
            'amount.gt'              => 'El monto debe ser mayor que 0.',
            'counterpart_cvu.size'   => 'El CVU debe tener 22 dígitos.',
            'counterpart_cvu.exists' => 'El CVU indicado no corresponde a ninguna cuenta.',
        ];
    }
}