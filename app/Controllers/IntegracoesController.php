<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\KbService;
use App\Services\NotificationService;
use App\Services\IntelbrasDvrService;
use App\Services\OmadaService;
use App\Services\OmadaSshService;
use App\Services\UnifiService;

/**
 * Hub de integrações do Sistema -- admin-only (dados sensíveis: senha de
 * SMTP, API key da base central etc., que usuários com perfil abaixo de
 * admin não precisam ver mesmo que tenham acesso ao módulo relacionado).
 */
class IntegracoesController extends Controller
{
    public function index(): void
    {
        AuthMiddleware::checkAdmin();

        $this->view('administracao/integracoes', []);
    }

    public function baseConhecimentoForm(): void
    {
        AuthMiddleware::checkAdmin();

        $service = new KbService();

        $this->view('administracao/integracoes_base_conhecimento', [
            'urlCentral' => $service->urlCentral(),
            'apiKeyCentral' => $service->apiKeyCentral(),
        ]);
    }

    public function baseConhecimentoSalvar(): void
    {
        AuthMiddleware::checkAdmin();

        $resultado = (new KbService())->salvarConfigCentral($_POST['url'] ?? '', $_POST['api_key'] ?? '');

        if ($resultado['success']) {
            AuditService::registrar('Integrações', 'Base de Conhecimento', 'Configuração da base central atualizada.');
            NotificationService::success($resultado['message']);
        } else {
            NotificationService::error($resultado['message']);
        }

        header('Location: ' . url('/administracao/integracoes/base-conhecimento'));
        exit;
    }

    public function unifiForm(): void
    {
        AuthMiddleware::checkAdmin();

        $service = new UnifiService();

        $this->view('administracao/integracoes_unifi', [
            'urlAtual' => $service->urlAtual(),
            'configurado' => $service->configurado(),
            'siteId' => $service->siteIdAtual(),
        ]);
    }

    public function unifiSalvar(): void
    {
        AuthMiddleware::checkAdmin();

        (new UnifiService())->salvarConfiguracao($_POST['url'] ?? '', $_POST['api_key'] ?? '');

        header('Location: ' . url('/administracao/integracoes/unifi'));
        exit;
    }

    public function unifiRemover(): void
    {
        AuthMiddleware::checkAdmin();

        (new UnifiService())->removerConfiguracao();

        header('Location: ' . url('/administracao/integracoes/unifi'));
        exit;
    }

    public function unifiTestar(): void
    {
        AuthMiddleware::checkAdmin();
        header('Content-Type: application/json');

        set_time_limit(30);

        echo json_encode((new UnifiService())->testarConexao());
    }

    public function omadaForm(): void
    {
        AuthMiddleware::checkAdmin();

        $service = new OmadaService();
        $ssh = new OmadaSshService();

        $this->view('administracao/integracoes_omada', [
            'modo' => $service->modoAtual(),
            'urlAtual' => $service->urlAtual(),
            'omadacIdAtual' => $service->omadacIdAtual(),
            'clientIdAtual' => $service->clientIdAtual(),
            'configurado' => $service->configurado(),
            'controllerConfigurado' => $service->controllerConfigurado(),
            'siteId' => $service->siteIdAtual(),
            'sshConfigurado' => $ssh->configurado(),
            'sshUsuarioAtual' => $ssh->usuarioAtual(),
            'sshPortaAtual' => $ssh->portaAtual(),
            'sshManutUsuarioAtual' => $ssh->usuarioManutencaoAtual(),
        ]);
    }

    public function omadaSalvar(): void
    {
        AuthMiddleware::checkAdmin();

        $service = new OmadaService();
        $modo = in_array($_POST['modo'] ?? '', [OmadaService::MODO_SSH, OmadaService::MODO_AMBOS], true) ? $_POST['modo'] : OmadaService::MODO_CONTROLLER;

        // Combinado: salva os dois blocos; só vale se os dois forem aceitos.
        $salvou = true;
        if ($modo !== OmadaService::MODO_SSH) {
            $salvou = $service->salvarConfiguracao($_POST['url'] ?? '', $_POST['omadac_id'] ?? '', $_POST['client_id'] ?? '', $_POST['client_secret'] ?? '');
        }
        if ($salvou && $modo !== OmadaService::MODO_CONTROLLER) {
            $salvou = (new OmadaSshService())->salvarConfiguracao($_POST['ssh_usuario'] ?? '', $_POST['ssh_senha'] ?? '', $_POST['ssh_porta'] ?? '');
            if ($salvou) {
                (new OmadaSshService())->salvarCredencialManutencao($_POST['manut_usuario'] ?? '', $_POST['manut_senha'] ?? '');
            }
        }

        // Só troca o modo quando a credencial dele foi aceita -- senão um
        // formulário incompleto derrubaria a coleta que já funcionava no outro modo.
        if ($salvou) {
            $service->salvarModo($modo);
        }

        header('Location: ' . url('/administracao/integracoes/omada'));
        exit;
    }

