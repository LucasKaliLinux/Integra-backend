<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCampanhaPoliticoRequest;
use App\Http\Requests\UpdateCampanhaPoliticoRequest;
use App\Http\Resources\CampanhaPoliticoResource;
use App\Models\CampanhaMaterial;
use App\Models\CampanhaPolitico;
use Illuminate\Http\Request;

class CampanhaPoliticoController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', CampanhaPolitico::class);

        // Query params precisam ser escalares/limitados: arrays ou valores
        // fora da faixa causariam erro 500 no scopeFilter/paginate.
        $request->validate([
            'per_page' => 'sometimes|nullable|integer|min:1|max:50',
            'search' => 'sometimes|nullable|string|max:255',
            'cargo' => 'sometimes|nullable|string|max:255',
            'partido' => 'sometimes|nullable|string|max:255',
            'ativo' => 'sometimes|nullable|string|max:10',
        ], [
            'per_page.integer' => 'O parâmetro per_page deve ser um número inteiro.',
            'per_page.min' => 'O parâmetro per_page deve ser no mínimo 1.',
            'per_page.max' => 'O parâmetro per_page deve ser no máximo 50.',
            'search.string' => 'O parâmetro search deve ser um texto.',
            'cargo.string' => 'O parâmetro cargo deve ser um texto.',
            'partido.string' => 'O parâmetro partido deve ser um texto.',
            'ativo.string' => 'O parâmetro ativo deve ser um texto.',
        ]);

        $query = CampanhaPolitico::where('deputado_id', $request->user()->deputado_id)
            ->filter($request->all())
            ->latest();

        // Lista completa para selects (composição de materiais).
        if ($request->boolean('all')) {
            return CampanhaPoliticoResource::collection($query->get());
        }

        $perPage = (int) ($request->input('per_page') ?: 12);

        return CampanhaPoliticoResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function store(StoreCampanhaPoliticoRequest $request)
    {
        $this->authorize('create', CampanhaPolitico::class);

        $politico = new CampanhaPolitico($request->validated());
        $politico->deputado_id = $request->user()->deputado_id;
        $politico->save();

        return new CampanhaPoliticoResource($politico);
    }

    public function show(Request $request, string $id)
    {
        $politico = CampanhaPolitico::where('deputado_id', $request->user()->deputado_id)
            ->findOrFail($id);

        $this->authorize('view', $politico);

        return new CampanhaPoliticoResource($politico);
    }

    public function update(UpdateCampanhaPoliticoRequest $request, string $id)
    {
        $politico = CampanhaPolitico::where('deputado_id', $request->user()->deputado_id)
            ->findOrFail($id);

        $this->authorize('update', $politico);

        $validated = $request->validated();

        // Integridade da composição: trocar o cargo de um político já usado em
        // materiais invalidaria o slot que ele ocupa (slot exige cargo fixo).
        if (($validated['cargo'] ?? $politico->cargo) !== $politico->cargo) {
            $referencias = $this->contarReferenciasEmMateriais($politico, $request->user()->deputado_id);

            if ($referencias > 0) {
                return response()->json([
                    'error' => "Este político está na composição de {$referencias} material(is) de campanha e não pode ter o cargo alterado. Remova-o dos materiais ou cadastre um novo político.",
                ], 422);
            }
        }

        $politico->update($validated);

        return new CampanhaPoliticoResource($politico->fresh());
    }

    public function destroy(Request $request, string $id)
    {
        $politico = CampanhaPolitico::where('deputado_id', $request->user()->deputado_id)
            ->findOrFail($id);

        $this->authorize('delete', $politico);

        $referencias = $this->contarReferenciasEmMateriais($politico, $request->user()->deputado_id);

        if ($referencias > 0) {
            return response()->json([
                'error' => "Este político está na composição de {$referencias} material(is) de campanha e não pode ser excluído. Inative-o para que deixe de aparecer nas seleções.",
            ], 422);
        }

        $politico->delete();

        return response()->json(['message' => 'Político removido com sucesso.']);
    }

    /**
     * Quantos materiais do deputado referenciam o político em algum slot
     * de composição. Escopado por deputado (defesa em profundidade).
     */
    private function contarReferenciasEmMateriais(CampanhaPolitico $politico, int $deputadoId): int
    {
        return CampanhaMaterial::where('deputado_id', $deputadoId)
            ->where(function ($query) use ($politico) {
                foreach (array_keys(CampanhaMaterial::SLOTS_COMPOSICAO) as $campo) {
                    $query->orWhere($campo, $politico->id);
                }
            })->count();
    }
}
