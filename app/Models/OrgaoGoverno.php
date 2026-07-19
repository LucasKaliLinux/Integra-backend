<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrgaoGoverno extends Model
{
    protected $table = 'orgaos_governo';

    protected $fillable = [
        'tipo_orgao_id',
        'nome',
        'sigla',
    ];

    public function tipoOrgao()
    {
        return $this->belongsTo(TipoOrgao::class, 'tipo_orgao_id');
    }

    public function acoes()
    {
        return $this->hasMany(Acao::class, 'orgao_governo_id');
    }

    public function scopeFilter($query, $filters)
    {
        return $query
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where(fn ($subQ) => $subQ->where('nome', 'like', "%{$v}%")
                ->orWhere('sigla', 'like', "%{$v}%")
            )
            )
            ->when($filters['tipo_orgao_id'] ?? null, fn ($q, $v) => $q->where('tipo_orgao_id', $v)
            )
            ->when($filters['esfera_id'] ?? null, fn ($q, $v) => $q->whereHas('tipoOrgao', fn ($subQ) => $subQ->where('esfera_governo_id', $v)
            )
            );
    }
}
