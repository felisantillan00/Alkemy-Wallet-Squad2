<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminAccountUpdateRequest extends FormRequest
{
    # LA AUTORIZACION DE ROL YA LA RESUELVE EL MIDDLEWARE "admin" EN LA RUTA
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            # SOLO SE PUEDEN ACTUALIZAR type Y currency.
            # 'user_id' NO es un campo valido aca a proposito: reasignar la
            # titularidad de una cuenta no es una operacion de este endpoint,
            # para evitar reasignaciones accidentales.
            # 'balance' tampoco es valido aca: se toca solo via depositos/transferencias.
            'type' => ['sometimes', 'string', 'in:savings,checking'],
            'currency' => ['sometimes', 'string', 'in:ARS,USD'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'El tipo de cuenta debe ser savings o checking.',
            'currency.in' => 'La moneda debe ser ARS o USD.',
        ];
    }
}
