<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AcaoMunicipioResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'titulo' => $this->titulo, // ⬅️ MUDOU
            'tipo' => $this->tipoAcao?->nome ?? 'N/A', // ⬅️ MUDOU
            'categoria' => $this->categoriaInvestimento?->nome ?? 'N/A', // ⬅️ NOVO
            'orgao' => $this->orgao?->sigla ?? $this->orgao?->nome ?? 'N/A', // ⬅️ NOVO
            'responsavel' => $this->liderancaSolicitante?->nome ?? $request->user()->name, // ⬅️ MUDOU
            'valor' => (float) $this->valor,
            'status' => $this->status?->nome ?? 'N/A' // ⬅️ MUDOU
        ];
    }
}