<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Pesquisa de satisfação (NPS) pós-atendimento -- só entra em ação se o
 * setor do atendimento tiver `nps_ativo`. Fluxo: WhatsAppAtendimentoService::encerrar()
 * chama perguntar() em vez de fechar na hora (atendimento fica em
 * 'aguardando_nps'); a próxima mensagem do cliente é interceptada por
 * receberMensagem() e passa por processarResposta() em vez do chatbot
 * normal -- só aí o atendimento fecha de verdade.
 */
class WhatsAppNpsService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function perguntar(array $atendimentoComContato): void
    {
        $config = new WhatsAppConfigService();
        $chatbot = new WhatsAppChatbotService();

        $pergunta = $chatbot->renderizarTemplate($config->npsPergunta(), ['nome' => $atendimentoComContato['contato_nome']]);

        (new WhatsAppMensagemService())->enviar($atendimentoComContato['numero'], $pergunta);

        $atendimentoService = new WhatsAppAtendimentoService();
        $atendimentoService->registrarMensagemSaida((int)$atendimentoComContato['id'], $pergunta, 'bot');

        $this->pdo->prepare("UPDATE whatsapp_atendimentos SET status = 'aguardando_nps' WHERE id = ?")
            ->execute([$atendimentoComContato['id']]);
    }

    /**
     * @param array $atendimento precisa ter pelo menos id, contato_id, setor_id, usuario_id, numero, contato_nome
     */
    public function processarResposta(array $atendimento, string $texto): void
    {
        $atendimentoService = new WhatsAppAtendimentoService();
        $config = new WhatsAppConfigService();
        $chatbot = new WhatsAppChatbotService();

        $texto = trim($texto);

        if (!preg_match('/^\d{1,2}$/', $texto) || (int)$texto < 0 || (int)$texto > 10) {
            $mensagemErro = 'Não entendi -- responda só com um número de 0 a 10, por favor.';
            (new WhatsAppMensagemService())->enviar($atendimento['numero'], $mensagemErro);
            $atendimentoService->registrarMensagemSaida((int)$atendimento['id'], $mensagemErro, 'bot');
            return;
        }

        $nota = (int)$texto;

        $this->pdo->prepare(
            "INSERT INTO whatsapp_nps_respostas (atendimento_id, contato_id, setor_id, usuario_id, nota)
             VALUES (?, ?, ?, ?, ?)"
        )->execute([$atendimento['id'], $atendimento['contato_id'], $atendimento['setor_id'], $atendimento['usuario_id'], $nota]);

        $agradecimento = $chatbot->renderizarTemplate($config->npsAgradecimento(), ['nome' => $atendimento['contato_nome']]);
        (new WhatsAppMensagemService())->enviar($atendimento['numero'], $agradecimento);
        $atendimentoService->registrarMensagemSaida((int)$atendimento['id'], $agradecimento, 'bot');

        $this->pdo->prepare("UPDATE whatsapp_atendimentos SET status = 'encerrado', encerrado_em = NOW() WHERE id = ?")
            ->execute([$atendimento['id']]);
    }

    /**
     * Resumo (total, média, distribuição 0-10 e o score NPS clássico:
     * % promotores [9-10] menos % detratores [0-6], de -100 a 100) --
     * $setorId null = todos os setores.
     */
    public function resumo(?int $setorId = null, int $dias = 90): array
    {
        $sql = "SELECT nota FROM whatsapp_nps_respostas WHERE criado_em >= (NOW() - INTERVAL ? DAY)";
        $params = [$dias];

        if ($setorId !== null) {
            $sql .= ' AND setor_id = ?';
            $params[] = $setorId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $notas = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        $total = count($notas);
        $distribuicao = array_fill(0, 11, 0);
        foreach ($notas as $nota) {
            $distribuicao[$nota]++;
        }

        if ($total === 0) {
            return ['total' => 0, 'media' => null, 'score' => null, 'distribuicao' => $distribuicao];
        }

        $promotores = 0;
        $detratores = 0;
        foreach ($notas as $nota) {
            if ($nota >= 9) {
                $promotores++;
            } elseif ($nota <= 6) {
                $detratores++;
            }
        }

        return [
            'total' => $total,
            'media' => round(array_sum($notas) / $total, 1),
            'score' => (int)round((($promotores - $detratores) / $total) * 100),
            'distribuicao' => $distribuicao,
        ];
    }

    /**
     * Últimas respostas, com contato/setor/atendente pra exibir numa
     * tabela -- mesmo padrão de join dos outros listar() do módulo.
     */
    public function listar(int $limite = 50): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT n.*, c.nome AS contato_nome, c.numero, s.nome AS setor_nome, u.nome AS usuario_nome
             FROM whatsapp_nps_respostas n
             JOIN whatsapp_contatos c ON c.id = n.contato_id
             LEFT JOIN whatsapp_setores s ON s.id = n.setor_id
             LEFT JOIN usuarios u ON u.id = n.usuario_id
             ORDER BY n.id DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
