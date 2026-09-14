<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DepositRequest extends FormRequest
{
    # CUALQUIER USUARIO AUTENTICADO PUEDE DEPOSITAR EN SU PROPIA CUENTA
    public function authorize(): bool
    {
        return true;
    }

    # REGLAS DE VALIDACION DEL DEPOSITO
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    # MENSAJES DE ERROR EN ESPAÑOL
    public function messages(): array
    {
        return [
            'amount.required' => 'El monto es obligatorio.',
            'amount.numeric'  => 'El monto debe ser un número.',
            'amount.min'      => 'El monto debe ser mayor que 0.',
        ];
    }
}