<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Motor do chatbot (árvore de menus, múltiplos níveis) + CRUD da
 * árvore usado pela tela de configuração. A mensagem de cada nó
 * "menu" é texto livre escrito pelo admin (inclusive a lista numerada
 * de opções) -- o motor só interpreta o NÚMERO que o cliente responde
 * como a posição (1, 2, 3...) entre os filhos ativos do nó atual, na
 * ordem de `ordem`. Por isso a tela mostra a posição de cada opção:
 * o admin precisa escrever esse número na mensagem do nó pai.
 */
class WhatsAppChatbotService
{
    private const TIPOS_VALIDOS = ['menu', 'resposta_final', 'encaminhar_setor'];
    private const MAX_TENTATIVAS_INVALIDAS = 3;

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    // ------------------------------------------------------------------
    // Boas-vindas (nó raiz) -- único ponto de entrada do bot
    // ------------------------------------------------------------------

    public function raiz(): ?array
    {
        return $this->buscarRaizBruta();
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function salvarRaiz(string $mensagem, bool $ativo): array
    {
        $mensagem = trim($mensagem);

        if ($mensagem === '') {
            return ['success' => false, 'message' => 'Escreva a mensagem de boas-vindas.'];
        }

        $raiz = $this->buscarRaizBruta();

        if ($raiz) {
            $stmt = $this->pdo->prepare('UPDATE whatsapp_chatbot_nos SET mensagem = ?, ativo = ? WHERE id = ?');
            $stmt->execute([$mensagem, $ativo ? 1 : 0, $raiz['id']]);

            return ['success' => true, 'message' => 'Mensagem de boas-vindas atualizada.'];
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO whatsapp_chatbot_nos (no_pai_id, ordem, rotulo, mensagem, tipo, ativo) VALUES (NULL, 0, 'Boas-vindas', ?, 'menu', ?)"
        );
        $stmt->execute([$mensagem, $ativo ? 1 : 0]);

        return ['success' => true, 'message' => 'Mensagem de boas-vindas criada.'];
    }

    // ------------------------------------------------------------------
    // Árvore de opções (filhos da raiz em diante) -- CRUD pra tela
    // ------------------------------------------------------------------

    public function arvoreDeOpcoes(): array
    {
        $raiz = $this->buscarRaizBruta();

        return $raiz ? $this->montarSubarvore((int)$raiz['id']) : [];
    }

    public function no(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM whatsapp_chatbot_nos WHERE id = ?');
        $stmt->execute([$id]);

        $no = $stmt->fetch(PDO::FETCH_ASSOC);

        return $no ?: null;
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function criarNo(int $noPaiId, string $rotulo, string $mensagem, string $tipo, ?int $setorDestinoId): array
    {
        $erro = $this->validarCampos($rotulo, $mensagem, $tipo, $setorDestinoId);
        if ($erro) {
            return $erro;
        }

        $stmtOrdem = $this->pdo->prepare('SELECT COALESCE(MAX(ordem), -1) + 1 FROM whatsapp_chatbot_nos WHERE no_pai_id = ?');
        $stmtOrdem->execute([$noPaiId]);
        $proximaOrdem = (int)$stmtOrdem->fetchColumn();

        $stmt = $this->pdo->prepare(
            'INSERT INTO whatsapp_chatbot_nos (no_pai_id, ordem, rotulo, mensagem, tipo, setor_destino_id) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $noPaiId,
            $proximaOrdem,
            trim($rotulo),
            trim($mensagem),
            $tipo,
            $tipo === 'encaminhar_setor' ? $setorDestinoId : null,
        ]);

        return ['success' => true, 'message' => 'Opção adicionada.'];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function atualizarNo(int $id, string $rotulo, string $mensagem, string $tipo, ?int $setorDestinoId, bool $ativo): array
    {
        $erro = $this->validarCampos($rotulo, $mensagem, $tipo, $setorDestinoId);
        if ($erro) {
            return $erro;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE whatsapp_chatbot_nos SET rotulo = ?, mensagem = ?, tipo = ?, setor_destino_id = ?, ativo = ? WHERE id = ?'
        );
        $stmt->execute([
            trim($rotulo),
            trim($mensagem),
            $tipo,
            $tipo === 'encaminhar_setor' ? $setorDestinoId : null,
            $ativo ? 1 : 0,
            $id,
        ]);

        return ['success' => true, 'message' => 'Opção atualizada.'];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function excluirNo(int $id): array
    {
        // FK ON DELETE CASCADE cuida dos descendentes -- avisa no
        // texto pra não ser surpresa (apaga uma sub-árvore inteira).
        $stmt = $this->pdo->prepare('DELETE FROM whatsapp_chatbot_nos WHERE id = ?');
        $stmt->execute([$id]);

        return ['success' => true, 'message' => 'Opção removida (e as opções abaixo dela, se houver).'];
    }

    // ------------------------------------------------------------------
    // Motor -- chamado pelo webhook quando o atendimento está em modo bot
    // ------------------------------------------------------------------

    public function processarMensagem(array $atendimento, string $numero, string $texto): void
    {
        $mensageiro = new WhatsAppMensagemService();
        $atendimentoService = new WhatsAppAtendimentoService();

        if (empty($atendimento['no_bot_atual_id'])) {
            $raiz = $this->buscarRaiz();

            if (!$raiz) {
                // nenhum chatbot configurado (ou raiz desativada) --
                // manda direto pra fila geral, sem tentar interpretar
                // a mensagem como escolha de menu
                $this->irParaFilaGeral((int)$atendimento['id']);
                return;
            }

            $this->entrarNo((int)$atendimento['id'], $raiz, $numero, $mensageiro, $atendimentoService, true);
            return;
        }

        $atual = $this->no((int)$atendimento['no_bot_atual_id']);

        if (!$atual) {
            $this->irParaFilaGeral((int)$atendimento['id']);
            return;
        }

        $filhos = $this->filhosAtivos((int)$atual['id']);
        $escolha = ctype_digit(trim($texto)) ? (int)trim($texto) : 0;
        $selecionado = ($escolha >= 1 && $escolha <= count($filhos)) ? $filhos[$escolha - 1] : null;

        if (!$selecionado) {
            $tentativas = (int)$atendimento['tentativas_invalidas_bot'] + 1;

            if ($tentativas >= self::MAX_TENTATIVAS_INVALIDAS) {
                $this->irParaFilaGeral((int)$atendimento['id']);
                return;
            }

            $this->pdo->prepare('UPDATE whatsapp_atendimentos SET tentativas_invalidas_bot = ? WHERE id = ?')
                ->execute([$tentativas, $atendimento['id']]);

            $aviso = "Opção inválida. " . $atual['mensagem'];
            $mensageiro->enviar($numero, $aviso);
            $atendimentoService->registrarMensagemSaida((int)$atendimento['id'], $aviso, 'bot');
            return;
        }

        $this->entrarNo((int)$atendimento['id'], $selecionado, $numero, $mensageiro, $atendimentoService, true);
    }

    private function entrarNo(
        int $atendimentoId,
        array $no,
        string $numero,
        WhatsAppMensagemService $mensageiro,
        WhatsAppAtendimentoService $atendimentoService,
        bool $reiniciaTentativas
    ): void {
        $mensageiro->enviar($numero, $no['mensagem']);
        $atendimentoService->registrarMensagemSaida($atendimentoId, $no['mensagem'], 'bot');

        if ($no['tipo'] === 'resposta_final') {
            $stmt = $this->pdo->prepare(
                "UPDATE whatsapp_atendimentos SET status = 'encerrado', encerrado_em = NOW(), no_bot_atual_id = ? WHERE id = ?"
            );
            $stmt->execute([$no['id'], $atendimentoId]);
            return;
        }

        if ($no['tipo'] === 'encaminhar_setor') {
            $stmt = $this->pdo->prepare(
                "UPDATE whatsapp_atendimentos SET status = 'fila', setor_id = ?, no_bot_atual_id = ? WHERE id = ?"
            );
            $stmt->execute([$no['setor_destino_id'], $no['id'], $atendimentoId]);
            return;
        }

        $sql = 'UPDATE whatsapp_atendimentos SET no_bot_atual_id = ?'
            . ($reiniciaTentativas ? ', tentativas_invalidas_bot = 0' : '')
            . ' WHERE id = ?';
        $this->pdo->prepare($sql)->execute([$no['id'], $atendimentoId]);
    }

    private function irParaFilaGeral(int $atendimentoId): void
    {
        $stmt = $this->pdo->prepare("UPDATE whatsapp_atendimentos SET status = 'fila', setor_id = NULL WHERE id = ?");
        $stmt->execute([$atendimentoId]);
    }

    private function buscarRaizBruta(): ?array
    {
        $stmt = $this->pdo->query('SELECT * FROM whatsapp_chatbot_nos WHERE no_pai_id IS NULL ORDER BY id LIMIT 1');
        $no = $stmt->fetch(PDO::FETCH_ASSOC);

        return $no ?: null;
    }

    private function buscarRaiz(): ?array
    {
        $stmt = $this->pdo->query("SELECT * FROM whatsapp_chatbot_nos WHERE no_pai_id IS NULL AND ativo = 1 ORDER BY id LIMIT 1");
        $no = $stmt->fetch(PDO::FETCH_ASSOC);

        return $no ?: null;
    }

    private function filhosAtivos(int $noPaiId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM whatsapp_chatbot_nos WHERE no_pai_id = ? AND ativo = 1 ORDER BY ordem, id');
        $stmt->execute([$noPaiId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function montarSubarvore(int $noPaiId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM whatsapp_chatbot_nos WHERE no_pai_id = ? ORDER BY ordem, id');
        $stmt->execute([$noPaiId]);
        $filhos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($filhos as $indice => $filho) {
            $filhos[$indice]['posicao'] = $indice + 1;
            $filhos[$indice]['filhos'] = $this->montarSubarvore((int)$filho['id']);
        }

        return $filhos;
    }

    /**
     * @return array{success: bool, message: string}|null null = campos válidos
     */
    private function validarCampos(string $rotulo, string $mensagem, string $tipo, ?int $setorDestinoId): ?array
    {
        if (trim($rotulo) === '' || trim($mensagem) === '') {
            return ['success' => false, 'message' => 'Preencha rótulo e mensagem.'];
        }

        if (!in_array($tipo, self::TIPOS_VALIDOS, true)) {
            return ['success' => false, 'message' => 'Tipo inválido.'];
        }

        if ($tipo === 'encaminhar_setor' && !$setorDestinoId) {
            return ['success' => false, 'message' => 'Escolha o setor de destino.'];
        }

        return null;
    }
}
