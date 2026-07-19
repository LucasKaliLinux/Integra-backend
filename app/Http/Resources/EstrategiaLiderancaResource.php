<?php

namespace App\Http\Resources;

use App\Helpers\StringHelper;
use Illuminate\Http\Resources\Json\JsonResource;

class EstrategiaLiderancaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'sequencial_candidato' => $this->sequencial_candidato,
            'cargo' => $this->cargo,
            'sigla_partido' => $this->sigla_partido,
            'votos' => $this->votos,
            'resultado' => $this->resultado,
            'ano' => $this->ano,
            'nome' => StringHelper::formatarNome($this->nome),
            'nome_urna' => StringHelper::formatarNome($this->nome_urna),
        ];
    }
}
