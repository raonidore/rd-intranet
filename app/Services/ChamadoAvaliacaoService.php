<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Avaliação pós-chamado -- pergunta só quando o chamado é marcado como
 * resolvido pela primeira vez (chamados_avaliacoes.chamado_id é UNIQUE,
 * então só existe uma resposta por chamado). O convite sai por e-mail
 * (link do Portal, hash+expiração do ChamadoSolicitanteTokenService)
 * quando há e-mail cadastrado; sem e-mail mas com telefone, sai por
 * WhatsApp -- e ali dá pra responder direto no chat (nota 1-5, depois
 * resolvido sim/não), mesmo padrão de 2 perguntas do WhatsAppNpsService,
 * mas numa arquitetura isolada: o estado "aguardando resposta" fica na
 * própria linha de chamados_avaliacoes (coluna pergunta_estado), nunca
 * em whatsapp_atendimentos -- WhatsAppAtendimentoService::receberMensagem()
 * checa isso ANTES de tudo (tentarProcessarRespostaWhatsApp()), então
 * não compete nem reaproveita o dispatcher/enum de status do atendimento
 * de suporte. Enquanto pergunta_estado não é NULL a linha é só um
 * rascunho pendente -- jaAvaliado()/buscar()/resumo() só contam uma
 * avaliação como "respondida" quando nota já foi preenchida.
 */
