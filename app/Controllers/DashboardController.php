<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\SambaService;
use App\Services\ApacheStatusService;
use App\Services\ServerInfoService;
use App\Services\SpeedtestService;
use App\Services\AtivoService;
use App\Services\BackupService;
use App\Services\PermissionService;
use App\Services\WhatsAppEstatisticaService;
use App\Services\ChamadoEstatisticaService;

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
            'backup' => null,
            'whatsapp' => null,
            'chamados' => null,
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

        if (
            PermissionService::temAcesso('backup_configuracao')
            || PermissionService::temAcesso('backup_historico')
        ) {
            $dados['backup'] = (new BackupService())->dashboard();
        }

        if (
            PermissionService::temAcesso('whatsapp_atendimentos')
            || PermissionService::temAcesso('whatsapp_fila')
            || PermissionService::temAcesso('whatsapp_chatbot')
            || PermissionService::temAcesso('whatsapp_setores')
            || PermissionService::temAcesso('whatsapp_estatisticas')
            || PermissionService::temAcesso('whatsapp_configuracoes')
        ) {
            $dados['whatsapp'] = (new WhatsAppEstatisticaService())->tempoReal();
        }

        if (
            PermissionService::temAcesso('chamados_atendimentos')
            || PermissionService::temAcesso('chamados_fila')
            || PermissionService::temAcesso('chamados_categorias')
            || PermissionService::temAcesso('chamados_setores')
            || PermissionService::temAcesso('chamados_estatisticas')
            || PermissionService::temAcesso('chamados_configuracoes')
        ) {
            $dados['chamados'] = (new ChamadoEstatisticaService())->tempoReal();
        }

        $this->view('dashboard/index', $dados);
    }
}
