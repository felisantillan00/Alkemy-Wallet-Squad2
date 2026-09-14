<?php

namespace App\Http\Requests;

use App\Models\Account;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;

class SaveCbuRequest extends FormRequest
{
    # SOLO EL PROPIO USUARIO AUTENTICADO PUEDE MODIFICAR SU LISTA DE CBUs GUARDADOS
    public function authorize(): bool
    {
        return auth('api')->id() === (int) $this->route('idUser');
    }

    # EL CBU Y EL idUser VIENEN POR LA URL, NO POR EL BODY
    public function validationData()
    {
        return array_merge($this->all(), $this->route()->parameters());
    }

    # REGLAS DE VALIDACION DEL CBU A GUARDAR
    public function rules(): array
    {
        return [
            'cbu'    => ['required', 'string', 'size:22', 'exists:accounts,cbu'],
            'idUser' => ['required', 'integer'],
        ];
    }

    # MENSAJES DE ERROR EN ESPAÑOL
    public function messages(): array
    {
        return [
            'cbu.required' => 'El CBU es obligatorio.',
            'cbu.size'     => 'El CBU debe tener 22 dígitos.',
            'cbu.exists'   => 'No existe ninguna cuenta con ese CBU.',
        ];
    }

    # VALIDACIONES QUE DEPENDEN DEL USUARIO AUTENTICADO
    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            $cbu = $this->route('cbu');
            $user = auth('api')->user();

            if (! $user || ! $cbu) {
                return;
            }

            $user->load('account');

            if ($cbu === $user->account?->cbu) {
                $validator->errors()->add('cbu', 'No podés guardar tu propio CBU.');

                return;
            }

            $cuenta = Account::where('cbu', $cbu)->first();

            if ($cuenta && $user->savedAccounts()->where('account_id', $cuenta->id)->exists()) {
                $validator->errors()->add('cbu', 'Ese CBU ya está guardado.');
            }
        });
    }
}
