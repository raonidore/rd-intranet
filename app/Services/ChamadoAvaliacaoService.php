<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Avaliação pós-chamado -- pergunta só quando o chamado é marcado como
 * resolvido pela primeira vez (chamados_avaliacoes.chamado_id é UNIQUE,
 * então só existe uma resposta por chamado). Hoje o único canal de
 * pergunta é o link do Portal por e-mail (mesmo hash+expiração do
 * ChamadoSolicitanteTokenService) -- quando a abertura via WhatsApp
 * existir, o canal 'whatsapp' deve perguntar por lá também, reaproveitando
 * o motor de 2 perguntas do WhatsAppNpsService, igual mapeado no plano.
 */
class ChamadoAvaliacaoService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function jaAvaliado(int $chamadoId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM chamados_avaliacoes WHERE chamado_id = ?');
        $stmt->execute([$chamadoId]);

        return (bool)$stmt->fetchColumn();
    }

    public function buscar(int $chamadoId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM chamados_avaliacoes WHERE chamado_id = ?');
        $stmt->execute([$chamadoId]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    /** Dispara o convite pra avaliar -- silencioso se já foi avaliado, sem e-mail cadastrado ou SMTP não configurado. */
    public function perguntar(array $chamado): void
    {
        if ($this->jaAvaliado((int)$chamado['id']) || empty($chamado['solicitante_email'])) {
            return;
        }

        $urlBase = (($_SERVER['HTTPS'] ?? 'off') !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
        $link = (new ChamadoSolicitanteTokenService())->emitirLink((int)$chamado['solicitante_id'], $urlBase);
        if ($link === null) {
            return;
        }

        $assunto = 'Como foi o atendimento do seu chamado #' . (int)$chamado['id'] . '?';
        $corpo = '<p>Olá, ' . htmlspecialchars($chamado['solicitante_nome']) . '!</p>'
            . '<p>Seu chamado <strong>#' . (int)$chamado['id'] . ' -- ' . htmlspecialchars($chamado['titulo']) . '</strong> foi marcado como resolvido.</p>'
            . '<p>Poderia avaliar o atendimento? Leva menos de um minuto.</p>'
            . '<p><a href="' . htmlspecialchars($link) . '">Avaliar atendimento</a></p>';

        (new EmailService())->enviar($chamado['solicitante_email'], $assunto, $corpo);
    }

    /** @return array{success: bool, message: string} */
    public function registrar(int $chamadoId, int $solicitanteId, int $nota, ?bool $resolvido, ?string $comentario): array
    {
        if ($nota < 1 || $nota > 5) {
            return ['success' => false, 'message' => 'Escolha uma nota de 1 a 5.'];
        }
        if ($this->jaAvaliado($chamadoId)) {
            return ['success' => false, 'message' => 'Esse chamado já foi avaliado.'];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO chamados_avaliacoes (chamado_id, solicitante_id, nota, resolvido, comentario) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$chamadoId, $solicitanteId, $nota, $resolvido === null ? null : (int)$resolvido, $comentario ?: null]);

        return ['success' => true, 'message' => 'Obrigado pela avaliação!'];
    }

    /** Resumo pro painel de indicadores -- mesmas métricas do WhatsAppNpsService::resumo(). */
    public function resumo(int $dias = 90): array
    {
        $stmt = $this->pdo->prepare('SELECT nota, resolvido FROM chamados_avaliacoes WHERE criado_em >= (NOW() - INTERVAL ? DAY)');
        $stmt->execute([$dias]);
        $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $notas = array_map(fn (array $l) => (int)$l['nota'], $linhas);
        $resolucoes = array_values(array_filter(
            array_map(fn (array $l) => $l['resolvido'] !== null ? (int)$l['resolvido'] : null, $linhas),
            fn ($n) => $n !== null
        ));

        return [
            'total' => count($linhas),
            'media' => $notas ? round(array_sum($notas) / count($notas), 1) : null,
            'indice_satisfacao' => $notas ? (int)round((array_sum($notas) / (count($notas) * 5)) * 100) : null,
            'pct_resolvido' => $resolucoes ? (int)round((array_sum($resolucoes) / count($resolucoes)) * 100) : null,
        ];
    }
}
