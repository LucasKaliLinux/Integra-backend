<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Municipio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MunicipioController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        if ($search) {
            $municipios = Municipio::select('id_municipio as id', 'nome')
                ->filter($request->all())
                ->orderBy('nome')
                ->limit(15)
                ->get();

            return response()->json($municipios);
        }

        $municipios = Cache::remember('municipios', 3600, function () {
            return Municipio::select('id_municipio as id', 'nome')
                ->orderBy('nome')
                ->get();
        });

        return response()->json($municipios);
    }
}
