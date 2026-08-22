<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\UserService;
use App\Services\WhatsAppConfigService;
use App\Services\WhatsAppPermissaoService;

class WhatsAppConfiguracaoController extends Controller
{
    public function index(): void
    {
        AuthMiddleware::checkModulo('whatsapp_configuracoes');

        $config = new WhatsAppConfigService();
        $permissao = new WhatsAppPermissaoService();

        $usuariosAtivos = array_values(array_filter(
            (new UserService())->listar(),
            fn (array $u) => (bool)$u['ativo']
        ));

        $this->view('whatsapp/configuracoes', [
            'anexosAtivos' => $config->anexosAtivos(),
            'imagensAtivas' => $config->imagensAtivas(),
            'documentosAtivos' => $config->documentosAtivos(),
            'audiosAtivos' => $config->audiosAtivos(),
            'usuariosAtivos' => $usuariosAtivos,
            'encerradosRestritoAtivo' => $permissao->encerradosRestritoAtivo(),
            'idsComAcessoEncerrados' => $permissao->idsComAcessoEncerrados(),
            'npsRestritoAtivo' => $permissao->npsRestritoAtivo(),
            'idsComAcessoNps' => $permissao->idsComAcessoNps(),
        ]);
    }

    public function salvar(): void
    {
        AuthMiddleware::checkModulo('whatsapp_configuracoes');

        $resultado = (new WhatsAppConfigService())->salvarAnexos(
            isset($_POST['anexos_ativos']),
            isset($_POST['imagens_ativas']),
            isset($_POST['documentos_ativos']),
            isset($_POST['audios_ativos'])
        );

        AuditService::registrar('WhatsApp', 'Configurações', $resultado['message']);

        NotificationService::success($resultado['message']);
        header('Location: ' . url('/whatsapp/configuracoes'));
        exit;
    }

    public function salvarAcessoEncerrados(): void
    {
        AuthMiddleware::checkModulo('whatsapp_configuracoes');

        $usuarios = $_POST['usuarios'] ?? [];
        $resultado = (new WhatsAppPermissaoService())->salvarAcessoEncerrados(
            isset($_POST['restrito']),
            is_array($usuarios) ? $usuarios : []
        );

        AuditService::registrar('WhatsApp', 'Configurações - acesso a Encerrados', $resultado['message']);

        NotificationService::success($resultado['message']);
        header('Location: ' . url('/whatsapp/configuracoes'));
        exit;
    }

    public function salvarAcessoNps(): void
    {
        AuthMiddleware::checkModulo('whatsapp_configuracoes');

        $usuarios = $_POST['usuarios'] ?? [];
        $resultado = (new WhatsAppPermissaoService())->salvarAcessoNps(
            isset($_POST['restrito']),
            is_array($usuarios) ? $usuarios : []
        );

        AuditService::registrar('WhatsApp', 'Configurações - acesso à pesquisa de satisfação', $resultado['message']);

        NotificationService::success($resultado['message']);
        header('Location: ' . url('/whatsapp/configuracoes'));
        exit;
    }
}
