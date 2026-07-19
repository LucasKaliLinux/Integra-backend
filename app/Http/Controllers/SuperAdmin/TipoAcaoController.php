<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\TipoAcao;

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
