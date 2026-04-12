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

        // Invalida dashboard também
        self::invalidarDashboard($userId);
        
        // ⬇️ NOVO: Invalida metadados de ações
        self::invalidarMetadados($userId);
    }

    /**
     * Invalida cache do dashboard do usuário
     */
    public static function invalidarDashboard(int $userId): void
    {
        Cache::forget("dashboard_{$userId}");
    }

    /**
     * ⬇️ NOVO: Invalida metadados de ações (min/max ano e valor)
     */
    public static function invalidarMetadados(int $userId): void
    {
        Cache::forget("acoes_metadata_{$userId}");
    }

    /**
     * ⬇️ NOVO: Invalida cache de importação Excel
     */
    public static function invalidarExcelTemplate(int $userId): void
    {
        Cache::forget("excel_template_data_{$userId}");
    }

    /**
     * Invalida TUDO relacionado ao usuário
     * (Útil quando faz mudanças grandes tipo sync de municípios ou importação em massa)
     */
    public static function invalidarTudo(int $userId): void
    {
        // Invalida dashboard
        self::invalidarDashboard($userId);

        // ⬇️ NOVO: Invalida metadados
        self::invalidarMetadados($userId);

        // ⬇️ NOVO: Invalida dados do template Excel
        self::invalidarExcelTemplate($userId);

        // Invalida ano base (pega deputado_id do user)
        $user = \App\Models\User::find($userId);
        if ($user && $user->deputado) {
            Cache::forget("ano_base_{$user->deputado->titulo_eleitoral}");
        }

        // Nota: Cache::forget não suporta wildcards com file driver
        // Se usar Redis, poderia fazer: Cache::tags(['user_'.$userId])->flush();
    }
}