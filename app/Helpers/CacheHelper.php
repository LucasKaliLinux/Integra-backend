<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;

class CacheHelper
{
    /**
     * Invalida cache de um município específico
     */
    public static function invalidarMunicipio(int $idMunicipio, int $userId): void
    {
        $keys = [
            "municipio_detalhes_{$idMunicipio}_{$userId}",
            "municipio_overview_{$idMunicipio}_{$userId}",
            "municipio_eleitoral_{$idMunicipio}_{$userId}",
            "municipio_estrutura_{$idMunicipio}_{$userId}",
            "municipio_historico_{$idMunicipio}_{$userId}",
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        // ⬇️ ADICIONA: Invalida dashboard também!
        self::invalidarDashboard($userId);
    }

    /**
     * Invalida cache do dashboard do usuário
     */
    public static function invalidarDashboard(int $userId): void
    {
        Cache::forget("dashboard_{$userId}");
    }

    /**
     * Invalida TUDO relacionado ao usuário
     * (Útil quando faz mudanças grandes tipo sync de municípios)
     */
    public static function invalidarTudo(int $userId): void
    {
        // Invalida dashboard
        self::invalidarDashboard($userId);

        // Invalida ano base (usado em várias queries)
        $tituloEleitoral = \App\Models\User::find($userId)?->titulo_eleitoral;
        if ($tituloEleitoral) {
            Cache::forget("ano_base_{$tituloEleitoral}");
        }

        // Invalida candidatos de municípios (se usar)
        // Cache::forget("candidatos_municipio_*"); // Não funciona com file driver
    }
}