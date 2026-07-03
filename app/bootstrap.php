<?php
session_start();

require_once __DIR__ . '/../config/db.php';

function auth_required()
{
    if (!isset($_SESSION['usuario'])) {
        header('Location: /rd.intranet/index.php');
        exit;
    }
}

function view($arquivo, $dados = [])
{
    extract($dados);
    require __DIR__ . '/Views/' . $arquivo . '.php';
}