class ChamadoAvaliacaoService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    /** "Avaliado" quer dizer nota preenchida -- uma linha com pergunta_estado pendente (nota ainda NULL) não conta. */
    public function jaAvaliado(int $chamadoId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM chamados_avaliacoes WHERE chamado_id = ? AND nota IS NOT NULL');
        $stmt->execute([$chamadoId]);

        return (bool)$stmt->fetchColumn();
    }

    public function buscar(int $chamadoId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM chamados_avaliacoes WHERE chamado_id = ? AND nota IS NOT NULL');
        $stmt->execute([$chamadoId]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    /** Dispara o convite pra avaliar -- silencioso se já foi avaliado, sem contato cadastrado, ou se o canal disponível (e-mail/WhatsApp) não estiver configurado. */
    public function perguntar(array $chamado): void
    {
        if ($this->jaAvaliado((int)$chamado['id'])) {
            return;
        }

        $urlBase = (($_SERVER['HTTPS'] ?? 'off') !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
        $link = (new ChamadoSolicitanteTokenService())->emitirLink((int)$chamado['solicitante_id'], $urlBase);
        if ($link === null) {
            return;
        }

        if (!empty($chamado['solicitante_email'])) {
            $this->perguntarPorEmail($chamado, $link);
            return;
        }

        if (!empty($chamado['solicitante_telefone'])) {
            $this->perguntarPorWhatsApp($chamado, $link);
        }
    }

    private function perguntarPorEmail(array $chamado, string $link): void
    {
        $assunto = 'Como foi o atendimento do seu chamado #' . (int)$chamado['id'] . '?';
        $corpo = '<p>Olá, ' . htmlspecialchars($chamado['solicitante_nome']) . '!</p>'
            . '<p>Seu chamado <strong>#' . (int)$chamado['id'] . ' -- ' . htmlspecialchars($chamado['titulo']) . '</strong> foi marcado como resolvido.</p>'
            . '<p>Poderia avaliar o atendimento? Leva menos de um minuto.</p>'
            . '<p><a href="' . htmlspecialchars($link) . '">Avaliar atendimento</a></p>';

        (new EmailService())->enviar($chamado['solicitante_email'], $assunto, $corpo);
    }

    private const LEGENDA_NOTA = "\n\n5 - Muito satisfeito. O atendimento superou as expectativas, foi rápido e eficiente."
        . "\n4 - Satisfeito. O problema foi resolvido de forma correta e sem grandes transtornos."
        . "\n3 - Neutro ou indiferente. Um atendimento mediano, que não comprometeu mas também não encantou."
        . "\n2 - Insatisfeito. Houve demora, falha na comunicação ou o problema não foi totalmente resolvido."
        . "\n1 - Muito insatisfeito. Experiência ruim, atendimento rude ou solução ausente.";

    private const LEGENDA_RESOLVIDO = "\nDigite:\n1 - Para Sim!\n2 - Para Não!";

    /**
     * Manda a 1ª pergunta (nota 1-5) por WhatsApp e deixa uma linha
     * "pendente" em chamados_avaliacoes esperando a resposta -- quem
     * responder por lá é pego por tentarProcessarRespostaWhatsApp() antes
     * de qualquer coisa em WhatsAppAtendimentoService::receberMensagem().
     * O link do Portal continua junto, pra quem preferir responder por lá.
     */
    private function perguntarPorWhatsApp(array $chamado, string $link): void
    {
        $numero = (new WhatsAppContatoService())->normalizarNumeroBr($chamado['solicitante_telefone']);
        if ($numero === null) {
            return;
        }

        $this->upsertPendente((int)$chamado['id'], (int)$chamado['solicitante_id'], 'aguardando_nota');

        $texto = 'Olá, ' . $chamado['solicitante_nome'] . '! Seu chamado #' . (int)$chamado['id'] . ' -- ' . $chamado['titulo']
            . ' foi marcado como resolvido. De 1 a 5, qual nota você dá pro atendimento?' . self::LEGENDA_NOTA
            . "\n\n(Se preferir, também dá pra avaliar por aqui: " . $link . ')';

        (new WhatsAppMensagemService())->enviar($numero, $texto);
    }

    private function upsertPendente(int $chamadoId, int $solicitanteId, string $perguntaEstado): void
    {
        $this->pdo->prepare(
            'INSERT INTO chamados_avaliacoes (chamado_id, solicitante_id, pergunta_estado) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE pergunta_estado = VALUES(pergunta_estado)'
        )->execute([$chamadoId, $solicitanteId, $perguntaEstado]);
    }

    /**
     * Chamado ANTES de qualquer coisa em WhatsAppAtendimentoService::receberMensagem()
     * -- se achar uma avaliação de chamado pendente pra esse número,
     * processa e consome a mensagem (retorna true) sem tocar em
     * whatsapp_atendimentos; senão devolve false e o fluxo normal de
     * suporte segue intacto. Só intercepta respostas curtas (nota 1-5 ou
     * sim/não 1-2) -- uma mensagem mais longa é o cliente começando outro
     * assunto, não respondendo a pesquisa, e não deve ser "engolida".
     */
    public function tentarProcessarRespostaWhatsApp(string $numero, string $texto): bool
    {
        $texto = trim($texto);
        if ($texto === '' || strlen($texto) > 3) {
            return false;
        }

        $numeroNormalizado = (new WhatsAppContatoService())->normalizarNumeroBr($numero);
        if ($numeroNormalizado === null) {
            return false;
        }

        $pendente = $this->buscarPendentePorTelefone($numeroNormalizado);
        if ($pendente === null) {
            return false;
        }

        if ($pendente['pergunta_estado'] === 'aguardando_nota') {
            $this->processarNotaWhatsApp($pendente, $numeroNormalizado, $texto);
        } else {
            $this->processarResolucaoWhatsApp($pendente, $numeroNormalizado, $texto);
        }

        return true;
    }

    /** Poucas linhas pendentes de cada vez, na prática -- comparar telefone normalizado em PHP evita depender do formato exato salvo em chamados_solicitantes.telefone. */
    private function buscarPendentePorTelefone(string $numeroNormalizado): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT ca.chamado_id, ca.pergunta_estado, s.telefone, s.nome AS solicitante_nome
             FROM chamados_avaliacoes ca
             JOIN chamados_solicitantes s ON s.id = ca.solicitante_id
             WHERE ca.pergunta_estado IS NOT NULL
             ORDER BY ca.criado_em DESC"
        );
        $stmt->execute();

        $contatoService = new WhatsAppContatoService();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $linha) {
            if ($linha['telefone'] !== null && $contatoService->normalizarNumeroBr($linha['telefone']) === $numeroNormalizado) {
                return $linha;
            }
        }

        return null;
    }

    private function processarNotaWhatsApp(array $pendente, string $numero, string $texto): void
    {
        if (!preg_match('/^[1-5]$/', $texto)) {
            (new WhatsAppMensagemService())->enviar($numero, 'Não entendi -- responda só com um número de 1 a 5, por favor.');
            return;
        }

        $this->pdo->prepare('UPDATE chamados_avaliacoes SET nota = ?, pergunta_estado = ? WHERE chamado_id = ?')
            ->execute([(int)$texto, 'aguardando_resolvido', $pendente['chamado_id']]);

        $pergunta = 'Seu problema foi resolvido?' . self::LEGENDA_RESOLVIDO;
        (new WhatsAppMensagemService())->enviar($numero, $pergunta);
    }

    private function processarResolucaoWhatsApp(array $pendente, string $numero, string $texto): void
    {
        if ($texto !== '1' && $texto !== '2') {
            (new WhatsAppMensagemService())->enviar($numero, 'Não entendi -- digite 1 para Sim ou 2 para Não, por favor.');
            return;
        }

        $this->pdo->prepare('UPDATE chamados_avaliacoes SET resolvido = ?, pergunta_estado = NULL WHERE chamado_id = ?')
            ->execute([$texto === '1' ? 1 : 0, $pendente['chamado_id']]);

        (new WhatsAppMensagemService())->enviar($numero, 'Obrigado pela avaliação!');
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function registrar(int $chamadoId, int $solicitanteId, int $nota, ?bool $resolvido, ?string $comentario): array
    {
        if ($nota < 1 || $nota > 5) {
            return ['success' => false, 'message' => 'Escolha uma nota de 1 a 5.'];
        }
        if ($this->jaAvaliado($chamadoId)) {
            return ['success' => false, 'message' => 'Esse chamado já foi avaliado.'];
        }

        // Upsert: pode já existir uma linha "pendente" (convite por WhatsApp em andamento)
        // se o solicitante decidir responder pelo Portal em vez de continuar no chat.
        $stmt = $this->pdo->prepare(
            'INSERT INTO chamados_avaliacoes (chamado_id, solicitante_id, nota, resolvido, comentario, pergunta_estado)
             VALUES (?, ?, ?, ?, ?, NULL)
             ON DUPLICATE KEY UPDATE nota = VALUES(nota), resolvido = VALUES(resolvido), comentario = VALUES(comentario), pergunta_estado = NULL'
        );
        $stmt->execute([$chamadoId, $solicitanteId, $nota, $resolvido === null ? null : (int)$resolvido, $comentario ?: null]);

        return ['success' => true, 'message' => 'Obrigado pela avaliação!'];
    }

    /** Resumo pro painel de indicadores -- mesmas métricas do WhatsAppNpsService::resumo(); linha "pendente" (nota ainda não respondida) não entra na conta. */
    public function resumo(int $dias = 90): array
    {
        $stmt = $this->pdo->prepare('SELECT nota, resolvido FROM chamados_avaliacoes WHERE criado_em >= (NOW() - INTERVAL ? DAY) AND nota IS NOT NULL');
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
