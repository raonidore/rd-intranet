<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\SambaCompartilhamentoService;

class SambaCompartilhamentoController extends Controller
{
    private SambaCompartilhamentoService $service;

    public function __construct()
    {
        $this->service = new SambaCompartilhamentoService();
    }

    /**
     * Lista os compartilhamentos Samba.
     */
    public function index(): void
    {
        AuthMiddleware::check();

        $compartilhamentos = $this->service->listar();
        $dashboard = $this->service->dashboard();

        $this->view('samba/compartilhamentos', [
            'compartilhamentos' => $compartilhamentos,
            'total' => $dashboard['total'],
            'ativos' => $dashboard['ativos'],
            'lixeira' => $dashboard['lixeira'],
            'bloqueioExtensoes' => $dashboard['bloqueio_extensoes'],
        ]);
    }
}
