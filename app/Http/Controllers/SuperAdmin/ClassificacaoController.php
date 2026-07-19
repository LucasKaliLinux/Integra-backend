<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ClassificacaoLideranca;
use Illuminate\Support\Facades\Cache;

class ClassificacaoController extends Controller
{
    public function index()
    {
        $cargos = Cache::remember('classificacao_lideranca', 3600, function () {
            return ClassificacaoLideranca::select('id', 'nome', 'slug')
                ->orderBy('nome')
                ->get();
        });

        return response()->json($cargos);
    }
}
