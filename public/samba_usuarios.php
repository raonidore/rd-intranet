<?php

require_once __DIR__ . '/../app/bootstrap.php';

use App\Models\SambaUsuario;

auth_required();

$usuarios = SambaUsuario::listar();

$total = SambaUsuario::contarTotal();
$ativos = SambaUsuario::contarAtivos();
$sshTotal = SambaUsuario::contarComSsh();

view('samba/usuarios', compact('usuarios', 'total', 'ativos', 'sshTotal'));
