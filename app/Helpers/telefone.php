<?php

/**
 * Formata um número de telefone brasileiro (com DDI 55) pro padrão
 * "55 (DDD) XXXX-XXXX" (fixo/local antigo, 8 dígitos) ou
 * "55 (DDD) 9XXXX-XXXX" (celular, 9 dígitos) -- usar em toda view em vez
 * de ecoar os dígitos crus vindos do WhatsApp/banco. Números que não
 * batem com esse padrão (outro país, formato inesperado) voltam como
 * vieram, sem tentar forçar uma formatação errada.
 */
function telefone_br(string $numero): string
{
    $digitos = preg_replace('/\D+/', '', $numero) ?? '';

    if (preg_match('/^55(\d{2})(\d{4})(\d{4})$/', $digitos, $m)) {
        return "55 ({$m[1]}) {$m[2]}-{$m[3]}";
    }

    if (preg_match('/^55(\d{2})(\d{5})(\d{4})$/', $digitos, $m)) {
        return "55 ({$m[1]}) {$m[2]}-{$m[3]}";
    }

    return $numero;
}
