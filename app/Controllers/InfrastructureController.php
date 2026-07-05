<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\SystemServiceManager;
use App\Services\AuditService;
use App\Services\NotificationService;

class InfrastructureController extends Controller
{
    private SystemServiceManager $serviceManager;

    public function __construct()
    {
        $this->serviceManager = new SystemServiceManager();
    }

    public function servicos(): void
    {
        AuthMiddleware::checkModulo('infra_servicos');

        $servicos = [];

        foreach ($this->serviceManager->listarServicos() as $chave => $nome) {
            $servicos[] = [
                'chave' => $chave,
                'nome' => $nome,
                'status' => $this->serviceManager->status($chave)
            ];
        }

        $this->view('infrastructure/servicos', [
            'servicos' => $servicos
        ]);
    }

    public function reiniciar(): void
    {
        AuthMiddleware::checkModulo('infra_servicos');

        $servico = $_GET['service'] ?? '';

        $this->serviceManager->reiniciar($servico);

        header('Location: ' . url('/infraestrutura/servicos'));
        exit;
    }

    public function recarregar(): void
    {
        AuthMiddleware::checkModulo('infra_servicos');

        $servico = $_GET['service'] ?? '';

        $this->serviceManager->recarregar($servico);

        header('Location: ' . url('/infraestrutura/servicos'));
        exit;
    }

    public function logs(): void
    {
        AuthMiddleware::checkModulo('infra_servicos');

        $servico = $_GET['service'] ?? '';

        $logs = $this->serviceManager->logs($servico);

        $this->view('infrastructure/logs', [
            'servico' => $servico,
            'logs' => $logs
        ]);
    }

    public function servicosConfigurar(): void
    {
        AuthMiddleware::checkModulo('infra_servicos');

        $this->view('infrastructure/servicos_configurar', [
            'catalogo' => $this->serviceManager->catalogoDisponivel(),
        ]);
    }

    public function servicosSalvar(): void
    {
        AuthMiddleware::checkModulo('infra_servicos');

        $selecionados = $_POST['unidades'] ?? [];

        $sucesso = $this->serviceManager->salvarSelecao(is_array($selecionados) ? $selecionados : []);

        if ($sucesso) {
            AuditService::registrar('Serviços', 'Configurar', 'Lista de serviços gerenciados atualizada.');
            NotificationService::success('Serviços gerenciados atualizados com sucesso.');
        } else {
            NotificationService::error('Não foi possível salvar a lista de serviços gerenciados.');
        }

        header('Location: ' . url('/infraestrutura/servicos'));
        exit;
    }
}
