<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->route('id'); 

        return [
            'name'     => 'sometimes|string|max:255',
            'email'    => 'sometimes|email|unique:users,email,' . $userId,
            'password' => 'sometimes|string|min:8|confirmed',
            'age'      => 'sometimes|integer|min:18|max:100',
            'image'    => 'sometimes|url|max:2048',
            'role_id'  => 'sometimes|exists:roles,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.string' => 'El nombre debe ser una cadena de texto válida.',
            'name.max' => 'El nombre debe tener un máximo de 255 caracteres.',

            'email.email' => 'El email debe tener un formato válido.',
            'email.unique' => 'Email ya registrado. Por favor intente con otro.',

            'password.string' => 'La contraseña debe ser una cadena de texto válida.',
            'password.min' => 'La contraseña debe tener por lo menos 8 caracteres.',
            'password.confirmed' => 'La contraseña debe confirmarse.',

            'age.integer' => 'La edad debe ser un valor numérico.',
            'age.min' => 'La edad mínima es de 18 años.',
            'age.max' => 'La edad máxima es de 100 años.',

            'image.url' => 'El link de la imagen debe tener una url válida.',
            'image.max' => 'El link de la imagen no debe superar los 2048 caracteres.',

            'role_id.exists' => 'El id del rol debe ser un id válido.',
        ];
    }
}