<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Motor do chatbot (árvore de menus, múltiplos níveis) + CRUD da
 * árvore usado pela tela de configuração. A mensagem de um nó tipo
 * 'menu' é só o texto de saudação/introdução -- a lista numerada das
 * opções filhas é GERADA automaticamente (montarMensagemDoNo()), nunca
 * digitada à mão pelo admin. O motor interpreta o NÚMERO que o cliente
 * responde como a posição (1, 2, 3...) entre os filhos ativos do nó
 * atual, na mesma ordem usada pra gerar a lista -- por isso os dois
 * (texto mostrado e número aceito) nunca desincronizam.
 */
class WhatsAppChatbotService
{
    private const TIPOS_VALIDOS = ['menu', 'resposta_final', 'encaminhar_setor', 'abrir_chamado'];
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

    /**
     * Filhos diretos de um nível (TODOS, não só os ativos -- usado pela
     * tela, que precisa listar tudo pra edição; o motor usa a versão
     * privada filhosAtivos()). Com a posição (1, 2, 3...) já calculada,
     * mesmo número que o cliente digita pra escolher aquela opção.
     */
    public function filhos(int $noPaiId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM whatsapp_chatbot_nos WHERE no_pai_id = ? ORDER BY ordem, id');
        $stmt->execute([$noPaiId]);
        $filhos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($filhos as $indice => $filho) {
            $filhos[$indice]['posicao'] = $indice + 1;
        }

        return $filhos;
    }

    public function no(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM whatsapp_chatbot_nos WHERE id = ?');
        $stmt->execute([$id]);

        $no = $stmt->fetch(PDO::FETCH_ASSOC);

        return $no ?: null;
    }

    /** Caminho da raiz até o nó (inclusive), pra breadcrumb da tela. */
    public function caminhoAteRaiz(int $noId): array
    {
        $caminho = [];
        $atual = $this->no($noId);

        while ($atual) {
            array_unshift($caminho, $atual);
            $atual = $atual['no_pai_id'] !== null ? $this->no((int)$atual['no_pai_id']) : null;
        }

        return $caminho;
    }

    /**
     * Monta o texto de verdade que vai pro cliente: aplica os
     * templates ({nome}/{periodo}) e, só pra nó tipo 'menu', acrescenta
     * a lista numerada dos filhos ativos (nunca escrita à mão).
     */
    public function montarMensagemDoNo(array $no, array $filhosAtivos, array $contato): string
    {
        $texto = $this->renderizarTemplate($no['mensagem'], $contato);

        if ($no['tipo'] !== 'menu' || empty($filhosAtivos)) {
            return $texto;
        }

        $linhas = [];
        foreach ($filhosAtivos as $indice => $filho) {
            $linhas[] = ($indice + 1) . ' - ' . $filho['rotulo'];
        }

        return $texto . "\n\n" . implode("\n", $linhas);
    }

    public function renderizarTemplate(string $texto, array $contato): string
    {
        $hora = (int)date('G');
        $periodo = $hora < 12 ? 'Bom dia' : ($hora < 18 ? 'Boa tarde' : 'Boa noite');
        $nome = trim((string)($contato['nome'] ?? ''));

        return str_replace(['{nome}', '{periodo}'], [$nome, $periodo], $texto);
    }

