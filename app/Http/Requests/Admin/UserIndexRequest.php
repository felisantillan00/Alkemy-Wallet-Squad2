<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UserIndexRequest extends FormRequest
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
            'per_page' => 'sometimes|integer|min:1|max:100',
            'sort' => 'sometimes|string|in:id,name,email,age,created_at',
            'order' => 'sometimes|string|in:asc,desc',
            'role_id' => 'sometimes|integer|exists:roles,id'
        ];
    }

    public function messages(): array
    {
        return [
            // per_page
            'per_page.integer' => 'El numero de elementos por pagina debe ser un entero.',
            'per_page.min' => 'El valor minimo de elementos por pagina es 1.',
            'per_page.max' => 'El valor maximo de elementos por pagina es 100.',
            
            // sort
            'sort.string' => 'El orden debe ser una cadena de texto valida.',
            'sort.in' => 'Solo se puede ordenar por: id, name, email, age, created_at.',

            // order
            'order.string' => 'El sentido de ordenamiento debe ser una cadena de texto valida.',
            'order.in' => 'El sentido de ordenamiento solo puede ser asc o desc.',

            // role_id
            'role_id.integer' => 'El role_id debe ser un numero entero.',
            'role_id.exists' => 'El rol ingresado no existe.'
        ];
    }
}
