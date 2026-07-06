<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\ServerInfoService;

class ServerController extends Controller
{
    public function index(): void
    {
        AuthMiddleware::checkModulo('infra_servidor');

        $this->view('infrastructure/servidor', [
            'info' => (new ServerInfoService())->snapshot(),
        ]);
    }

    public function api(): void
    {
        AuthMiddleware::checkModulo('infra_servidor');

        header('Content-Type: application/json');
        header('Cache-Control: no-cache');

        echo json_encode((new ServerInfoService())->snapshot());
    }
}
