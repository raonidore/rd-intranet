<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\ApacheGlobalConfigService;
use App\Services\NotificationService;
use App\Services\AuditService;

class ApacheConfiguracaoController extends Controller
{
    private ApacheGlobalConfigService $service;

    public function __construct()
    {
        $this->service = new ApacheGlobalConfigService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('apache_config');

        $this->view('apache/configuracao', [
            'config' => $this->service->lerConfigAtual(),
            'backups' => $this->service->listarBackups(),
            'grupos' => ApacheGlobalConfigService::$grupos,
        ]);
    }

    public function salvar(): void
    {
        AuthMiddleware::checkModulo('apache_config');

        $params = $this->extrairParametros($_POST);
        $resultado = $this->service->aplicar($params);

        if ($resultado['success']) {
            AuditService::registrar('Configuração', 'Editar config Apache', 'Configuração global do Apache aplicada.');
            NotificationService::success('Configuração global aplicada com sucesso.', $resultado['output']);
        } else {
            NotificationService::error('Erro ao aplicar configuração. Verifique os valores.', $resultado['output']);
        }

        header('Location: ' . url('/apache/configuracao'));
        exit;
    }

    public function restaurarBackup(): void
    {
        AuthMiddleware::checkModulo('apache_config');
        header('Content-Type: application/json');

        $arquivo = trim($_POST['arquivo'] ?? '');
        $resultado = $this->service->restaurarBackup($arquivo);

        if ($resultado['success']) {
            AuditService::registrar('Configuração', 'Restaurar backup Apache', 'Backup restaurado: ' . basename($arquivo));
        }

        while (ob_get_level()) {
            ob_end_clean();
        }

        echo json_encode(['success' => $resultado['success'], 'message' => $resultado['output']]);
    }

    private function extrairParametros(array $post): array
    {
        $params = [];

        foreach (ApacheGlobalConfigService::$grupos as $grupo) {
            foreach ($grupo['campos'] as $campo) {
                $chave = $campo['key'];
                $params[$chave] = trim($post[$chave] ?? '');
            }
        }

        return $params;
    }
}
