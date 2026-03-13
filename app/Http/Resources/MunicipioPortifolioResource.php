<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MunicipioPortifolioResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_municipio' => $this->id_municipio,
            'nome' => $this->nome,
            'populacao' => $this->populacao,
            'total_eleitores' => $this->total_eleitores,
            'votos' => $this->votos,
            'percentual_votos' => $this->percentual_votos,
        ];
    }
}
