<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Pesquisa de satisfação (NPS) pós-atendimento -- só entra em ação se o
 * setor do atendimento tiver `nps_ativo`. Fluxo em 2 perguntas (cada
 * uma um status próprio, igual ao motor do chatbot):
 *
 *   encerrar() -> perguntar() -> 'aguardando_nps_atendente'
 *   -> resposta 1-5 -> 'aguardando_nps_resolucao'
 *   -> resposta 1/2 -> agradecimento -> 'encerrado'
 *
 * A pergunta em si (texto) é configurável pelo usuário; a legenda das
 * notas e das opções de sim/não é fixa (WPP_NPS_LEGENDA_*) porque
 * alimenta as estatísticas -- mudar o significado das notas quebraria
 * a comparação histórica.
 */
class WhatsAppNpsService
{
    private const LEGENDA_ATENDENTE = "\n\n5 - Muito satisfeito. O atendimento superou as expectativas, foi rápido e eficiente."
        . "\n4 - Satisfeito. O problema foi resolvido de forma correta e sem grandes transtornos."
        . "\n3 - Neutro ou indiferente. Um atendimento mediano, que não comprometeu mas também não encantou."
        . "\n2 - Insatisfeito. Houve demora, falha na comunicação ou o problema não foi totalmente resolvido."
        . "\n1 - Muito insatisfeito. Experiência ruim, atendimento rude ou solução ausente.";

    private const LEGENDA_RESOLUCAO = "\nDigite:\n1 - Para Sim!\n2 - Para Não!";

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function perguntar(array $atendimentoComContato): void
    {
        $config = new WhatsAppConfigService();
        $chatbot = new WhatsAppChatbotService();
        $mensageiro = new WhatsAppMensagemService();
        $atendimentoService = new WhatsAppAtendimentoService();

        $nome = $atendimentoComContato['contato_nome'];
        $numero = $atendimentoComContato['numero'];

        $conexaoId = $atendimentoComContato['conexao_id'] ?? null;

        $mensagemEspera = $chatbot->renderizarTemplate($config->npsMensagemEspera(), ['nome' => $nome]);
        $envio1 = $mensageiro->enviar($numero, $mensagemEspera, $conexaoId);
        $atendimentoService->registrarMensagemSaida((int)$atendimentoComContato['id'], $mensagemEspera, 'bot', null, 'texto', null, 'nps', $envio1['success'] ? 'enviado' : 'falhou');

        $perguntaAtendente = $chatbot->renderizarTemplate($config->npsPerguntaAtendente(), ['nome' => $nome]) . self::LEGENDA_ATENDENTE;
        $envio2 = $mensageiro->enviar($numero, $perguntaAtendente, $conexaoId);
        $atendimentoService->registrarMensagemSaida((int)$atendimentoComContato['id'], $perguntaAtendente, 'bot', null, 'texto', null, 'nps', $envio2['success'] ? 'enviado' : 'falhou');

        $this->pdo->prepare("UPDATE whatsapp_atendimentos SET status = 'aguardando_nps_atendente' WHERE id = ?")
            ->execute([$atendimentoComContato['id']]);
    }

    /**
     * @param array $atendimento precisa ter id, contato_id, setor_id, usuario_id, status, numero, contato_nome
     */
    public function processarResposta(array $atendimento, string $texto): void
    {
        if ($atendimento['status'] === 'aguardando_nps_atendente') {
            $this->processarNotaAtendente($atendimento, $texto);
            return;
        }

        if ($atendimento['status'] === 'aguardando_nps_resolucao') {
            $this->processarResolucao($atendimento, $texto);
        }
    }

    private function processarNotaAtendente(array $atendimento, string $texto): void
    {
        $atendimentoService = new WhatsAppAtendimentoService();
        $texto = trim($texto);

        $conexaoId = $atendimento['conexao_id'] ?? null;

        if (!preg_match('/^[1-5]$/', $texto)) {
            $mensagemErro = 'Não entendi -- responda só com um número de 1 a 5, por favor.';
            $envio = (new WhatsAppMensagemService())->enviar($atendimento['numero'], $mensagemErro, $conexaoId);
            $atendimentoService->registrarMensagemSaida((int)$atendimento['id'], $mensagemErro, 'bot', null, 'texto', null, 'nps', $envio['success'] ? 'enviado' : 'falhou');
            return;
        }

        $this->pdo->prepare(
            "INSERT INTO whatsapp_nps_respostas (atendimento_id, contato_id, setor_id, usuario_id, nota_atendente)
             VALUES (?, ?, ?, ?, ?)"
        )->execute([$atendimento['id'], $atendimento['contato_id'], $atendimento['setor_id'], $atendimento['usuario_id'], (int)$texto]);

        $config = new WhatsAppConfigService();
        $chatbot = new WhatsAppChatbotService();
        $perguntaResolucao = $chatbot->renderizarTemplate($config->npsPerguntaResolucao(), ['nome' => $atendimento['contato_nome']]) . self::LEGENDA_RESOLUCAO;

        $envio = (new WhatsAppMensagemService())->enviar($atendimento['numero'], $perguntaResolucao, $conexaoId);
        $atendimentoService->registrarMensagemSaida((int)$atendimento['id'], $perguntaResolucao, 'bot', null, 'texto', null, 'nps', $envio['success'] ? 'enviado' : 'falhou');

        $this->pdo->prepare("UPDATE whatsapp_atendimentos SET status = 'aguardando_nps_resolucao' WHERE id = ?")
            ->execute([$atendimento['id']]);
    }

