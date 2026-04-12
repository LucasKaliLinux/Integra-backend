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
            'categoria' => $this->categoriaInvestimento?->nome ?? 'N/A',
            'orgao' => $this->orgao?->sigla ?? $this->orgao?->nome ?? 'N/A',
            'liderancas' => $this->whenLoaded('liderancas', function () {
                return $this->liderancas->map(fn ($lideranca) => [
                    'id' => $lideranca->id,
                    'nome' => $lideranca->nome,
                ])->values();
            }, []),
            'responsavel' => $this->whenLoaded('liderancas', fn () => $this->liderancas->first()?->nome ?? $request->user()->name, $request->user()->name),
            'valor' => (float) $this->valor,
            'status' => $this->status?->nome ?? 'N/A' // ⬅️ MUDOU
        ];
    }
}