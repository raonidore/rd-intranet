<?php

namespace App\Services;

use PDO;

/**
 * Número de controle legível (ex: "CI-020926-1", "CE-020926-1") pra
 * Chamados (interno) e Chamados Externos -- prefixo + data (DDMMAA) +
 * posição sequencial NAQUELE DIA. Usado só como rótulo pro humano; o
 * `id` (auto-increment) continua sendo o identificador técnico em
 * URLs/FKs/joins, sem nenhuma mudança.
 */
class NumeroControleService
{
    /**
     * Posição = "quantos registros desse dia têm id menor ou igual ao
     * meu" -- não precisa de lock/transação (auto-increment já garante
     * a ordem), e não fica bagunçado se um registro antigo do mesmo dia
     * for excluído depois (só abre um buraco no número, igual
     * protocolo/nota fiscal de verdade).
     *
     * A data de referência é lida do próprio registro no banco (não
     * `date('Y-m-d H:i:s')` do PHP) porque o timezone do PHP pode não
     * bater com o do MySQL -- usar o relógio do PHP fazia a comparação
     * `DATE(coluna) = DATE(?)` não achar nem o próprio registro recém-
     * inserido perto da virada do dia.
     */
    public static function gerar(PDO $pdo, string $tabela, string $colunaData, string $prefixo, int $id): string
    {
        $stmt = $pdo->prepare("SELECT {$colunaData} FROM {$tabela} WHERE id = ?");
        $stmt->execute([$id]);
        $dataReferencia = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$tabela} WHERE DATE({$colunaData}) = DATE(?) AND id <= ?");
        $stmt->execute([$dataReferencia, $id]);
        $posicao = (int)$stmt->fetchColumn();

        return $prefixo . '-' . date('dmy', strtotime($dataReferencia)) . '-' . $posicao;
    }

    /**
     * Prévia do número que o PRÓXIMO registro (ainda não criado) vai
     * receber -- pra mostrar no formulário de abertura antes de o
     * usuário confirmar. Usa `CURDATE()` do próprio MySQL (não o
     * relógio do PHP) pelo mesmo motivo do método acima. É só uma
     * prévia: se dois chamados forem abertos ao mesmo tempo o número
     * real pode variar em 1, mas isso não trava nada -- `gerar()` é
     * quem decide o número de verdade, na hora da criação.
     */
    public static function previewProximo(PDO $pdo, string $tabela, string $colunaData, string $prefixo): string
    {
        $hoje = $pdo->query('SELECT CURDATE()')->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$tabela} WHERE DATE({$colunaData}) = ?");
        $stmt->execute([$hoje]);
        $posicao = (int)$stmt->fetchColumn() + 1;

        return $prefixo . '-' . date('dmy', strtotime($hoje)) . '-' . $posicao;
    }
}