    private function processarResolucao(array $atendimento, string $texto): void
    {
        $atendimentoService = new WhatsAppAtendimentoService();
        $texto = trim($texto);

        $conexaoId = $atendimento['conexao_id'] ?? null;

        if ($texto !== '1' && $texto !== '2') {
            $mensagemErro = 'Não entendi -- digite 1 para Sim ou 2 para Não, por favor.';
            $envio = (new WhatsAppMensagemService())->enviar($atendimento['numero'], $mensagemErro, $conexaoId);
            $atendimentoService->registrarMensagemSaida((int)$atendimento['id'], $mensagemErro, 'bot', null, 'texto', null, 'nps', $envio['success'] ? 'enviado' : 'falhou');
            return;
        }

        $this->pdo->prepare("UPDATE whatsapp_nps_respostas SET resolvido = ? WHERE atendimento_id = ?")
            ->execute([$texto === '1' ? 1 : 0, $atendimento['id']]);

        $config = new WhatsAppConfigService();
        $chatbot = new WhatsAppChatbotService();
        $agradecimento = $chatbot->renderizarTemplate($config->npsAgradecimento(), ['nome' => $atendimento['contato_nome']]);

        $envio = (new WhatsAppMensagemService())->enviar($atendimento['numero'], $agradecimento, $conexaoId);
        $atendimentoService->registrarMensagemSaida((int)$atendimento['id'], $agradecimento, 'bot', null, 'texto', null, 'nps', $envio['success'] ? 'enviado' : 'falhou');

        $this->pdo->prepare("UPDATE whatsapp_atendimentos SET status = 'encerrado', encerrado_em = NOW() WHERE id = ?")
            ->execute([$atendimento['id']]);
    }

    /**
     * Resumo do período: total de respostas com nota, média (1-5), %
     * de resolução (entre quem já respondeu a 2ª pergunta) e
     * distribuição das notas -- $setorId null = todos os setores.
     */
    public function resumo(?int $setorId = null, int $dias = 90): array
    {
        $sql = "SELECT nota_atendente, resolvido FROM whatsapp_nps_respostas WHERE criado_em >= (NOW() - INTERVAL ? DAY)";
        $params = [$dias];

        if ($setorId !== null) {
            $sql .= ' AND setor_id = ?';
            $params[] = $setorId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $notas = array_values(array_filter(array_map(
            fn (array $l) => $l['nota_atendente'] !== null ? (int)$l['nota_atendente'] : null,
            $linhas
        ), fn ($n) => $n !== null));

        $resolucoes = array_values(array_filter(array_map(
            fn (array $l) => $l['resolvido'] !== null ? (int)$l['resolvido'] : null,
            $linhas
        ), fn ($n) => $n !== null));

        $distribuicao = array_fill(1, 5, 0);
        foreach ($notas as $nota) {
            $distribuicao[$nota]++;
        }

        return [
            'total' => count($linhas),
            'media' => $notas ? round(array_sum($notas) / count($notas), 1) : null,
            'indice_satisfacao' => $notas ? (int)round((array_sum($notas) / (count($notas) * 5)) * 100) : null,
            'pct_resolvido' => $resolucoes ? (int)round((array_sum($resolucoes) / count($resolucoes)) * 100) : null,
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

    /**
     * Índice de satisfação médio (0-100) por atendente, num período --
     * usado no ranking. $usuarioIds vazio = ninguém tem resposta ainda.
     *
     * @return array<int, float> usuario_id => índice (0-100)
     */
    public function indicePorAtendente(string $desde): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT usuario_id, AVG(nota_atendente) AS media
             FROM whatsapp_nps_respostas
             WHERE usuario_id IS NOT NULL AND nota_atendente IS NOT NULL AND criado_em >= ?
             GROUP BY usuario_id"
        );
        $stmt->execute([$desde]);

        $indices = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $linha) {
            $indices[(int)$linha['usuario_id']] = round(((float)$linha['media'] / 5) * 100, 1);
        }

        return $indices;
    }
}
