<?php

use App\Controllers\SambaController;

$router->get('/samba/usuarios', [SambaController::class, 'usuarios']);
