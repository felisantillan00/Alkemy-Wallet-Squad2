<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SavedAccountDestroyRequest extends FormRequest
{
    # SOLO EL PROPIO USUARIO AUTENTICADO PUEDE MODIFICAR SU LISTA DE CBUs GUARDADOS
    public function authorize(): bool
    {
        return auth('api')->id() === (int) $this->route('idUser');
    }

    public function rules(): array
    {
        return [];
    }
}
