<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AtivoService;
use App\Services\AuditService;
use App\Services\RdpService;

/** RDP pelo navegador (Ativos > ficha da máquina) -- todo o fluxo é modal + fetch, por isso as respostas são sempre JSON, nunca redirect. */
class RdpController extends Controller
{
    private RdpService $service;
    private AtivoService $ativoService;

    public function __construct()
    {
        $this->service = new RdpService();
        $this->ativoService = new AtivoService();
    }

    public function status(): void
    {
        AuthMiddleware::checkModulo('ativos_rdp');
        header('Content-Type: application/json');

        $ativoId = (int)($_GET['ativo_id'] ?? 0);

        echo json_encode([
            'success' => true,
            'credencial' => $this->service->credencial($ativoId),
            'gateway' => $this->service->statusGateway(),
        ]);
    }

    public function salvarCredencial(): void
    {
        AuthMiddleware::checkModulo('ativos_rdp');
        header('Content-Type: application/json');

        $ativoId = (int)($_POST['ativo_id'] ?? 0);

        echo json_encode($this->service->salvarCredencial(
            $ativoId,
            $_POST['host'] ?? '',
            (int)($_POST['porta'] ?? 3389),
            $_POST['usuario'] ?? '',
            $_POST['senha'] ?? ''
        ));
    }

    public function removerCredencial(): void
    {
        AuthMiddleware::checkModulo('ativos_rdp');
        header('Content-Type: application/json');

        echo json_encode($this->service->removerCredencial((int)($_POST['ativo_id'] ?? 0)));
    }

    public function instalarGateway(): void
    {
        AuthMiddleware::checkModulo('ativos_rdp');
        header('Content-Type: application/json');
        set_time_limit(180);

        echo json_encode($this->service->instalarGateway());
    }

    public function conectar(): void
    {
        AuthMiddleware::checkModulo('ativos_rdp');
        header('Content-Type: application/json');

        $ativoId = (int)($_POST['ativo_id'] ?? 0);
        $ativo = $this->ativoService->buscar($ativoId);

        if (!$ativo) {
            echo json_encode(['success' => false, 'message' => 'Ativo não encontrado.']);
            return;
        }

        if (!$this->service->gatewayPronto()) {
            echo json_encode(['success' => false, 'message' => 'O suporte a RDP pelo navegador ainda não está pronto neste servidor.']);
            return;
        }

        $largura = (int)($_POST['largura'] ?? 1024);
        $altura = (int)($_POST['altura'] ?? 768);
        $token = $this->service->gerarToken($ativoId, $largura, $altura);
        if ($token === null) {
            echo json_encode(['success' => false, 'message' => 'Nenhuma credencial de RDP configurada pra este ativo.']);
            return;
        }

        AuditService::registrar('Ativos', 'RDP', "Sessão de RDP pelo navegador aberta para o ativo #{$ativoId}.");

        echo json_encode(['success' => true, 'token' => $token]);
    }
}
