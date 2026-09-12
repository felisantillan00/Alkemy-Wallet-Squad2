<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;

class TransferRequest extends FormRequest
{
    # CUALQUIER USUARIO AUTENTICADO PUEDE TRANSFERIR DESDE SU PROPIA CUENTA
    public function authorize(): bool
    {
        return true;
    }

    # REGLAS DE VALIDACION DE LA TRANSFERENCIA
    public function rules(): array
    {
        return [
            'destination_cbu' => ['required', 'string', 'exists:accounts,cbu'],
            'amount'          => ['required', 'numeric', 'min:0.01'],
        ];
    }

    # MENSAJES DE ERROR
    public function messages(): array
    {
        return [
            'destination_cbu.required' => 'El CBU de destino es obligatorio.',
            'destination_cbu.exists'   => 'No existe ninguna cuenta con ese CBU.',
            'amount.required'          => 'El monto es obligatorio.',
            'amount.numeric'           => 'El monto debe ser un número.',
            'amount.min'               => 'El monto debe ser mayor que 0.',
        ];
    }

    # VALIDACIONES QUE DEPENDEN DE LA CUENTA AUTENTICADA
    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            $cuentaOrigen = auth('api')->user()?->account;

            if (! $cuentaOrigen) {
                return;
            }

            if ($this->filled('destination_cbu') && $this->input('destination_cbu') === $cuentaOrigen->cbu) {
                $validator->errors()->add('destination_cbu', 'No podés transferirte a tu propia cuenta.');
            }

            if ($this->filled('amount') && is_numeric($this->input('amount')) && $cuentaOrigen->balance < $this->input('amount')) {
                $validator->errors()->add('amount', 'Saldo insuficiente para realizar la transferencia.');
            }
        });
    }
}
