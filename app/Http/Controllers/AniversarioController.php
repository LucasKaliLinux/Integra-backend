<?php

namespace App\Http\Controllers;

use App\Http\Resources\AniversarioLiderancaResource;
use App\Models\Lideranca;
use Illuminate\Http\Request;

class AniversarioController extends Controller
{
    public function index(Request $request)
    {
        $deputadoId = $request->user()->deputado_id;

        // Próxima data de aniversário (ignorando o ano) e dias restantes até lá.
        $proximoAniversarioSql = "DATE_ADD(data_nascimento, INTERVAL (
            YEAR(CURDATE()) - YEAR(data_nascimento)
            + IF(DATE_FORMAT(data_nascimento, '%m-%d') < DATE_FORMAT(CURDATE(), '%m-%d'), 1, 0)
        ) YEAR)";

        $baseQuery = function () use ($deputadoId, $request) {
            return Lideranca::where('deputado_id', $deputadoId)
                ->whereNotNull('data_nascimento')
                ->filter($request->all());
        };

        $periodoSql = match ($request->input('periodo')) {
            'hoje' => "DATEDIFF({$proximoAniversarioSql}, CURDATE()) = 0",
            '7_dias' => "DATEDIFF({$proximoAniversarioSql}, CURDATE()) BETWEEN 0 AND 7",
            '30_dias' => "DATEDIFF({$proximoAniversarioSql}, CURDATE()) BETWEEN 0 AND 30",
            '90_dias' => "DATEDIFF({$proximoAniversarioSql}, CURDATE()) BETWEEN 0 AND 90",
            'este_ano' => "YEAR({$proximoAniversarioSql}) = YEAR(CURDATE())",
            default => null,
        };

        $query = $baseQuery()
            ->with([
                'municipio:id_municipio,nome',
                'classificacao:id,nome,slug',
                'funcao:id,nome,slug',
            ])
            ->selectRaw("liderancas.*, {$proximoAniversarioSql} as proximo_aniversario, DATEDIFF({$proximoAniversarioSql}, CURDATE()) as dias_ate_aniversario")
            ->when($periodoSql, fn ($q) => $q->whereRaw($periodoSql))
            ->orderByRaw("DATEDIFF({$proximoAniversarioSql}, CURDATE()) asc");

        $perPage = min($request->input('per_page', 12), 16);

        $paginado = $query->paginate($perPage)->withQueryString();

        $resumo = [
            'hoje' => $baseQuery()
                ->whereRaw("DATE_FORMAT(data_nascimento, '%m-%d') = DATE_FORMAT(CURDATE(), '%m-%d')")
                ->count(),

            'proximos_7_dias' => $baseQuery()
                ->whereRaw("DATEDIFF({$proximoAniversarioSql}, CURDATE()) BETWEEN 0 AND 7")
                ->count(),

            'este_mes' => $baseQuery()
                ->whereRaw('MONTH(data_nascimento) = MONTH(CURDATE())')
                ->count(),

            'mes_que_vem' => $baseQuery()
                ->whereRaw('MONTH(data_nascimento) = MOD(MONTH(CURDATE()), 12) + 1')
                ->count(),
        ];

        return AniversarioLiderancaResource::collection($paginado)->additional(['resumo' => $resumo]);
    }
}
