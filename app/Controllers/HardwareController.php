<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\ServerInfoService;

class HardwareController extends Controller
{
    public function index(): void
    {
        AuthMiddleware::checkModulo('infra_hardware');

        $this->view('infrastructure/hardware', [
            'info' => (new ServerInfoService())->snapshot(),
        ]);
    }

    public function api(): void
    {
        AuthMiddleware::checkModulo('infra_hardware');

        header('Content-Type: application/json');
        header('Cache-Control: no-cache');

        echo json_encode((new ServerInfoService())->snapshot());
    }
}
