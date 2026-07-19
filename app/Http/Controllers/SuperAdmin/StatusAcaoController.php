<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\StatusAcao;

class StatusAcaoController extends Controller
{
    /**
     * Lista todos os status ordenados por ordem
     */
    public function index()
    {
        $status = StatusAcao::select('id', 'nome', 'slug', 'ordem')
            ->orderBy('ordem')
            ->get();

        return response()->json($status);
    }
}
