<?php

namespace App\Console\Commands;

use App\Models\AtividadeSessao;
use Illuminate\Console\Command;

/**
 * Fecha, por inferência, sessões de atividade abertas há mais de 30 minutos
 * sem nova atividade. O encerramento é datado da última atividade conhecida e
 * a origem passa a `inferida` (não houve logout explícito).
 */
class FecharSessoesInativas extends Command
{
    /** Minutos de inatividade que caracterizam uma sessão abandonada. */
    private const TIMEOUT_MIN = 30;

    protected $signature = 'atividades:fechar-sessoes-inativas';

    protected $description = 'Encerra por inferência sessões de atividade inativas há mais de 30 minutos.';

    public function handle(): int
    {
        $limite = now()->subMinutes(self::TIMEOUT_MIN);

        $sessoes = AtividadeSessao::whereNull('encerrada_em')
            ->where('ultima_atividade_em', '<', $limite)
            ->get();

        foreach ($sessoes as $sessao) {
            $sessao->forceFill([
                'encerrada_em' => $sessao->ultima_atividade_em,
                'duracao_segundos' => (int) abs($sessao->iniciada_em->diffInSeconds($sessao->ultima_atividade_em)),
                'origem' => 'inferida',
            ])->save();
        }

        $this->info("Sessões encerradas por inatividade: {$sessoes->count()}");

        return self::SUCCESS;
    }
}
