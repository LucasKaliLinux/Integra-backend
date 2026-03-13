<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CargoLideranca;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CargoController extends Controller
{
    public function index() {
        $cargos = Cache::remember('cargos_lideranca', 3600, function () {
            return CargoLideranca::select('id', 'nome', 'classificacao_id')
                ->orderBy('nome')
                ->get();
        });

        return response()->json($cargos);
    }

    public function porClassificacao($id) {
        $cargos = CargoLideranca::where('classificacao_id', $id)
            ->select('id', 'nome', 'slug')
            ->orderBy('nome')
            ->get();

        return response()->json($cargos);
    }
}
