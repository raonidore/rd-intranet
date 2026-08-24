<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\ChatService;
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

        $resultado = (new ChatService())->enviar($conversaId, $usuarioId, $texto);

        echo json_encode($resultado);
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
}
