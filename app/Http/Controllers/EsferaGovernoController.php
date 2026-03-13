<?php

namespace App\Http\Controllers;

use App\Models\EsferaGoverno;
use Illuminate\Http\Request;

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