<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\DeployCenterService;
use App\Services\ConfigService;

class DeployCenterController extends Controller
{
    private const EXT_DEFAULT = 'txt,csv,log,conf,cfg,ini,xml,json,html,css,js,php,py,sql,sh,md';

    private DeployCenterService $service;

    public function __construct()
    {
        $this->service = new DeployCenterService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('deploy');

        $samba      = $this->service->status('samba');
        $pendencias = $this->service->pendencias('samba');

        $this->view('deploy/index', [
            'samba'          => $samba,
            'pendencias'     => $pendencias,
            'extVisualizar'  => array_filter(array_map('trim', explode(',',
                ConfigService::get('samba_arquivos_ext_visualizar', self::EXT_DEFAULT)))),
            'extEditar'      => array_filter(array_map('trim', explode(',',
                ConfigService::get('samba_arquivos_ext_editar', self::EXT_DEFAULT)))),
        ]);
    }

    public function salvarConfiguracoes(): void
    {
        AuthMiddleware::checkModulo('deploy');
        header('Content-Type: application/json');

        $sanitize = static function (string $raw): string {
            $exts = array_filter(array_map(
                static fn(string $e): string => preg_replace('/[^a-z0-9]/', '', strtolower(trim($e))),
                explode(',', $raw)
            ));
            return implode(',', array_unique($exts));
        };

        ConfigService::set('samba_arquivos_ext_visualizar', $sanitize($_POST['ext_visualizar'] ?? ''));
        ConfigService::set('samba_arquivos_ext_editar',     $sanitize($_POST['ext_editar']     ?? ''));

        echo json_encode(['success' => true, 'message' => 'Configurações salvas com sucesso.']);
    }

    public function aplicarSamba(): void
    {
        AuthMiddleware::checkModulo('deploy');

        $this->service->aplicarSamba();

        header('Location: ' . url('/deploy'));
        exit;
    }
}
