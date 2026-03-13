<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Acao extends Model
{
    protected $table = 'acoes';

    protected $fillable = [
        'user_id',
        'id_municipio',
        'orgao_governo_id',
        'categoria_investimento_id',
        'tipo_acao_id',
        'status_id',
        'lideranca_solicitante_id',
        'titulo',
        'numero_sei',
        'instrumento_path',
        'valor',
        'ano',
        'observacao'
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'ano' => 'integer'
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function municipio()
    {
        return $this->belongsTo(Municipio::class, 'id_municipio', 'id_municipio');
    }

    public function orgao()
    {
        return $this->belongsTo(OrgaoGoverno::class, 'orgao_governo_id');
    }

    public function categoriaInvestimento()
    {
        return $this->belongsTo(CategoriaInvestimento::class, 'categoria_investimento_id');
    }

    public function tipoAcao()
    {
        return $this->belongsTo(TipoAcao::class, 'tipo_acao_id');
    }

    public function status()
    {
        return $this->belongsTo(StatusAcao::class, 'status_id');
    }

    public function liderancaSolicitante()
    {
        return $this->belongsTo(Lideranca::class, 'lideranca_solicitante_id');
    }

    public function historico()
    {
        return $this->hasMany(AcaoHistorico::class, 'acao_id')->orderBy('created_at');
    }
    
    // Scopes
    public function scopeFilter($query, $filters)
    {
        return $query
            ->when($filters['cidade'] ?? null, fn ($q, $v) =>
                $q->where('id_municipio', $v)
            )
            ->when($filters['status'] ?? null, fn ($q, $v) =>
                $q->where('status_id', $v)
            )
            ->when($filters['ano'] ?? null, fn ($q, $v) =>
                $q->where('ano', $v)
            )
            ->when($filters['orgao'] ?? null, fn ($q, $v) =>
                $q->where('orgao_governo_id', $v)
            )
            ->when($filters['categoria'] ?? null, fn ($q, $v) =>
                $q->where('categoria_investimento_id', $v)
            )
            ->when($filters['search'] ?? null, fn ($q, $v) =>
                $q->where('titulo', 'like', "%{$v}%")
            );
    }
}