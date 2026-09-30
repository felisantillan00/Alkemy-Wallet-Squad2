<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            // La confirmación es opcional (el panel no la envía); si se envía, debe coincidir.
            'password' => array_merge(['required', 'string', 'min:8'], $this->has('password_confirmation') ? ['confirmed'] : []),
            // Mismas reglas que el perfil propio: edad de 1 a 120 e imagen opcionales.
            'age' => 'nullable|integer|between:1,120',
            'image' => 'nullable|url|max:2048',
            'role_id' => 'required|exists:roles,id',
            'role' => 'nullable|in:user,admin',

            // Campos opcionales para la cuenta bancaria inicial
            'account_type'     => 'nullable|in:savings,checking',
            'account_currency' => 'nullable|in:ARS,USD',
        ];
    }

    # Mensajes de error en español
    public function messages():array
    {
        return [
            # name
            'name.required' => 'El nombre de usuario es requerido.',
            'name.string' => 'El nombre debe ser una cadena de texto valida.',
            'name.max' => 'El nombre debe tener un maximo de 255 caracteres.',

            #email
            'email.required' => 'El email es requerido.',
            'email.email' => 'El email debe tener un formato valido.',
            'email.unique' => 'Email ya registrado. Por favor intente con otro.',
            
            #password
            'password.required' => 'La contraseña es requerida.',
            'password.string' => 'La contraseña debe ser una cadena de texto valida.',
            'password.min' => 'La contraseña debe tener por lo menos 8 caracteres.',
            'password.confirmed' => 'La contraseña debe confirmarse.',
            
            #age
            'age.integer' => 'La edad debe ser un valor numerico.',
            'age.between' => 'La edad debe estar entre 1 y 120.',

            #image
            'image.url' => 'El link de la imagen debe tener una url valida.',
            'image.max' => 'El link de la imagen debe tener a lo sumo una extension de 2048 caracteres.',

            # roles
            'role_id.required' => 'Indicá el rol del usuario (role o role_id).',
            'role_id.exists' => 'El id del rol debe ser un id valido.',
            'role.in' => 'El rol debe ser "user" o "admin".',

            # cuenta
            'account_type.in'     => 'El tipo de cuenta debe ser savings o checking.',
            'account_currency.in' => 'La moneda debe ser ARS o USD.',
        ];
    }
}
