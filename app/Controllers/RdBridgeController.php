<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\NotificationService;
use App\Services\RdBridgeService;
use App\Services\UnidadeService;

/**
 * Tela administrativa (Integrações > RD.Bridge) pra criar/revogar
 * coletores por unidade, e os endpoints de API que o próprio Bridge
 * instalado no site remoto chama -- autenticados por token
 * (X-RD-Bridge-Chave), nunca por sessão, mesmo modelo do agente Windows.
 */
class RdBridgeController extends Controller
{
    private RdBridgeService $service;

    public function __construct()
    {
        $this->service = new RdBridgeService();
    }

    public function index(): void
    {
        AuthMiddleware::checkAdmin();

        $this->view('administracao/integracoes_rdbridge', [
            'coletores' => $this->service->listar(),
            'unidades' => (new UnidadeService())->listarAtivas(),
        ]);
    }

    public function criar(): void
    {
        AuthMiddleware::checkAdmin();
        header('Content-Type: application/json');

        $unidadeId = (int)($_POST['unidade_id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $modo = trim($_POST['modo'] ?? 'http');

        echo json_encode($this->service->criar($unidadeId, $nome, $modo));
    }

    public function revogar(): void
    {
        AuthMiddleware::checkAdmin();

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->revogar($id);

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);

        header('Location: ' . url('/administracao/integracoes/rd-bridge'));
        exit;
    }

    // ── API chamada pelo próprio RD.Bridge instalado no site remoto ──────

    private function autenticarRequisicao(): ?array
    {
        $token = $_SERVER['HTTP_X_RD_BRIDGE_CHAVE'] ?? '';

        return $this->service->autenticar($token);
    }

    public function apiHeartbeat(): void
    {
        header('Content-Type: application/json');

        $bridge = $this->autenticarRequisicao();
        if (!$bridge) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Token inválido.']);
            return;
        }

        $payload = json_decode(file_get_contents('php://input'), true) ?: [];
        $versao = isset($payload['versao']) ? trim((string)$payload['versao']) : null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        echo json_encode($this->service->heartbeat($bridge, $ip, $versao ?: null));
    }

    public function apiColetaResultado(): void
    {
        header('Content-Type: application/json');

        $bridge = $this->autenticarRequisicao();
        if (!$bridge) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Token inválido.']);
            return;
        }

        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Corpo JSON inválido.']);
            return;
        }

        $ativoId = (int)($payload['ativo_id'] ?? 0);
        $dados = is_array($payload['dados'] ?? null) ? $payload['dados'] : [];

        echo json_encode($this->service->registrarResultadoColeta($bridge, $ativoId, $dados));
    }
}
