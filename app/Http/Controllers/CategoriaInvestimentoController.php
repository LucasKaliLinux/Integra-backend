<?php

namespace App\Http\Controllers;

use App\Models\CategoriaInvestimento;
use Illuminate\Http\Request;

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

        $categorias = $query->orderBy('nome')->get();

        return response()->json($categorias);
    }
}