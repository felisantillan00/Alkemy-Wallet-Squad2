<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    # EL REGISTRO ES PUBLICO: NO REQUIERE AUTORIZACION PREVIA
    public function authorize(): bool
    {
        return true;
    }

    # REGLAS DE VALIDACION DEL REGISTRO
    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
        ];
    }

    # MENSAJES DE ERROR EN ESPAÑOL
    public function messages(): array
    {
        return [
            'name.required'      => 'El nombre es obligatorio.',
            'email.required'     => 'El email es obligatorio.',
            'email.email'        => 'El email debe tener un formato válido.',
            'email.unique'       => 'Ya existe un usuario registrado con ese email.',
            'password.required'  => 'La contraseña es obligatoria.',
            'password.min'       => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
        ];
    }
}