    public function omadaRemover(): void
    {
        AuthMiddleware::checkAdmin();

        (new OmadaService())->removerConfiguracao();

        header('Location: ' . url('/administracao/integracoes/omada'));
        exit;
    }

    public function omadaTestar(): void
    {
        AuthMiddleware::checkAdmin();
        header('Content-Type: application/json');

        set_time_limit(30);

        $service = new OmadaService();
        $ip = trim((string)($_POST['ip'] ?? ''));

        $resultados = [];
        if ($service->usaController() && $service->controllerConfigurado()) {
            $resultados['Controller'] = $service->testarConexao();
        }
        // No combinado o IP é opcional: sem ele, testa só o Controller.
        if ($service->usaSsh() && ($ip !== '' || !$resultados)) {
            $resultados['SSH'] = (new OmadaSshService())->testarConexao($ip);
        }

        if (count($resultados) === 1) {
            echo json_encode(reset($resultados));
            return;
        }

        $mensagens = [];
        foreach ($resultados as $fonte => $r) {
            $mensagens[] = "{$fonte}: " . $r['message'];
        }
        echo json_encode([
            'success' => !in_array(false, array_column($resultados, 'success'), true),
            'message' => implode(' | ', $mensagens),
        ]);
    }

    public function intelbrasDvrForm(): void
    {
        AuthMiddleware::checkAdmin();

        $service = new IntelbrasDvrService();

        $stmt = Database::connection()->query("
            SELECT cc.nome, cs.nome AS setor_nome
            FROM chamados_categorias cc
            LEFT JOIN chamados_setores cs ON cs.id = cc.setor_padrao_id
            WHERE cc.nome = 'DVR/NVR'
            LIMIT 1
        ");
        $categoriaAlerta = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;

        $this->view('administracao/integracoes_intelbras_dvr', [
            'usuarioAtual' => $service->usuarioAtual(),
            'configurado' => $service->configurado(),
            'credenciais' => $service->listarCredenciais(),
            'categoriaAlerta' => $categoriaAlerta,
        ]);
    }

    public function intelbrasDvrSalvar(): void
    {
        AuthMiddleware::checkAdmin();

        (new IntelbrasDvrService())->salvarConfiguracao($_POST['usuario'] ?? '', $_POST['senha'] ?? '');

        header('Location: ' . url('/administracao/integracoes/intelbras-dvr'));
        exit;
    }

    public function intelbrasDvrRemover(): void
    {
        AuthMiddleware::checkAdmin();

        (new IntelbrasDvrService())->removerConfiguracao();

        header('Location: ' . url('/administracao/integracoes/intelbras-dvr'));
        exit;
    }

    public function intelbrasDvrCredencialSalvar(): void
    {
        AuthMiddleware::checkAdmin();

        $resultado = (new IntelbrasDvrService())->salvarCredencialPorIp(
            $_POST['ip'] ?? '',
            $_POST['usuario'] ?? '',
            $_POST['senha'] ?? ''
        );

        if ($resultado['success']) {
            NotificationService::success($resultado['message']);
        } else {
            NotificationService::error($resultado['message']);
        }

        header('Location: ' . url('/administracao/integracoes/intelbras-dvr'));
        exit;
    }

    public function intelbrasDvrCredencialRemover(): void
    {
        AuthMiddleware::checkAdmin();

        (new IntelbrasDvrService())->removerCredencialPorIp($_POST['ip'] ?? '');

        NotificationService::success('Credencial removida.');

        header('Location: ' . url('/administracao/integracoes/intelbras-dvr'));
        exit;
    }

}
