<?php

require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\Router;
use App\Controllers\SambaController;

$router = new Router();

$router->get('/samba/usuarios', [SambaController::class, 'usuarios']);

$router->dispatch();
