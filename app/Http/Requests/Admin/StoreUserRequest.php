<?php

namespace App\Http\Requests\Admin;

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
            'password' => 'required|string|min:8|confirmed',
            'age' => 'required|integer|min:18|max:100',
            'image' => 'required|url|max:2048',
            'role_id' => 'required|exists:roles,id',

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
            'age.required' => 'La edad es requerida.',
            'age.integer' => 'La edad debe ser un valor numerico.',
            'age.min' => 'La edad minima es de 18 años.',
            'age.max' => 'La edad maxima es de 100 años.',

            #image
            'image.required' => 'El link de imagen es requerida.',
            'image.url' => 'El link de la imagen debe tener una url valida.',
            'image.max' => 'El link de la imagen debe tener a lo sumo una extension de 2048 caracteres.',

            # roles
            'role_id.required' => 'El id del rol a colocar es requerido.',
            'role_id.exists' => 'El id del rol debe ser un id valido.',

            # cuenta
            'account_type.in'     => 'El tipo de cuenta debe ser savings o checking.',
            'account_currency.in' => 'La moneda debe ser ARS o USD.',
        ];
    }
}
