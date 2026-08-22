<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\WhatsAppAtendimentoService;
use App\Services\WhatsAppConfigService;
use App\Services\WhatsAppContatoService;
use App\Services\WhatsAppMensagemService;
use App\Services\WhatsAppMidiaService;
use App\Services\WhatsAppPermissaoService;
use App\Services\WhatsAppSetorService;

class WhatsAppAtendimentoController extends Controller
{
    public function index(): void
    {
        AuthMiddleware::checkModulo('whatsapp_atendimentos');

        $service = new WhatsAppAtendimentoService();
        $permissao = new WhatsAppPermissaoService();
        $usuarioId = (int)$_SESSION['usuario']['id'];

        $podeVerEncerrados = $permissao->usuarioPodeVerEncerrados($_SESSION['usuario']);
        $podeVerNps = $permissao->usuarioPodeVerNps($_SESSION['usuario']);

        $aba = $_GET['aba'] ?? 'andamento';
        if ($aba === 'encerrados' && !$podeVerEncerrados) {
            $aba = 'andamento';
        }

        $this->view('whatsapp/atendimentos', [
            'aba' => $aba,
            'atendimentos' => $service->listarDoUsuario($usuarioId),
            'encerrados' => $podeVerEncerrados ? $service->listarEncerradosDoUsuario($usuarioId, !$podeVerNps) : [],
            'podeVerEncerrados' => $podeVerEncerrados,
        ]);
    }

    /**
     * Atendente inicia o contato por conta própria (botão "Iniciar
     * Atendimento" em vez de esperar o cliente mandar mensagem primeiro).
     */
    public function iniciar(): void
    {
        AuthMiddleware::checkModulo('whatsapp_atendimentos');

        $numero = (new WhatsAppContatoService())->normalizarNumeroBr((string)($_POST['telefone'] ?? ''));

        if ($numero === null) {
            NotificationService::error('Telefone inválido -- informe DDD + número (com ou sem o 55 na frente).');
            header('Location: ' . url('/whatsapp/atendimentos'));
            exit;
        }

        $resultado = (new WhatsAppAtendimentoService())->iniciarProativo(
            $numero,
            trim($_POST['nome'] ?? '') ?: null,
            $_POST['mensagem'] ?? '',
            (int)$_SESSION['usuario']['id'],
            (string)$_SESSION['usuario']['nome']
        );

        AuditService::registrar('WhatsApp', 'Iniciar atendimento', "Número {$numero}: {$resultado['message']}");

        if ($resultado['success']) {
            NotificationService::success($resultado['message']);
            header('Location: ' . url('/whatsapp/atendimentos/ver?id=' . $resultado['atendimento_id']));
            exit;
        }

        NotificationService::error($resultado['message']);
        header('Location: ' . url('/whatsapp/atendimentos'));
        exit;
    }

    public function ver(): void
    {
        AuthMiddleware::checkModulo('whatsapp_atendimentos');

        $id = (int)($_GET['id'] ?? 0);
        $service = new WhatsAppAtendimentoService();
        $atendimento = $service->buscarComContato($id);

        if (!$this->pertenceAoUsuarioLogado($atendimento)) {
            NotificationService::error('Atendimento não encontrado ou não é seu.');
            header('Location: ' . url('/whatsapp/atendimentos'));
            exit;
        }

        $permissao = new WhatsAppPermissaoService();

        if ($atendimento['status'] === 'encerrado' && !$permissao->usuarioPodeVerEncerrados($_SESSION['usuario'])) {
            NotificationService::error('Você não tem permissão pra ver atendimentos encerrados.');
            header('Location: ' . url('/whatsapp/atendimentos'));
            exit;
        }

        $podeVerNps = $permissao->usuarioPodeVerNps($_SESSION['usuario']);

        $setoresAtivos = array_values(array_filter(
            (new WhatsAppSetorService())->listar(),
            fn (array $s) => (bool)$s['ativo'] && (int)$s['id'] !== (int)$atendimento['setor_id']
        ));

        $config = new WhatsAppConfigService();

        $this->view('whatsapp/atendimento_chat', [
            'atendimento' => $atendimento,
            'mensagens' => $service->mensagens($id, 0, !$podeVerNps),
            'setoresAtivos' => $setoresAtivos,
            'anexosAtivos' => $config->anexosAtivos(),
        ]);
    }

