<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\BitdefenderService;
use App\Services\NotificationService;
use App\Services\PermissionService;

/**
 * Segurança > Bitdefender: máquinas do console do GravityZone, quarentena e
 * ações (verificar, restaurar/remover da quarentena, isolar). Só aparece no
 * menu com a integração configurada em Administração > Integrações.
 */
class BitdefenderController extends Controller
{
    private BitdefenderService $service;

    public function __construct()
    {
        $this->service = new BitdefenderService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('seguranca_bitdefender');

        if (!$this->service->configurado()) {
            NotificationService::error('Configure a integração com o Bitdefender em Administração > Integrações.');
            header('Location: ' . url(PermissionService::ehAdmin() ? '/administracao/integracoes/bitdefender' : '/dashboard'));
            exit;
        }

        $this->view('seguranca/bitdefender', [
            'endpoints' => $this->service->endpoints(),
            'quarentena' => $this->service->quarentena(),
            'semBitdefender' => $this->service->maquinasSemBitdefender(),
            'licenca' => $this->service->licenca(),
            'ultimaSincronizacao' => $this->service->ultimaSincronizacao(),
            'ultimoErro' => $this->service->ultimoErro(),
        ]);
    }

    public function sincronizar(): void
    {
        AuthMiddleware::checkModulo('seguranca_bitdefender');

        try {
            $r = $this->service->sincronizar();
            NotificationService::success("Sincronizado: {$r['endpoints']} máquina(s), {$r['quarentena']} item(ns) em quarentena" . ($r['alertas'] ? ", {$r['alertas']} alerta(s) novo(s) na Central de Segurança" : '') . '.');
        } catch (\RuntimeException $e) {
            NotificationService::error($e->getMessage());
        }
        $this->voltar();
    }

    public function verificar(): void
    {
        AuthMiddleware::checkModulo('seguranca_bitdefender');

        $r = $this->service->verificar((string)($_POST['endpoint_id'] ?? ''), (int)($_POST['tipo'] ?? 1), $this->usuario());
        $r['success'] ? NotificationService::success($r['message']) : NotificationService::error($r['message']);
        $this->voltar();
    }

    public function isolar(): void
    {
        AuthMiddleware::checkModulo('seguranca_bitdefender');

        $r = $this->service->isolar((string)($_POST['endpoint_id'] ?? ''), ($_POST['acao'] ?? '') === 'isolar', $this->usuario());
        $r['success'] ? NotificationService::success($r['message']) : NotificationService::error($r['message']);
        $this->voltar();
    }

    public function quarentena(): void
    {
        AuthMiddleware::checkModulo('seguranca_bitdefender');

        $r = $this->service->quarentenaAcao(array_map('strval', (array)($_POST['itens'] ?? [])), ($_POST['acao'] ?? '') === 'restaurar', $this->usuario());
        $r['success'] ? NotificationService::success($r['message']) : NotificationService::error($r['message']);
        $this->voltar('#quarentena');
    }

    private function usuario(): string
    {
        return (string)($_SESSION['usuario']['nome'] ?? 'usuário');
    }

    private function voltar(string $ancora = ''): never
    {
        header('Location: ' . url('/seguranca/bitdefender') . $ancora);
        exit;
    }
}
