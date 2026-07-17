<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\SambaService;
use App\Services\ApacheStatusService;
use App\Services\ServerInfoService;
use App\Services\SpeedtestService;
use App\Services\AtivoService;
use App\Services\PermissionService;

class DashboardController extends Controller
{
    public function index(): void
    {
        AuthMiddleware::check();

        $dados = [
            'samba' => null,
            'apache' => null,
            'servidor' => null,
            'ativos' => null,
            'speedtest' => null,
        ];

        if (
            PermissionService::temAcesso('samba_usuarios')
            || PermissionService::temAcesso('samba_compartilhamentos')
            || PermissionService::temAcesso('samba_dashboard')
        ) {
            $dados['samba'] = (new SambaService())->dashboard();
        }

        if (
            PermissionService::temAcesso('apache_dashboard')
            || PermissionService::temAcesso('apache_sites')
            || PermissionService::temAcesso('apache_modulos')
        ) {
            $dados['apache'] = (new ApacheStatusService())->snapshot();
        }

        if (PermissionService::temAcesso('infra_servidor')) {
            $dados['servidor'] = (new ServerInfoService())->snapshot();
        }

        if (PermissionService::temAcesso('infra_speedtest')) {
            $dados['speedtest'] = (new SpeedtestService())->ultimoConcluido();
        }

        if (PermissionService::temAcesso('ativos_dashboard')) {
            $dados['ativos'] = (new AtivoService())->resumoDashboard();
        }

        $this->view('dashboard/index', $dados);
    }
}
