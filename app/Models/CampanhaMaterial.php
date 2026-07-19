<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampanhaMaterial extends Model
{
    /**
     * Status aceitos (validados no FormRequest; transições livres).
     */
    public const STATUS = ['solicitado', 'produzido', 'entregue', 'cancelado'];

    /**
     * Slots de composição ("casadinha"): coluna => cargo exigido do político.
     */
    public const SLOTS_COMPOSICAO = [
        'politico_estadual_id' => 'estadual',
        'politico_federal_id' => 'federal',
        'politico_senador_id' => 'senador',
        'politico_governador_id' => 'governador',
        'politico_presidente_id' => 'presidente',
    ];

    protected $table = 'campanha_materiais';

    /**
     * "deputado_id" e "user_id" ficam FORA do fillable de propósito: são
     * atribuídos explicitamente no controller a partir do usuário autenticado.
     */
    protected $fillable = [
        'id_municipio',
        'lideranca_id',
        'tipo_material_id',
        'variante',
        'quantidade',
        'status',
        'politico_estadual_id',
        'politico_federal_id',
        'politico_senador_id',
        'politico_governador_id',
        'politico_presidente_id',
        'recebedor',
        'data',
        'arte_url',
        'observacao',
    ];

    protected $casts = [
        'data' => 'date',
        'quantidade' => 'integer',
    ];

    public function scopeForDeputado($query, $deputadoId)
    {
        return $query->where('deputado_id', $deputadoId);
    }

    public function scopeFilter($query, $filters)
    {
        return $query
            // Filtro por município
            ->when($filters['municipio'] ?? null, fn ($q, $v) => $q->where('id_municipio', $v))

            // Filtro por status (solicitado|produzido|entregue|cancelado)
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))

            // Filtro por tipo de material
            ->when($filters['tipo_material_id'] ?? null, fn ($q, $v) => $q->where('tipo_material_id', $v))

            // Busca textual (recebedor, observação, liderança ou município)
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where(fn ($subQ) => $subQ
                ->where('recebedor', 'like', "%{$v}%")
                ->orWhere('observacao', 'like', "%{$v}%")
                ->orWhereHas('lideranca', fn ($l) => $l->where('nome', 'like', "%{$v}%"))
                ->orWhereHas('municipio', fn ($m) => $m->where('nome', 'like', "%{$v}%"))
            ));
    }

    /*
    |--------------------------------------------------------------------------
    | RELACIONAMENTOS
    |--------------------------------------------------------------------------
    */

    public function deputado()
    {
        return $this->belongsTo(Deputado::class);
    }

    public function municipio()
    {
        return $this->belongsTo(Municipio::class, 'id_municipio', 'id_municipio');
    }

    public function lideranca()
    {
        return $this->belongsTo(Lideranca::class);
    }

    public function tipoMaterial()
    {
        return $this->belongsTo(CampanhaMaterialTipo::class, 'tipo_material_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function politicoEstadual()
    {
        return $this->belongsTo(CampanhaPolitico::class, 'politico_estadual_id');
    }

    public function politicoFederal()
    {
        return $this->belongsTo(CampanhaPolitico::class, 'politico_federal_id');
    }

    public function politicoSenador()
    {
        return $this->belongsTo(CampanhaPolitico::class, 'politico_senador_id');
    }

    public function politicoGovernador()
    {
        return $this->belongsTo(CampanhaPolitico::class, 'politico_governador_id');
    }

    public function politicoPresidente()
    {
        return $this->belongsTo(CampanhaPolitico::class, 'politico_presidente_id');
    }
}
