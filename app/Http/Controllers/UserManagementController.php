<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserManagementController extends Controller
{
    /**
     * Hierarquia de papéis atribuíveis por esta rota (super_admin nunca entra aqui).
     * Quanto maior o número, mais alto o privilégio.
     */
    private const HIERARQUIA_PAPEIS = [
        'cabinet' => 1,
        'manager' => 2,
        'admin' => 3,
    ];

    /**
     * Um ator só pode criar um usuário com papel de nível igual ou inferior ao seu.
     * Papéis desconhecidos (ex.: super_admin) recebem nível máximo e são sempre negados.
     */
    private function podeAtribuirPapel(User $ator, string $papelAlvo): bool
    {
        $nivelAtor = collect($ator->getRoleNames())
            ->map(fn ($papel) => self::HIERARQUIA_PAPEIS[$papel] ?? 0)
            ->max() ?? 0;

        $nivelAlvo = self::HIERARQUIA_PAPEIS[$papelAlvo] ?? PHP_INT_MAX;

        return $nivelAlvo <= $nivelAtor;
    }

    public function index(Request $request)
    {
        $deputadoId = $request->user()->deputado_id;

        $users = User::where('deputado_id', $deputadoId)
            ->withTrashed()
            ->with('roles:name')
            ->select('id', 'name', 'email', 'ativo', 'created_at', 'deleted_at')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'ativo' => $u->ativo,
                'role' => $u->getRoleNames()->first(),
                'deletado' => ! is_null($u->deleted_at),
            ]);

        return response()->json($users);
    }

    public function store(StoreUserRequest $request)
    {
        // Impede escalação de privilégio: um manager não pode criar um admin.
        if (! $this->podeAtribuirPapel($request->user(), $request->role)) {
            return response()->json([
                'message' => 'Você não pode criar um usuário com papel superior ao seu.',
            ], 403);
        }

        $newUser = User::create([
            'deputado_id' => $request->user()->deputado_id,
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'ativo' => true,
        ]);

        $newUser->assignRole($request->role);

        return response()->json([
            'message' => 'Usuário criado com sucesso',
            'user' => [
                'id' => $newUser->id,
                'name' => $newUser->name,
                'email' => $newUser->email,
                'role' => $request->role,
            ],
        ], 201);
    }

    public function update(UpdateUserRequest $request, int $id)
    {
        if (! $request->user()->hasAnyRole(['admin'])) {
            return response()->json(['message' => 'Sem permissão'], 403);
        }

        if ($request->user()->id === $id) {
            return response()->json(['message' => 'Você não pode alterar a sua propria conta'], 422);
        }

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
                'role' => $targetUser->getRoleNames()->first(),
            ],
        ]);
    }

    public function toggleStatus(Request $request, int $id)
    {
        if (! $request->user()->hasAnyRole(['admin'])) {
            return response()->json(['message' => 'Sem permissão'], 403);
        }

        if ($request->user()->id === $id) {
            return response()->json(['message' => 'Você não pode alterar o status de sua própria conta'], 422);
        }

        $targetUser = User::where('deputado_id', $request->user()->deputado_id)
            ->findOrFail($id);

        $targetUser->ativo = ! $targetUser->ativo;
        $targetUser->save();

        return response()->json([
            'message' => $targetUser->ativo ? 'Usuário ativado com sucesso' : 'Usuário desativado com sucesso',
            'ativo' => $targetUser->ativo,
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        if (! $request->user()->hasAnyRole(['admin'])) {
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
