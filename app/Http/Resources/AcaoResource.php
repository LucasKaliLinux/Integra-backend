<?php

namespace App\Http\Resources;

use App\Helpers\InstrumentoHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AcaoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'governo' => $this->orgao->tipoOrgao->esferaGoverno->nome ?? '-',
            'titulo' => $this->titulo,
            'numero_sei' => $this->numero_sei,
            'orgao' => [
                'id' => $this->orgao->id,
                'nome' => $this->orgao->nome,
                'sigla' => $this->orgao->sigla,
            ],
            'categoria' => [
                'id' => $this->categoriaInvestimento->id,
                'nome' => $this->categoriaInvestimento->nome,
            ],
            'tipo' => [
                'id' => $this->tipoAcao->id,
                'nome' => $this->tipoAcao->nome,
            ],
            'municipio' => [
                'id' => $this->municipio->id_municipio,
                'nome' => $this->municipio->nome,
            ],
            'liderancas' => $this->liderancas->map(fn ($lideranca) => [
                'id' => $lideranca->id,
                'nome' => $lideranca->nome,
            ]),
            'valor' => (float) $this->valor,
            'ano' => $this->ano,
            'status' => [
                'id' => $this->status->id,
                'nome' => $this->status->nome,
            ],
            'observacao' => $this->observacao,
            'instrumento' => InstrumentoHelper::buildFromPath($this->instrumento_path, $this->updated_at),
        ];
    }
}
