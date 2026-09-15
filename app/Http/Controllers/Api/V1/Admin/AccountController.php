<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminAccountStoreRequest;
use App\Http\Requests\Admin\AdminAccountUpdateRequest;
use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    # GET /api/v1/admin/accounts
    # Lista paginada de cuentas. Solo accesible para administradores (middleware "admin").
    public function index(Request $request): JsonResponse
    {
        # Ordenamiento basico: whitelist de columnas permitidas para no exponer
        # ordenamiento arbitrario sobre columnas no deseadas.
        $ordenablesPermitidos = ['id', 'balance', 'created_at'];
        $ordenarPor = in_array($request->query('sort_by'), $ordenablesPermitidos, true)
            ? $request->query('sort_by')
            : 'id';
        $direccion = $request->query('sort_dir') === 'desc' ? 'desc' : 'asc';

        $cuentas = Account::with('user')
            ->orderBy($ordenarPor, $direccion)
            ->paginate(15);

        $cuentas->through(fn (Account $account) => [
            'id'       => $account->id,
            'cbu'      => $account->cbu,
            'type'     => $account->type,
            'currency' => $account->currency,
            'balance'  => $account->balance,
            'user'     => [
                'id'    => $account->user?->id,
                'name'  => $account->user?->name,
                'email' => $account->user?->email,
            ],
        ]);

        return response()->json($cuentas);
    }

    # GET /api/v1/admin/accounts/{account}
    # Detalle de una cuenta puntual. Solo accesible para administradores.
    public function show(Account $account): JsonResponse
    {
        $account->load('user');

        return response()->json([
            'success' => true,
            'data' => [
                'id'       => $account->id,
                'cbu'      => $account->cbu,
                'type'     => $account->type,
                'currency' => $account->currency,
                'balance'  => $account->balance,
                'user'     => [
                    'id'    => $account->user?->id,
                    'name'  => $account->user?->name,
                    'email' => $account->user?->email,
                ],
            ],
        ], 200);
    }

    # POST /api/v1/admin/accounts
    # Crea una cuenta para un usuario que todavia no tenga una.
    # El CBU se genera automaticamente y el balance siempre arranca en 0.00.
    public function store(AdminAccountStoreRequest $request): JsonResponse
    {
        $datos = $request->validated();

        $account = Account::create([
            'user_id'  => $datos['user_id'],
            'cbu'      => Account::generarCbuUnico(),
            'type'     => $datos['type'],
            'currency' => $datos['currency'],
            # balance no se asigna: usa el default de la migracion (0.00)
        ]);

        # refresh() vuelve a leer el registro de la base de datos: sin esto,
        # 'balance' queda en null en memoria porque nunca se lo asignamos
        # explicitamente (aunque en la DB la columna sí tiene el default 0.00).
        $account->refresh();
        $account->load('user');

        return response()->json([
            'success' => true,
            'data' => [
                'id'       => $account->id,
                'cbu'      => $account->cbu,
                'type'     => $account->type,
                'currency' => $account->currency,
                'balance'  => $account->balance,
                'user'     => [
                    'id'    => $account->user?->id,
                    'name'  => $account->user?->name,
                    'email' => $account->user?->email,
                ],
            ],
        ], 201);
    }

    # PUT/PATCH /api/v1/admin/accounts/{account}
    # Actualiza type y/o currency. No permite reasignar user_id ni tocar balance/cbu.
    public function update(AdminAccountUpdateRequest $request, Account $account): JsonResponse
    {
        $account->fill($request->validated());
        $account->save();

        $account->load('user');

        return response()->json([
            'success' => true,
            'data' => [
                'id'       => $account->id,
                'cbu'      => $account->cbu,
                'type'     => $account->type,
                'currency' => $account->currency,
                'balance'  => $account->balance,
                'user'     => [
                    'id'    => $account->user?->id,
                    'name'  => $account->user?->name,
                    'email' => $account->user?->email,
                ],
            ],
        ], 200);
    }

    # DELETE /api/v1/admin/accounts/{account}
    # Elimina una cuenta solo si no tiene saldo ni movimientos asociados.
    # movements.account_id tiene onDelete('set null'): si se dejara borrar
    # una cuenta con movimientos, esos movimientos quedarian "huerfanos"
    # (account_id = null) en silencio. Por eso se bloquea explicitamente.
    public function destroy(Account $account): JsonResponse
    {
        if ((float) $account->balance !== 0.0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar la cuenta: el saldo no es 0.',
            ], 422);
        }

        if ($account->movements()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar la cuenta: tiene movimientos asociados.',
            ], 422);
        }

        $account->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cuenta eliminada correctamente.',
        ], 200);
    }
}
