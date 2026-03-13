<?php

namespace App\Http\Controllers;

use App\Models\TipoOrgao;
use Illuminate\Http\Request;

class TipoOrgaoController extends Controller
{
    /**
     * Lista tipos de órgão filtrados por esfera
     * 
     * Query params:
     * - esfera_id: Filtra por esfera de governo
     */
    public function index(Request $request)
    {
        $query = TipoOrgao::query()
            ->select('id', 'esfera_governo_id', 'nome', 'slug')
            ->with('esferaGoverno:id,nome');

        // Filtro por esfera
        if ($request->has('esfera_id')) {
            $query->where('esfera_governo_id', $request->esfera_id);
        }

        $tipos = $query->orderBy('nome')->get();

        return response()->json($tipos);
    }
}