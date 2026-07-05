<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\ApacheStatusService;

class ApacheController extends Controller
{
    public function dashboard(): void
    {
        AuthMiddleware::checkModulo('apache_dashboard');

        $this->view('apache/dashboard', [
            'status' => (new ApacheStatusService())->snapshot(),
        ]);
    }
}
