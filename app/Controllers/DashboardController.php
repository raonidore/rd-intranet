<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\SambaService;
use App\Middleware\AuthMiddleware;

class DashboardController extends Controller
{
    public function index(): void
    {
        AuthMiddleware::check();

        $samba = new SambaService();

        $dashboardSamba = $samba->dashboard();

        $this->view('dashboard/index', [
            'dashboardSamba' => $dashboardSamba
        ]);
    }
}
