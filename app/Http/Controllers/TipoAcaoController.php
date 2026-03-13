<?php

namespace App\Http\Controllers;

use App\Models\TipoAcao;
use Illuminate\Http\Request;

class TipoAcaoController extends Controller
{
    /**
     * Lista todos os tipos de ação
     */
    public function index()
    {
        $tipos = TipoAcao::select('id', 'nome', 'slug')
            ->orderBy('nome')
            ->get();

        return response()->json($tipos);
    }
}