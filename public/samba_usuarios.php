<?php

require_once __DIR__ . '/../app/bootstrap.php';

use App\Controllers\SambaController;

auth_required();

$controller = new SambaController();
$controller->usuarios();
