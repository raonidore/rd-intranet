<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\SegurancaAuditoriaService;

class SegurancaAuditoriaController extends Controller
{
    private SegurancaAuditoriaService $service;

    public function __construct()
    {
        $this->service = new SegurancaAuditoriaService();
    }

    private function usuarioId(): ?int
    {
        return isset($_SESSION['usuario']['id']) ? (int)$_SESSION['usuario']['id'] : null;
    }

    public function index(): void
    {
        AuthMiddleware::checkModuloRestrito('seguranca_auditoria_credenciais');

        $this->view('seguranca/auditoria_credenciais', [
            'historico' => $this->service->listarHistorico(),
        ]);
    }

    public function local(): void
    {
        AuthMiddleware::checkModuloRestrito('seguranca_auditoria_credenciais');
        header('Content-Type: application/json');

        echo json_encode($this->service->auditarLocal($this->usuarioId()));
    }

    public function credenciaisPadrao(): void
    {
        AuthMiddleware::checkModuloRestrito('seguranca_auditoria_credenciais');
        header('Content-Type: application/json');

        $resultado = $this->service->testarCredenciaisPadrao(
            trim($_POST['ip'] ?? ''),
            trim($_POST['servico'] ?? ''),
            $this->usuarioId()
        );

        echo json_encode($resultado);
    }

    public function sshIniciar(): void
    {
        AuthMiddleware::checkModuloRestrito('seguranca_auditoria_credenciais');
        header('Content-Type: application/json');

        $resultado = $this->service->iniciarTesteSsh(
            trim($_POST['ip'] ?? ''),
            trim($_POST['usuario'] ?? '')
        );

        echo json_encode($resultado);
    }

    public function sshStatus(): void
    {
        AuthMiddleware::checkModuloRestrito('seguranca_auditoria_credenciais');
        header('Content-Type: application/json');

        $status = $this->service->statusTesteSsh((string)($_GET['id'] ?? ''));

        // Grava no histórico só na primeira vez que o polling encontra
        // "concluido" (mesmo motivo do "finalizar" separado do IP
        // Scanner: polling é idempotente, aqui teria efeito colateral se
        // não fosse uma ação explícita à parte).
        echo json_encode($status);
    }

    public function sshFinalizar(): void
    {
        AuthMiddleware::checkModuloRestrito('seguranca_auditoria_credenciais');
        header('Content-Type: application/json');

        $execucaoId = (string)($_POST['id'] ?? '');
        $ip = trim((string)($_POST['ip'] ?? ''));
        $usuario = trim((string)($_POST['usuario'] ?? ''));

        $status = $this->service->statusTesteSsh($execucaoId);
        if (($status['status'] ?? '') !== 'concluido') {
            echo json_encode(['success' => false, 'message' => 'Teste ainda não concluído.']);
            return;
        }

        $this->service->registrarResultadoTesteSsh($ip, $usuario, $status['encontrado'] ?? null, $this->usuarioId());

        echo json_encode(['success' => true]);
    }
}
