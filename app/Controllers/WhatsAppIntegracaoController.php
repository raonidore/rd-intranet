<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\CryptoService;
use App\Services\LinuxService;
use App\Services\NotificationService;
use App\Services\WhatsAppApiOficialService;
use App\Services\WhatsAppBridgeService;
use App\Services\WhatsAppConexaoService;
use App\Services\WhatsAppConfigService;
use App\Services\WhatsAppSetorService;
use App\Services\WhatsAppTwilioService;

class WhatsAppIntegracaoController extends Controller
{
    private WhatsAppConfigService $config;
    private WhatsAppConexaoService $conexoes;

    public function __construct()
    {
        $this->config = new WhatsAppConfigService();
        $this->conexoes = new WhatsAppConexaoService();
    }

    public function form(): void
    {
        AuthMiddleware::checkAdmin();

        $apiOficial = new WhatsAppApiOficialService();
        $twilio = new WhatsAppTwilioService();
        $setorService = new WhatsAppSetorService();

        $esquema = ($_SERVER['HTTPS'] ?? '') === 'on' ? 'https' : 'http';
        $baseUrl = $esquema . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

        $setores = $setorService->listar();
        $conexoes = $this->conexoes->listar();
        foreach ($conexoes as &$conexao) {
            $conexao['setor_ids'] = $this->conexoes->idsSetoresDaConexao((int)$conexao['id']);
        }
        unset($conexao);

        $this->view('administracao/integracoes_whatsapp', [
            'tipoAtual' => $this->config->tipoIntegracao(),
            'conexoes' => $conexoes,
            'setores' => $setores,
            'webhookMetaUrl' => $baseUrl . url('/api/whatsapp/webhook/meta'),
            'webhookTwilioUrl' => $baseUrl . url('/api/whatsapp/webhook/twilio'),
            'metaPhoneNumberId' => $apiOficial->phoneNumberId(),
            'metaVerifyToken' => $apiOficial->verifyToken(),
            'metaConfigurado' => $apiOficial->configurado(),
            'twilioAccountSid' => $twilio->accountSid(),
            'twilioNumero' => $twilio->numero(),
            'twilioConfigurado' => $twilio->configurado(),
        ]);
    }

    public function salvarMeta(): void
    {
        AuthMiddleware::checkAdmin();

        $resultado = (new WhatsAppApiOficialService())->salvarConfig(
            $_POST['phone_number_id'] ?? '',
            $_POST['access_token'] ?? '',
            $_POST['verify_token'] ?? '',
            $_POST['app_secret'] ?? ''
        );

        AuditService::registrar('Integrações', 'WhatsApp API Oficial', $resultado['message']);

        if ($resultado['success']) {
            NotificationService::success($resultado['message']);
        } else {
            NotificationService::error($resultado['message']);
        }

        header('Location: ' . url('/administracao/integracoes/whatsapp'));
        exit;
    }

    public function salvarTwilio(): void
    {
        AuthMiddleware::checkAdmin();

        $resultado = (new WhatsAppTwilioService())->salvarConfig(
            $_POST['account_sid'] ?? '',
            $_POST['auth_token'] ?? '',
            $_POST['numero'] ?? ''
        );

        AuditService::registrar('Integrações', 'WhatsApp Twilio', $resultado['message']);

        if ($resultado['success']) {
            NotificationService::success($resultado['message']);
        } else {
            NotificationService::error($resultado['message']);
        }

        header('Location: ' . url('/administracao/integracoes/whatsapp'));
        exit;
    }

    public function salvarTipo(): void
    {
        AuthMiddleware::checkAdmin();

        $tipo = $_POST['tipo'] ?? '';
        $resultado = $this->config->salvarTipoIntegracao($tipo);

        if ($resultado['success']) {
            AuditService::registrar('Integrações', 'WhatsApp', "Tipo de integração alterado para \"{$tipo}\".");
            NotificationService::success($resultado['message']);
        } else {
            NotificationService::error($resultado['message']);
        }

        header('Location: ' . url('/administracao/integracoes/whatsapp'));
        exit;
    }

    /** Cadastra uma conexão nova (um número/departamento a mais) -- ainda não instalada, o admin clica "Instalar" no card dela em seguida. */
    public function novaConexao(): void
    {
        AuthMiddleware::checkAdmin();

        $resultado = $this->conexoes->criar($_POST['nome'] ?? '');

        AuditService::registrar('Integrações', 'WhatsApp', 'Nova conexão: ' . $resultado['message']);

        if ($resultado['success']) {
            NotificationService::success($resultado['message']);
        } else {
            NotificationService::error($resultado['message']);
        }

        header('Location: ' . url('/administracao/integracoes/whatsapp'));
        exit;
    }

