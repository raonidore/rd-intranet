<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\ChatBridgeService;
use App\Services\ChatConfigService;
use App\Services\ChatService;
use App\Services\ChatSocketTokenService;
use App\Services\NotificationService;
use App\Services\UsuarioOnlineService;

class ChatController extends Controller
{
    public function index(): void
    {
        AuthMiddleware::checkModulo('chat_conversas');

        $usuarioId = (int)$_SESSION['usuario']['id'];
        $service = new ChatService();
        $online = new UsuarioOnlineService();

        $conversas = $service->listarConversas($usuarioId);
        $onlineIds = $online->idsOnline(array_values(array_filter(array_column($conversas, 'outro_usuario_id'))));

        $conversaId = (int)($_GET['conversa_id'] ?? 0);
        $conversaSelecionada = null;
        $mensagens = [];
        $participantes = [];
        $tituloConversa = null;
        $outroOnline = false;

        if ($conversaId && $service->ehParticipante($conversaId, $usuarioId)) {
            $conversaSelecionada = $service->buscarConversa($conversaId);
            $mensagens = $service->mensagens($conversaId);
            $participantes = $service->participantes($conversaId);
            $service->marcarComoLida($conversaId, $usuarioId);

            if ($conversaSelecionada['tipo'] === 'grupo') {
                $tituloConversa = $conversaSelecionada['nome'];
            } else {
                $outro = null;
                foreach ($participantes as $p) {
                    if ((int)$p['id'] !== $usuarioId) {
                        $outro = $p;
                    }
                }
                $tituloConversa = $outro['nome'] ?? '(usuário removido)';
                $outroOnline = $outro !== null && !empty($online->idsOnline([(int)$outro['id']]));
            }
        }

        $usuariosDisponiveis = $service->usuariosDisponiveis($usuarioId);
        $onlineDisponiveis = $online->idsOnline(array_column($usuariosDisponiveis, 'id'));

        $this->view('chat/index', [
            'conversas' => $conversas,
            'onlineIds' => $onlineIds,
            'conversaId' => $conversaId,
            'conversaSelecionada' => $conversaSelecionada,
            'mensagens' => $mensagens,
            'participantes' => $participantes,
            'tituloConversa' => $tituloConversa,
            'outroOnline' => $outroOnline,
            'usuarioId' => $usuarioId,
            'usuariosDisponiveis' => $usuariosDisponiveis,
            'onlineDisponiveis' => $onlineDisponiveis,
        ]);
    }

    public function novaDireta(): void
    {
        AuthMiddleware::checkModulo('chat_conversas');

        $usuarioId = (int)$_SESSION['usuario']['id'];
        $outroUsuarioId = (int)($_POST['usuario_id'] ?? 0);

        $resultado = (new ChatService())->criarOuBuscarDireta($usuarioId, $outroUsuarioId);

        if (!$resultado['success']) {
            NotificationService::error($resultado['message']);
            header('Location: ' . url('/chat'));
            exit;
        }

        header('Location: ' . url('/chat?conversa_id=' . $resultado['id']));
        exit;
    }

    public function novoGrupo(): void
    {
        AuthMiddleware::checkModulo('chat_conversas');

        $usuarioId = (int)$_SESSION['usuario']['id'];
        $nome = trim($_POST['nome'] ?? '');
        $participantes = $_POST['participantes'] ?? [];

        $resultado = (new ChatService())->criarGrupo($nome, $usuarioId, is_array($participantes) ? $participantes : []);

        if (!$resultado['success']) {
            NotificationService::error($resultado['message']);
            header('Location: ' . url('/chat'));
            exit;
        }

        AuditService::registrar('Chat', 'Criar grupo', "Grupo \"{$nome}\" criado.");

        header('Location: ' . url('/chat?conversa_id=' . $resultado['id']));
        exit;
    }

    public function enviarApi(): void
    {
        AuthMiddleware::checkModulo('chat_conversas');
        header('Content-Type: application/json');

        $usuarioId = (int)$_SESSION['usuario']['id'];
        $conversaId = (int)($_POST['conversa_id'] ?? 0);
        $texto = (string)($_POST['texto'] ?? '');

        $service = new ChatService();
        $resultado = $service->enviar($conversaId, $usuarioId, $texto);

        if ($resultado['success']) {
            $this->notificarTempoReal($service, $conversaId, $usuarioId, (int)$resultado['id']);
        }

        echo json_encode($resultado);
    }

