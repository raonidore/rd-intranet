<?php

require_once __DIR__ . '/../vendor/autoload.php';

\App\Core\Application::boot();
\App\Core\Bootstrap\CoreBootstrap::boot();

require_once __DIR__ . '/Helpers/url.php';
require_once __DIR__ . '/Helpers/data.php';

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
