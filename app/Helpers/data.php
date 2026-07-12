<?php

/**
 * Formata uma data/hora vinda do banco (formato MySQL "Y-m-d H:i:s") ou
 * qualquer string reconhecida por strtotime() no padrão brasileiro.
 * Usar em toda view em vez de ecoar a coluna crua -- o banco/PHP sempre
 * trabalham em Y-m-d, só a exibição é dd/mm/aaaa.
 */
function data_br(?string $valor, string $formato = 'd/m/Y H:i:s'): string
{
    if (!$valor) {
        return '—';
    }

    $timestamp = strtotime($valor);
    if ($timestamp === false) {
        return $valor;
    }

    return date($formato, $timestamp);
}
