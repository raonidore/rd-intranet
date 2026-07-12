<?php

use App\Controllers\AuthController;
use App\Controllers\AuditoriaController;
use App\Controllers\DashboardController;
use App\Controllers\DeployCenterController;
use App\Controllers\SambaConfiguracaoController;
use App\Controllers\InfrastructureController;
use App\Controllers\ServerController;
use App\Controllers\NetworkController;
use App\Controllers\SambaActionController;
use App\Controllers\SambaCompartilhamentoController;
use App\Controllers\SambaController;
use App\Controllers\SambaDiagnosticoController;
use App\Controllers\SambaLixeiraController;
use App\Controllers\SambaDashboardController;
use App\Controllers\SambaMonitorController;
use App\Controllers\SambaArquivosController;
use App\Controllers\UserController;
use App\Controllers\SambaGrupoController;
use App\Controllers\ApacheController;
use App\Controllers\ApacheSiteController;
use App\Controllers\ApacheModuloController;
use App\Controllers\ApacheConfiguracaoController;
use App\Controllers\HardwareController;
use App\Controllers\NetworkRouteController;
use App\Controllers\NetworkToolsController;
use App\Controllers\DbConexaoController;
use App\Controllers\DbConsoleController;
use App\Controllers\CronController;
use App\Controllers\IptablesController;
use App\Controllers\CertificadoController;
use App\Controllers\DependenciaController;
use App\Controllers\SpeedtestController;
use App\Controllers\DdnsController;
use App\Controllers\VpnController;
use App\Controllers\VpnWireguardController;
use App\Controllers\VpnOpenvpnController;
use App\Controllers\VpnOpenvpnSaidaController;
use App\Controllers\AtualizacaoController;
use App\Controllers\AntivirusController;

$router->get('/', [DashboardController::class, 'index']);
$router->get('/dashboard', [DashboardController::class, 'index']);

