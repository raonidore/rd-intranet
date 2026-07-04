<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\DeployCenterService;

class DeployCenterController extends Controller
{
    private DeployCenterService $service;

    public function __construct()
    {
        $this->service = new DeployCenterService();
    }

    public function index(): void
    {
        AuthMiddleware::check();

        $samba = $this->service->status('samba');

        $pendencias = $this->service->pendencias('samba');

        $this->view('deploy/index', [
            'samba' => $samba,
            'pendencias' => $pendencias
        ]);
    }

    public function aplicarSamba(): void
    {
        AuthMiddleware::check();

        $this->service->aplicarSamba();

        header('Location: ' . url('/deploy'));
        exit;
    }
}
