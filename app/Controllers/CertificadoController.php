<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\CertificadoService;
use App\Services\NotificationService;

class CertificadoController extends Controller
{
    private CertificadoService $service;

    public function __construct()
    {
        $this->service = new CertificadoService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('infra_certificado');

        $this->view('infrastructure/certificado', [
            'status' => $this->service->status(),
            'ips' => $this->service->interfacesEIps(),
        ]);
    }

    public function autoassinadoForm(): void
    {
        AuthMiddleware::checkModulo('infra_certificado');

        $this->view('infrastructure/certificado_autoassinado', [
            'ips' => $this->service->interfacesEIps(),
        ]);
    }

    public function autoassinadoGerar(): void
    {
        AuthMiddleware::checkModulo('infra_certificado');

        $cn = trim($_POST['cn'] ?? '');
        $ip = trim($_POST['ip_extra'] ?? '');

        $resultado = $this->service->gerarAutoassinado($cn, $ip);

        if ($resultado['success']) {
            AuditService::registrar('Certificado', 'Gerar autoassinado', "Certificado autoassinado gerado para {$cn}.");
        }
        $this->notificarEVoltar($resultado);
    }

    public function letsencryptForm(): void
    {
        AuthMiddleware::checkModulo('infra_certificado');

        $this->view('infrastructure/certificado_letsencrypt', [
            'status' => $this->service->status(),
        ]);
    }

    public function letsencryptAplicar(): void
    {
        AuthMiddleware::checkModulo('infra_certificado');

        $dominio = trim($_POST['dominio'] ?? '');
        $email = trim($_POST['email'] ?? '');

        $resultado = $this->service->configurarLetsEncrypt($dominio, $email);

        if ($resultado['success']) {
            AuditService::registrar('Certificado', "Let's Encrypt", "Certificado Let's Encrypt obtido para {$dominio}.");
        }
        $this->notificarEVoltar($resultado);
    }

    public function importarForm(): void
    {
        AuthMiddleware::checkModulo('infra_certificado');

        $this->view('infrastructure/certificado_importar', []);
    }

    public function importarSalvar(): void
    {
        AuthMiddleware::checkModulo('infra_certificado');

        $resultado = $this->service->importar(
            $_FILES['certificado'] ?? [],
            $_FILES['chave'] ?? [],
            $_FILES['cadeia'] ?? null
        );

        if ($resultado['success']) {
            AuditService::registrar('Certificado', 'Importar', 'Certificado próprio importado.');
        }
        $this->notificarEVoltar($resultado);
    }

    private function notificarEVoltar(array $resultado): void
    {
        if ($resultado['success']) {
            NotificationService::success($resultado['message']);
        } else {
            NotificationService::error($resultado['message']);
        }

        header('Location: ' . url('/infraestrutura/certificado'));
        exit;
    }
}
