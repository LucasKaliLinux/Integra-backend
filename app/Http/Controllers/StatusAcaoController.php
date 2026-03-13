<?php

namespace App\Http\Controllers;

use App\Models\StatusAcao;
use Illuminate\Http\Request;

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