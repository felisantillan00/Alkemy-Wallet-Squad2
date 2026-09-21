<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Requests\Admin\UserIndexRequest;
use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    # GET /api/v1/admin/users
    # Lista paginada de usuarios. Solo accesible para administradores (middleware "admin").
    public function index(UserIndexRequest $request): JsonResponse
    {
        // Validamos los datos enviados
        $datos = $request->validated();

        // Cantidad de items por pagina (por defecto: 15)
        $perPage = $datos['per_page'] ?? 15;

        // Dato con el cual ordenamos (por defecto: id)
        $sort = $datos['sort'] ?? 'id';

        // Orden que usamos para organizar los datos (por defecto: asc)
        $direction = $datos['order'] ?? 'asc';

        // Creamos una query
        $query = User::with('role');

        // Filtro por rol
        if(isset($datos['role_id'])){
            $query->where('role_id', $datos['role_id']);
        }

        // Buscamos los usuarios
        $usuarios = $query->orderBy($sort, $direction)
                            ->paginate($perPage)
                            ->appends($request->query());

        // Formato
        $usuarios->through(fn (User $user) => [
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'age'   => $user->age,
            'image' => $user->image,
            'role'  => $user->role?->role_name,
        ]);

        // Retornamos los usuarios obtenidos.
        return response()->json($usuarios);
    }

    # POST /api/v1/admin/users
    # Crea un usuario en base a los campos enviados. Solo accesible para administradores.

    public function store(StoreUserRequest $request): JsonResponse
    {
        // Validamos los datos
        $datos = $request->validated();

        $user = DB::transaction(function () use ($datos) {

            // Creamos el usuario
            $user = User::create($datos);

            // Creamos la cuenta (si envian los campos para el tipo de cuenta y moneda
            // utilizamos los campos)
            Account::create([
                'user_id' => $user->id,
                'cbu'     => Account::generarCbuUnico(),
                'type'     => $datos['account_type'] ?? 'savings',
                'currency' => $datos['account_currency'] ?? 'ARS',
            ]);

            return $user;
        });

        // Cargamos la relacion con roles
        $user->load('role');

        // Mandamos un json con un 201 creado
        return response()->json([
            'message' => 'Usuario creado exitosamente.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'age' => $user->age,
                    'image' => $user->image,
                    'role' => $user->role?->role_name,
                    'account' => [
                        'id' => $user->account->id,
                        'cbu' => $user->account->cbu,
                        'type' => $user->account->type,
                        'currency' => $user->account->currency,
                        'balance'  => $user->account->balance,
                    ],
                ],
            ],
        ], 201);
    }

    # GET api/v1/admin/users/{id}
    # Muestra el detalle de un usuario en particular. Solo accesible para administradores.

    public function show(int $id): JsonResponse
    {
        // Buscamos el usuario con su rol. 
        $user = User::with('role')->findOrFail($id);
    
        // Retornamos el usuario con su informacion.
        return response()->json([
            'data' => [
                'user' => [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                    'age'   => $user->age,
                    'image' => $user->image,
                    'role'  => $user->role?->role_name,
                ],
            ],
        ]);
    }

    # PUT api/v1/admin/users/{id}
    # Permite editar un usuario enviando los campos que se desean cambiar (no necesariamente
    # deben estar todos). Solo accesible para administradores.

    public function update(UpdateUserRequest $request, int $id, ): JsonResponse 
    {
        // Buscamos el usuario con su rol. 
        $user = User::with('role')->findOrFail($id);       
        
        // Validamos la informacion
        $datos = $request->validated();

        // Actualizamos el usuario
        $user->update($datos);

        // Cargamos la relacion con roles
        $user->load('role');

        // Retornamos la respuesta
        return response()->json([
            'message' => 'Usuario actualizado exitosamente.',
            'data' => [
                'user' => [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                    'age'   => $user->age,
                    'image' => $user->image,
                    'role'  => $user->role?->role_name,
                ],
            ],
        ]);
    }

    # DELETE api/v1/users/{id}
    # Elimina un usuario. Al eliminar se respeta el comportamiento documentado de cuenta y movimientos.
    # Utilizamos SoftDeletes que es el borrado logico.

    public function destroy(int $id): JsonResponse
    {
        // Buscamos el usuario
        $user = User::findOrFail($id);

        // SoftDelete: el usuario se da de baja lógica. La cuenta y los movimientos
        // asociados permanecen intactos (no se disparan las FK onDelete('set null')
        // porque no hay un DELETE físico) — comportamiento intencional para preservar
        // el historial financiero.
        $user->delete(); 

        // Retornamos la respuesta exitosa
        return response()->json([
            'message' => 'Usuario eliminado exitosamente.',
        ], 200);
    }
}   
