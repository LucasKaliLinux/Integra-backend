<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\CategoriaInvestimento;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoriaInvestimentoController extends Controller
{
    /**
     * Lista todas as categorias de investimento
     *
     * Query params:
     * - search: Busca por nome
     */
    public function index(Request $request)
    {
        $query = CategoriaInvestimento::query()
            ->select('id', 'nome', 'slug');

        // Filtro de busca
        if ($request->filled('search')) {
            $query->where('nome', 'like', "%{$request->search}%");
        }

        $query->orderBy('nome');

        $isSuperAdmin = $request->user()->hasRole('super_admin');

        $transform = fn ($categoria) => $isSuperAdmin
            ? [
                'id' => $categoria->id,
                'nome' => $categoria->nome,
                'slug' => $categoria->slug,
                'em_uso' => $categoria->acoes()->exists(),
            ]
            : [
                'id' => $categoria->id,
                'nome' => $categoria->nome,
                'slug' => $categoria->slug,
            ];

        // Paginação ativada apenas quando `per_page` é informado, para não
        // quebrar os dropdowns que consomem a lista completa.
        if ($request->filled('per_page')) {
            $categorias = $query->paginate((int) $request->per_page);
            $categorias->getCollection()->transform($transform);

            return response()->json($categorias);
        }

        $categorias = $query->get();

        if ($isSuperAdmin) {
            $categorias->transform($transform);
        }

        return response()->json($categorias);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:255|unique:categorias_investimento,nome',
        ], [
            'nome.required' => 'O nome da categoria é obrigatório.',
            'nome.unique' => 'Esta categoria já existe.',
            'nome.max' => 'O nome não pode ter mais de 255 caracteres.',
        ]);

        $categoria = CategoriaInvestimento::create([
            'nome' => $validated['nome'],
            'slug' => Str::slug($validated['nome']),
        ]);

        return response()->json([
            'message' => 'Categoria criada com sucesso',
            'categoria' => $categoria,
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $categoria = CategoriaInvestimento::findOrFail($id);

        $validated = $request->validate([
            'nome' => 'required|string|max:255|unique:categorias_investimento,nome,'.$id,
        ], [
            'nome.required' => 'O nome da categoria é obrigatório.',
            'nome.unique' => 'Esta categoria já existe.',
        ]);

        $categoria->update([
            'nome' => $validated['nome'],
            'slug' => Str::slug($validated['nome']),
        ]);

        return response()->json([
            'message' => 'Categoria atualizada com sucesso',
            'categoria' => $categoria,
        ]);
    }

    public function destroy(int $id)
    {
        $categoria = CategoriaInvestimento::findOrFail($id);

        // ⬇️ Verificação OTIMIZADA (só verifica se existe 1)
        if ($categoria->acoes()->exists()) {
            return response()->json([
                'message' => 'Não é possível deletar esta categoria pois ela está sendo utilizada em ações.',
            ], 422);
        }

        $categoria->delete();

        return response()->json(['message' => 'Categoria deletada com sucesso']);
    }
}
