<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        $deputadoId = $request->user()->deputado_id;

        $users = User::where('deputado_id', $deputadoId)
            ->withTrashed()
            ->with('roles:name')
            ->select('id', 'name', 'email', 'ativo', 'created_at', 'deleted_at')
            ->get()
            ->map(fn($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'ativo' => $u->ativo,
                'role' => $u->getRoleNames()->first(),
                'deletado' => !is_null($u->deleted_at),
            ]);

        return response()->json($users);
    }

    public function store(StoreUserRequest $request)
    {
        $newUser = User::create([
            'deputado_id' => $request->user()->deputado_id,
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'ativo' => true
        ]);

        $newUser->assignRole($request->role);

        return response()->json([
            'message' => 'Usuário criado com sucesso',
            'user' => [
                'id' => $newUser->id,
                'name' => $newUser->name,
                'email' => $newUser->email,
                'role' => $request->role
            ]
        ], 201);
    }

    public function update(UpdateUserRequest $request, int $id)
    {
        $targetUser = User::where('deputado_id', $request->user()->deputado_id)
            ->findOrFail($id);

        $targetUser->update($request->only(['name', 'email']));

        if ($request->has('role')) {
            $targetUser->syncRoles([$request->role]);
        }

        return response()->json([
            'message' => 'Usuário atualizado com sucesso',
            'user' => [
                'id' => $targetUser->id,
                'name' => $targetUser->name,
                'email' => $targetUser->email,
                'role' => $targetUser->getRoleNames()->first()
            ]
        ]);
    }

    public function toggleStatus(Request $request, int $id)
    {
        if (!$request->user()->hasAnyRole(['admin', 'manager'])) {
            return response()->json(['message' => 'Sem permissão'], 403);
        }

        if ($request->user()->id === $id) {
            return response()->json(['message' => 'Você não pode alterar o status de sua própria conta'], 422);
        }

        $targetUser = User::where('deputado_id', $request->user()->deputado_id)
            ->findOrFail($id);

        $targetUser->ativo = !$targetUser->ativo;
        $targetUser->save();

        return response()->json([
            'message' => $targetUser->ativo ? 'Usuário ativado com sucesso' : 'Usuário desativado com sucesso',
            'ativo' => $targetUser->ativo
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        if (!$request->user()->hasAnyRole(['admin', 'manager'])) {
            return response()->json(['message' => 'Sem permissão'], 403);
        }

        $targetUser = User::where('deputado_id', $request->user()->deputado_id)
            ->findOrFail($id);

        if ($targetUser->id === $request->user()->id) {
            return response()->json(['message' => 'Você não pode deletar sua própria conta'], 422);
        }

        $targetUser->delete();

        return response()->json(['message' => 'Usuário deletado com sucesso']);
    }
}