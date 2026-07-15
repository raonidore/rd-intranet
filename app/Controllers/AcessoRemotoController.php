<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AcessoRemotoService;
use App\Services\AtivoService;
use App\Services\AuditService;
use App\Services\NotificationService;

class AcessoRemotoController extends Controller
{
    private AcessoRemotoService $service;
    private AtivoService $ativoService;

    public function __construct()
    {
        $this->service = new AcessoRemotoService();
        $this->ativoService = new AtivoService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('ativos_acesso_remoto');

        $rodando = $this->service->rodando();
        $credenciaisConfiguradas = $this->service->credenciaisConfiguradas();

        $meshAgentesDisponiveis = [];
        foreach (array_keys(AcessoRemotoService::ARQUITETURAS_MESH_AGENTE) as $arquitetura) {
            $meshAgentesDisponiveis[$arquitetura] = $this->service->meshAgenteDisponivel($arquitetura);
        }

        $this->view('ativos/acesso_remoto', [
            'instalado' => $this->service->instalado(),
            'rodando' => $rodando,
            'porta' => $this->service->porta(),
            'urlConsole' => $this->service->urlConsole(),
            'credenciaisConfiguradas' => $credenciaisConfiguradas,
            'usuarioTokenAtual' => $this->service->usuarioTokenAtual(),
            'dispositivos' => ($rodando && $credenciaisConfiguradas) ? $this->service->listarDispositivos() : [],
            'ativos' => $this->ativoService->listar(),
            'portaLiberada' => $this->service->portaLiberadaNoFirewall(),
            'arquiteturasMeshAgente' => AcessoRemotoService::ARQUITETURAS_MESH_AGENTE,
            'meshAgentesDisponiveis' => $meshAgentesDisponiveis,
        ]);
    }

    /** Download manual de um dos instaladores do MeshAgent pelo admin. */
    public function baixarMeshAgente(): void
    {
        AuthMiddleware::checkModulo('ativos_acesso_remoto');

        $arquitetura = $_GET['arquitetura'] ?? '';
        $caminho = $this->service->caminhoMeshAgentePublico($arquitetura);

        if ($caminho === null) {
            http_response_code(404);
            echo 'Nenhum instalador enviado ainda pra essa arquitetura.';
            return;
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="MeshAgent-' . $arquitetura . '.exe"');
        header('Content-Length: ' . filesize($caminho));
        readfile($caminho);
    }

    public function uploadMeshAgente(): void
    {
        AuthMiddleware::checkModulo('ativos_acesso_remoto');

        $arquivo = $_FILES['arquivo'] ?? null;
        $arquitetura = $_POST['arquitetura'] ?? '';

        if (!$arquivo || $arquivo['error'] !== UPLOAD_ERR_OK) {
            NotificationService::error('Falha no upload do arquivo.');
        } else {
            $this->service->salvarMeshAgente($arquitetura, $arquivo['tmp_name']);
        }

        header('Location: ' . url('/ativos/acesso-remoto'));
        exit;
    }

    public function liberarPorta(): void
    {
        AuthMiddleware::checkModulo('ativos_acesso_remoto');
        header('Content-Type: application/json');

        echo json_encode($this->service->liberarPortaNoFirewall());
    }

    public function vincular(): void
    {
        AuthMiddleware::checkModulo('ativos_acesso_remoto');

        $ativoId = (int)($_POST['ativo_id'] ?? 0);
        $meshDeviceId = trim($_POST['mesh_device_id'] ?? '');

        if ($ativoId > 0) {
            $this->ativoService->vincularDispositivoMesh($ativoId, $meshDeviceId);
        } elseif ($meshDeviceId !== '') {
            $this->ativoService->desvincularDispositivoMesh($meshDeviceId);
        }

        header('Location: ' . url('/ativos/acesso-remoto'));
        exit;
    }

    public function instalar(): void
    {
        AuthMiddleware::checkModulo('ativos_acesso_remoto');
        header('Content-Type: application/json');

        echo json_encode($this->service->instalar());
    }

    public function salvarCredenciais(): void
    {
        AuthMiddleware::checkModulo('ativos_acesso_remoto');

        $this->service->salvarCredenciais(
            $_POST['usuario'] ?? '',
            $_POST['senha'] ?? ''
        );

        header('Location: ' . url('/ativos/acesso-remoto'));
        exit;
    }

    public function compartilhar(): void
    {
        AuthMiddleware::checkModulo('ativos_acesso_remoto');
        header('Content-Type: application/json');

        $ativoId = (int)($_POST['ativo_id'] ?? 0);
        $ativo = $this->ativoService->buscar($ativoId);

        if (!$ativo || empty($ativo['mesh_device_id'])) {
            echo json_encode(['success' => false, 'message' => 'Este ativo não está vinculado a um dispositivo do MeshCentral.']);
            return;
        }

        $convidado = $_SESSION['usuario']['nome'] ?? 'RD Intranet';
        $url = $this->service->gerarLinkCompartilhamento($ativo['mesh_device_id'], $convidado);

        if ($url === null) {
            echo json_encode(['success' => false, 'message' => 'Falha ao gerar o link de acesso remoto. O dispositivo pode estar offline.']);
            return;
        }

        AuditService::registrar('Ativos', 'Acesso Remoto', "Sessão de tela remota aberta para o ativo #{$ativoId}.");

        echo json_encode(['success' => true, 'url' => $url]);
    }
}
