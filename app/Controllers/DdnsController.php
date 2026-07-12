<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\CronService;
use App\Services\DdnsService;
use App\Services\NotificationService;

class DdnsController extends Controller
{
    private const NOME_JOB_CRON = 'Atualização automática de DNS Dinâmico';
    private const PROVEDORES_LABEL = [
        'noip' => 'No-IP',
        'dyndns' => 'DynDNS',
        'cloudflare' => 'Cloudflare',
        'duckdns' => 'DuckDNS',
        'freedns' => 'FreeDNS (afraid.org)',
    ];

    private DdnsService $service;

    public function __construct()
    {
        $this->service = new DdnsService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('infra_ddns');

        $this->view('infrastructure/ddns', [
            'contas' => $this->service->listar(),
            'provedoresLabel' => self::PROVEDORES_LABEL,
            'atualizacaoAutomaticaAtiva' => $this->atualizacaoAutomaticaAtiva(),
        ]);
    }

    public function novoForm(): void
    {
        AuthMiddleware::checkModulo('infra_ddns');

        $this->view('infrastructure/ddns_form', [
            'conta' => null,
            'provedoresLabel' => self::PROVEDORES_LABEL,
        ]);
    }

    public function novo(): void
    {
        AuthMiddleware::checkModulo('infra_ddns');

        $resultado = $this->service->salvar($_POST);

        if ($resultado['success']) {
            AuditService::registrar('DNS Dinâmico', 'Criar conta', "Conta \"{$_POST['apelido']}\" criada.");
        }
        $this->notificarEVoltar($resultado);
    }

    public function historico(): void
    {
        AuthMiddleware::checkModulo('infra_ddns');

        $id = (int)($_GET['id'] ?? 0);
        $conta = $this->service->buscar($id);

        if (!$conta) {
            header('Location: ' . url('/infraestrutura/ddns'));
            exit;
        }

        $this->view('infrastructure/ddns_historico', [
            'conta' => $conta,
            'provedoresLabel' => self::PROVEDORES_LABEL,
            'historico' => $this->service->historico($id),
        ]);
    }

    public function editarForm(): void
    {
        AuthMiddleware::checkModulo('infra_ddns');

        $id = (int)($_GET['id'] ?? 0);
        $conta = $this->service->buscar($id);

        if (!$conta) {
            header('Location: ' . url('/infraestrutura/ddns'));
            exit;
        }

        $this->view('infrastructure/ddns_form', [
            'conta' => $conta,
            'provedoresLabel' => self::PROVEDORES_LABEL,
        ]);
    }

    public function editar(): void
    {
        AuthMiddleware::checkModulo('infra_ddns');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->salvar($_POST + ['id' => $id]);

        if ($resultado['success']) {
            AuditService::registrar('DNS Dinâmico', 'Editar conta', "Conta #{$id} atualizada.");
        }
        $this->notificarEVoltar($resultado);
    }

    public function excluir(): void
    {
        AuthMiddleware::checkModulo('infra_ddns');

        $id = (int)($_POST['id'] ?? 0);
        $this->service->excluir($id);

        AuditService::registrar('DNS Dinâmico', 'Excluir conta', "Conta #{$id} excluída.");
        NotificationService::success('Conta excluída.');

        header('Location: ' . url('/infraestrutura/ddns'));
        exit;
    }

    public function ativar(): void
    {
        AuthMiddleware::checkModulo('infra_ddns');

        $id = (int)($_GET['id'] ?? 0);
        $this->service->ativar($id);

        AuditService::registrar('DNS Dinâmico', 'Ativar conta', "Conta #{$id} ativada.");
        NotificationService::success('Conta ativada.');

        header('Location: ' . url('/infraestrutura/ddns'));
        exit;
    }

    public function desativar(): void
    {
        AuthMiddleware::checkModulo('infra_ddns');

        $id = (int)($_GET['id'] ?? 0);
        $this->service->desativar($id);

        AuditService::registrar('DNS Dinâmico', 'Desativar conta', "Conta #{$id} desativada.");
        NotificationService::success('Conta desativada.');

        header('Location: ' . url('/infraestrutura/ddns'));
        exit;
    }

    public function atualizarAgora(): void
    {
        AuthMiddleware::checkModulo('infra_ddns');
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->atualizarContaId($id);

        AuditService::registrar('DNS Dinâmico', 'Atualizar agora', "Conta #{$id}: " . ($resultado['message'] ?? ''));

        echo json_encode($resultado);
    }

    public function atualizarTodasAgora(): void
    {
        AuthMiddleware::checkModulo('infra_ddns');
        header('Content-Type: application/json');

        set_time_limit(60);

        $resultado = $this->service->atualizarTodas();

        AuditService::registrar('DNS Dinâmico', 'Atualizar todas agora', $resultado['message'] ?? '');

        echo json_encode($resultado);
    }

    public function ativarAtualizacaoAutomatica(): void
    {
        AuthMiddleware::checkModulo('infra_ddns');
        header('Content-Type: application/json');

        if ($this->atualizacaoAutomaticaAtiva()) {
            echo json_encode(['success' => true, 'message' => 'Atualização automática já estava ativa.']);
            return;
        }

        $resultado = (new CronService())->criar([
            'nome' => self::NOME_JOB_CRON,
            'descricao' => 'Verifica o IP público do servidor e atualiza os provedores de DNS dinâmico ativos (Infraestrutura > DNS Dinâmico).',
            'expressao' => '*/15 * * * *',
            'usuario_execucao' => 'www-data',
            'comando' => 'php /var/www/rd.intranet/rd ddns:atualizar',
            'ativo' => true,
        ]);

        AuditService::registrar('DNS Dinâmico', 'Ativar atualização automática', $resultado['message']);

        echo json_encode($resultado);
    }

    private function atualizacaoAutomaticaAtiva(): bool
    {
        foreach ((new CronService())->listar() as $job) {
            if ($job['nome'] === self::NOME_JOB_CRON) {
                return true;
            }
        }

        return false;
    }

    private function notificarEVoltar(array $resultado): void
    {
        if ($resultado['success']) {
            NotificationService::success($resultado['message']);
        } else {
            NotificationService::error($resultado['message']);
        }

        header('Location: ' . url('/infraestrutura/ddns'));
        exit;
    }
}
