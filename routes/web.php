<?php

use App\Controllers\AuthController;
use App\Controllers\AuditoriaController;
use App\Controllers\DashboardController;
use App\Controllers\DeployCenterController;
use App\Controllers\InfrastructureController;
use App\Controllers\SambaActionController;
use App\Controllers\SambaCompartilhamentoController;
use App\Controllers\SambaController;
use App\Controllers\SambaDiagnosticoController;
use App\Controllers\SambaLixeiraController;
use App\Controllers\SambaDashboardController;

$router->get('/', [DashboardController::class, 'index']);
$router->get('/dashboard', [DashboardController::class, 'index']);

$router->get('/login', [AuthController::class, 'loginForm']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/logout', [AuthController::class, 'logout']);

$router->get('/samba/usuarios', [SambaController::class, 'usuarios']);
$router->get('/samba/usuarios/editar', [SambaController::class, 'editarForm']);
$router->post('/samba/usuarios/editar', [SambaController::class, 'editar']);
$router->get('/samba/usuarios/senha', [SambaController::class, 'alterarSenhaForm']);
$router->post('/samba/usuarios/senha', [SambaController::class, 'alterarSenha']);
$router->get('/samba/usuarios/ativar', [SambaController::class, 'ativar']);
$router->get('/samba/usuarios/desativar', [SambaController::class, 'desativar']);
$router->get('/samba/usuarios/excluir', [SambaController::class, 'excluirForm']);
$router->post('/samba/usuarios/excluir', [SambaController::class, 'excluir']);

$router->get('/samba/compartilhamentos', [SambaCompartilhamentoController::class, 'index']);
$router->get('/samba/compartilhamentos/novo', [SambaCompartilhamentoController::class, 'novoForm']);
$router->post('/samba/compartilhamentos/novo', [SambaCompartilhamentoController::class, 'novo']);
$router->get('/samba/compartilhamentos/editar', [SambaCompartilhamentoController::class, 'editarForm']);
$router->post('/samba/compartilhamentos/editar', [SambaCompartilhamentoController::class, 'editar']);
$router->get('/samba/compartilhamentos/usuarios', [SambaCompartilhamentoController::class, 'usuariosForm']);
$router->post('/samba/compartilhamentos/usuarios', [SambaCompartilhamentoController::class, 'usuariosSalvar']);
$router->get('/samba/compartilhamentos/seguranca', [SambaCompartilhamentoController::class, 'segurancaForm']);
$router->post('/samba/compartilhamentos/seguranca', [SambaCompartilhamentoController::class, 'segurancaSalvar']);
$router->get('/samba/compartilhamentos/excluir', [SambaCompartilhamentoController::class, 'excluirForm']);
$router->post('/samba/compartilhamentos/excluir', [SambaCompartilhamentoController::class, 'excluir']);
$router->get('/samba/lixeira', [SambaLixeiraController::class, 'index']);
$router->post('/samba/lixeira/restaurar', [SambaLixeiraController::class, 'restaurar']);
$router->post('/samba/lixeira/excluir', [SambaLixeiraController::class, 'excluir']);

$router->get('/samba/diagnostico', [SambaDiagnosticoController::class, 'index']);
$router->post('/samba/actions/importar-compartilhamento', [SambaActionController::class, 'importarCompartilhamento']);
$router->post('/samba/actions/mover-pasta-lixeira', [SambaActionController::class, 'moverPastaParaLixeira']);
$router->get('/samba/dashboard', [SambaDashboardController::class, 'index']);

$router->get('/infraestrutura/servicos', [InfrastructureController::class, 'servicos']);
$router->get('/infraestrutura/servicos/reiniciar', [InfrastructureController::class, 'reiniciar']);
$router->get('/infraestrutura/servicos/recarregar', [InfrastructureController::class, 'recarregar']);
$router->get('/infraestrutura/servicos/logs', [InfrastructureController::class, 'logs']);

$router->get('/deploy', [DeployCenterController::class, 'index']);
$router->get('/deploy/samba/aplicar', [DeployCenterController::class, 'aplicarSamba']);

$router->get('/auditoria', [AuditoriaController::class, 'index']);
