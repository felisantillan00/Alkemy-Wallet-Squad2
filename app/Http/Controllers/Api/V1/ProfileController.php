<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    # GET /api/v1/profile
    # Devuelve los datos del usuario autenticado.
    # El usuario sale del token: el cliente no envía user_id, por lo que
    # nadie puede consultar el perfil de otra persona.
    public function show(): JsonResponse
    {
        $user = auth('api')->user();

        return response()->json([
            'success' => true,
            'data' => $this->perfil($user),
        ]);
    }

    # PUT|PATCH /api/v1/profile
    # Actualiza los datos del usuario autenticado. Un user_id ajeno enviado en el
    # body se ignora: siempre se opera sobre auth('api')->user().
    public function update(ProfileUpdateRequest $request): JsonResponse
    {
        $datos = $request->validated();

        $user = auth('api')->user();

        if ($request->hasFile('image')) {
            $datos['image'] = $request->file('image')->store('profile-images', 'public');
        }

        $user->update($datos);

        return response()->json([
            'success' => true,
            'message' => 'Perfil actualizado correctamente.',
            'data' => $this->perfil($user),
        ]);
    }

    # DELETE /api/v1/profile
    # Da de baja (soft delete) al usuario autenticado.
    # La cuenta, su balance, sus movimientos y los CBUs guardados no se tocan:
    # accounts.user_id sigue apuntando al mismo id, ya que el registro de users
    # no se borra de verdad (solo se marca deleted_at).
    public function destroy(): JsonResponse
    {
        $user = auth('api')->user();
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Perfil eliminado correctamente.',
        ]);
    }

    # FORMA COMUN DE DEVOLVER EL PERFIL, SIN CONTRASEÑA
    private function perfil($user): array
    {
        return [
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'age'   => $user->age,
            'image' => $user->image ? Storage::disk('public')->url($user->image) : null,
        ];
    }
}