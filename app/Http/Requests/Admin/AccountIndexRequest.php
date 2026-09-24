<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AccountIndexRequest extends FormRequest
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
            'order' => 'sometimes|string|in:asc,desc',
            'sort' => 'sometimes|string|in:id,balance,type,currency,created_at',
        ];
    }

    public function messages()
    {
        return [
            // per_page
            'per_page.integer' => 'El numero de elementos por pagina debe ser un numero entero.',
            'per_page.min' => 'El numero minimo de elementos por pagina debe ser 1.',
            'per_page.max' => 'El numero maximo de elementos por pagina es 100.',
            
            // order
            'order.string' => 'El sentido de ordenamiento debe ser una cadena de texto valida.',
            'order.in' => 'El sentido de ordenamiento solo puede ser: asc o desc.',

            // sort
            'sort.string' => 'El orden debe ser una cadena de texto valida.',
            'sort.in' => 'Solo se puede ordenar por: id, balance, type, currency y created_at.'
        ];
    }
}
