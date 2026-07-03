<?php

require_once __DIR__ . '/../vendor/autoload.php';

\App\Core\Application::boot();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/Helpers/url.php';

function auth_required()
{
    if (!isset($_SESSION['usuario'])) {
        header('Location: ' . url('/login'));
        exit;
    }
}

function view($arquivo, $dados = [])
{
    extract($dados);
    require __DIR__ . '/Views/' . $arquivo . '.php';
}
