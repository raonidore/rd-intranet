<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\SambaGlobalConfigService;
use App\Services\NotificationService;
use App\Services\AuditService;

class SambaConfiguracaoController extends Controller
{
    private SambaGlobalConfigService $service;

    public function __construct()
    {
        $this->service = new SambaGlobalConfigService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('samba_config');

        $this->view('samba/configuracao', [
            'config'  => $this->service->lerConfigAtual(),
            'backups' => $this->service->listarBackups(),
            'grupos'  => SambaGlobalConfigService::$grupos,
        ]);
    }

    public function salvar(): void
    {
        AuthMiddleware::checkModulo('samba_config');

        $params  = $this->extrairParametros($_POST);
        $resultado = $this->service->aplicar($params);

        if ($resultado['success']) {
            AuditService::registrar('Configuração', 'Editar smb.conf global', 'Configuração global do Samba aplicada.');
            NotificationService::success('Configuração global aplicada com sucesso.', $resultado['output']);
        } else {
            NotificationService::error('Erro ao aplicar configuração. Verifique os valores.', $resultado['output']);
        }

        header('Location: ' . url('/samba/configuracao'));
        exit;
    }

    public function restaurarBackup(): void
    {
        AuthMiddleware::checkModulo('samba_config');
        header('Content-Type: application/json');

        $arquivo  = trim($_POST['arquivo'] ?? '');
        $resultado = $this->service->restaurarBackup($arquivo);

        if ($resultado['success']) {
            AuditService::registrar('Configuração', 'Restaurar backup smb.conf', 'Backup restaurado: ' . basename($arquivo));
        }

        while (ob_get_level()) ob_end_clean();
        echo json_encode(['success' => $resultado['success'], 'message' => $resultado['output']]);
    }

    // ── Extrai e normaliza parâmetros do POST ────────────────────────────
    private function extrairParametros(array $post): array
    {
        $params = [];

        foreach (SambaGlobalConfigService::$grupos as $grupo) {
            foreach ($grupo['campos'] as $campo) {
                $chave    = $campo['key'];
                $postKey  = str_replace([' ', ':'], ['_', '__'], $chave);
                $valor    = trim($post[$postKey] ?? '');
                $params[$chave] = $valor;
            }
        }

        return $params;
    }
}
