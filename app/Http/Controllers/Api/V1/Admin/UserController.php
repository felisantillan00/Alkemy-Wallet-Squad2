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
use OpenApi\Attributes as OA;
class UserController extends Controller
{
    # GET /api/v1/admin/users
    # Lista paginada de usuarios. Solo accesible para administradores (middleware "admin").
       #[OA\Get(
        path: '/api/v1/admin/users',
        summary: 'Listar usuarios',
        description: 'Lista paginada de usuarios, con filtro por rol y ordenamiento. Solo para administradores. La respuesta usa el formato de paginación de Laravel.',
        tags: ['Admin: usuarios'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'Número de página', schema: new OA\Schema(type: 'integer', minimum: 1, default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, description: 'Elementos por página (1 a 100)', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 15)),
            new OA\Parameter(name: 'sort', in: 'query', required: false, description: 'Campo por el que se ordena', schema: new OA\Schema(type: 'string', enum: ['id', 'name', 'email', 'age', 'created_at'], default: 'id')),
            new OA\Parameter(name: 'order', in: 'query', required: false, description: 'Sentido del ordenamiento', schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'], default: 'asc')),
            new OA\Parameter(name: 'role_id', in: 'query', required: false, description: 'Filtra por rol (1 = admin, 2 = user)', schema: new OA\Schema(type: 'integer', example: 2)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Listado paginado de usuarios',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'current_page', type: 'integer', example: 1),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/AdminUser')),
                        new OA\Property(property: 'first_page_url', type: 'string', example: 'http://localhost:8000/api/v1/admin/users?page=1'),
                        new OA\Property(property: 'from', type: 'integer', nullable: true, example: 1),
                        new OA\Property(property: 'last_page', type: 'integer', example: 2),
                        new OA\Property(property: 'last_page_url', type: 'string', example: 'http://localhost:8000/api/v1/admin/users?page=2'),
                        new OA\Property(property: 'next_page_url', type: 'string', nullable: true, example: 'http://localhost:8000/api/v1/admin/users?page=2'),
                        new OA\Property(property: 'path', type: 'string', example: 'http://localhost:8000/api/v1/admin/users'),
                        new OA\Property(property: 'per_page', type: 'integer', example: 15),
                        new OA\Property(property: 'prev_page_url', type: 'string', nullable: true, example: null),
                        new OA\Property(property: 'to', type: 'integer', nullable: true, example: 15),
                        new OA\Property(property: 'total', type: 'integer', example: 20),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 403, description: 'El usuario autenticado no es administrador', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenError')),
            new OA\Response(response: 422, description: 'Error de validación en los parámetros de consulta', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
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

        #[OA\Post(
        path: '/api/v1/admin/users',
        summary: 'Crear usuario',
        description: 'Crea un usuario con el rol indicado y su cuenta asociada con CBU único y saldo inicial 0. Solo para administradores.',
        tags: ['Admin: usuarios'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'password_confirmation', 'age', 'image', 'role_id'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Nuevo Usuario'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', description: 'Único en el sistema', example: 'nuevo@example.test'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8, example: 'password123'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', description: 'Debe coincidir con password', example: 'password123'),
                    new OA\Property(property: 'age', type: 'integer', minimum: 18, maximum: 100, example: 30),
                    new OA\Property(property: 'image', type: 'string', format: 'uri', maxLength: 2048, description: 'URL de la imagen del usuario', example: 'https://example.test/foto.jpg'),
                    new OA\Property(property: 'role_id', type: 'integer', description: 'ID de un rol existente (1 = admin, 2 = user)', example: 2),
                    new OA\Property(property: 'account_type', type: 'string', enum: ['savings', 'checking'], nullable: true, description: 'Opcional, por defecto savings', example: 'savings'),
                    new OA\Property(property: 'account_currency', type: 'string', enum: ['ARS', 'USD'], nullable: true, description: 'Opcional, por defecto ARS', example: 'ARS'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Usuario creado exitosamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Usuario creado exitosamente.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'user',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 5),
                                        new OA\Property(property: 'name', type: 'string', example: 'Nuevo Usuario'),
                                        new OA\Property(property: 'email', type: 'string', example: 'nuevo@example.test'),
                                        new OA\Property(property: 'age', type: 'integer', nullable: true, example: 30),
                                        new OA\Property(property: 'image', type: 'string', nullable: true, example: 'https://example.test/foto.jpg'),
                                        new OA\Property(property: 'role', type: 'string', nullable: true, example: 'user'),
                                        new OA\Property(
                                            property: 'account',
                                            type: 'object',
                                            properties: [
                                                new OA\Property(property: 'id', type: 'integer', example: 5),
                                                new OA\Property(property: 'cbu', type: 'string', example: '0000003100000000000005'),
                                                new OA\Property(property: 'type', type: 'string', enum: ['savings', 'checking'], example: 'savings'),
                                                new OA\Property(property: 'currency', type: 'string', enum: ['ARS', 'USD'], example: 'ARS'),
                                                new OA\Property(property: 'balance', type: 'number', format: 'float', example: 0),
                                            ]
                                        ),
                                    ]
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 403, description: 'El usuario autenticado no es administrador', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenError')),
            new OA\Response(response: 422, description: 'Error de validación (campos faltantes, email duplicado, edad fuera de rango, rol inexistente)', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
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

     #[OA\Get(
        path: '/api/v1/admin/users/{id}',
        summary: 'Ver detalle de un usuario',
        description: 'Devuelve los datos de un usuario por su ID. Solo para administradores.',
        tags: ['Admin: usuarios'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID del usuario', schema: new OA\Schema(type: 'integer', example: 2)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Usuario obtenido correctamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'user', ref: '#/components/schemas/AdminUser'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 403, description: 'El usuario autenticado no es administrador', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenError')),
            new OA\Response(response: 404, description: 'El usuario no existe', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundError')),
        ]
    )]
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

      #[OA\Put(
        path: '/api/v1/admin/users/{id}',
        summary: 'Actualizar un usuario',
        description: 'Actualización parcial: enviá solo los campos a modificar. Solo para administradores.',
        tags: ['Admin: usuarios'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID del usuario', schema: new OA\Schema(type: 'integer', example: 2)),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                description: 'Todos los campos son opcionales: se actualizan solo los enviados.',
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Nombre Actualizado'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', description: 'Único en el sistema (se permite reenviar el propio)', example: 'actualizado@example.test'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8, description: 'Requiere password_confirmation', example: 'password123'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'password123'),
                    new OA\Property(property: 'age', type: 'integer', minimum: 18, maximum: 100, example: 35),
                    new OA\Property(property: 'image', type: 'string', format: 'uri', maxLength: 2048, example: 'https://example.test/otra.jpg'),
                    new OA\Property(property: 'role_id', type: 'integer', description: 'ID de un rol existente (1 = admin, 2 = user)', example: 2),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Usuario actualizado exitosamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Usuario actualizado exitosamente.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'user', ref: '#/components/schemas/AdminUser'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 403, description: 'El usuario autenticado no es administrador', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenError')),
            new OA\Response(response: 404, description: 'El usuario no existe', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundError')),
            new OA\Response(response: 422, description: 'Error de validación', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
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

       #[OA\Delete(
        path: '/api/v1/admin/users/{id}',
        summary: 'Eliminar un usuario',
        description: 'Baja lógica (soft delete) del usuario. La cuenta y los movimientos se conservan intactos para preservar el historial financiero. Solo para administradores.',
        tags: ['Admin: usuarios'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID del usuario', schema: new OA\Schema(type: 'integer', example: 2)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Usuario eliminado exitosamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Usuario eliminado exitosamente.'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 403, description: 'El usuario autenticado no es administrador', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenError')),
            new OA\Response(response: 404, description: 'El usuario no existe', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundError')),
        ]
    )]
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