    /** Quais setores (whatsapp_setores) esse número atende -- filtra as opções "encaminhar pro setor X" no menu do bot pra esse número. */
    public function salvarSetores(): void
    {
        AuthMiddleware::checkAdmin();

        $id = (int)($_POST['id'] ?? 0);
        $setorIds = array_map('intval', $_POST['setor_ids'] ?? []);

        $resultado = $this->conexoes->salvarSetoresDaConexao($id, $setorIds);

        AuditService::registrar('Integrações', 'WhatsApp', "Setores da conexão #{$id} atualizados.");

        if ($resultado['success']) {
            NotificationService::success($resultado['message']);
        } else {
            NotificationService::error($resultado['message']);
        }

        header('Location: ' . url('/administracao/integracoes/whatsapp'));
        exit;
    }

    /**
     * Dispara a instalação do bridge dessa conexão em segundo plano
     * (npm install pode levar dezenas de segundos) -- a tela acompanha
     * via polling em status()/qrcode(), não espera essa requisição
     * terminar. Todos os caminhos (diretório/usuário/unit systemd) já
     * vêm gravados na linha da conexão (fixos, pra "Principal", ou
     * derivados do id na criação, pra uma nova) -- nada é decidido aqui.
     */
    public function instalar(): void
    {
        AuthMiddleware::checkAdmin();
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $conexao = $this->conexoes->buscar($id);

        if (!$conexao) {
            echo json_encode(['success' => false, 'message' => 'Conexão não encontrada.']);
            return;
        }

        // URL absoluta de verdade (não só o path de url()) -- o bridge é
        // um processo Node à parte, sem noção de "host atual" nenhuma,
        // então precisa do endereço completo pra conseguir chamar o
        // webhook de volta. SEMPRE via loopback HTTP, nunca pelo
        // host/esquema que o admin usou pra acessar esta tela: bridge e
        // painel sempre rodam na mesma máquina, então não há motivo pra
        // esse aviso interno passar por TLS/host externo -- e em
        // instalações com certificado autoassinado (comuns em intranets
        // sem domínio público), o fetch() do Node rejeita o certificado
        // e a mensagem chega no bridge mas nunca é repassada pro painel,
        // sem erro nenhum visível (só um log "fetch failed" no bridge).
        // A mesma URL serve pra todas as conexões -- quem identifica de
        // qual conexão veio a mensagem é a API key (ver WhatsAppWebhookController).
        $webhookUrl = 'http://127.0.0.1' . url('/api/whatsapp/webhook');

        $repoDir = realpath(__DIR__ . '/../..');
        $apiKey = !empty($conexao['api_key_cifrada']) ? CryptoService::decriptar($conexao['api_key_cifrada']) : '';

        (new LinuxService())->executarScriptEmSegundoPlano(
            '/opt/rdtecnologia/scripts/whatsapp_bridge_instalar_web.sh',
            [
                $repoDir,
                (string)$conexao['porta'],
                $apiKey,
                $webhookUrl,
                $conexao['diretorio_instalacao'],
                $conexao['usuario_sistema'],
                $conexao['unit_systemd'],
            ]
        );

        $this->conexoes->marcarInstalado($id);

        AuditService::registrar('Integrações', 'WhatsApp', "Instalação da conexão \"{$conexao['nome']}\" disparada.");

        echo json_encode(['success' => true, 'message' => 'Instalação iniciada -- pode levar um minuto.']);
    }

    public function status(): void
    {
        AuthMiddleware::checkAdmin();
        header('Content-Type: application/json');

        $conexao = $this->conexoes->buscar((int)($_GET['id'] ?? 0));

        if (!$conexao) {
            echo json_encode(['success' => false, 'message' => 'Conexão não encontrada.']);
            return;
        }

        echo json_encode((new WhatsAppBridgeService($conexao))->status());
    }

    public function qrcode(): void
    {
        AuthMiddleware::checkAdmin();
        header('Content-Type: application/json');

        $conexao = $this->conexoes->buscar((int)($_GET['id'] ?? 0));

        if (!$conexao) {
            echo json_encode(['success' => false, 'message' => 'Conexão não encontrada.']);
            return;
        }

        echo json_encode((new WhatsAppBridgeService($conexao))->qrcode());
    }

    public function desconectar(): void
    {
        AuthMiddleware::checkAdmin();

        $id = (int)($_POST['id'] ?? 0);
        $conexao = $this->conexoes->buscar($id);

        if (!$conexao) {
            NotificationService::error('Conexão não encontrada.');
            header('Location: ' . url('/administracao/integracoes/whatsapp'));
            exit;
        }

        $resultado = (new WhatsAppBridgeService($conexao))->desconectar();

        AuditService::registrar('Integrações', 'WhatsApp', "Desconexão da conexão \"{$conexao['nome']}\" solicitada.");

        if ($resultado['success']) {
            NotificationService::success($resultado['message'] ?? 'Desconectado.');
        } else {
            NotificationService::error($resultado['message'] ?? 'Falha ao desconectar.');
        }

        header('Location: ' . url('/administracao/integracoes/whatsapp'));
        exit;
    }
}