    /**
     * Salva TODAS as opções de um nível de uma vez só (a tela manda a
     * lista inteira) -- faz diff contra o que já existe: atualiza quem
     * tem id, cria quem não tem, remove quem sumiu da lista (cascade
     * cuida dos descendentes de quem foi removido). Tudo numa transação
     * só, pra nunca ficar num estado parcial se der erro no meio.
     *
     * @param array<int, array{id: ?int, rotulo: string, tipo: string, setor_destino_id: ?int, mensagem: ?string}> $linhas
     * @return array{success: bool, message: string}
     */
    public function salvarOpcoes(int $noPaiId, array $linhas): array
    {
        if (!$this->no($noPaiId)) {
            return ['success' => false, 'message' => 'Nível não encontrado.'];
        }

        $existeStmt = $this->pdo->prepare('SELECT id FROM whatsapp_chatbot_nos WHERE no_pai_id = ?');
        $existeStmt->execute([$noPaiId]);
        $idsExistentes = array_map('intval', $existeStmt->fetchAll(PDO::FETCH_COLUMN));

        $idsMantidos = [];
        $ordem = 0;

        $this->pdo->beginTransaction();

        try {
            foreach ($linhas as $linha) {
                $rotulo = trim((string)($linha['rotulo'] ?? ''));

                if ($rotulo === '') {
                    continue; // linha em branco (opção adicionada e não preenchida) -- ignora, não é erro
                }

                $tipo = (string)($linha['tipo'] ?? '');
                if (!in_array($tipo, self::TIPOS_VALIDOS, true)) {
                    throw new RuntimeException("Tipo inválido na opção \"{$rotulo}\".");
                }

                $setorDestinoId = !empty($linha['setor_destino_id']) ? (int)$linha['setor_destino_id'] : null;
                if ($tipo === 'encaminhar_setor' && !$setorDestinoId) {
                    throw new RuntimeException("Escolha o setor de destino da opção \"{$rotulo}\".");
                }
                if (!in_array($tipo, ['encaminhar_setor', 'abrir_chamado'], true)) {
                    $setorDestinoId = null;
                }

                // "Abre chamado" precisa saber em qual categoria do
                // módulo Chamados registrar o ticket -- setor_destino_id
                // (whatsapp_setores) continua sendo só pra onde a
                // conversa é encaminhada depois, cadastro diferente.
                $categoriaChamadoId = !empty($linha['categoria_chamado_id']) ? (int)$linha['categoria_chamado_id'] : null;
                if ($tipo === 'abrir_chamado' && !$categoriaChamadoId) {
                    throw new RuntimeException("Escolha a categoria de chamado da opção \"{$rotulo}\".");
                }
                if ($tipo !== 'abrir_chamado') {
                    $categoriaChamadoId = null;
                }

                $mensagem = trim((string)($linha['mensagem'] ?? ''));
                if ($mensagem === '') {
                    if ($tipo === 'encaminhar_setor') {
                        // pra "encaminha pro setor" a mensagem é opcional --
                        // sem ela, manda um texto padrão de transferência
                        $mensagem = "Encaminhando você para o setor {$rotulo}. Aguarde um instante.";
                    } elseif ($tipo === 'abrir_chamado') {
                        $mensagem = "Chamado aberto! Nossa equipe vai continuar por aqui.";
                    } else {
                        throw new RuntimeException("Escreva a mensagem da opção \"{$rotulo}\".");
                    }
                }

                $id = !empty($linha['id']) ? (int)$linha['id'] : null;

                if ($id !== null && in_array($id, $idsExistentes, true)) {
                    $upd = $this->pdo->prepare(
                        // ativo = 1 sempre: a tela nova não tem toggle de
                        // "opção inativa" (só existe/removida) -- força
                        // reativar aqui pra evitar uma opção antiga que
                        // ficou ativo=0 (criada pela tela antiga, com
                        // formulário por nó) aparecer no editor mas ser
                        // pulada pelo motor sem nenhum jeito de corrigir.
                        'UPDATE whatsapp_chatbot_nos SET rotulo = ?, mensagem = ?, tipo = ?, setor_destino_id = ?, categoria_chamado_id = ?, ordem = ?, ativo = 1 WHERE id = ?'
                    );
                    $upd->execute([$rotulo, $mensagem, $tipo, $setorDestinoId, $categoriaChamadoId, $ordem, $id]);
                    $idsMantidos[] = $id;
                } else {
                    $ins = $this->pdo->prepare(
                        'INSERT INTO whatsapp_chatbot_nos (no_pai_id, ordem, rotulo, mensagem, tipo, setor_destino_id, categoria_chamado_id) VALUES (?, ?, ?, ?, ?, ?, ?)'
                    );
                    $ins->execute([$noPaiId, $ordem, $rotulo, $mensagem, $tipo, $setorDestinoId, $categoriaChamadoId]);
                    $idsMantidos[] = (int)$this->pdo->lastInsertId();
                }

                $ordem++;
            }

            $idsRemover = array_values(array_diff($idsExistentes, $idsMantidos));
            if ($idsRemover) {
                $marcadores = implode(',', array_fill(0, count($idsRemover), '?'));
                $this->pdo->prepare("DELETE FROM whatsapp_chatbot_nos WHERE id IN ({$marcadores})")->execute($idsRemover);
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }

        return ['success' => true, 'message' => 'Fluxo salvo.'];
    }

    // ------------------------------------------------------------------
    // Motor -- chamado pelo webhook quando o atendimento está em modo bot
    // ------------------------------------------------------------------

    public function processarMensagem(array $atendimento, string $numero, string $texto): void
    {
        $mensageiro = new WhatsAppMensagemService();
        $atendimentoService = new WhatsAppAtendimentoService();
        $contato = (new WhatsAppContatoService())->buscarPorId((int)$atendimento['contato_id']) ?? [];

        if (empty($atendimento['no_bot_atual_id'])) {
            $raiz = $this->buscarRaiz();

            if (!$raiz) {
                // nenhum chatbot configurado (ou raiz desativada) --
                // manda direto pra fila geral, sem tentar interpretar
                // a mensagem como escolha de menu
                $this->irParaFilaGeral((int)$atendimento['id']);
                return;
            }

            $this->entrarNo((int)$atendimento['id'], $raiz, $numero, $contato, $mensageiro, $atendimentoService, true);
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

            $aviso = "Opção inválida. " . $this->montarMensagemDoNo($atual, $filhos, $contato);
            $envio = $mensageiro->enviar($numero, $aviso);
            $atendimentoService->registrarMensagemSaida((int)$atendimento['id'], $aviso, 'bot', null, 'texto', null, 'atendimento', $envio['success'] ? 'enviado' : 'falhou');
            return;
        }

        $this->entrarNo((int)$atendimento['id'], $selecionado, $numero, $contato, $mensageiro, $atendimentoService, true);
    }

    private function entrarNo(
        int $atendimentoId,
        array $no,
        string $numero,
        array $contato,
        WhatsAppMensagemService $mensageiro,
        WhatsAppAtendimentoService $atendimentoService,
        bool $reiniciaTentativas
    ): void {
        $filhos = $no['tipo'] === 'menu' ? $this->filhosAtivos((int)$no['id']) : [];
        $texto = $this->montarMensagemDoNo($no, $filhos, $contato);

        $envio = $mensageiro->enviar($numero, $texto);
        $atendimentoService->registrarMensagemSaida($atendimentoId, $texto, 'bot', null, 'texto', null, 'atendimento', $envio['success'] ? 'enviado' : 'falhou');

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

        if ($no['tipo'] === 'abrir_chamado') {
            $this->criarChamadoDoAtendimento($atendimentoId, $no, $contato, $numero);

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

    /**
     * Abre um chamado a partir da escolha "abrir_chamado" no bot --
     * unidade sempre a padrão (quem fala pelo WhatsApp não escolhe
     * unidade no menu, isso ficaria complexo demais pro bot; dá pra
     * corrigir depois no painel). Não trava o atendimento se algo
     * falhar (nó mal configurado, sem unidade padrão cadastrada) --
     * o cliente é encaminhado pro setor normalmente de qualquer jeito.
     */
    private function criarChamadoDoAtendimento(int $atendimentoId, array $no, array $contato, string $numero): void
    {
        if (empty($no['categoria_chamado_id'])) {
            return;
        }

        $stmt = $this->pdo->prepare('SELECT chamado_id FROM whatsapp_atendimentos WHERE id = ?');
        $stmt->execute([$atendimentoId]);
        if ($stmt->fetchColumn()) {
            return; // já tem chamado vinculado -- não abre outro
        }

        $unidade = (new UnidadeService())->padrao();
        if (!$unidade) {
            return;
        }

        $nome = trim((string)($contato['nome'] ?? '')) ?: $numero;

        $resultado = (new ChamadoService())->abrir([
            'titulo' => 'Chamado via WhatsApp -- ' . $nome,
            'descricao' => "Aberto pelo chatbot do WhatsApp. Acompanhe a conversa completa em WhatsApp > Atendimentos (atendimento #{$atendimentoId}).",
            'categoria_id' => $no['categoria_chamado_id'],
            'unidade_id' => $unidade['id'],
            'solicitante_nome' => $nome,
            'solicitante_telefone' => $numero,
        ], 'whatsapp');

        if (!empty($resultado['success']) && !empty($resultado['id'])) {
            $this->pdo->prepare('UPDATE whatsapp_atendimentos SET chamado_id = ? WHERE id = ?')
                ->execute([$resultado['id'], $atendimentoId]);
        }
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
}
