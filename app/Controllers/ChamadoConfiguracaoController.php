<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\ChamadoConfigService;
use App\Services\NotificationService;

class ChamadoConfiguracaoController extends Controller
{
    public function index(): void
    {
        AuthMiddleware::checkModulo('chamados_configuracoes');

        $config = new ChamadoConfigService();

        $this->view('chamados/configuracoes', [
            'expedienteAtivo' => $config->expedienteAtivo(),
            'expedienteInicio' => $config->expedienteInicio(),
            'expedienteFim' => $config->expedienteFim(),
            'distribuicaoAtiva' => $config->distribuicaoAutomaticaAtiva(),
            'distribuicaoMinutos' => $config->distribuicaoAutomaticaMinutos(),
        ]);
    }

    public function salvarExpediente(): void
    {
        AuthMiddleware::checkModulo('chamados_configuracoes');

        $resultado = (new ChamadoConfigService())->salvarExpediente(
            isset($_POST['ativo']),
            $_POST['inicio'] ?? '',
            $_POST['fim'] ?? ''
        );

        AuditService::registrar('Chamados', 'Configurações - Expediente', $resultado['message']);

        if ($resultado['success']) {
            NotificationService::success($resultado['message']);
        } else {
            NotificationService::error($resultado['message']);
        }

        header('Location: ' . url('/chamados/configuracoes'));
        exit;
    }

    public function salvarDistribuicao(): void
    {
        AuthMiddleware::checkModulo('chamados_configuracoes');

        $resultado = (new ChamadoConfigService())->salvarDistribuicaoAutomatica(
            isset($_POST['ativo']),
            (int)($_POST['minutos'] ?? 0)
        );

        AuditService::registrar('Chamados', 'Configurações - Distribuição automática', $resultado['message']);

        if ($resultado['success']) {
            NotificationService::success($resultado['message']);
        } else {
            NotificationService::error($resultado['message']);
        }

        header('Location: ' . url('/chamados/configuracoes'));
        exit;
    }
}
