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
    public function scopeFilter($query, array $filters)
    {
        // Município
        if (!empty($filters['municipio'])) {
            $query->where('acoes.id_municipio', $filters['municipio']); // ⬅️ PREFIXO acoes.
        }

        // Status
        if (!empty($filters['status'])) {
            $query->where('acoes.status_id', $filters['status']); // ⬅️ PREFIXO acoes.
        }

        // Ano específico
        if (!empty($filters['ano'])) {
            $query->where('acoes.ano', $filters['ano']); // ⬅️ PREFIXO acoes.
        }

        // Ano mínimo
        if (!empty($filters['ano_min'])) {
            $query->where('acoes.ano', '>=', $filters['ano_min']); // ⬅️ PREFIXO acoes.
        }

        // Ano máximo
        if (!empty($filters['ano_max'])) {
            $query->where('acoes.ano', '<=', $filters['ano_max']); // ⬅️ PREFIXO acoes.
        }

        // Valor mínimo
        if (!empty($filters['valor_min'])) {
            $query->where('acoes.valor', '>=', $filters['valor_min']); // ⬅️ PREFIXO acoes.
        }

        // Valor máximo
        if (!empty($filters['valor_max'])) {
            $query->where('acoes.valor', '<=', $filters['valor_max']); // ⬅️ PREFIXO acoes.
        }

        // Órgão
        if (!empty($filters['orgao'])) {
            $query->where('acoes.orgao_governo_id', $filters['orgao']); // ⬅️ PREFIXO acoes.
        }

        // Categoria
        if (!empty($filters['categoria'])) {
            $query->where('acoes.categoria_investimento_id', $filters['categoria']); // ⬅️ PREFIXO acoes.
        }

        // Tipo de ação
        if (!empty($filters['tipo_acao'])) {
            $query->where('acoes.tipo_acao_id', $filters['tipo_acao']); // ⬅️ PREFIXO acoes.
        }

        // Esfera (via relationship - precisa de JOIN)
        if (!empty($filters['esfera'])) {
            $query->whereHas('orgao.tipoOrgao', function($q) use ($filters) {
                $q->where('esfera_governo_id', $filters['esfera']);
            });
        }

        // Tipo de órgão (via relationship - precisa de JOIN)
        if (!empty($filters['tipo_orgao'])) {
            $query->whereHas('orgao', function($q) use ($filters) {
                $q->where('tipo_orgao_id', $filters['tipo_orgao']);
            });
        }

        // Busca textual (título ou número SEI)
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('acoes.titulo', 'like', "%{$search}%") // ⬅️ PREFIXO acoes.
                ->orWhere('acoes.numero_sei', 'like', "%{$search}%"); // ⬅️ PREFIXO acoes.
            });
        }

        return $query;
    }

    public function scopeSort($query, ?string $sortBy = null, ?string $sortOrder = 'asc')
    {
        // Valida ordem
        $sortOrder = strtolower($sortOrder) === 'desc' ? 'desc' : 'asc';

        // Se não passou sortBy ou é inválido, usa padrão (ID = ordem de inserção)
        if (!$sortBy) {
            return $query->orderBy('id', 'desc'); // Padrão: mais recentes primeiro
        }

        // Ordenações diretas (colunas da própria tabela)
        $directSorts = [
            'titulo' => 'titulo',
            'valor' => 'valor',
            'ano' => 'ano'
        ];

        if (isset($directSorts[$sortBy])) {
            return $query->orderBy($directSorts[$sortBy], $sortOrder);
        }

        // Ordenações via relacionamento
        switch ($sortBy) {
            case 'esfera':
                return $query
                    ->join('orgaos_governo as og', 'acoes.orgao_governo_id', '=', 'og.id')
                    ->join('tipos_orgao as to', 'og.tipo_orgao_id', '=', 'to.id')
                    ->join('esferas_governo as eg', 'to.esfera_governo_id', '=', 'eg.id')
                    ->orderBy('eg.nome', $sortOrder)
                    ->select('acoes.*'); // Evita conflito de colunas

            case 'orgao':
                return $query
                    ->join('orgaos_governo as og', 'acoes.orgao_governo_id', '=', 'og.id')
                    ->orderBy('og.nome', $sortOrder)
                    ->select('acoes.*');

            case 'categoria':
                return $query
                    ->join('categorias_investimento as ci', 'acoes.categoria_investimento_id', '=', 'ci.id')
                    ->orderBy('ci.nome', $sortOrder)
                    ->select('acoes.*');

            case 'municipio':
                return $query
                    ->join('municipios as m', 'acoes.id_municipio', '=', 'm.id_municipio')
                    ->orderBy('m.nome', $sortOrder)
                    ->select('acoes.*');

            case 'status':
                return $query
                    ->join('status_acao as sa', 'acoes.status_id', '=', 'sa.id')
                    ->orderBy('sa.ordem', $sortOrder) // ⬅️ Ordena pela ordem lógica (1,2,3,4,5)
                    ->select('acoes.*');

            default:
                // Se passou algo inválido, usa padrão
                return $query->orderBy('id', 'desc');
        }
    }
}