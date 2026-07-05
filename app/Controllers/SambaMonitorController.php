<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\SambaMonitorService;

class SambaMonitorController extends Controller
{
    public function index(): void
    {
        AuthMiddleware::check();

        $monitor = (new SambaMonitorService())->snapshot();

        $this->view('samba/monitor', [
            'monitor' => $monitor,
        ]);
    }

    public function api(): void
    {
        AuthMiddleware::check();

        header('Content-Type: application/json');
        header('Cache-Control: no-cache');

        echo json_encode((new SambaMonitorService())->snapshot());
    }

    public function encerrar(): void
    {
        AuthMiddleware::check();

        header('Content-Type: application/json');

        $pid = $_POST['pid'] ?? '';

        if (!preg_match('/^\d+$/', $pid)) {
            echo json_encode(['success' => false, 'message' => 'PID inválido.']);
            return;
        }

        $output = shell_exec('sudo /opt/rdtecnologia/scripts/kick_sessao_samba_web.sh ' . escapeshellarg($pid) . ' 2>&1');
        $result = json_decode(trim($output ?? ''), true);

        if (json_last_error() === JSON_ERROR_NONE) {
            echo json_encode($result);
        } else {
            echo json_encode(['success' => false, 'message' => 'Resposta inesperada do script.']);
        }
    }
}
