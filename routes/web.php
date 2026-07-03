<?php

use App\Controllers\DashboardController;
use App\Controllers\SambaController;

$router->get('/', [DashboardController::class, 'index']);
$router->get('/dashboard', [DashboardController::class, 'index']);
$router->get('/samba/usuarios', [SambaController::class, 'usuarios']);
