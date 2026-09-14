<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    # GET /api/v1/admin/users
    # Lista paginada de usuarios. Solo accesible para administradores (middleware "admin").
    public function index(): JsonResponse
    {
        $usuarios = User::with('role')
            ->orderBy('id')
            ->paginate(15);

        $usuarios->through(fn (User $user) => [
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'role'  => $user->role?->role_name,
        ]);

        return response()->json($usuarios);
    }
}
