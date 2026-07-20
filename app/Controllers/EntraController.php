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

        $this->enviarScriptParaAtivos(EntraService::gerarScriptRestricaoLogin($upns), $ativoIds, 'Restrição de login aplicada', count($upns) . ' conta(s) autorizada(s)');

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

        $this->enviarScriptParaAtivos(EntraService::gerarScriptRemoverRestricaoLogin(), $ativoIds, 'Restrição de login removida', 'login liberado pra todo mundo de novo');

        header('Location: ' . url('/entra/acesso-maquinas'));
        exit;
    }

    /*
     |---------------------------------------------------------
     | Dispositivos gerenciados pelo Intune + inscrição de máquinas
     | usando o agente próprio -- ver plano da feature. Reaproveita o
     | mesmo canal de comando remoto já existente pra Ativos, por isso
     | as ações que tocam máquina exigem os dois módulos (entra_dispositivos
     | + ativos_novo), igual "Acesso às Máquinas".
     |---------------------------------------------------------
     */

    public function dispositivos(): void
    {
        AuthMiddleware::checkModulo('entra_dispositivos');

        $configurado = $this->service->configurado();
        $dispositivosIntune = $configurado ? $this->service->listarDispositivosGerenciados() : [];
        $faltaPermissaoIntune = $configurado && $this->service->ultimoErroDispositivosFoiPermissao();

        $ativoService = new AtivoService();
        $computadores = array_values(array_filter(
            $ativoService->listar(['tipo' => 'computador']),
            fn($a) => $a['origem'] === 'agente' && ($a['agente_versao'] ?? '') !== 'ps1'
        ));

        $this->view('entra/dispositivos', [
            'configurado' => $configurado,
            'dispositivosIntune' => $dispositivosIntune,
            'faltaPermissaoIntune' => $faltaPermissaoIntune,
            'computadores' => $computadores,
            'provisioningConfigurado' => $this->service->provisioningConfigurado(),
            'provisioningInfo' => $this->service->provisioningInfo(),
            'companyPortalConfigurado' => $this->service->companyPortalConfigurado(),
            'companyPortalInfo' => $this->service->companyPortalInfo(),
        ]);
    }

    public function dispositivoSincronizar(): void
    {
        AuthMiddleware::checkModulo('entra_dispositivos');
        $this->service->sincronizarDispositivoIntune($_POST['device_id'] ?? '', $_POST['nome'] ?? '');
        header('Location: ' . url('/entra/dispositivos'));
        exit;
    }

    public function dispositivoReiniciar(): void
    {
        AuthMiddleware::checkModulo('entra_dispositivos');
        $this->service->reiniciarDispositivoIntune($_POST['device_id'] ?? '', $_POST['nome'] ?? '');
        header('Location: ' . url('/entra/dispositivos'));
        exit;
    }

    public function dispositivoBloquear(): void
    {
        AuthMiddleware::checkModulo('entra_dispositivos');
        $this->service->bloquearDispositivoIntune($_POST['device_id'] ?? '', $_POST['nome'] ?? '');
        header('Location: ' . url('/entra/dispositivos'));
        exit;
    }

    public function dispositivoRetirar(): void
    {
        AuthMiddleware::checkModulo('entra_dispositivos');
        $this->service->retirarDispositivoIntune($_POST['device_id'] ?? '', $_POST['nome'] ?? '');
        header('Location: ' . url('/entra/dispositivos'));
        exit;
    }

    public function forcarEnrollment(): void
    {
        AuthMiddleware::checkModulo('entra_dispositivos');
        AuthMiddleware::checkModulo('ativos_novo');

        $ativoIds = array_map('intval', $_POST['ativos'] ?? []);

        if (empty($ativoIds)) {
            NotificationService::error('Selecione ao menos uma máquina.');
            header('Location: ' . url('/entra/dispositivos'));
            exit;
        }

        $this->enviarScriptParaAtivos(EntraService::scriptForcarEnrollmentIntune(), $ativoIds, 'Inscrição no Intune forçada', 'deviceenroller /c /AutoEnrollMDM disparado');

        header('Location: ' . url('/entra/dispositivos'));
        exit;
    }

    public function provisioningUpload(): void
    {
        AuthMiddleware::checkModulo('entra_dispositivos');

        $arquivo = $_FILES['pacote'] ?? null;

        if (!$arquivo || $arquivo['error'] !== UPLOAD_ERR_OK) {
            NotificationService::error('Selecione um arquivo .ppkg válido.');
            header('Location: ' . url('/entra/dispositivos'));
            exit;
        }

        $this->service->salvarProvisioningPackage($arquivo['tmp_name'], $arquivo['name']);

        header('Location: ' . url('/entra/dispositivos'));
        exit;
    }

    public function provisioningRemover(): void
    {
        AuthMiddleware::checkModulo('entra_dispositivos');
        $this->service->removerProvisioningPackage();
        header('Location: ' . url('/entra/dispositivos'));
        exit;
    }

    public function provisioningEnviar(): void
    {
        AuthMiddleware::checkModulo('entra_dispositivos');
        AuthMiddleware::checkModulo('ativos_novo');

        $ativoIds = array_map('intval', $_POST['ativos'] ?? []);

        if (empty($ativoIds)) {
            NotificationService::error('Selecione ao menos uma máquina.');
            header('Location: ' . url('/entra/dispositivos'));
            exit;
        }

        if (!$this->service->provisioningConfigurado()) {
            NotificationService::error('Envie o pacote de provisionamento (.ppkg) antes.');
            header('Location: ' . url('/entra/dispositivos'));
            exit;
        }

        $solicitadoPor = $_SESSION['usuario']['nome'] ?? null;
        $enviados = 0;
        foreach ($ativoIds as $ativoId) {
            if ($this->enviarProvisioningParaAtivo($ativoId, $solicitadoPor)) {
                $enviados++;
            }
        }

        AuditService::registrar('Microsoft Entra', 'Pacote de provisionamento enviado', "Pacote de provisionamento despachado pra {$enviados} máquina(s).");

        if ($enviados > 0) {
            NotificationService::success("Enviado pra {$enviados} máquina(s) -- confira o resultado no histórico de comandos de cada ativo em alguns minutos.");
        } else {
            NotificationService::error('Não foi possível enviar pra nenhuma máquina selecionada.');
        }

        header('Location: ' . url('/entra/dispositivos'));
        exit;
    }

    private function enviarProvisioningParaAtivo(int $ativoId, ?string $solicitadoPor): bool
    {
        $pastaTransferencias = __DIR__ . '/../../storage/uploads/ativos_transferencias';
        if (!is_dir($pastaTransferencias) && !@mkdir($pastaTransferencias, 0777, true) && !is_dir($pastaTransferencias)) {
            return false;
        }

        $copiaTemp = $pastaTransferencias . '/enviar_' . uniqid('', true) . '_bulk_enrollment.ppkg';
        if (!@copy(EntraService::caminhoProvisioningPackage(), $copiaTemp)) {
            return false;
        }

        $destino = 'C:\\Windows\\Temp\\RDIntranetProvisioning\\bulk_enrollment.ppkg';
        $ativoService = new AtivoService();

        $resultadoArquivo = $ativoService->enviarComando($ativoId, 'enviar_arquivo', $solicitadoPor, $destino, 'bulk_enrollment.ppkg', $copiaTemp);
        if (!($resultadoArquivo['success'] ?? false)) {
            @unlink($copiaTemp);
            return false;
        }

        $resultadoScript = $ativoService->solicitarListagem($ativoId, 'executar_powershell', EntraService::scriptInstalarProvisioningPackage($destino), $solicitadoPor, true);

        return $resultadoScript['success'] ?? false;
    }

    /*
     |---------------------------------------------------------
     | Instalador do Company Portal -- só entrega o arquivo (mesmo canal
     | enviar_arquivo já testado), sem tentar instalar automaticamente.
     | Ver EntraService::caminhoCompanyPortal() pro porquê de não rodar
     | via nosso canal elevado aqui.
     |---------------------------------------------------------
     */

    public function companyPortalUpload(): void
    {
        AuthMiddleware::checkModulo('entra_dispositivos');

        $arquivo = $_FILES['instalador'] ?? null;

        if (!$arquivo || $arquivo['error'] !== UPLOAD_ERR_OK) {
            NotificationService::error('Selecione o instalador do Company Portal (.exe, .msix ou .msixbundle).');
            header('Location: ' . url('/entra/dispositivos'));
            exit;
        }

        $this->service->salvarCompanyPortal($arquivo['tmp_name'], $arquivo['name']);

        header('Location: ' . url('/entra/dispositivos'));
        exit;
    }

    public function companyPortalRemover(): void
    {
        AuthMiddleware::checkModulo('entra_dispositivos');
        $this->service->removerCompanyPortal();
        header('Location: ' . url('/entra/dispositivos'));
        exit;
    }

    public function companyPortalEnviar(): void
    {
        AuthMiddleware::checkModulo('entra_dispositivos');
        AuthMiddleware::checkModulo('ativos_novo');

        $ativoIds = array_map('intval', $_POST['ativos'] ?? []);

        if (empty($ativoIds)) {
            NotificationService::error('Selecione ao menos uma máquina.');
            header('Location: ' . url('/entra/dispositivos'));
            exit;
        }

        $infoInstalador = $this->service->companyPortalInfo();
        if ($infoInstalador === null) {
            NotificationService::error('Envie o instalador do Company Portal antes.');
            header('Location: ' . url('/entra/dispositivos'));
            exit;
        }

        $solicitadoPor = $_SESSION['usuario']['nome'] ?? null;
        $enviados = 0;
        foreach ($ativoIds as $ativoId) {
            if ($this->enviarCompanyPortalParaAtivo($ativoId, $solicitadoPor, $infoInstalador['nome'])) {
                $enviados++;
            }
        }

        AuditService::registrar('Microsoft Entra', 'Instalador Company Portal enviado', "Instalador do Company Portal despachado pra {$enviados} máquina(s).");

        if ($enviados > 0) {
            NotificationService::success("Enviado pra {$enviados} máquina(s) -- abra o arquivo em cada uma como o usuário logado (sem \"Executar como administrador\") pra instalar.");
        } else {
            NotificationService::error('Não foi possível enviar pra nenhuma máquina selecionada.');
        }

        header('Location: ' . url('/entra/dispositivos'));
        exit;
    }

    private function enviarCompanyPortalParaAtivo(int $ativoId, ?string $solicitadoPor, string $nomeOriginal): bool
    {
        $pastaTransferencias = __DIR__ . '/../../storage/uploads/ativos_transferencias';
        if (!is_dir($pastaTransferencias) && !@mkdir($pastaTransferencias, 0777, true) && !is_dir($pastaTransferencias)) {
            return false;
        }

        $nomeSanitizado = preg_replace('/[^A-Za-z0-9._-]/', '_', $nomeOriginal) ?: 'company_portal_installer';
        $copiaTemp = $pastaTransferencias . '/enviar_' . uniqid('', true) . '_' . $nomeSanitizado;
        if (!@copy(EntraService::caminhoCompanyPortal(), $copiaTemp)) {
            return false;
        }

        $destino = 'C:\\Windows\\Temp\\RDIntranetProvisioning\\' . $nomeSanitizado;
        $ativoService = new AtivoService();

        $resultadoArquivo = $ativoService->enviarComando($ativoId, 'enviar_arquivo', $solicitadoPor, $destino, $nomeSanitizado, $copiaTemp);
        if (!($resultadoArquivo['success'] ?? false)) {
            @unlink($copiaTemp);
            return false;
        }

        return true;
    }

    private function enviarScriptParaAtivos(string $script, array $ativoIds, string $rotuloAuditoria, string $detalheAuditoria): void
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
