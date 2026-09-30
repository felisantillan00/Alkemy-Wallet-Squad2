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
            'destination_cvu' => ['required', 'string', 'exists:accounts,cvu'],
            'amount'          => ['required', 'numeric', 'min:0.01'],
        ];
    }

    # MENSAJES DE ERROR
    public function messages(): array
    {
        return [
            'destination_cvu.required' => 'El CVU de destino es obligatorio.',
            'destination_cvu.exists'   => 'No existe ninguna cuenta con ese CVU.',
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

            if ($this->filled('destination_cvu') && $this->input('destination_cvu') === $cuentaOrigen->cvu) {
                $validator->errors()->add('destination_cvu', 'No podés transferirte a tu propia cuenta.');
            }

            if ($this->filled('amount') && is_numeric($this->input('amount')) && $cuentaOrigen->balance < $this->input('amount')) {
                $validator->errors()->add('amount', 'Saldo insuficiente para realizar la transferencia.');
            }
        });
    }
}