    public function responder(): void
    {
        AuthMiddleware::checkModulo('whatsapp_atendimentos');
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $texto = trim($_POST['texto'] ?? '');

        if ($texto === '') {
            echo json_encode(['success' => false, 'message' => 'Digite uma mensagem.']);
            return;
        }

        $service = new WhatsAppAtendimentoService();
        $atendimento = $service->buscarComContato($id);

        if (!$this->pertenceAoUsuarioLogado($atendimento)) {
            echo json_encode(['success' => false, 'message' => 'Atendimento não encontrado ou não é seu.']);
            return;
        }

        $textoComIdentificacao = WhatsAppAtendimentoService::comIdentificacaoDoAtendente(
            $texto,
            $atendimento['setor_nome'] ?? null,
            (string)$_SESSION['usuario']['nome']
        );

        $envio = (new WhatsAppMensagemService())->enviar($atendimento['numero'], $textoComIdentificacao);

        if (!$envio['success']) {
            echo json_encode(['success' => false, 'message' => $envio['message'] ?? 'Falha ao enviar mensagem pelo WhatsApp.']);
            return;
        }

        $service->registrarMensagemSaida($id, $texto, 'usuario', (int)$_SESSION['usuario']['id']);

        echo json_encode(['success' => true]);
    }

    public function anexo(): void
    {
        AuthMiddleware::checkModulo('whatsapp_atendimentos');
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $service = new WhatsAppAtendimentoService();
        $atendimento = $service->buscarComContato($id);

        if (!$this->pertenceAoUsuarioLogado($atendimento)) {
            echo json_encode(['success' => false, 'message' => 'Atendimento não encontrado ou não é seu.']);
            return;
        }

        if (empty($_FILES['arquivo']) || ($_FILES['arquivo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'Selecione um arquivo.']);
            return;
        }

        $arquivo = $_FILES['arquivo'];

        if ((int)$arquivo['size'] > WhatsAppMidiaService::TAMANHO_MAXIMO) {
            echo json_encode(['success' => false, 'message' => 'Arquivo maior que 16MB.']);
            return;
        }

        $mimetype = mime_content_type($arquivo['tmp_name']) ?: 'application/octet-stream';
        $tipo = WhatsAppMidiaService::tipoPorMimetype($mimetype);

        if ($tipo === null) {
            echo json_encode(['success' => false, 'message' => 'Tipo de arquivo não suportado.']);
            return;
        }

        if (!(new WhatsAppConfigService())->tipoAnexoPermitido($tipo)) {
            echo json_encode(['success' => false, 'message' => 'Esse tipo de anexo está desativado -- veja WhatsApp > Configurações.']);
            return;
        }

        $nomeArquivo = WhatsAppMidiaService::gerarNomeArquivo($mimetype);
        $caminhoCompleto = WhatsAppMidiaService::caminhoCompleto($nomeArquivo);

        if (!move_uploaded_file($arquivo['tmp_name'], $caminhoCompleto)) {
            echo json_encode(['success' => false, 'message' => 'Falha ao salvar o arquivo.']);
            return;
        }

        $legenda = trim($_POST['legenda'] ?? '');
        $nomeUsuario = (string)$_SESSION['usuario']['nome'];
        $setorNome = $atendimento['setor_nome'] ?? null;

        if ($tipo === 'audio') {
            // Nota de voz não aceita legenda/caption no protocolo do
            // WhatsApp -- manda a identificação como mensagem de texto
            // avulsa logo antes, só pro cliente saber quem tá falando
            // (não vira uma linha extra no nosso histórico -- o balão
            // já mostra o nome de quem mandou).
            (new WhatsAppMensagemService())->enviar(
                $atendimento['numero'],
                WhatsAppAtendimentoService::comIdentificacaoDoAtendente('', $setorNome, $nomeUsuario)
            );
            $legendaParaEnvio = null;
        } else {
            $legendaParaEnvio = WhatsAppAtendimentoService::comIdentificacaoDoAtendente($legenda, $setorNome, $nomeUsuario);
        }

        $envio = (new WhatsAppMensagemService())->enviarMidia(
            $atendimento['numero'],
            $caminhoCompleto,
            $mimetype,
            $tipo,
            $legendaParaEnvio,
            $arquivo['name']
        );

        if (!$envio['success']) {
            unlink($caminhoCompleto);
            echo json_encode(['success' => false, 'message' => $envio['message'] ?? 'Falha ao enviar anexo pelo WhatsApp.']);
            return;
        }

        $service->registrarMensagemSaida($id, $legenda, 'usuario', (int)$_SESSION['usuario']['id'], $tipo, $nomeArquivo);

        echo json_encode(['success' => true]);
    }

    /**
     * Serve o arquivo de um anexo -- nunca por URL direta (storage/
     * fica fora de public/), só depois de confirmar que a mensagem é
     * de um atendimento do usuário logado.
     */
    public function midia(): void
    {
        AuthMiddleware::checkModulo('whatsapp_atendimentos');

        $mensagemId = (int)($_GET['id'] ?? 0);
        $service = new WhatsAppAtendimentoService();
        $mensagem = $service->buscarMensagem($mensagemId);

        if (!$mensagem || !$mensagem['midia_path']) {
            http_response_code(404);
            return;
        }

        $atendimento = $service->buscar((int)$mensagem['atendimento_id']);

        if (!$this->pertenceAoUsuarioLogado($atendimento)) {
            http_response_code(403);
            return;
        }

        $caminho = WhatsAppMidiaService::caminhoCompleto($mensagem['midia_path']);

        if (!is_file($caminho)) {
            http_response_code(404);
            return;
        }

        header('Content-Type: ' . (mime_content_type($caminho) ?: 'application/octet-stream'));
        header('Content-Length: ' . filesize($caminho));
        header('Content-Disposition: inline; filename="' . basename($mensagem['midia_path']) . '"');
        readfile($caminho);
    }

    public function mensagensApi(): void
    {
        AuthMiddleware::checkModulo('whatsapp_atendimentos');
        header('Content-Type: application/json');

        $id = (int)($_GET['id'] ?? 0);
        $desde = (int)($_GET['desde'] ?? 0);

        $service = new WhatsAppAtendimentoService();
        $atendimento = $service->buscar($id);

        if (!$this->pertenceAoUsuarioLogado($atendimento)) {
            echo json_encode(['success' => false, 'message' => 'Atendimento não encontrado ou não é seu.']);
            return;
        }

        $podeVerNps = (new WhatsAppPermissaoService())->usuarioPodeVerNps($_SESSION['usuario']);

        echo json_encode(['success' => true, 'mensagens' => $service->mensagens($id, $desde, !$podeVerNps)]);
    }

    /** Contagem de "aguardando resposta" -- badge do menu e alerta sonoro em Atendimentos. */
    public function contadorApi(): void
    {
        AuthMiddleware::checkModulo('whatsapp_atendimentos');
        header('Content-Type: application/json');

        $usuarioId = (int)$_SESSION['usuario']['id'];
        $service = new WhatsAppAtendimentoService();

        echo json_encode([
            'success' => true,
            'aguardando' => $service->contarAguardandoResposta($usuarioId),
            'ultimaMensagemId' => $service->ultimoIdMensagemRecebida($usuarioId),
        ]);
    }

    public function encerrar(): void
    {
        AuthMiddleware::checkModulo('whatsapp_atendimentos');

        $id = (int)($_POST['id'] ?? 0);
        $service = new WhatsAppAtendimentoService();
        $atendimento = $service->buscar($id);

        if (!$this->pertenceAoUsuarioLogado($atendimento)) {
            NotificationService::error('Atendimento não encontrado ou não é seu.');
            header('Location: ' . url('/whatsapp/atendimentos'));
            exit;
        }

        $resultado = $service->encerrar($id);

        AuditService::registrar('WhatsApp', 'Encerrar atendimento', "Atendimento #{$id} encerrado.");

        NotificationService::success($resultado['message']);
        header('Location: ' . url('/whatsapp/atendimentos'));
        exit;
    }

    public function transferir(): void
    {
        AuthMiddleware::checkModulo('whatsapp_atendimentos');

        $id = (int)($_POST['id'] ?? 0);
        $setorId = (int)($_POST['setor_id'] ?? 0);
        $service = new WhatsAppAtendimentoService();
        $atendimento = $service->buscar($id);

        if (!$this->pertenceAoUsuarioLogado($atendimento)) {
            NotificationService::error('Atendimento não encontrado ou não é seu.');
            header('Location: ' . url('/whatsapp/atendimentos'));
            exit;
        }

        $resultado = $service->transferir($id, $setorId);

        AuditService::registrar('WhatsApp', 'Transferir atendimento', "Atendimento #{$id}: {$resultado['message']}");

        if ($resultado['success']) {
            NotificationService::success($resultado['message']);
        } else {
            NotificationService::error($resultado['message']);
        }

        header('Location: ' . url('/whatsapp/atendimentos'));
        exit;
    }

    private function pertenceAoUsuarioLogado(?array $atendimento): bool
    {
        return $atendimento !== null
            && (int)($atendimento['usuario_id'] ?? 0) === (int)($_SESSION['usuario']['id'] ?? 0);
    }
}