    /**
     * Empurra a mensagem recém-salva pro chat-bridge (Fase 2), pros
     * outros participantes que estiverem com socket aberto -- silencioso
     * se o bridge não estiver instalado/rodando, a mensagem já está
     * salva no banco de qualquer forma, o polling da Fase 1 continua
     * entregando normalmente.
     */
    private function notificarTempoReal(ChatService $service, int $conversaId, int $usuarioId, int $mensagemId): void
    {
        $mensagem = $service->mensagens($conversaId, $mensagemId - 1);
        if (empty($mensagem)) {
            return;
        }

        $outrosParticipantes = array_values(array_filter(
            array_column($service->participantes($conversaId), 'id'),
            fn (int $id) => $id !== $usuarioId
        ));

        (new ChatBridgeService())->notificar($outrosParticipantes, 'mensagem_nova', [
            'conversaId' => $conversaId,
            'mensagem' => $mensagem[0],
        ]);
    }

    public function mensagensApi(): void
    {
        AuthMiddleware::checkModulo('chat_conversas');
        header('Content-Type: application/json');

        $usuarioId = (int)$_SESSION['usuario']['id'];
        $conversaId = (int)($_GET['conversa_id'] ?? 0);
        $desde = (int)($_GET['desde'] ?? 0);

        $service = new ChatService();

        if (!$service->ehParticipante($conversaId, $usuarioId)) {
            echo json_encode(['success' => false, 'message' => 'Conversa não encontrada.']);
            return;
        }

        $service->marcarComoLida($conversaId, $usuarioId);

        echo json_encode(['success' => true, 'mensagens' => $service->mensagens($conversaId, $desde)]);
    }

    /** Contador de não lidas -- badge do menu/widget flutuante, mesmo padrão do WhatsApp/Chamados. */
    public function contadorApi(): void
    {
        AuthMiddleware::checkModulo('chat_conversas');
        header('Content-Type: application/json');

        $usuarioId = (int)$_SESSION['usuario']['id'];
        $service = new ChatService();

        echo json_encode([
            'success' => true,
            'naoLidas' => $service->contarNaoLidas($usuarioId),
            'ultimaMensagemId' => $service->ultimoIdMensagemRecebida($usuarioId),
        ]);
    }

    /** Lista de conversas em JSON -- mantém a coluna da esquerda atualizada (prévia/não lidas) sem recarregar a página inteira. */
    public function conversasApi(): void
    {
        AuthMiddleware::checkModulo('chat_conversas');
        header('Content-Type: application/json');

        $usuarioId = (int)$_SESSION['usuario']['id'];
        $service = new ChatService();

        $conversas = $service->listarConversas($usuarioId);
        $onlineIds = (new UsuarioOnlineService())->idsOnline(array_values(array_filter(array_column($conversas, 'outro_usuario_id'))));

        foreach ($conversas as &$c) {
            $c['online'] = $c['outro_usuario_id'] !== null && in_array((int)$c['outro_usuario_id'], $onlineIds, true);
        }

        echo json_encode(['success' => true, 'conversas' => $conversas]);
    }

    /**
     * Token de 60s/uso único pro navegador abrir o WebSocket com o
     * chat-bridge (Fase 2) -- autenticado por sessão normal, igual
     * qualquer outra tela. Se o bridge não estiver instalado, o
     * navegador simplesmente não consegue abrir o socket e continua no
     * polling de sempre -- por isso não faz sentido nenhuma checagem de
     * "bridge instalado" aqui, o pior caso já é inofensivo.
     */
    public function socketTokenApi(): void
    {
        AuthMiddleware::checkModulo('chat_conversas');
        header('Content-Type: application/json');

        $usuarioId = (int)$_SESSION['usuario']['id'];
        $token = (new ChatSocketTokenService())->emitir($usuarioId);

        echo json_encode(['success' => true, 'token' => $token]);
    }

    /**
     * Endpoint interno -- quem chama é o processo chat-bridge (Node),
     * nunca o navegador diretamente, por isso a autenticação aqui é
     * X-Api-Key (a mesma que o PHP usa pra chamar o bridge), não sessão.
     */
    public function validarSocketTokenApi(): void
    {
        header('Content-Type: application/json');

        $chaveRecebida = $_SERVER['HTTP_X_API_KEY'] ?? '';
        $chaveEsperada = (new ChatConfigService())->bridgeApiKey();

        if (!hash_equals($chaveEsperada, $chaveRecebida)) {
            http_response_code(403);
            echo json_encode(['success' => false]);
            return;
        }

        $token = (string)($_GET['token'] ?? '');
        $usuarioId = (new ChatSocketTokenService())->validar($token);

        if ($usuarioId === null) {
            echo json_encode(['success' => false]);
            return;
        }

        echo json_encode(['success' => true, 'usuarioId' => $usuarioId]);
    }
}
