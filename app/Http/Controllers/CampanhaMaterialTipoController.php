<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCampanhaMaterialTipoRequest;
use App\Http\Requests\UpdateCampanhaMaterialTipoRequest;
use App\Http\Resources\CampanhaMaterialTipoResource;
use App\Models\CampanhaMaterial;
use App\Models\CampanhaMaterialTipo;
use Illuminate\Http\Request;

class CampanhaMaterialTipoController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', CampanhaMaterialTipo::class);

        // Query params precisam ser escalares/limitados: arrays ou valores
        // fora da faixa causariam erro 500 no scopeFilter/paginate.
        $request->validate([
            'per_page' => 'sometimes|nullable|integer|min:1|max:50',
            'search' => 'sometimes|nullable|string|max:255',
            'ativo' => 'sometimes|nullable|string|max:10',
        ], [
            'per_page.integer' => 'O parâmetro per_page deve ser um número inteiro.',
            'per_page.min' => 'O parâmetro per_page deve ser no mínimo 1.',
            'per_page.max' => 'O parâmetro per_page deve ser no máximo 50.',
            'search.string' => 'O parâmetro search deve ser um texto.',
            'ativo.string' => 'O parâmetro ativo deve ser um texto.',
        ]);

        $query = CampanhaMaterialTipo::forDeputado($request->user()->deputado_id)
            ->when($request->filled('search'), fn ($q) => $q->where('nome', 'like', '%'.$request->input('search').'%'))
            ->when(
                $request->has('ativo') && $request->input('ativo') !== '',
                fn ($q) => $q->where('ativo', filter_var($request->input('ativo'), FILTER_VALIDATE_BOOLEAN))
            )
            ->withCount('materiais')
            ->orderBy('nome');

        // Lista completa para selects (formulário de materiais).
        if ($request->boolean('all')) {
            return CampanhaMaterialTipoResource::collection($query->get());
        }

        $perPage = (int) ($request->input('per_page') ?: 12);

        return CampanhaMaterialTipoResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function store(StoreCampanhaMaterialTipoRequest $request)
    {
        $this->authorize('create', CampanhaMaterialTipo::class);

        $tipo = new CampanhaMaterialTipo($request->validated());
        $tipo->deputado_id = $request->user()->deputado_id;
        $tipo->save();

        return new CampanhaMaterialTipoResource($tipo);
    }

    public function show(Request $request, string $id)
    {
        $tipo = CampanhaMaterialTipo::forDeputado($request->user()->deputado_id)
            ->findOrFail($id);

        $this->authorize('view', $tipo);

        return new CampanhaMaterialTipoResource($tipo);
    }

    public function update(UpdateCampanhaMaterialTipoRequest $request, string $id)
    {
        $tipo = CampanhaMaterialTipo::forDeputado($request->user()->deputado_id)
            ->findOrFail($id);

        $this->authorize('update', $tipo);

        $tipo->update($request->validated());

        return new CampanhaMaterialTipoResource($tipo->fresh());
    }

    public function destroy(Request $request, string $id)
    {
        $tipo = CampanhaMaterialTipo::forDeputado($request->user()->deputado_id)
            ->findOrFail($id);

        $this->authorize('delete', $tipo);

        $referencias = CampanhaMaterial::where('deputado_id', $request->user()->deputado_id)
            ->where('tipo_material_id', $tipo->id)
            ->count();

        if ($referencias > 0) {
            return response()->json([
                'error' => "Este tipo de material está em uso em {$referencias} material(is) de campanha e não pode ser excluído. Inative-o para que deixe de aparecer nas seleções.",
            ], 422);
        }

        $tipo->delete();

        return response()->json(['message' => 'Tipo de material removido com sucesso.']);
    }
}
