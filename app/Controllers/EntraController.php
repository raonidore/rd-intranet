<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\EntraService;
use App\Services\AtivoService;
use App\Services\AuditService;
use App\Services\NotificationService;

class EntraController extends Controller
{
    private EntraService $service;

    public function __construct()
    {
        $this->service = new EntraService();
    }

    public function dashboard(): void
    {
        AuthMiddleware::checkModulo('entra_dashboard');

        $configurado = $this->service->configurado();
        $usuarios = [];
        $skus = [];

        if ($configurado) {
            $usuarios = $this->service->listarUsuarios();
            $skus = $this->service->listarSkus();
        }

        $this->view('entra/dashboard', [
            'configurado' => $configurado,
            'totalUsuarios' => count($usuarios),
            'totalAtivos' => count(array_filter($usuarios, fn($u) => $u['accountEnabled'] ?? false)),
            'skus' => $skus,
        ]);
    }

    public function configuracaoForm(): void
    {
        AuthMiddleware::checkModulo('entra_configuracao');

        $this->view('entra/configuracao', [
            'configurado' => $this->service->configurado(),
            'tenantIdAtual' => $this->service->tenantIdAtual(),
            'clientIdAtual' => $this->service->clientIdAtual(),
        ]);
    }

    public function configuracaoSalvar(): void
    {
        AuthMiddleware::checkModulo('entra_configuracao');

        $this->service->salvarConfiguracao(
            $_POST['tenant_id'] ?? '',
            $_POST['client_id'] ?? '',
            $_POST['client_secret'] ?? ''
        );

        header('Location: ' . url('/entra/configuracao'));
        exit;
    }

    public function configuracaoRemover(): void
    {
        AuthMiddleware::checkModulo('entra_configuracao');

        $this->service->removerConfiguracao();

        header('Location: ' . url('/entra/configuracao'));
        exit;
    }

    public function usuarios(): void
    {
        AuthMiddleware::checkModulo('entra_usuarios');

        $configurado = $this->service->configurado();
        $usuarios = [];
        $skus = [];

        if ($configurado) {
            $usuarios = $this->service->listarUsuarios();
            $skus = $this->service->listarSkus();
        }

        $this->view('entra/usuarios', [
            'configurado' => $configurado,
            'usuarios' => $usuarios,
            'skus' => $skus,
        ]);
    }

    public function usuarioNovo(): void
    {
        AuthMiddleware::checkModulo('entra_usuarios');

        $this->service->criarUsuario(
            $_POST['nome'] ?? '',
            $_POST['upn'] ?? '',
            $_POST['senha'] ?? ''
        );

        header('Location: ' . url('/entra/usuarios'));
        exit;
    }

    public function resetarSenha(): void
    {
        AuthMiddleware::checkModulo('entra_usuarios');

        $this->service->resetarSenha(
            $_POST['user_id'] ?? '',
            $_POST['upn'] ?? '',
            $_POST['senha'] ?? ''
        );

        header('Location: ' . url('/entra/usuarios'));
        exit;
    }

    public function ativar(): void
    {
        AuthMiddleware::checkModulo('entra_usuarios');

        $this->service->ativarDesativar($_POST['user_id'] ?? '', $_POST['upn'] ?? '', true);

        header('Location: ' . url('/entra/usuarios'));
        exit;
    }

    public function desativar(): void
    {
        AuthMiddleware::checkModulo('entra_usuarios');

        $this->service->ativarDesativar($_POST['user_id'] ?? '', $_POST['upn'] ?? '', false);

        header('Location: ' . url('/entra/usuarios'));
        exit;
    }

    public function licencaAtribuir(): void
    {
        AuthMiddleware::checkModulo('entra_usuarios');

        $this->service->atribuirLicenca($_POST['user_id'] ?? '', $_POST['upn'] ?? '', $_POST['sku_id'] ?? '');

        header('Location: ' . url('/entra/usuarios'));
        exit;
    }

    public function licencaRemover(): void
    {
        AuthMiddleware::checkModulo('entra_usuarios');

        $this->service->removerLicenca($_POST['user_id'] ?? '', $_POST['upn'] ?? '', $_POST['sku_id'] ?? '');

        header('Location: ' . url('/entra/usuarios'));
        exit;
    }