$router->get('/login', [AuthController::class, 'loginForm']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/logout', [AuthController::class, 'logout']);

$router->get('/samba/usuarios', [SambaController::class, 'usuarios']);
$router->get('/samba/usuarios/novo', [SambaController::class, 'novoForm']);
$router->post('/samba/usuarios/novo', [SambaController::class, 'novo']);
$router->get('/samba/grupos', [SambaGrupoController::class, 'index']);
$router->get('/samba/grupos/renomear', [SambaGrupoController::class, 'renomearForm']);
$router->post('/samba/grupos/renomear', [SambaGrupoController::class, 'renomear']);
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

$router->get('/infraestrutura/servidor', [ServerController::class, 'index']);
$router->get('/infraestrutura/servidor/api', [ServerController::class, 'api']);

$router->get('/infraestrutura/servidor/rede/editar', [NetworkController::class, 'editarForm']);
$router->post('/infraestrutura/servidor/rede/aplicar', [NetworkController::class, 'aplicar']);
$router->post('/infraestrutura/servidor/rede/confirmar', [NetworkController::class, 'confirmar']);
$router->get('/infraestrutura/servidor/rede/status', [NetworkController::class, 'status']);

$router->get('/infraestrutura/hardware', [HardwareController::class, 'index']);
$router->get('/infraestrutura/hardware/api', [HardwareController::class, 'api']);

$router->get('/infraestrutura/rede', [NetworkController::class, 'index']);
$router->get('/infraestrutura/rede/api', [NetworkController::class, 'api']);

$router->get('/infraestrutura/rede/arp', [NetworkToolsController::class, 'arp']);

$router->get('/infraestrutura/rede/ping', [NetworkToolsController::class, 'pingForm']);
$router->post('/infraestrutura/rede/ping', [NetworkToolsController::class, 'pingExecutar']);

$router->get('/infraestrutura/rede/traceroute', [NetworkToolsController::class, 'tracerouteForm']);
$router->post('/infraestrutura/rede/traceroute', [NetworkToolsController::class, 'tracerouteExecutar']);

$router->get('/infraestrutura/rede/trafego', [NetworkToolsController::class, 'trafego']);
$router->get('/infraestrutura/rede/trafego/api', [NetworkToolsController::class, 'trafegoApi']);
$router->get('/infraestrutura/rede/trafego/historico', [NetworkToolsController::class, 'historico']);

$router->get('/infraestrutura/rede/rotas', [NetworkRouteController::class, 'index']);
$router->get('/infraestrutura/rede/rotas/novo', [NetworkRouteController::class, 'novoForm']);
$router->post('/infraestrutura/rede/rotas/novo', [NetworkRouteController::class, 'novo']);
$router->post('/infraestrutura/rede/rotas/confirmar', [NetworkRouteController::class, 'confirmar']);
$router->get('/infraestrutura/rede/rotas/status', [NetworkRouteController::class, 'status']);
$router->post('/infraestrutura/rede/rotas/excluir', [NetworkRouteController::class, 'excluir']);
$router->post('/infraestrutura/rede/rotas/testar', [NetworkRouteController::class, 'testar']);

$router->get('/infraestrutura/servicos', [InfrastructureController::class, 'servicos']);
$router->get('/infraestrutura/servicos/reiniciar', [InfrastructureController::class, 'reiniciar']);
$router->get('/infraestrutura/servicos/recarregar', [InfrastructureController::class, 'recarregar']);
$router->get('/infraestrutura/servicos/logs', [InfrastructureController::class, 'logs']);
$router->get('/infraestrutura/servicos/configurar', [InfrastructureController::class, 'servicosConfigurar']);
$router->post('/infraestrutura/servicos/configurar', [InfrastructureController::class, 'servicosSalvar']);

$router->get('/infraestrutura/cron', [CronController::class, 'index']);
$router->get('/infraestrutura/cron/novo', [CronController::class, 'novoForm']);
$router->post('/infraestrutura/cron/novo', [CronController::class, 'novo']);
$router->get('/infraestrutura/cron/editar', [CronController::class, 'editarForm']);
$router->post('/infraestrutura/cron/editar', [CronController::class, 'editar']);
$router->get('/infraestrutura/cron/excluir', [CronController::class, 'excluirForm']);
$router->post('/infraestrutura/cron/excluir', [CronController::class, 'excluir']);
$router->get('/infraestrutura/cron/ativar', [CronController::class, 'ativar']);
$router->get('/infraestrutura/cron/desativar', [CronController::class, 'desativar']);
$router->post('/infraestrutura/cron/executar', [CronController::class, 'executarAgora']);
$router->get('/infraestrutura/cron/logs', [CronController::class, 'logs']);

$router->get('/infraestrutura/iptables', [IptablesController::class, 'index']);
$router->get('/infraestrutura/iptables/ao-vivo', [IptablesController::class, 'aoVivo']);
$router->get('/infraestrutura/iptables/ao-vivo/contadores', [IptablesController::class, 'contadores']);
$router->get('/infraestrutura/iptables/status', [IptablesController::class, 'status']);
$router->get('/infraestrutura/iptables/logs', [IptablesController::class, 'logsRegra']);
$router->post('/infraestrutura/iptables/confirmar', [IptablesController::class, 'confirmar']);
$router->post('/infraestrutura/iptables/reverter', [IptablesController::class, 'reverterAgora']);
$router->post('/infraestrutura/iptables/panico/ativar', [IptablesController::class, 'panicoAtivar']);
$router->post('/infraestrutura/iptables/panico/desativar', [IptablesController::class, 'panicoDesativar']);
$router->post('/infraestrutura/iptables/avaliar-risco', [IptablesController::class, 'avaliarRisco']);

$router->get('/infraestrutura/iptables/novo', [IptablesController::class, 'novoForm']);
$router->post('/infraestrutura/iptables/novo', [IptablesController::class, 'novo']);
$router->get('/infraestrutura/iptables/editar', [IptablesController::class, 'editarForm']);
$router->post('/infraestrutura/iptables/editar', [IptablesController::class, 'editar']);
$router->get('/infraestrutura/iptables/excluir', [IptablesController::class, 'excluirForm']);
$router->post('/infraestrutura/iptables/excluir', [IptablesController::class, 'excluir']);
$router->get('/infraestrutura/iptables/ativar', [IptablesController::class, 'ativar']);
$router->get('/infraestrutura/iptables/desativar', [IptablesController::class, 'desativar']);
$router->get('/infraestrutura/iptables/mover', [IptablesController::class, 'mover']);
$router->post('/infraestrutura/iptables/politica', [IptablesController::class, 'politicaSalvar']);

$router->get('/infraestrutura/iptables/templates', [IptablesController::class, 'templates']);
$router->get('/infraestrutura/iptables/templates/form', [IptablesController::class, 'templateForm']);
$router->post('/infraestrutura/iptables/templates/aplicar', [IptablesController::class, 'templateAplicar']);
$router->get('/infraestrutura/iptables/exportar', [IptablesController::class, 'exportar']);
$router->get('/infraestrutura/iptables/importar', [IptablesController::class, 'importarForm']);
$router->post('/infraestrutura/iptables/importar', [IptablesController::class, 'importar']);

$router->get('/infraestrutura/certificado', [CertificadoController::class, 'index']);
$router->get('/infraestrutura/certificado/autoassinado', [CertificadoController::class, 'autoassinadoForm']);
$router->post('/infraestrutura/certificado/autoassinado', [CertificadoController::class, 'autoassinadoGerar']);
$router->get('/infraestrutura/certificado/letsencrypt', [CertificadoController::class, 'letsencryptForm']);
$router->post('/infraestrutura/certificado/letsencrypt', [CertificadoController::class, 'letsencryptAplicar']);
$router->get('/infraestrutura/certificado/importar', [CertificadoController::class, 'importarForm']);
$router->post('/infraestrutura/certificado/importar', [CertificadoController::class, 'importarSalvar']);

$router->get('/infraestrutura/dependencias', [DependenciaController::class, 'index']);
$router->post('/infraestrutura/dependencias/instalar', [DependenciaController::class, 'instalar']);

$router->get('/infraestrutura/velocidade', [SpeedtestController::class, 'index']);
$router->post('/infraestrutura/velocidade/instalar', [SpeedtestController::class, 'instalar']);
$router->post('/infraestrutura/velocidade/testar', [SpeedtestController::class, 'testar']);
$router->post('/infraestrutura/velocidade/periodico', [SpeedtestController::class, 'ativarPeriodico']);

$router->get('/infraestrutura/ddns', [DdnsController::class, 'index']);
$router->get('/infraestrutura/ddns/novo', [DdnsController::class, 'novoForm']);
$router->post('/infraestrutura/ddns/novo', [DdnsController::class, 'novo']);
$router->get('/infraestrutura/ddns/historico', [DdnsController::class, 'historico']);
$router->get('/infraestrutura/ddns/editar', [DdnsController::class, 'editarForm']);
$router->post('/infraestrutura/ddns/editar', [DdnsController::class, 'editar']);
$router->post('/infraestrutura/ddns/excluir', [DdnsController::class, 'excluir']);
$router->get('/infraestrutura/ddns/ativar', [DdnsController::class, 'ativar']);
$router->get('/infraestrutura/ddns/desativar', [DdnsController::class, 'desativar']);
$router->post('/infraestrutura/ddns/atualizar-agora', [DdnsController::class, 'atualizarAgora']);
$router->post('/infraestrutura/ddns/atualizar-todas', [DdnsController::class, 'atualizarTodasAgora']);
$router->post('/infraestrutura/ddns/automatica', [DdnsController::class, 'ativarAtualizacaoAutomatica']);

$router->get('/vpn', [VpnController::class, 'dashboard']);
$router->get('/vpn/ikev2', [VpnController::class, 'ikev2EmBreve']);

$router->get('/vpn/openvpn/servidor', [VpnOpenvpnController::class, 'servidor']);
$router->post('/vpn/openvpn/instalar', [VpnOpenvpnController::class, 'instalar']);
$router->post('/vpn/openvpn/pki/inicializar', [VpnOpenvpnController::class, 'inicializarPki']);
$router->post('/vpn/openvpn/salvar-config', [VpnOpenvpnController::class, 'salvarConfig']);
$router->post('/vpn/openvpn/expor', [VpnOpenvpnController::class, 'exporToggle']);
$router->post('/vpn/openvpn/ativar-coleta', [VpnOpenvpnController::class, 'ativarColeta']);
$router->get('/vpn/openvpn/clientes', [VpnOpenvpnController::class, 'clientes']);
$router->post('/vpn/openvpn/clientes/novo', [VpnOpenvpnController::class, 'criarCliente']);
$router->post('/vpn/openvpn/clientes/baixar', [VpnOpenvpnController::class, 'baixarClienteNovamente']);
$router->post('/vpn/openvpn/clientes/entregue', [VpnOpenvpnController::class, 'marcarEntregue']);
$router->post('/vpn/openvpn/clientes/revogar', [VpnOpenvpnController::class, 'revogarCliente']);
$router->get('/vpn/openvpn/trafego', [VpnOpenvpnController::class, 'trafego']);

$router->get('/vpn/openvpn/saida', [VpnOpenvpnSaidaController::class, 'index']);
$router->get('/vpn/openvpn/saida/novo', [VpnOpenvpnSaidaController::class, 'novoForm']);
$router->post('/vpn/openvpn/saida/novo', [VpnOpenvpnSaidaController::class, 'novo']);
$router->post('/vpn/openvpn/saida/conectar', [VpnOpenvpnSaidaController::class, 'conectar']);
$router->post('/vpn/openvpn/saida/desconectar', [VpnOpenvpnSaidaController::class, 'desconectar']);
$router->post('/vpn/openvpn/saida/boot', [VpnOpenvpnSaidaController::class, 'alternarBoot']);
$router->post('/vpn/openvpn/saida/remover', [VpnOpenvpnSaidaController::class, 'remover']);

$router->get('/vpn/wireguard/servidor', [VpnWireguardController::class, 'servidor']);
$router->post('/vpn/wireguard/instalar', [VpnWireguardController::class, 'instalar']);
$router->post('/vpn/wireguard/salvar-config', [VpnWireguardController::class, 'salvarConfig']);
$router->post('/vpn/wireguard/expor', [VpnWireguardController::class, 'exporToggle']);
$router->post('/vpn/wireguard/ativar-coleta', [VpnWireguardController::class, 'ativarColeta']);
$router->get('/vpn/wireguard/peers', [VpnWireguardController::class, 'peers']);
$router->post('/vpn/wireguard/peers/novo', [VpnWireguardController::class, 'criarPeer']);
$router->post('/vpn/wireguard/peers/entregue', [VpnWireguardController::class, 'marcarEntregue']);
$router->post('/vpn/wireguard/peers/revogar', [VpnWireguardController::class, 'revogarPeer']);
$router->get('/vpn/wireguard/trafego', [VpnWireguardController::class, 'trafego']);

$router->get('/deploy', [DeployCenterController::class, 'index']);
$router->get('/deploy/samba/aplicar', [DeployCenterController::class, 'aplicarSamba']);
$router->post('/deploy/configuracoes', [DeployCenterController::class, 'salvarConfiguracoes']);

$router->get('/samba/configuracao', [SambaConfiguracaoController::class, 'index']);
$router->post('/samba/configuracao/salvar', [SambaConfiguracaoController::class, 'salvar']);
$router->post('/samba/configuracao/restaurar', [SambaConfiguracaoController::class, 'restaurarBackup']);

$router->get('/apache/dashboard', [ApacheController::class, 'dashboard']);

$router->get('/apache/sites', [ApacheSiteController::class, 'index']);
$router->get('/apache/sites/ver', [ApacheSiteController::class, 'ver']);
$router->post('/apache/sites/habilitar', [ApacheSiteController::class, 'habilitar']);
$router->post('/apache/sites/desabilitar', [ApacheSiteController::class, 'desabilitar']);

$router->get('/apache/modulos', [ApacheModuloController::class, 'index']);
$router->post('/apache/modulos/habilitar', [ApacheModuloController::class, 'habilitar']);
$router->post('/apache/modulos/desabilitar', [ApacheModuloController::class, 'desabilitar']);

$router->get('/apache/configuracao', [ApacheConfiguracaoController::class, 'index']);
$router->post('/apache/configuracao/salvar', [ApacheConfiguracaoController::class, 'salvar']);
$router->post('/apache/configuracao/restaurar', [ApacheConfiguracaoController::class, 'restaurarBackup']);

$router->get('/auditoria', [AuditoriaController::class, 'index']);

$router->get('/seguranca/antivirus', [AntivirusController::class, 'index']);
$router->post('/seguranca/antivirus/instalar', [AntivirusController::class, 'instalar']);
$router->post('/seguranca/antivirus/verificar-agora', [AntivirusController::class, 'verificarAgora']);
$router->post('/seguranca/antivirus/tempo-real/ativar', [AntivirusController::class, 'ativarTempoReal']);
$router->post('/seguranca/antivirus/tempo-real/desativar', [AntivirusController::class, 'desativarTempoReal']);
$router->post('/seguranca/antivirus/verificacao-periodica', [AntivirusController::class, 'verificacaoPeriodica']);
$router->post('/seguranca/antivirus/quarentena/excluir', [AntivirusController::class, 'quarentenaExcluir']);

$router->get('/administracao/usuarios', [UserController::class, 'index']);
$router->get('/administracao/usuarios/novo', [UserController::class, 'novoForm']);
$router->post('/administracao/usuarios/novo', [UserController::class, 'novo']);
$router->get('/administracao/usuarios/editar', [UserController::class, 'editarForm']);
$router->post('/administracao/usuarios/editar', [UserController::class, 'editar']);
$router->get('/administracao/usuarios/senha', [UserController::class, 'senhaForm']);
$router->post('/administracao/usuarios/senha', [UserController::class, 'senha']);
$router->get('/administracao/usuarios/ativar', [UserController::class, 'ativar']);
$router->get('/administracao/usuarios/desativar', [UserController::class, 'desativar']);
$router->get('/administracao/usuarios/excluir', [UserController::class, 'excluirForm']);
$router->post('/administracao/usuarios/excluir', [UserController::class, 'excluir']);

$router->get('/administracao/atualizacoes', [AtualizacaoController::class, 'index']);
$router->post('/administracao/atualizacoes/verificar', [AtualizacaoController::class, 'verificar']);
$router->post('/administracao/atualizacoes/aplicar', [AtualizacaoController::class, 'aplicar']);
$router->post('/administracao/atualizacoes/reverter', [AtualizacaoController::class, 'reverter']);
$router->post('/administracao/atualizacoes/checagem-diaria', [AtualizacaoController::class, 'checagemDiaria']);
$router->post('/administracao/atualizacoes/passos-manuais/confirmar', [AtualizacaoController::class, 'confirmarPassoManual']);
$router->post('/administracao/atualizacoes/passos-manuais/desconfirmar', [AtualizacaoController::class, 'desconfirmarPassoManual']);

$router->get('/banco-dados/conexoes', [DbConexaoController::class, 'index']);
$router->get('/banco-dados/conexoes/novo', [DbConexaoController::class, 'novoForm']);
$router->post('/banco-dados/conexoes/novo', [DbConexaoController::class, 'novo']);
$router->get('/banco-dados/conexoes/editar', [DbConexaoController::class, 'editarForm']);
$router->post('/banco-dados/conexoes/editar', [DbConexaoController::class, 'editar']);
$router->get('/banco-dados/conexoes/senha', [DbConexaoController::class, 'senhaForm']);
$router->post('/banco-dados/conexoes/senha', [DbConexaoController::class, 'senha']);
$router->get('/banco-dados/conexoes/ativar', [DbConexaoController::class, 'ativar']);
$router->get('/banco-dados/conexoes/desativar', [DbConexaoController::class, 'desativar']);
$router->get('/banco-dados/conexoes/excluir', [DbConexaoController::class, 'excluirForm']);
$router->post('/banco-dados/conexoes/excluir', [DbConexaoController::class, 'excluir']);
$router->post('/banco-dados/conexoes/testar', [DbConexaoController::class, 'testar']);

$router->get('/banco-dados/console', [DbConsoleController::class, 'bancos']);
$router->get('/banco-dados/console/tabelas', [DbConsoleController::class, 'tabelas']);
$router->get('/banco-dados/console/estrutura', [DbConsoleController::class, 'estrutura']);
$router->get('/banco-dados/console/estrutura/coluna/nova', [DbConsoleController::class, 'colunaNovaForm']);
$router->post('/banco-dados/console/estrutura/coluna/nova', [DbConsoleController::class, 'colunaNova']);
$router->get('/banco-dados/console/estrutura/coluna/editar', [DbConsoleController::class, 'colunaEditarForm']);
$router->post('/banco-dados/console/estrutura/coluna/editar', [DbConsoleController::class, 'colunaEditar']);
$router->post('/banco-dados/console/estrutura/coluna/remover', [DbConsoleController::class, 'colunaRemover']);
$router->post('/banco-dados/console/sql-rapido', [DbConsoleController::class, 'sqlExecutarAjax']);
$router->get('/banco-dados/console/dados', [DbConsoleController::class, 'dados']);
$router->get('/banco-dados/console/dados/inserir', [DbConsoleController::class, 'dadosInserirForm']);
$router->post('/banco-dados/console/dados/inserir', [DbConsoleController::class, 'dadosInserir']);
$router->get('/banco-dados/console/dados/editar', [DbConsoleController::class, 'dadosEditarForm']);
$router->post('/banco-dados/console/dados/editar', [DbConsoleController::class, 'dadosEditar']);
$router->post('/banco-dados/console/dados/celula', [DbConsoleController::class, 'dadosAtualizarCelula']);
$router->post('/banco-dados/console/dados/excluir', [DbConsoleController::class, 'dadosExcluir']);
$router->post('/banco-dados/console/dados/duplicar', [DbConsoleController::class, 'dadosDuplicar']);
$router->get('/banco-dados/console/dados/exportar', [DbConsoleController::class, 'dadosExportar']);
$router->get('/banco-dados/console/arvore', [DbConsoleController::class, 'arvoreTabelas']);
$router->get('/banco-dados/console/arvore/bancos', [DbConsoleController::class, 'arvoreBancos']);
$router->get('/banco-dados/console/sql', [DbConsoleController::class, 'sqlForm']);
$router->post('/banco-dados/console/sql', [DbConsoleController::class, 'sqlExecutar']);
