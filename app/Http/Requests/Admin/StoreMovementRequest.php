<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreMovementRequest extends FormRequest
{
    # La autorizacion la resuelve el middleware "admin" en las rutas.
    public function authorize(): bool
    {
        return true;
    }

    # Validacion de cuenta, tipo, monto y CVU contraparte.
    public function rules(): array
    {
        return [
            'account_id'      => ['required', 'integer', 'exists:accounts,id'],
            'type'            => ['required', 'string', 'in:deposit,transfer_out,transfer_in'],
            'amount'          => ['required', 'numeric', 'gt:0'],
            'counterpart_cvu' => ['nullable', 'string', 'size:22', 'exists:accounts,cvu'],
        ];
    }

    public function messages(): array
    {
        return [
            'account_id.required'     => 'La cuenta es obligatoria.',
            'account_id.exists'       => 'La cuenta indicada no existe.',
            'type.required'           => 'El tipo de movimiento es obligatorio.',
            'type.in'                 => 'El tipo debe ser deposit, transfer_out o transfer_in.',
            'amount.required'         => 'El monto es obligatorio.',
            'amount.gt'               => 'El monto debe ser mayor que 0.',
            'counterpart_cvu.size'    => 'El CVU debe tener 22 dígitos.',
            'counterpart_cvu.exists'  => 'El CVU indicado no corresponde a ninguna cuenta.',
        ];
    }
}