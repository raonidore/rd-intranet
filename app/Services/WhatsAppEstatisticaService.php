<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Relatórios de atendimento (aba "Geral" e "Ranking" da tela
 * Estatísticas) -- tudo calculado em cima de whatsapp_atendimentos,
 * sem tabela própria. "Tempo de espera" = do momento que o atendimento
 * abriu até um atendente assumir (aberto_em -> atribuido_em); "tempo de
 * atendimento" = do momento que foi assumido até encerrar
 * (atribuido_em -> encerrado_em). Só entram atendimentos já encerrados
 * (métricas de algo em andamento ainda não fazem sentido aqui -- isso
 * é o que a aba "Em tempo real" mostra).
 */
class WhatsAppEstatisticaService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public const PERIODOS_GERAL = ['mes', 'semana', 'dia', 'hora'];
    public const PERIODOS_RANKING = ['geral', 'mes', 'semana', 'hoje', 'hora'];

    /**
     * @return array{linhas: array, total_atendimentos: int, tempo_total: int, tempo_medio_espera: int|null}
     */
    public function geral(string $periodo): array
    {
        $formato = match ($periodo) {
            'semana' => "DATE_FORMAT(DATE_SUB(a.encerrado_em, INTERVAL WEEKDAY(a.encerrado_em) DAY), '%d/%m/%Y')",
            'dia' => "DATE_FORMAT(a.encerrado_em, '%d/%m/%Y')",
            'hora' => "DATE_FORMAT(a.encerrado_em, '%d/%m/%Y %H:00')",
            default => "DATE_FORMAT(a.encerrado_em, '%m/%Y')",
        };

        $sql = "SELECT
                    {$formato} AS periodo_label,
                    MAX(a.encerrado_em) AS periodo_ordenacao,
                    COALESCE(s.nome, 'Sem Setor') AS setor_nome,
                    COUNT(*) AS total_atendimentos,
                    SUM(TIMESTAMPDIFF(SECOND, COALESCE(a.atribuido_em, a.aberto_em), a.encerrado_em)) AS tempo_total_atendimento,
                    AVG(TIMESTAMPDIFF(SECOND, a.aberto_em, COALESCE(a.atribuido_em, a.encerrado_em))) AS tempo_medio_espera
                FROM whatsapp_atendimentos a
                LEFT JOIN whatsapp_setores s ON s.id = a.setor_id
                WHERE a.encerrado_em IS NOT NULL
                GROUP BY periodo_label, setor_nome
                ORDER BY periodo_ordenacao DESC, total_atendimentos DESC";

        $linhas = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        $totalAtendimentos = 0;
        $tempoTotal = 0;
        $somaEspera = 0;
        $comEspera = 0;

        foreach ($linhas as $linha) {
            $totalAtendimentos += (int)$linha['total_atendimentos'];
            $tempoTotal += (int)$linha['tempo_total_atendimento'];
            if ($linha['tempo_medio_espera'] !== null) {
                $somaEspera += (float)$linha['tempo_medio_espera'] * (int)$linha['total_atendimentos'];
                $comEspera += (int)$linha['total_atendimentos'];
            }
        }

        return [
            'linhas' => $linhas,
            'total_atendimentos' => $totalAtendimentos,
            'tempo_total' => $tempoTotal,
            'tempo_medio_espera' => $comEspera > 0 ? (int)round($somaEspera / $comEspera) : null,
        ];
    }

    /**
     * @return array<int, array{usuario_id:int, usuario_nome:string, setor_nome:?string, total_atendimentos:int, tempo_medio:?int, indice_satisfacao:?float}>
     */
    public function ranking(string $periodo): array
    {
        $desde = $this->inicioPeriodoRanking($periodo);

        $sql = "SELECT
                    u.id AS usuario_id,
                    u.nome AS usuario_nome,
                    COUNT(*) AS total_atendimentos,
                    AVG(TIMESTAMPDIFF(SECOND, a.atribuido_em, a.encerrado_em)) AS tempo_medio,
                    (SELECT COALESCE(s2.nome, 'Sem Setor')
                     FROM whatsapp_atendimentos a2
                     LEFT JOIN whatsapp_setores s2 ON s2.id = a2.setor_id
                     WHERE a2.usuario_id = u.id AND a2.encerrado_em IS NOT NULL AND a2.encerrado_em >= ?
                     GROUP BY s2.nome
                     ORDER BY COUNT(*) DESC
                     LIMIT 1) AS setor_nome
                FROM whatsapp_atendimentos a
                JOIN usuarios u ON u.id = a.usuario_id
                WHERE a.encerrado_em IS NOT NULL AND a.atribuido_em IS NOT NULL AND a.encerrado_em >= ?
                GROUP BY u.id, u.nome
                ORDER BY total_atendimentos DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$desde, $desde]);
        $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $indices = (new WhatsAppNpsService())->indicePorAtendente($desde);

        foreach ($linhas as &$linha) {
            $linha['total_atendimentos'] = (int)$linha['total_atendimentos'];
            $linha['tempo_medio'] = $linha['tempo_medio'] !== null ? (int)round((float)$linha['tempo_medio']) : null;
            $linha['indice_satisfacao'] = $indices[(int)$linha['usuario_id']] ?? null;
        }

        return $linhas;
    }

    /**
     * Retrato de agora: quanto tem em cada status não-encerrado, pra
     * dar uma noção de fila/carga sem esperar nada fechar primeiro.
     */
    public function tempoReal(): array
    {
        $porStatus = $this->pdo->query(
            "SELECT status, COUNT(*) AS total FROM whatsapp_atendimentos WHERE status != 'encerrado' GROUP BY status"
        )->fetchAll(PDO::FETCH_KEY_PAIR);

        $porSetorFila = $this->pdo->query(
            "SELECT COALESCE(s.nome, 'Sem Setor') AS setor_nome, COUNT(*) AS total
             FROM whatsapp_atendimentos a
             LEFT JOIN whatsapp_setores s ON s.id = a.setor_id
             WHERE a.status = 'fila'
             GROUP BY setor_nome
             ORDER BY total DESC"
        )->fetchAll(PDO::FETCH_ASSOC);

        $atendentesAtivos = (int)$this->pdo->query(
            "SELECT COUNT(DISTINCT usuario_id) FROM whatsapp_atendimentos WHERE status = 'em_atendimento'"
        )->fetchColumn();

        return [
            'em_espera_bot' => (int)($porStatus['bot'] ?? 0),
            'fila' => (int)($porStatus['fila'] ?? 0),
            'em_atendimento' => (int)($porStatus['em_atendimento'] ?? 0),
            'aguardando_nps' => (int)($porStatus['aguardando_nps_atendente'] ?? 0) + (int)($porStatus['aguardando_nps_resolucao'] ?? 0),
            'atendentes_ativos' => $atendentesAtivos,
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
