<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Status do Ativo muda sozinho quando um Chamado Externo é aberto ou
 * fechado sobre ele -- mas sempre editável na mão depois (confirmado
 * com o usuário: "muda sozinho, mas dá pra corrigir na mão"). Só entra
 * em ação em transições óbvias -- 'ativo' -> 'manutencao' ao abrir,
 * 'manutencao' -> 'ativo' ao fechar (e só se não sobrar nenhum outro
 * chamado externo aberto pro mesmo ativo). Se alguém já tinha
 * colocado o ativo em 'estoque' ou 'baixado' na mão, isso nunca é
 * sobrescrito automaticamente -- só essas duas transições específicas.
 * Toda mudança automática é registrada na Auditoria.
 */
class AtivoStatusAutomacaoService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function aoAbrirChamadoExterno(?int $ativoId): void
    {
        if (!$ativoId) {
            return;
        }

        $status = $this->statusAtual($ativoId);
        if ($status !== 'ativo') {
            return;
        }

        $this->pdo->prepare("UPDATE ativos SET status = 'manutencao' WHERE id = ?")->execute([$ativoId]);

        AuditService::registrar(
            'Ativos',
            'Status automático',
            "Ativo #{$ativoId}: status alterado de 'Em uso' para 'Em manutenção' -- chamado externo aberto."
        );
    }

    public function aoFecharChamadoExterno(?int $ativoId): void
    {
        if (!$ativoId) {
            return;
        }

        $status = $this->statusAtual($ativoId);
        if ($status !== 'manutencao') {
            return;
        }

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM chamados_externos WHERE ativo_id = ? AND status NOT IN ('resolvido', 'fechado')"
        );
        $stmt->execute([$ativoId]);

        if ((int)$stmt->fetchColumn() > 0) {
            return;
        }

        $this->pdo->prepare("UPDATE ativos SET status = 'ativo' WHERE id = ?")->execute([$ativoId]);

        AuditService::registrar(
            'Ativos',
            'Status automático',
            "Ativo #{$ativoId}: status alterado de 'Em manutenção' para 'Em uso' -- todos os chamados externos foram encerrados."
        );
    }

    private function statusAtual(int $ativoId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT status FROM ativos WHERE id = ?');
        $stmt->execute([$ativoId]);

        $status = $stmt->fetchColumn();

        return $status !== false ? $status : null;
    }
}
