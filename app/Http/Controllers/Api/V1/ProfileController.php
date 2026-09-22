<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;
class ProfileController extends Controller
{
    # GET /api/v1/profile
    # Devuelve los datos del usuario autenticado.
    # El usuario sale del token: el cliente no envía user_id, por lo que
    # nadie puede consultar el perfil de otra persona.
        #[OA\Get(
        path: '/api/v1/profile',
        summary: 'Ver perfil propio',
        description: 'Devuelve los datos del usuario autenticado (el usuario sale del token).',
        tags: ['Perfil'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Perfil obtenido correctamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Profile'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
        ]
    )]
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
        #[OA\Put(
        path: '/api/v1/profile',
        summary: 'Actualizar perfil propio',
        description: 'Actualización parcial: enviá solo los campos a modificar. Un user_id enviado en el body se ignora. Para subir `image` usá multipart/form-data; como PHP no procesa multipart en PUT/PATCH, desde un cliente real enviá POST con el campo `_method=PUT`.',
        tags: ['Perfil'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: false,
            content: [
                new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(ref: '#/components/schemas/ProfileUpdateInput')),
                new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(ref: '#/components/schemas/ProfileUpdateInput')),
            ]
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Perfil actualizado correctamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Perfil actualizado correctamente.'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Profile'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 422, description: 'Error de validación (edad fuera de rango, email en uso, imagen inválida o mayor a 2 MB)', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    #[OA\Patch(
        path: '/api/v1/profile',
        summary: 'Actualizar perfil propio (parcial)',
        description: 'Mismo comportamiento que PUT /api/v1/profile.',
        tags: ['Perfil'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: false,
            content: [
                new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(ref: '#/components/schemas/ProfileUpdateInput')),
                new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(ref: '#/components/schemas/ProfileUpdateInput')),
            ]
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Perfil actualizado correctamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Perfil actualizado correctamente.'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Profile'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 422, description: 'Error de validación', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
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
        #[OA\Delete(
        path: '/api/v1/profile',
        summary: 'Eliminar perfil propio',
        description: 'Baja lógica (soft delete) del usuario autenticado. La cuenta, su saldo, movimientos y CBUs guardados no se modifican. El token deja de ser válido.',
        tags: ['Perfil'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Perfil eliminado correctamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Perfil eliminado correctamente.'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
        ]
    )]
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