<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminAccountStoreRequest extends FormRequest
{
    # LA AUTORIZACION DE ROL YA LA RESUELVE EL MIDDLEWARE "admin" EN LA RUTA
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            # EL USUARIO DEBE EXISTIR Y NO TENER YA UNA CUENTA (accounts.user_id ES UNICO)
            'user_id' => ['required', 'integer', 'exists:users,id', 'unique:accounts,user_id'],

            # TIPO Y MONEDA VALIDOS SEGUN WAL-013
            'type' => ['required', 'string', 'in:savings,checking'],
            'currency' => ['required', 'string', 'in:ARS,USD'],

            # NOTA: 'cbu' y 'balance' NO se aceptan desde el cliente.
            # El CBU se genera con Account::generarCbuUnico() y el balance
            # de una cuenta nueva siempre arranca en 0.00 (no es fillable
            # y en el resto del proyecto se toca solo via depositos/transferencias).
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Debe indicar el usuario para el cual se crea la cuenta.',
            'user_id.exists' => 'El usuario indicado no existe.',
            'user_id.unique' => 'El usuario indicado ya tiene una cuenta asociada.',
            'type.required' => 'Debe indicar el tipo de cuenta.',
            'type.in' => 'El tipo de cuenta debe ser savings o checking.',
            'currency.required' => 'Debe indicar la moneda de la cuenta.',
            'currency.in' => 'La moneda debe ser ARS o USD.',
        ];
    }
}