    public function usuarioExcluir(): void
    {
        AuthMiddleware::checkModulo('entra_usuarios');

        $this->service->excluirUsuario($_POST['user_id'] ?? '', $_POST['upn'] ?? '');

        header('Location: ' . url('/entra/usuarios'));
        exit;
    }

    /*
     |---------------------------------------------------------
     | Restringir login local do Windows a uma lista de contas do Entra
     | -- entrega o script (EntraService) pelo canal de comando remoto já
     | existente pra Ativos (AtivoService::solicitarListagem), por isso
     | exige os dois módulos: precisa ver/escolher usuários do Entra E
     | poder mandar comando remoto em Ativos.
     |---------------------------------------------------------
     */

    public function acessoMaquinas(): void
    {
        AuthMiddleware::checkModulo('entra_usuarios');
        AuthMiddleware::checkModulo('ativos_novo');

        $configurado = $this->service->configurado();
        $usuarios = $configurado ? $this->service->listarUsuarios() : [];

        $ativoService = new AtivoService();
        $computadores = array_values(array_filter(
            $ativoService->listar(['tipo' => 'computador']),
            fn($a) => $a['origem'] === 'agente' && ($a['agente_versao'] ?? '') !== 'ps1'
        ));

        $this->view('entra/acesso_maquinas', [
            'configurado' => $configurado,
            'usuarios' => $usuarios,
            'computadores' => $computadores,
        ]);
    }

    public function acessoMaquinasAplicar(): void
    {
        AuthMiddleware::checkModulo('entra_usuarios');
        AuthMiddleware::checkModulo('ativos_novo');

        $upns = $_POST['upns'] ?? [];
        $ativoIds = array_map('intval', $_POST['ativos'] ?? []);

        if (empty($upns) || empty($ativoIds)) {
            NotificationService::error('Selecione ao menos um usuário e uma máquina.');
            header('Location: ' . url('/entra/acesso-maquinas'));
            exit;
        }

        $this->enviarScriptRestricao(EntraService::gerarScriptRestricaoLogin($upns), $ativoIds, 'Restrição de login aplicada', count($upns) . ' conta(s) autorizada(s)');

        header('Location: ' . url('/entra/acesso-maquinas'));
        exit;
    }

    public function acessoMaquinasRemover(): void
    {
        AuthMiddleware::checkModulo('entra_usuarios');
        AuthMiddleware::checkModulo('ativos_novo');

        $ativoIds = array_map('intval', $_POST['ativos'] ?? []);

        if (empty($ativoIds)) {
            NotificationService::error('Selecione ao menos uma máquina.');
            header('Location: ' . url('/entra/acesso-maquinas'));
            exit;
        }

        $this->enviarScriptRestricao(EntraService::gerarScriptRemoverRestricaoLogin(), $ativoIds, 'Restrição de login removida', 'login liberado pra todo mundo de novo');

        header('Location: ' . url('/entra/acesso-maquinas'));
        exit;
    }

    private function enviarScriptRestricao(string $script, array $ativoIds, string $rotuloAuditoria, string $detalheAuditoria): void
    {
        $solicitadoPor = $_SESSION['usuario']['nome'] ?? null;
        $ativoService = new AtivoService();

        $enviados = 0;
        foreach ($ativoIds as $ativoId) {
            $resultado = $ativoService->solicitarListagem($ativoId, 'executar_powershell', $script, $solicitadoPor, true);
            if ($resultado['success'] ?? false) {
                $enviados++;
            }
        }

        AuditService::registrar('Microsoft Entra', $rotuloAuditoria, "{$rotuloAuditoria} em {$enviados} máquina(s) -- {$detalheAuditoria}.");

        if ($enviados > 0) {
            NotificationService::success("Enviado pra {$enviados} máquina(s) -- confira o resultado no histórico de comandos de cada ativo em alguns segundos.");
        } else {
            NotificationService::error('Não foi possível enviar pra nenhuma máquina selecionada.');
        }
    }
}
