<?php

namespace App\Support;

/**
 * Deriva buckets estreitos a partir do user-agent. A string bruta é SEMPRE
 * descartada — nunca é persistida (regra de privacidade / LGPD).
 */
class AgenteUsuario
{
    /** Bucket de plataforma: desktop | mobile | tablet | outro. */
    public static function plataforma(?string $ua): ?string
    {
        if (! $ua) {
            return null;
        }

        $ua = strtolower($ua);

        if (str_contains($ua, 'ipad') || str_contains($ua, 'tablet')) {
            return 'tablet';
        }

        // Android sem "mobile" costuma ser tablet.
        if (str_contains($ua, 'android') && ! str_contains($ua, 'mobile')) {
            return 'tablet';
        }

        if (str_contains($ua, 'mobi') || str_contains($ua, 'iphone') || str_contains($ua, 'android')) {
            return 'mobile';
        }

        if (
            str_contains($ua, 'windows')
            || str_contains($ua, 'macintosh')
            || str_contains($ua, 'linux')
            || str_contains($ua, 'x11')
        ) {
            return 'desktop';
        }

        return 'outro';
    }

    /** Família do navegador: Chrome | Firefox | Safari | Edge | Outro. */
    public static function navegador(?string $ua): ?string
    {
        if (! $ua) {
            return null;
        }

        $ua = strtolower($ua);

        // Ordem importa: Edge e Chrome anunciam-se de forma sobreposta.
        if (str_contains($ua, 'edg')) {
            return 'Edge';
        }

        if (str_contains($ua, 'chrome') || str_contains($ua, 'crios')) {
            return 'Chrome';
        }

        if (str_contains($ua, 'firefox') || str_contains($ua, 'fxios')) {
            return 'Firefox';
        }

        if (str_contains($ua, 'safari')) {
            return 'Safari';
        }

        return 'Outro';
    }
}
