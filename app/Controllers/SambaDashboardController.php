<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\SambaInventoryService;

class SambaDashboardController extends Controller
{
    public function index(): void
    {
        AuthMiddleware::check();

        $inventory = (new SambaInventoryService())->snapshot();

        $this->view('samba/dashboard', [
            'inventory' => $inventory
        ]);
    }
}
