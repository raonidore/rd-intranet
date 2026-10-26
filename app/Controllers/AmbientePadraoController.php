<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AmbientePadraoService;
use App\Services\EntraService;
use App\Services\NotificationService;

/** Ativos > Ambiente padrão (ver AmbientePadraoService). */
class AmbientePadraoController extends Controller
{
    private AmbientePadraoService $service;

    public function __construct()
    {
        $this->service = new AmbientePadraoService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('ativos_ambiente');

        $this->view('ativos/ambiente_padrao', [
            'itens' => $this->service->listar(),
            'tipos' => AmbientePadraoService::TIPOS,
            'setores' => $this->service->setores(),
            'aplicacoes' => $this->service->aplicacoes(),
            'entraConfigurado' => (new EntraService())->configurado(),
        ]);
    }

    /** Grupos de segurança do Entra para o seletor de escopo (carregado só quando o usuário escolhe "grupo"). */
    public function grupos(): void
    {
        AuthMiddleware::checkModulo('ativos_ambiente');
        header('Content-Type: application/json');

        $entra = new EntraService();
        $grupos = $entra->configurado() ? $entra->listarGruposSeguranca() : [];
        usort($grupos, fn($a, $b) => strcasecmp($a['displayName'] ?? '', $b['displayName'] ?? ''));

        echo json_encode(array_map(fn($g) => ['id' => $g['id'], 'nome' => $g['displayName'] ?? $g['id']], $grupos));
    }

    public function salvar(): void
    {
        AuthMiddleware::checkModulo('ativos_ambiente');

        $id = (int)($_POST['id'] ?? 0) ?: null;
        $r = $this->service->salvar($_POST, $id, $_SESSION['usuario']['nome'] ?? 'portal');
        $r['success'] ? NotificationService::success($r['message']) : NotificationService::error($r['message']);

        $this->voltar();
    }

    public function alternar(): void
    {
        AuthMiddleware::checkModulo('ativos_ambiente');
        $this->service->alternar((int)($_POST['id'] ?? 0));
        $this->voltar();
    }

    public function excluir(): void
    {
        AuthMiddleware::checkModulo('ativos_ambiente');
        $this->service->excluir((int)($_POST['id'] ?? 0));
        NotificationService::success('Item excluído. O agente remove das máquinas na próxima conferência.');
        $this->voltar();
    }

    private function voltar(): void
    {
        header('Location: ' . url('/ativos/ambiente-padrao'));
        exit;
    }
}
