<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveCbuRequest;
use App\Http\Requests\SavedAccountDestroyRequest;
use App\Http\Requests\SavedAccountIndexRequest;
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

    # GET /api/v1/cbu/users/{idUser}
    # Lista los CBUs guardados por el usuario autenticado.
    public function index(SavedAccountIndexRequest $request, string $idUser): JsonResponse
    {
        $user = auth('api')->user();

        $guardados = $user->savedAccounts()
            ->with('user')
            ->get()
            ->map(fn (Account $cuenta) => [
                'cbu'     => $cuenta->cbu,
                'titular' => $cuenta->user?->name,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => $guardados,
        ], 200);
    }

    # DELETE /api/v1/cbu/{cbu}/users/{idUser}
    # Quita un CBU de la lista de guardados del usuario autenticado, sin tocar la cuenta ni al usuario tercero.
    public function destroy(SavedAccountDestroyRequest $request, string $cbu, string $idUser): JsonResponse
    {
        $user = auth('api')->user();
        $cuenta = Account::where('cbu', $cbu)->first();

        if (! $cuenta || ! $user->savedAccounts()->where('account_id', $cuenta->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Ese CBU no está en tu lista de guardados.',
            ], 404);
        }

        $user->savedAccounts()->detach($cuenta->id);

        return response()->json([
            'success' => true,
            'message' => 'CBU eliminado de tu lista correctamente.',
        ], 200);
    }
}
