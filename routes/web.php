<?php

use App\Controllers\AuthController;
use App\Controllers\AuditoriaController;
use App\Controllers\DashboardController;
use App\Controllers\DeployCenterController;
use App\Controllers\SambaConfiguracaoController;
use App\Controllers\InfrastructureController;
use App\Controllers\SambaActionController;
use App\Controllers\SambaCompartilhamentoController;
use App\Controllers\SambaController;
use App\Controllers\SambaDiagnosticoController;
use App\Controllers\SambaLixeiraController;
use App\Controllers\SambaDashboardController;
use App\Controllers\SambaMonitorController;
use App\Controllers\SambaArquivosController;

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
$router->get('/samba/monitor', [SambaMonitorController::class, 'index']);
$router->get('/samba/monitor/api', [SambaMonitorController::class, 'api']);
$router->post('/samba/monitor/encerrar', [SambaMonitorController::class, 'encerrar']);

$router->get('/samba/arquivos', [SambaArquivosController::class, 'index']);
$router->get('/samba/arquivos/download', [SambaArquivosController::class, 'download']);
$router->get('/samba/arquivos/visualizar', [SambaArquivosController::class, 'visualizar']);
$router->get('/samba/arquivos/ler', [SambaArquivosController::class, 'ler']);
$router->post('/samba/arquivos/salvar', [SambaArquivosController::class, 'salvar']);
$router->post('/samba/arquivos/upload', [SambaArquivosController::class, 'upload']);
$router->get('/samba/arquivos/listar-dirs', [SambaArquivosController::class, 'listarDirs']);
$router->post('/samba/arquivos/copiar', [SambaArquivosController::class, 'copiar']);
$router->post('/samba/arquivos/mover', [SambaArquivosController::class, 'mover']);
$router->post('/samba/arquivos/excluir', [SambaArquivosController::class, 'excluir']);
$router->post('/samba/arquivos/renomear', [SambaArquivosController::class, 'renomear']);
$router->post('/samba/arquivos/criar', [SambaArquivosController::class, 'criarArquivo']);
$router->post('/samba/arquivos/pasta', [SambaArquivosController::class, 'criarPasta']);

$router->get('/infraestrutura/servicos', [InfrastructureController::class, 'servicos']);
$router->get('/infraestrutura/servicos/reiniciar', [InfrastructureController::class, 'reiniciar']);
$router->get('/infraestrutura/servicos/recarregar', [InfrastructureController::class, 'recarregar']);
$router->get('/infraestrutura/servicos/logs', [InfrastructureController::class, 'logs']);

$router->get('/deploy', [DeployCenterController::class, 'index']);
$router->get('/deploy/samba/aplicar', [DeployCenterController::class, 'aplicarSamba']);
$router->post('/deploy/configuracoes', [DeployCenterController::class, 'salvarConfiguracoes']);

$router->get('/samba/configuracao', [SambaConfiguracaoController::class, 'index']);
$router->post('/samba/configuracao/salvar', [SambaConfiguracaoController::class, 'salvar']);
$router->post('/samba/configuracao/restaurar', [SambaConfiguracaoController::class, 'restaurarBackup']);

$router->get('/auditoria', [AuditoriaController::class, 'index']);
