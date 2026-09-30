<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    # El panel del frontend envía el rol por nombre ("user"/"admin"); se traduce a role_id.
    # Si viene role_id, tiene prioridad.
    protected function prepareForValidation(): void
    {
        if (! $this->filled('role_id') && $this->filled('role')) {
            $roleId = Role::where('role_name', $this->input('role'))->value('id');

            if ($roleId) {
                $this->merge(['role_id' => $roleId]);
            }
        }
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
            // La confirmación es opcional (el panel no la envía); si se envía, debe coincidir.
            'password' => array_merge(['sometimes', 'string', 'min:8'], $this->has('password_confirmation') ? ['confirmed'] : []),
            // Mismas reglas que el perfil propio; null borra el valor.
            'age'      => 'sometimes|nullable|integer|between:1,120',
            'image'    => 'sometimes|nullable|url|max:2048',
            'role_id'  => 'sometimes|exists:roles,id',
            'role'     => 'sometimes|in:user,admin',
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
            'age.between' => 'La edad debe estar entre 1 y 120.',

            'image.url' => 'El link de la imagen debe tener una url válida.',
            'image.max' => 'El link de la imagen no debe superar los 2048 caracteres.',

            'role_id.exists' => 'El id del rol debe ser un id válido.',
            'role.in' => 'El rol debe ser "user" o "admin".',
        ];
    }
}