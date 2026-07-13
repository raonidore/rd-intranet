<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AcessoRemotoService;
use App\Services\AtivoService;

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

        $this->view('ativos/acesso_remoto', [
            'instalado' => $this->service->instalado(),
            'rodando' => $rodando,
            'porta' => $this->service->porta(),
            'urlConsole' => $this->service->urlConsole(),
            'credenciaisConfiguradas' => $credenciaisConfiguradas,
            'usuarioTokenAtual' => $this->service->usuarioTokenAtual(),
            'dispositivos' => ($rodando && $credenciaisConfiguradas) ? $this->service->listarDispositivos() : [],
            'ativos' => $this->ativoService->listar(),
        ]);
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
}
