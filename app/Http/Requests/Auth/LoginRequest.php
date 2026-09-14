<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    # EL LOGIN ES PUBLICO: NO REQUIERE AUTORIZACION PREVIA
    public function authorize(): bool
    {
        return true;
    }

    # REGLAS DE VALIDACION DEL LOGIN
    public function rules(): array
    {
        return [
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    # MENSAJES DE ERROR EN ESPAÑOL
    public function messages(): array
    {
        return [
            'email.required'    => 'El email es obligatorio.',
            'email.email'       => 'El email debe tener un formato válido.',
            'password.required' => 'La contraseña es obligatoria.',
        ];
    }
}