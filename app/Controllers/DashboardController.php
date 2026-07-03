<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\SambaService;

class DashboardController extends Controller
{
    public function index(): void
    {
        $samba = new SambaService();

        $dashboardSamba = $samba->dashboard();

        $this->view('dashboard/index', [
            'dashboardSamba' => $dashboardSamba
        ]);
    }
}
