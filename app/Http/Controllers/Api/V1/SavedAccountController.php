<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveCbuRequest;
use App\Models\Account;
use Illuminate\Http\JsonResponse;

class SavedAccountController extends Controller
{
    # POST /api/v1/cbu/{cbu}/users/{idUser}
    # Guarda el CBU de una cuenta de tercero en la lista del usuario autenticado.
    public function store(SaveCbuRequest $request, string $cbu, string $idUser): JsonResponse
    {
        $cuentaGuardada = Account::where('cbu', $cbu)->first();

        $user = auth('api')->user();
        $user->savedAccounts()->attach($cuentaGuardada->id);

        return response()->json([
            'success' => true,
            'message' => 'CBU guardado correctamente.',
            'data' => [
                'cbu' => $cuentaGuardada->cbu,
            ],
        ], 201);
    }
}
