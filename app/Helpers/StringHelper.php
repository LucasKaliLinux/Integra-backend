<?php

namespace App\Helpers;

class StringHelper
{
    /**
     * Palavras que devem permanecer em minúsculo (conectivos, preposições e artigos).
     */
    private static array $minusculas = [
        'da', 'de', 'do', 'das', 'dos',
        'a', 'e', 'o', 'as', 'os',
        'em', 'na', 'no', 'nas', 'nos',
        'para', 'por', 'com', 'sem', 'sob',
        'ao', 'aos', 'à', 'às',
        'um', 'uma', 'uns', 'umas',
    ];

    /**
     * Formata um nome brasileiro corretamente.
     * Ex: "LUCAS SANTOS DA ANUNCIAÇÃO" → "Lucas Santos da Anunciação"
     */
    public static function formatarNome(?string $nome): ?string
    {
        if (empty($nome)) {
            return $nome;
        }

        $palavras = explode(' ', mb_strtolower(trim($nome), 'UTF-8'));

        $formatado = array_map(function (string $palavra, int $indice) {
            // Primeira palavra sempre maiúscula, independente de ser conectivo
            if ($indice === 0) {
                return mb_strtoupper(mb_substr($palavra, 0, 1, 'UTF-8'), 'UTF-8')
                    . mb_substr($palavra, 1, null, 'UTF-8');
            }

            // Conectivos e preposições ficam minúsculos
            if (in_array($palavra, self::$minusculas)) {
                return $palavra;
            }

            // Demais palavras com inicial maiúscula
            return mb_strtoupper(mb_substr($palavra, 0, 1, 'UTF-8'), 'UTF-8')
                . mb_substr($palavra, 1, null, 'UTF-8');

        }, $palavras, array_keys($palavras));

        return implode(' ', $formatado);
    }
}