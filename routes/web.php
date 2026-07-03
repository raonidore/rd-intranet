<?php

use App\Controllers\AuthController;
use App\Controllers\AuditoriaController;
use App\Controllers\DashboardController;
use App\Controllers\SambaController;

/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
*/

$router->get('/', [DashboardController::class, 'index']);
$router->get('/dashboard', [DashboardController::class, 'index']);

/*
|--------------------------------------------------------------------------
| Autenticação
|--------------------------------------------------------------------------
*/

$router->get('/login', [AuthController::class, 'loginForm']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/logout', [AuthController::class, 'logout']);

/*
|--------------------------------------------------------------------------
| Samba
|--------------------------------------------------------------------------
*/

// Lista
$router->get('/samba/usuarios', [SambaController::class, 'usuarios']);

// Novo usuário (mantemos a tela antiga por enquanto)
$router->get('/samba/usuarios/novo', [SambaController::class, 'novoForm']);
$router->post('/samba/usuarios/novo', [SambaController::class, 'novo']);

// Editar
$router->get('/samba/usuarios/editar', [SambaController::class, 'editarForm']);
$router->post('/samba/usuarios/editar', [SambaController::class, 'editar']);

// Senha
$router->get('/samba/usuarios/senha', [SambaController::class, 'alterarSenhaForm']);
$router->post('/samba/usuarios/senha', [SambaController::class, 'alterarSenha']);

// Ativar (implementaremos em seguida)
$router->get('/samba/usuarios/ativar', [SambaController::class, 'ativar']);

// Desativar
$router->get('/samba/usuarios/desativar', [SambaController::class, 'desativar']);

// Excluir
$router->get('/samba/usuarios/excluir', [SambaController::class, 'excluirForm']);
$router->post('/samba/usuarios/excluir', [SambaController::class, 'excluir']);

/*
|--------------------------------------------------------------------------
| Auditoria
|--------------------------------------------------------------------------
*/

$router->get('/auditoria', [AuditoriaController::class, 'index']);
