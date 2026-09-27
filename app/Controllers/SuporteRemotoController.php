<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AcessoRemotoService;
use App\Services\AuditService;
use App\Services\SuporteRemotoService;

/**
 * Chamados > Suporte Remoto: quem pediu ajuda pelo agente, atalho de
 * conexão pra todas as máquinas no MeshCentral e dispositivos do
 * MeshCentral sem ativo cadastrado. A conexão das máquinas cadastradas usa
 * o mesmo endpoint da "Tela remota" da ficha (/ativos/acesso-remoto/compartilhar).
 */
class SuporteRemotoController extends Controller
{
    private SuporteRemotoService $service;

    public function __construct()
    {
        $this->service = new SuporteRemotoService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('ativos_acesso_remoto');

        $this->view('chamados/suporte_remoto', [
            'pedidos' => $this->service->listarAbertos(),
            'ativos' => $this->service->ativosComAcessoRemoto(),
            'semCadastro' => $this->service->dispositivosSemCadastro(),
        ]);
    }

    public function atender(): void
    {
        AuthMiddleware::checkModulo('ativos_acesso_remoto');
        header('Content-Type: application/json');

        $ok = $this->service->marcarAtendido((int)($_POST['id'] ?? 0), $_SESSION['usuario']['nome'] ?? 'portal');
        echo json_encode(['success' => $ok, 'message' => $ok ? '' : 'Pedido não encontrado ou já atendido.']);
    }

    /** Conexão rápida a um dispositivo do MeshCentral que não é ativo cadastrado. */
    public function conectarDispositivo(): void
    {
        AuthMiddleware::checkModulo('ativos_acesso_remoto');
        header('Content-Type: application/json');

        $meshDeviceId = trim((string)($_POST['mesh_device_id'] ?? ''));
        if ($meshDeviceId === '' || !$this->service->dispositivoSemCadastroExiste($meshDeviceId)) {
            echo json_encode(['success' => false, 'message' => 'Dispositivo não encontrado no MeshCentral.']);
            return;
        }

        $url = (new AcessoRemotoService())->gerarLinkCompartilhamento($meshDeviceId, $_SESSION['usuario']['nome'] ?? 'RD Intranet');
        if ($url === null) {
            echo json_encode(['success' => false, 'message' => 'Falha ao gerar o link de acesso remoto. O dispositivo pode estar offline.']);
            return;
        }

        AuditService::registrar('Chamados', 'Suporte remoto', "Tela remota aberta para dispositivo sem cadastro {$meshDeviceId}.");
        echo json_encode(['success' => true, 'url' => $url]);
    }
}
