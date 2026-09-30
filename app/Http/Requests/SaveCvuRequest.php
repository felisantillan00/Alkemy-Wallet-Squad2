<?php

namespace App\Http\Requests;

use App\Models\Account;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;

class SaveCvuRequest extends FormRequest
{
    # SOLO EL PROPIO USUARIO AUTENTICADO PUEDE MODIFICAR SU LISTA DE CVUs GUARDADOS
    public function authorize(): bool
    {
        return auth('api')->id() === (int) $this->route('idUser');
    }

    # EL CVU Y EL idUser VIENEN POR LA URL, NO POR EL BODY
    public function validationData()
    {
        return array_merge($this->all(), $this->route()->parameters());
    }

    # REGLAS DE VALIDACION DEL CVU A GUARDAR
    public function rules(): array
    {
        return [
            'cvu'    => ['required', 'string', 'size:22', 'exists:accounts,cvu'],
            'idUser' => ['required', 'integer'],
        ];
    }

    # MENSAJES DE ERROR EN ESPAÑOL
    public function messages(): array
    {
        return [
            'cvu.required' => 'El CVU es obligatorio.',
            'cvu.size'     => 'El CVU debe tener 22 dígitos.',
            'cvu.exists'   => 'No existe ninguna cuenta con ese CVU.',
        ];
    }

    # VALIDACIONES QUE DEPENDEN DEL USUARIO AUTENTICADO
    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            $cvu = $this->route('cvu');
            $user = auth('api')->user();

            if (! $user || ! $cvu) {
                return;
            }

            $user->load('account');

            if ($cvu === $user->account?->cvu) {
                $validator->errors()->add('cvu', 'No podés guardar tu propio CVU.');

                return;
            }

            $cuenta = Account::where('cvu', $cvu)->first();

            if ($cuenta && $user->savedAccounts()->where('account_id', $cuenta->id)->exists()) {
                $validator->errors()->add('cvu', 'Ese CVU ya está guardado.');
            }
        });
    }
}
