<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\EsferaGoverno;

class EsferaGovernoController extends Controller
{
    /**
     * Lista todas as esferas de governo
     */
    public function index()
    {
        $esferas = EsferaGoverno::select('id', 'nome', 'slug')
            ->orderBy('nome')
            ->get();

        return response()->json($esferas);
    }
}
