<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    # CUALQUIER USUARIO AUTENTICADO PUEDE ACTUALIZAR SU PROPIO PERFIL
    public function authorize(): bool
    {
        return true;
    }

    # REGLAS DE VALIDACION DE LA ACTUALIZACION DEL PERFIL
    # Todos los campos son opcionales (actualizacion parcial); si se envian, deben ser validos.
    public function rules(): array
    {
        return [
            'name'  => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($this->user()?->id)],
            'age'   => ['nullable', 'integer', 'between:1,120'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    # MENSAJES DE ERROR EN ESPAÑOL
    public function messages(): array
    {
        return [
            'name.string'    => 'El nombre debe ser un texto.',
            'name.max'       => 'El nombre no puede superar los 255 caracteres.',
            'email.email'    => 'El email debe ser una dirección válida.',
            'email.unique'   => 'Ese email ya está en uso.',
            'age.integer'    => 'La edad debe ser un número entero.',
            'age.between'    => 'La edad debe estar entre 1 y 120.',
            'image.file'     => 'La imagen debe ser un archivo.',
            'image.mimes'    => 'La imagen debe ser JPG, PNG o WebP.',
            'image.max'      => 'La imagen no puede superar los 2 MB.',
        ];
    }
}
