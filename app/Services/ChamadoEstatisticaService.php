<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Relatórios de chamados (aba "Geral" e "Ranking" da tela Estatísticas)
 * -- mesmo raciocínio do WhatsAppEstatisticaService. "Tempo de espera"
 * = aberto_em -> atribuido_em; "tempo de atendimento" = atribuido_em ->
 * resolvido_em. Só entram chamados já resolvidos/fechados; "Em tempo
 * real" é o retrato de agora, sem esperar nada fechar.
 */
class ChamadoEstatisticaService
{
    private PDO $pdo;

    public const PERIODOS_GERAL = ['mes', 'semana', 'dia', 'hora'];
    public const PERIODOS_RANKING = ['geral', 'mes', 'semana', 'hoje', 'hora'];

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function geral(string $periodo): array
    {
        $formato = match ($periodo) {
            'semana' => "DATE_FORMAT(DATE_SUB(c.resolvido_em, INTERVAL WEEKDAY(c.resolvido_em) DAY), '%d/%m/%Y')",
            'dia' => "DATE_FORMAT(c.resolvido_em, '%d/%m/%Y')",
            'hora' => "DATE_FORMAT(c.resolvido_em, '%d/%m/%Y %H:00')",
            default => "DATE_FORMAT(c.resolvido_em, '%m/%Y')",
        };

        $sql = "SELECT
                    {$formato} AS periodo_label,
                    MAX(c.resolvido_em) AS periodo_ordenacao,
                    COALESCE(s.nome, 'Sem Setor') AS setor_nome,
                    COUNT(*) AS total_chamados,
                    SUM(TIMESTAMPDIFF(SECOND, COALESCE(c.atribuido_em, c.aberto_em), c.resolvido_em)) AS tempo_total_atendimento,
                    AVG(TIMESTAMPDIFF(SECOND, c.aberto_em, COALESCE(c.atribuido_em, c.resolvido_em))) AS tempo_medio_espera,
                    SUM(CASE WHEN c.sla_resolucao_prazo IS NOT NULL AND c.resolvido_em <= c.sla_resolucao_prazo THEN 1 ELSE 0 END) AS dentro_sla,
                    SUM(CASE WHEN c.sla_resolucao_prazo IS NOT NULL THEN 1 ELSE 0 END) AS com_sla
                FROM chamados c
                LEFT JOIN chamados_setores s ON s.id = c.setor_id
                WHERE c.resolvido_em IS NOT NULL
                GROUP BY periodo_label, setor_nome
                ORDER BY periodo_ordenacao DESC, total_chamados DESC";

        $linhas = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        $totalChamados = 0;
        $tempoTotal = 0;
        $somaEspera = 0;
        $comEspera = 0;
        $totalDentroSla = 0;
        $totalComSla = 0;

        foreach ($linhas as $linha) {
            $totalChamados += (int)$linha['total_chamados'];
            $tempoTotal += (int)$linha['tempo_total_atendimento'];
            $totalDentroSla += (int)$linha['dentro_sla'];
            $totalComSla += (int)$linha['com_sla'];
            if ($linha['tempo_medio_espera'] !== null) {
                $somaEspera += (float)$linha['tempo_medio_espera'] * (int)$linha['total_chamados'];
                $comEspera += (int)$linha['total_chamados'];
            }
        }

        return [
            'linhas' => $linhas,
            'total_chamados' => $totalChamados,
            'tempo_total' => $tempoTotal,
            'tempo_medio_espera' => $comEspera > 0 ? (int)round($somaEspera / $comEspera) : null,
            'pct_sla_cumprido' => $totalComSla > 0 ? (int)round(($totalDentroSla / $totalComSla) * 100) : null,
        ];
    }

    public function ranking(string $periodo): array
    {
        $desde = $this->inicioPeriodoRanking($periodo);

        $sql = "SELECT
                    u.id AS usuario_id,
                    u.nome AS usuario_nome,
                    COUNT(*) AS total_chamados,
                    AVG(TIMESTAMPDIFF(SECOND, c.atribuido_em, c.resolvido_em)) AS tempo_medio,
                    (SELECT COALESCE(s2.nome, 'Sem Setor')
                     FROM chamados c2
                     LEFT JOIN chamados_setores s2 ON s2.id = c2.setor_id
                     WHERE c2.usuario_id = u.id AND c2.resolvido_em IS NOT NULL AND c2.resolvido_em >= ?
                     GROUP BY s2.nome
                     ORDER BY COUNT(*) DESC
                     LIMIT 1) AS setor_nome
                FROM chamados c
                JOIN usuarios u ON u.id = c.usuario_id
                WHERE c.resolvido_em IS NOT NULL AND c.atribuido_em IS NOT NULL AND c.resolvido_em >= ?
                GROUP BY u.id, u.nome
                ORDER BY total_chamados DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$desde, $desde]);
        $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($linhas as &$linha) {
            $linha['total_chamados'] = (int)$linha['total_chamados'];
            $linha['tempo_medio'] = $linha['tempo_medio'] !== null ? (int)round((float)$linha['tempo_medio']) : null;
        }

        return $linhas;
    }

    /** Retrato de agora: quanto tem em cada status não-encerrado. */
    public function tempoReal(): array
    {
        $porStatus = $this->pdo->query(
            "SELECT status, COUNT(*) AS total FROM chamados WHERE status NOT IN ('resolvido','fechado') GROUP BY status"
        )->fetchAll(PDO::FETCH_KEY_PAIR);

        $porSetorFila = $this->pdo->query(
            "SELECT COALESCE(s.nome, 'Sem Setor') AS setor_nome, COUNT(*) AS total
             FROM chamados c
             LEFT JOIN chamados_setores s ON s.id = c.setor_id
             WHERE c.status = 'fila'
             GROUP BY setor_nome
             ORDER BY total DESC"
        )->fetchAll(PDO::FETCH_ASSOC);

        $atendentesAtivos = (int)$this->pdo->query(
            "SELECT COUNT(DISTINCT usuario_id) FROM chamados WHERE status = 'em_atendimento'"
        )->fetchColumn();

        $slaEmRisco = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM chamados WHERE status NOT IN ('resolvido','fechado') AND sla_resolucao_prazo IS NOT NULL
             AND sla_resolucao_prazo > NOW() AND sla_resolucao_prazo <= DATE_ADD(NOW(), INTERVAL 1 HOUR)"
        )->fetchColumn();

        $slaEstourado = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM chamados WHERE status NOT IN ('resolvido','fechado') AND sla_resolucao_prazo IS NOT NULL AND sla_resolucao_prazo <= NOW()"
        )->fetchColumn();

        return [
            'fila' => (int)($porStatus['fila'] ?? 0),
            'em_atendimento' => (int)($porStatus['em_atendimento'] ?? 0),
            'aguardando_cliente' => (int)($porStatus['aguardando_cliente'] ?? 0),
            'atendentes_ativos' => $atendentesAtivos,
            'sla_em_risco' => $slaEmRisco,
            'sla_estourado' => $slaEstourado,
            'fila_por_setor' => $porSetorFila,
        ];
    }

    private function inicioPeriodoRanking(string $periodo): string
    {
        return match ($periodo) {
            'mes' => date('Y-m-01 00:00:00'),
            'semana' => date('Y-m-d 00:00:00', strtotime('monday this week')),
            'hoje' => date('Y-m-d 00:00:00'),
            'hora' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            default => '1970-01-01 00:00:00',
        };
    }
}
