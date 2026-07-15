<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AtivoService;
use App\Services\NotificationService;

/**
 * Endpoints usados pelo agente Windows -- NÃO passam por sessão/login,
 * a autenticação é por chave de API compartilhada (mesmo modelo do
 * "deploy key" do OCS Inventory/GLPI), enviada no header
 * X-RD-Agente-Chave. O download do script em si (baixarScript) é a
 * única ação aqui que exige sessão -- é o admin, pelo navegador, que
 * baixa o instalador pra distribuir.
 */
class AtivoAgenteController extends Controller
{
    private AtivoService $service;

    public function __construct()
    {
        $this->service = new AtivoService();
    }

    public function checkin(): void
    {
        header('Content-Type: application/json');

        $chaveEnviada = $_SERVER['HTTP_X_RD_AGENTE_CHAVE'] ?? '';

        if ($chaveEnviada === '' || !hash_equals($this->service->chaveAgente(), $chaveEnviada)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Chave de API inválida.']);
            return;
        }

        $payload = json_decode(file_get_contents('php://input'), true);

        if (!is_array($payload)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Corpo JSON inválido.']);
            return;
        }

        echo json_encode($this->service->checkinAgente($payload));
    }

    /**
     * Ping leve de "estou ligado", chamado pelo agente a cada poucos
     * segundos -- ver AtivoService::registrarHeartbeat(). Propositalmente
     * mais simples que checkin(): só uma UPDATE indexada por machine_guid,
     * pra aguentar ser chamado com muito mais frequência sem pesar no
     * servidor.
     */
    public function heartbeat(): void
    {
        header('Content-Type: application/json');

        $chaveEnviada = $_SERVER['HTTP_X_RD_AGENTE_CHAVE'] ?? '';

        if ($chaveEnviada === '' || !hash_equals($this->service->chaveAgente(), $chaveEnviada)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Chave de API inválida.']);
            return;
        }

        $payload = json_decode(file_get_contents('php://input'), true);
        $machineGuid = trim((string)($payload['machine_guid'] ?? ''));

        if ($machineGuid === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'machine_guid é obrigatório.']);
            return;
        }

        echo json_encode($this->service->registrarHeartbeat($machineGuid));
    }

    public function baixarScript(): void
    {
        AuthMiddleware::checkModulo('ativos_dashboard');

        $template = file_get_contents(__DIR__ . '/../../scripts/agente/rd-intranet-agent.ps1');

        $esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $servidorUrl = $esquema . '://' . $host . url('');

        $conteudo = str_replace(
            ['__SERVER_URL__', '__API_KEY__', '__INTERVALO_MINUTOS__'],
            [$servidorUrl, $this->service->chaveAgente(), (string)$this->service->intervaloComunicacao()],
            $template
        );

        header('Content-Type: text/plain; charset=UTF-8');
        header('Content-Disposition: attachment; filename="rd-intranet-agent.ps1"');
        echo $conteudo;
    }

    /** Download manual do .exe pelo admin, pelo navegador -- pra instalar/distribuir. */
    public function baixarExecutavel(): void
    {
        AuthMiddleware::checkModulo('ativos_dashboard');

        $caminho = $this->service->caminhoAgenteExePublico();

        if ($caminho === null) {
            http_response_code(404);
            echo 'Nenhuma versão do agente .exe foi enviada ainda.';
            return;
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="RdIntranetAgente.exe"');
        header('Content-Length: ' . filesize($caminho));
        readfile($caminho);
    }

    /** Consultado pelo próprio agente (X-RD-Agente-Chave) pra saber se há versão nova. */
    public function versaoExecutavel(): void
    {
        header('Content-Type: application/json');

        $chaveEnviada = $_SERVER['HTTP_X_RD_AGENTE_CHAVE'] ?? '';

        if ($chaveEnviada === '' || !hash_equals($this->service->chaveAgente(), $chaveEnviada)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Chave de API inválida.']);
            return;
        }

        echo json_encode([
            'success' => true,
            'disponivel' => $this->service->agenteExeDisponivel(),
            'versao' => $this->service->versaoAgenteExe(),
        ]);
    }

    /** Baixado pelo próprio agente (X-RD-Agente-Chave) pra se autoatualizar. */
    public function downloadAtualizacao(): void
    {
        $chaveEnviada = $_SERVER['HTTP_X_RD_AGENTE_CHAVE'] ?? '';

        if ($chaveEnviada === '' || !hash_equals($this->service->chaveAgente(), $chaveEnviada)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Chave de API inválida.']);
            return;
        }

        $caminho = $this->service->caminhoAgenteExePublico();

        if ($caminho === null) {
            http_response_code(404);
            return;
        }

        header('Content-Type: application/octet-stream');
        header('Content-Length: ' . filesize($caminho));
        readfile($caminho);
    }

    public function uploadExecutavel(): void
    {
        AuthMiddleware::checkModulo('ativos_dashboard');

        $arquivo = $_FILES['arquivo'] ?? null;
        $versao = trim($_POST['versao'] ?? '');

        if (!$arquivo || $arquivo['error'] !== UPLOAD_ERR_OK) {
            NotificationService::error('Falha no upload do arquivo.');
        } else {
            $this->service->salvarNovoAgenteExe($arquivo['tmp_name'], $versao);
        }

        header('Location: ' . url('/ativos'));
        exit;
    }

    /** Download manual do instalador do .NET Desktop Runtime pelo admin. */
    public function baixarDotnetRuntime(): void
    {
        AuthMiddleware::checkModulo('ativos_dashboard');

        $caminho = $this->service->caminhoDotnetRuntimePublico();

        if ($caminho === null) {
            http_response_code(404);
            echo 'Nenhum instalador do .NET Desktop Runtime foi enviado ainda.';
            return;
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="windowsdesktop-runtime-win-x64.exe"');
        header('Content-Length: ' . filesize($caminho));
        readfile($caminho);
    }

    public function uploadDotnetRuntime(): void
    {
        AuthMiddleware::checkModulo('ativos_dashboard');

        $arquivo = $_FILES['arquivo'] ?? null;
        $label = trim($_POST['label'] ?? '');

        if (!$arquivo || $arquivo['error'] !== UPLOAD_ERR_OK) {
            NotificationService::error('Falha no upload do arquivo.');
        } else {
            $this->service->salvarDotnetRuntime($arquivo['tmp_name'], $label);
        }

        header('Location: ' . url('/ativos'));
        exit;
    }
}
