-- Schema base da RD Intranet, gerado a partir do banco de producao.
-- Usado apenas na instalacao de um servidor novo (scripts/install.sh):
-- cria todas as tabelas ja no estado final, sem precisar repetir o
-- historico incremental de database/migrations/ (algumas dessas
-- migrations usam ALTER TABLE, que nao e seguro reaplicar aqui).
-- Gerado em 2026-09-16 03:01:52.

-- Import nao respeita ordem de dependencia entre tabelas (algumas tem FK
-- pra tabelas que so aparecem depois neste arquivo) -- desliga a checagem
-- soh durante o import, como o proprio mysqldump faz.
SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------------------------------------------
-- antivirus_ameacas
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `antivirus_ameacas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `verificacao_id` int(11) NOT NULL,
  `caminho_original` varchar(500) NOT NULL,
  `caminho_quarentena` varchar(500) DEFAULT NULL,
  `assinatura` varchar(255) NOT NULL,
  `acao` enum('quarentena','ignorado','excluido') NOT NULL DEFAULT 'quarentena',
  `detectado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_antivirus_ameacas_verificacao` (`verificacao_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- antivirus_verificacoes
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `antivirus_verificacoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` enum('manual','agendada') NOT NULL DEFAULT 'manual',
  `caminho` varchar(500) NOT NULL,
  `status` enum('executando','concluida','erro') NOT NULL DEFAULT 'executando',
  `arquivos_verificados` int(11) NOT NULL DEFAULT 0,
  `ameacas_encontradas` int(11) NOT NULL DEFAULT 0,
  `saida` text DEFAULT NULL,
  `iniciado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `finalizado_em` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- ativos
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ativos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo_id` int(11) NOT NULL,
  `unidade_id` int(11) NOT NULL,
  `codigo_patrimonio` varchar(48) NOT NULL,
  `nome` varchar(150) NOT NULL,
  `apelido` varchar(150) DEFAULT NULL,
  `marca` varchar(100) DEFAULT NULL,
  `modelo` varchar(100) DEFAULT NULL,
  `numero_serie` varchar(100) DEFAULT NULL,
  `responsavel` varchar(150) DEFAULT NULL,
  `setor_id` int(11) DEFAULT NULL,
  `localizacao_id` int(11) DEFAULT NULL,
  `status` enum('ativo','manutencao','estoque','baixado') NOT NULL DEFAULT 'ativo',
  `ip` varchar(45) DEFAULT NULL,
  `snmp_habilitado` tinyint(1) NOT NULL DEFAULT 0,
  `snmp_community` varchar(100) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `detalhes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`detalhes`)),
  `origem` enum('manual','agente','snmp','api') NOT NULL DEFAULT 'manual',
  `agente_versao` varchar(20) DEFAULT NULL,
  `chave_api_atual` varchar(64) DEFAULT NULL,
  `elevacao_usuario` varchar(150) DEFAULT NULL,
  `elevacao_senha_cifrada` text DEFAULT NULL,
  `rede_usuario` varchar(150) DEFAULT NULL,
  `rede_senha_cifrada` text DEFAULT NULL,
  `machine_guid` varchar(64) DEFAULT NULL,
  `mesh_device_id` varchar(160) DEFAULT NULL,
  `ultimo_checkin` timestamp NULL DEFAULT NULL,
  `ultimo_heartbeat` timestamp NULL DEFAULT NULL,
  `checkin_solicitado_em` timestamp NULL DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo_patrimonio` (`codigo_patrimonio`),
  UNIQUE KEY `machine_guid` (`machine_guid`),
  KEY `idx_ativos_status` (`status`),
  KEY `fk_ativos_setor` (`setor_id`),
  KEY `fk_ativos_localizacao` (`localizacao_id`),
  KEY `fk_ativos_tipo` (`tipo_id`),
  KEY `fk_ativos_unidade` (`unidade_id`),
  CONSTRAINT `fk_ativos_localizacao` FOREIGN KEY (`localizacao_id`) REFERENCES `ativos_catalogos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ativos_setor` FOREIGN KEY (`setor_id`) REFERENCES `ativos_catalogos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ativos_tipo` FOREIGN KEY (`tipo_id`) REFERENCES `ativos_tipos` (`id`),
  CONSTRAINT `fk_ativos_unidade` FOREIGN KEY (`unidade_id`) REFERENCES `unidades` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- ativos_alertas
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ativos_alertas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ativo_id` int(11) NOT NULL,
  `nivel` enum('erro','aviso','informacao') NOT NULL,
  `origem_evento` varchar(150) DEFAULT NULL,
  `mensagem` text NOT NULL,
  `ocorrido_em` timestamp NULL DEFAULT NULL,
  `coletado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ativos_alertas_ativo` (`ativo_id`),
  CONSTRAINT `fk_ativos_alertas_ativo` FOREIGN KEY (`ativo_id`) REFERENCES `ativos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- ativos_atualizacoes_windows
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ativos_atualizacoes_windows` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ativo_id` int(11) NOT NULL,
  `kb` varchar(20) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `instalado_em` date DEFAULT NULL,
  `coletado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ativos_atualizacoes_ativo` (`ativo_id`),
  CONSTRAINT `fk_ativos_atualizacoes_ativo` FOREIGN KEY (`ativo_id`) REFERENCES `ativos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- ativos_bateria
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ativos_bateria` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ativo_id` int(11) NOT NULL,
  `nome` varchar(150) DEFAULT NULL,
  `fabricante` varchar(150) DEFAULT NULL,
  `numero_serie` varchar(100) DEFAULT NULL,
  `capacidade_projeto_mwh` int(11) DEFAULT NULL,
  `capacidade_atual_mwh` int(11) DEFAULT NULL,
  `coletado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ativos_bateria_ativo` (`ativo_id`),
  CONSTRAINT `fk_ativos_bateria_ativo` FOREIGN KEY (`ativo_id`) REFERENCES `ativos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- ativos_catalogos
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ativos_catalogos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` enum('setor','localizacao') NOT NULL,
  `nome` varchar(150) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ativos_catalogos_tipo_nome` (`tipo`,`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- ativos_chaves_api
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ativos_chaves_api` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chave` varchar(64) NOT NULL,
  `gerada_por` varchar(150) DEFAULT NULL,
  `criada_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `ativa` tinyint(1) NOT NULL DEFAULT 1,
  `notificar_agentes` tinyint(1) NOT NULL DEFAULT 1,
  `desativada_por` varchar(150) DEFAULT NULL,
  `desativada_em` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chave` (`chave`),
  KEY `idx_ativos_chaves_api_ativa` (`ativa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- ativos_comandos
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ativos_comandos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ativo_id` int(11) NOT NULL,
  `comando` enum('desligar','reiniciar','desinstalar_atualizacao','desinstalar_programa','executar_arquivo','encerrar_processo','renomear_arquivo','enviar_arquivo') NOT NULL,
  `alvo` varchar(500) DEFAULT NULL,
  `alvo_label` varchar(255) DEFAULT NULL,
  `arquivo_anexo` varchar(500) DEFAULT NULL,
  `status` enum('pendente','entregue') NOT NULL DEFAULT 'pendente',
  `solicitado_por` varchar(150) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `entregue_em` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ativos_comandos_ativo` (`ativo_id`),
  CONSTRAINT `fk_ativos_comandos_ativo` FOREIGN KEY (`ativo_id`) REFERENCES `ativos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- ativos_contadores
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ativos_contadores` (
  `tipo_id` int(11) NOT NULL,
  `unidade_id` int(11) NOT NULL,
  `ultimo_numero` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`tipo_id`,`unidade_id`),
  KEY `fk_ativos_contadores_unidade` (`unidade_id`),
  CONSTRAINT `fk_ativos_contadores_tipo` FOREIGN KEY (`tipo_id`) REFERENCES `ativos_tipos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ativos_contadores_unidade` FOREIGN KEY (`unidade_id`) REFERENCES `unidades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- ativos_controladoras
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ativos_controladoras` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ativo_id` int(11) NOT NULL,
  `nome` varchar(200) DEFAULT NULL,
  `fabricante` varchar(150) DEFAULT NULL,
  `interface` varchar(100) DEFAULT NULL,
  `classe` varchar(50) DEFAULT NULL,
  `coletado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ativos_controladoras_ativo` (`ativo_id`),
  CONSTRAINT `fk_ativos_controladoras_ativo` FOREIGN KEY (`ativo_id`) REFERENCES `ativos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- ativos_memoria
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ativos_memoria` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ativo_id` int(11) NOT NULL,
  `fabricante` varchar(100) DEFAULT NULL,
  `modelo` varchar(150) DEFAULT NULL,
  `capacidade_gb` decimal(10,1) DEFAULT NULL,
  `frequencia_mhz` int(11) DEFAULT NULL,
  `numero_serie` varchar(100) DEFAULT NULL,
  `coletado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ativos_memoria_ativo` (`ativo_id`),
  CONSTRAINT `fk_ativos_memoria_ativo` FOREIGN KEY (`ativo_id`) REFERENCES `ativos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- ativos_pacotes_software
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ativos_pacotes_software` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(150) NOT NULL,
  `arquivo_nome_original` varchar(255) NOT NULL,
  `arquivo_caminho` varchar(500) NOT NULL,
  `argumentos_silenciosos` varchar(255) DEFAULT NULL,
  `criado_por` varchar(150) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- ativos_placas_video
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ativos_placas_video` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ativo_id` int(11) NOT NULL,
  `nome` varchar(200) DEFAULT NULL,
  `vram_mb` int(11) DEFAULT NULL,
  `driver_versao` varchar(50) DEFAULT NULL,
  `processador_grafico` varchar(150) DEFAULT NULL,
  `coletado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ativos_placas_video_ativo` (`ativo_id`),
  CONSTRAINT `fk_ativos_placas_video_ativo` FOREIGN KEY (`ativo_id`) REFERENCES `ativos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- ativos_politicas_estado
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ativos_politicas_estado` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ativo_id` int(11) NOT NULL,
  `regra_id` varchar(60) NOT NULL,
  `desejado` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('pendente','aplicado','erro') NOT NULL DEFAULT 'pendente',
  `mensagem` varchar(500) DEFAULT NULL,
  `solicitacao_id` int(11) DEFAULT NULL,
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ativos_politicas_ativo_regra` (`ativo_id`,`regra_id`),
  KEY `idx_ativos_politicas_solicitacao` (`solicitacao_id`),
  CONSTRAINT `fk_ativos_politicas_ativo` FOREIGN KEY (`ativo_id`) REFERENCES `ativos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ativos_politicas_solicitacao` FOREIGN KEY (`solicitacao_id`) REFERENCES `ativos_solicitacoes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- ativos_portas
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ativos_portas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ativo_id` int(11) NOT NULL,
  `tipo` varchar(30) NOT NULL,
  `descricao` varchar(255) NOT NULL,
  `coletado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ativos_portas_ativo` (`ativo_id`),
  CONSTRAINT `fk_ativos_portas_ativo` FOREIGN KEY (`ativo_id`) REFERENCES `ativos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- ativos_portas_rede
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ativos_portas_rede` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ativo_id` int(11) NOT NULL,
  `protocolo` enum('tcp','udp') NOT NULL,
  `porta_local` int(11) NOT NULL,
  `endereco_local` varchar(45) DEFAULT NULL,
  `processo` varchar(255) DEFAULT NULL,
  `pid` int(11) DEFAULT NULL,
  `coletado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ativos_portas_rede_ativo` (`ativo_id`),
  CONSTRAINT `fk_ativos_portas_rede_ativo` FOREIGN KEY (`ativo_id`) REFERENCES `ativos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- ativos_programas
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ativos_programas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ativo_id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `versao` varchar(100) DEFAULT NULL,
  `data_instalacao` date DEFAULT NULL,
  `uninstall_string` varchar(500) DEFAULT NULL,
  `coletado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ativos_programas_ativo` (`ativo_id`),
  CONSTRAINT `fk_ativos_programas_ativo` FOREIGN KEY (`ativo_id`) REFERENCES `ativos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- ativos_rdp_credenciais
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ativos_rdp_credenciais` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ativo_id` int(11) NOT NULL,
  `host` varchar(255) NOT NULL,
  `porta` int(11) NOT NULL DEFAULT 3389,
  `usuario` varchar(150) NOT NULL,
  `senha_cifrada` text NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ativos_rdp_ativo` (`ativo_id`),
  CONSTRAINT `fk_ativos_rdp_ativo` FOREIGN KEY (`ativo_id`) REFERENCES `ativos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- ativos_redes
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ativos_redes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ativo_id` int(11) NOT NULL,
  `nome_adaptador` varchar(150) DEFAULT NULL,
  `mac` varchar(20) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `coletado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ativos_redes_ativo` (`ativo_id`),
  CONSTRAINT `fk_ativos_redes_ativo` FOREIGN KEY (`ativo_id`) REFERENCES `ativos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- ativos_setor_recursos
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ativos_setor_recursos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setor_id` int(11) NOT NULL,
  `tipo` enum('impressora','unidade_rede') NOT NULL,
  `nome_exibicao` varchar(150) NOT NULL,
  `letra_unidade` char(1) DEFAULT NULL,
  `caminho_unc` varchar(255) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ativos_setor_recursos_setor` (`setor_id`),
  CONSTRAINT `fk_ativos_setor_recursos_setor` FOREIGN KEY (`setor_id`) REFERENCES `ativos_catalogos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- ativos_solicitacoes
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ativos_solicitacoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ativo_id` int(11) NOT NULL,
  `tipo` enum('listar_arquivos','listar_processos','baixar_arquivo','executar_cmd','executar_powershell') NOT NULL,
  `parametro` text DEFAULT NULL,
  `solicitado_por` varchar(150) DEFAULT NULL,
  `elevado` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('pendente','concluido','erro') NOT NULL DEFAULT 'pendente',
  `resultado` longtext DEFAULT NULL,
  `arquivo_resultado` varchar(500) DEFAULT NULL,
  `erro_mensagem` varchar(500) DEFAULT NULL,
  `solicitado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `respondido_em` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ativos_solicitacoes_ativo` (`ativo_id`),
  CONSTRAINT `fk_ativos_solicitacoes_ativo` FOREIGN KEY (`ativo_id`) REFERENCES `ativos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- ativos_tipos
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ativos_tipos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(40) DEFAULT NULL,
  `nome` varchar(100) NOT NULL,
  `sigla` varchar(6) NOT NULL,
  `icone` varchar(40) NOT NULL DEFAULT 'bi-box-seam',
  `snmp_elegivel` tinyint(1) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ativos_tipos_nome` (`nome`),
  UNIQUE KEY `uq_ativos_tipos_sigla` (`sigla`),
  UNIQUE KEY `uq_ativos_tipos_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- ativos_volumes
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ativos_volumes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ativo_id` int(11) NOT NULL,
  `unidade` varchar(10) NOT NULL,
  `total_gb` decimal(10,1) DEFAULT NULL,
  `usado_gb` decimal(10,1) DEFAULT NULL,
  `modelo_disco` varchar(150) DEFAULT NULL,
  `fabricante_disco` varchar(100) DEFAULT NULL,
  `serial_disco` varchar(100) DEFAULT NULL,
  `tipo_disco` varchar(20) DEFAULT NULL,
  `rede` tinyint(1) NOT NULL DEFAULT 0,
  `caminho_rede` varchar(260) DEFAULT NULL,
  `coletado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ativos_volumes_ativo` (`ativo_id`),
  CONSTRAINT `fk_ativos_volumes_ativo` FOREIGN KEY (`ativo_id`) REFERENCES `ativos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- atualizacoes_log
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `atualizacoes_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` enum('aplicar','reverter') NOT NULL,
  `commit_antes` varchar(40) DEFAULT NULL,
  `commit_depois` varchar(40) DEFAULT NULL,
  `sucesso` tinyint(1) NOT NULL DEFAULT 0,
  `saida` text DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- auditoria
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `auditoria` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_nome` varchar(120) DEFAULT NULL,
  `modulo` varchar(60) NOT NULL,
  `acao` varchar(120) NOT NULL,
  `descricao` text DEFAULT NULL,
  `ip_origem` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `criado_em` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- avisos
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `avisos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(200) NOT NULL,
  `conteudo` text NOT NULL,
  `severidade` enum('informativo','atencao','urgente') NOT NULL DEFAULT 'informativo',
  `fixado` tinyint(1) NOT NULL DEFAULT 0,
  `confirmacao_obrigatoria` tinyint(1) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_por` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_avisos_fixado` (`fixado`),
  KEY `idx_avisos_ativo` (`ativo`),
  KEY `fk_avisos_criado_por` (`criado_por`),
  CONSTRAINT `fk_avisos_criado_por` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- avisos_destinatarios
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `avisos_destinatarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aviso_id` int(11) NOT NULL,
  `tipo` enum('todos','grupo','usuario') NOT NULL,
  `destinatario_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_avisos_destinatarios_aviso` (`aviso_id`),
  KEY `idx_avisos_destinatarios_lookup` (`tipo`,`destinatario_id`),
  CONSTRAINT `fk_avisos_destinatarios_aviso` FOREIGN KEY (`aviso_id`) REFERENCES `avisos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- avisos_leituras
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `avisos_leituras` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aviso_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `visto_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `confirmado_em` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_avisos_leituras` (`aviso_id`,`usuario_id`),
  KEY `idx_avisos_leituras_usuario` (`usuario_id`),
  CONSTRAINT `fk_avisos_leituras_aviso` FOREIGN KEY (`aviso_id`) REFERENCES `avisos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_avisos_leituras_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- backup_destinos
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `backup_destinos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `provider` enum('b2','s3','drive','dropbox','storj','scaleway','hetzner','akamai') NOT NULL,
  `nome` varchar(100) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 0,
  `retencao_dias` int(11) NOT NULL DEFAULT 30,
  `b2_key_id` varchar(255) DEFAULT NULL,
  `b2_application_key_cifrada` text DEFAULT NULL,
  `b2_bucket` varchar(255) DEFAULT NULL,
  `b2_prefixo` varchar(255) DEFAULT NULL,
  `s3_access_key_id` varchar(255) DEFAULT NULL,
  `s3_secret_access_key_cifrada` text DEFAULT NULL,
  `s3_bucket` varchar(255) DEFAULT NULL,
  `s3_regiao` varchar(64) DEFAULT NULL,
  `s3_endpoint` varchar(255) DEFAULT NULL,
  `s3_prefixo` varchar(255) DEFAULT NULL,
  `drive_token_cifrado` text DEFAULT NULL,
  `drive_client_id` varchar(255) DEFAULT NULL,
  `drive_client_secret_cifrada` text DEFAULT NULL,
  `drive_pasta_id` varchar(255) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `relatorio_diario_ativo` tinyint(1) NOT NULL DEFAULT 0,
  `alerta_falha_ativo` tinyint(1) NOT NULL DEFAULT 0,
  `email_notificacao` varchar(255) DEFAULT NULL,
  `dropbox_token_cifrado` text DEFAULT NULL,
  `dropbox_client_id` varchar(255) DEFAULT NULL,
  `dropbox_client_secret_cifrada` text DEFAULT NULL,
  `dropbox_prefixo` varchar(255) DEFAULT NULL,
  `storj_bucket` varchar(255) DEFAULT NULL,
  `storj_prefixo` varchar(255) DEFAULT NULL,
  `storj_access_key_id` varchar(255) DEFAULT NULL,
  `storj_secret_access_key_cifrada` text DEFAULT NULL,
  `scaleway_access_key_id` varchar(255) DEFAULT NULL,
  `scaleway_secret_access_key_cifrada` text DEFAULT NULL,
  `scaleway_bucket` varchar(255) DEFAULT NULL,
  `scaleway_regiao` varchar(32) DEFAULT NULL,
  `scaleway_prefixo` varchar(255) DEFAULT NULL,
  `hetzner_access_key_id` varchar(255) DEFAULT NULL,
  `hetzner_secret_access_key_cifrada` text DEFAULT NULL,
  `hetzner_bucket` varchar(255) DEFAULT NULL,
  `hetzner_regiao` varchar(32) DEFAULT NULL,
  `hetzner_prefixo` varchar(255) DEFAULT NULL,
  `akamai_access_key_id` varchar(255) DEFAULT NULL,
  `akamai_secret_access_key_cifrada` text DEFAULT NULL,
  `akamai_bucket` varchar(255) DEFAULT NULL,
  `akamai_regiao` varchar(32) DEFAULT NULL,
  `akamai_prefixo` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- backup_execucao_arquivos
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `backup_execucao_arquivos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `execucao_id` int(11) NOT NULL,
  `compartilhamento` varchar(100) NOT NULL,
  `caminho_relativo` varchar(1024) NOT NULL,
  `tipo` enum('novo','atualizado','excluido') NOT NULL,
  `timestamp_versao` varchar(20) DEFAULT NULL,
  `tamanho_anterior` bigint(20) DEFAULT NULL,
  `tamanho_novo` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_backup_execucao_arquivos_execucao` (`execucao_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- backup_execucoes
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `backup_execucoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `destino_id` int(11) NOT NULL,
  `tipo` enum('manual','agendada') NOT NULL DEFAULT 'manual',
  `status` enum('executando','concluida','erro') NOT NULL DEFAULT 'executando',
  `arquivos_enviados` int(11) NOT NULL DEFAULT 0,
  `bytes_enviados` bigint(20) NOT NULL DEFAULT 0,
  `versoes_criadas` int(11) NOT NULL DEFAULT 0,
  `mensagem_erro` text DEFAULT NULL,
  `iniciado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `finalizado_em` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_backup_execucoes_destino` (`destino_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- base_conhecimento
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `base_conhecimento` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) NOT NULL,
  `problema` text DEFAULT NULL,
  `solucao` text NOT NULL,
  `visibilidade` enum('privado','publico') NOT NULL DEFAULT 'privado',
  `categoria_id` int(11) DEFAULT NULL,
  `subcategoria_id` int(11) DEFAULT NULL,
  `central_id` int(11) DEFAULT NULL,
  `status_central` enum('nao_enviado','proposto','aprovado','rejeitado') NOT NULL DEFAULT 'nao_enviado',
  `usuario_id` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_bc_visibilidade` (`visibilidade`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- base_conhecimento_categorias
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `base_conhecimento_categorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bc_categoria_nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- base_conhecimento_imagens
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `base_conhecimento_imagens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `artigo_id` int(11) NOT NULL,
  `arquivo` varchar(255) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_bc_imagem_artigo` (`artigo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- base_conhecimento_publica
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `base_conhecimento_publica` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `central_id` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `categoria` varchar(100) DEFAULT NULL,
  `problema` text DEFAULT NULL,
  `solucao` text NOT NULL,
  `sincronizado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bc_publica_central_id` (`central_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- base_conhecimento_subcategorias
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `base_conhecimento_subcategorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `categoria_id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bc_subcategoria_nome` (`categoria_id`,`nome`),
  KEY `idx_bc_subcategoria_categoria` (`categoria_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- chamados
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `chamados` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numero_controle` varchar(20) DEFAULT NULL,
  `titulo` varchar(200) NOT NULL,
  `descricao` text NOT NULL,
  `categoria_id` int(11) NOT NULL,
  `setor_id` int(11) DEFAULT NULL,
  `unidade_id` int(11) NOT NULL,
  `ativo_id` int(11) DEFAULT NULL,
  `solicitante_id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_abertura_id` int(11) DEFAULT NULL,
  `prioridade` enum('baixa','media','alta','urgente') NOT NULL DEFAULT 'media',
  `status` enum('fila','em_atendimento','aguardando_cliente','resolvido','fechado') NOT NULL DEFAULT 'fila',
  `canal_abertura` enum('painel','email','whatsapp','portal','sistema') NOT NULL DEFAULT 'painel',
  `aguardando_resposta` tinyint(1) NOT NULL DEFAULT 0,
  `sla_resposta_prazo` datetime DEFAULT NULL,
  `sla_resolucao_prazo` datetime DEFAULT NULL,
  `sla_pausado_em` datetime DEFAULT NULL,
  `primeira_resposta_em` datetime DEFAULT NULL,
  `atribuido_em` datetime DEFAULT NULL,
  `resolvido_em` datetime DEFAULT NULL,
  `fechado_em` datetime DEFAULT NULL,
  `ultima_mensagem_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `aberto_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chamados_numero_controle` (`numero_controle`),
  KEY `idx_chamados_status` (`status`),
  KEY `idx_chamados_setor` (`setor_id`),
  KEY `idx_chamados_usuario` (`usuario_id`),
  KEY `idx_chamados_categoria` (`categoria_id`),
  KEY `idx_chamados_unidade` (`unidade_id`),
  KEY `idx_chamados_ativo` (`ativo_id`),
  KEY `idx_chamados_solicitante` (`solicitante_id`),
  KEY `idx_chamados_usuario_abertura` (`usuario_abertura_id`),
  CONSTRAINT `fk_chamados_ativo` FOREIGN KEY (`ativo_id`) REFERENCES `ativos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_chamados_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `chamados_categorias` (`id`),
  CONSTRAINT `fk_chamados_setor` FOREIGN KEY (`setor_id`) REFERENCES `chamados_setores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_chamados_solicitante` FOREIGN KEY (`solicitante_id`) REFERENCES `chamados_solicitantes` (`id`),
  CONSTRAINT `fk_chamados_unidade` FOREIGN KEY (`unidade_id`) REFERENCES `unidades` (`id`),
  CONSTRAINT `fk_chamados_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_chamados_usuario_abertura` FOREIGN KEY (`usuario_abertura_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- chamados_anexos
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `chamados_anexos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chamado_id` int(11) NOT NULL,
  `comentario_id` int(11) DEFAULT NULL,
  `caminho_arquivo` varchar(255) NOT NULL,
  `nome_original` varchar(255) NOT NULL,
  `tipo_mime` varchar(100) DEFAULT NULL,
  `tamanho_bytes` int(11) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_chamados_anexos_chamado` (`chamado_id`),
  KEY `fk_chamados_anexos_comentario` (`comentario_id`),
  KEY `fk_chamados_anexos_usuario` (`usuario_id`),
  CONSTRAINT `fk_chamados_anexos_chamado` FOREIGN KEY (`chamado_id`) REFERENCES `chamados` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chamados_anexos_comentario` FOREIGN KEY (`comentario_id`) REFERENCES `chamados_comentarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chamados_anexos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- chamados_avaliacoes
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `chamados_avaliacoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chamado_id` int(11) NOT NULL,
  `solicitante_id` int(11) NOT NULL,
  `pergunta_estado` enum('aguardando_nota','aguardando_resolvido') DEFAULT NULL,
  `nota` int(11) DEFAULT NULL,
  `resolvido` tinyint(1) DEFAULT NULL,
  `comentario` text DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chamados_avaliacoes_chamado` (`chamado_id`),
  KEY `fk_chamados_avaliacoes_solicitante` (`solicitante_id`),
  CONSTRAINT `fk_chamados_avaliacoes_chamado` FOREIGN KEY (`chamado_id`) REFERENCES `chamados` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chamados_avaliacoes_solicitante` FOREIGN KEY (`solicitante_id`) REFERENCES `chamados_solicitantes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- chamados_categorias
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `chamados_categorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `setor_padrao_id` int(11) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chamados_categoria_nome` (`nome`),
  KEY `idx_chamados_categorias_setor` (`setor_padrao_id`),
  CONSTRAINT `fk_chamados_categorias_setor` FOREIGN KEY (`setor_padrao_id`) REFERENCES `chamados_setores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- chamados_comentarios
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `chamados_comentarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chamado_id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `tipo` enum('interna','publica') NOT NULL DEFAULT 'publica',
  `conteudo` text NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_chamados_comentarios_chamado` (`chamado_id`),
  KEY `fk_chamados_comentarios_usuario` (`usuario_id`),
  CONSTRAINT `fk_chamados_comentarios_chamado` FOREIGN KEY (`chamado_id`) REFERENCES `chamados` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chamados_comentarios_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- chamados_externos
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `chamados_externos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numero_controle` varchar(20) DEFAULT NULL,
  `titulo` varchar(200) NOT NULL,
  `descricao` text DEFAULT NULL,
  `fornecedor_id` int(11) NOT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `ativo_id` int(11) DEFAULT NULL,
  `protocolo_fornecedor` varchar(100) DEFAULT NULL,
  `status` enum('aberto','aguardando_fornecedor','em_andamento','resolvido','fechado') NOT NULL DEFAULT 'aberto',
  `prioridade` enum('baixa','media','alta','urgente') NOT NULL DEFAULT 'media',
  `criado_por` int(11) DEFAULT NULL,
  `aberto_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolvido_em` timestamp NULL DEFAULT NULL,
  `fechado_em` timestamp NULL DEFAULT NULL,
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chamados_externos_numero_controle` (`numero_controle`),
  KEY `idx_chamados_externos_fornecedor` (`fornecedor_id`),
  KEY `idx_chamados_externos_categoria` (`categoria_id`),
  KEY `idx_chamados_externos_ativo` (`ativo_id`),
  KEY `idx_chamados_externos_status` (`status`),
  KEY `fk_chamados_externos_criado_por` (`criado_por`),
  CONSTRAINT `fk_chamados_externos_ativo` FOREIGN KEY (`ativo_id`) REFERENCES `ativos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_chamados_externos_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `chamados_externos_categorias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_chamados_externos_criado_por` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_chamados_externos_fornecedor` FOREIGN KEY (`fornecedor_id`) REFERENCES `fornecedores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- chamados_externos_anexos
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `chamados_externos_anexos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chamado_externo_id` int(11) NOT NULL,
  `comentario_id` int(11) DEFAULT NULL,
  `anexo_origem` enum('upload','samba') NOT NULL,
  `anexo_caminho` varchar(500) NOT NULL,
  `anexo_nome_original` varchar(255) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_chamados_externos_anexos_chamado` (`chamado_externo_id`),
  KEY `fk_chamados_externos_anexos_comentario` (`comentario_id`),
  KEY `fk_chamados_externos_anexos_usuario` (`usuario_id`),
  CONSTRAINT `fk_chamados_externos_anexos_chamado` FOREIGN KEY (`chamado_externo_id`) REFERENCES `chamados_externos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chamados_externos_anexos_comentario` FOREIGN KEY (`comentario_id`) REFERENCES `chamados_externos_comentarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chamados_externos_anexos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- chamados_externos_categorias
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `chamados_externos_categorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chamados_externos_categoria_nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- chamados_externos_comentarios
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `chamados_externos_comentarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chamado_externo_id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `tipo` enum('nota','sistema') NOT NULL DEFAULT 'nota',
  `conteudo` text NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_chamados_externos_comentarios_chamado` (`chamado_externo_id`),
  KEY `fk_chamados_externos_comentarios_usuario` (`usuario_id`),
  CONSTRAINT `fk_chamados_externos_comentarios_chamado` FOREIGN KEY (`chamado_externo_id`) REFERENCES `chamados_externos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chamados_externos_comentarios_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- chamados_historico
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `chamados_historico` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chamado_id` int(11) NOT NULL,
  `campo` varchar(40) NOT NULL,
  `valor_anterior` varchar(150) DEFAULT NULL,
  `valor_novo` varchar(150) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_chamados_historico_chamado` (`chamado_id`),
  KEY `fk_chamados_historico_usuario` (`usuario_id`),
  CONSTRAINT `fk_chamados_historico_chamado` FOREIGN KEY (`chamado_id`) REFERENCES `chamados` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chamados_historico_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- chamados_setor_usuarios
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `chamados_setor_usuarios` (
  `setor_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`setor_id`,`usuario_id`),
  KEY `idx_chamados_setor_usuarios_usuario` (`usuario_id`),
  CONSTRAINT `fk_chamados_setor_usuarios_setor` FOREIGN KEY (`setor_id`) REFERENCES `chamados_setores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chamados_setor_usuarios_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- chamados_setores
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `chamados_setores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chamados_setor_nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- chamados_slas
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `chamados_slas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `categoria_id` int(11) NOT NULL,
  `prioridade` enum('baixa','media','alta','urgente') NOT NULL,
  `tempo_primeira_resposta_min` int(11) NOT NULL,
  `tempo_resolucao_min` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chamados_slas_categoria_prioridade` (`categoria_id`,`prioridade`),
  CONSTRAINT `fk_chamados_slas_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `chamados_categorias` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- chamados_solicitante_tokens
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `chamados_solicitante_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `solicitante_id` int(11) NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `expira_em` datetime NOT NULL,
  `usado_em` datetime DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chamados_solicitante_tokens_hash` (`token_hash`),
  KEY `idx_chamados_solicitante_tokens_solicitante` (`solicitante_id`),
  CONSTRAINT `fk_chamados_solicitante_tokens_solicitante` FOREIGN KEY (`solicitante_id`) REFERENCES `chamados_solicitantes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- chamados_solicitantes
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `chamados_solicitantes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `telefone` varchar(30) DEFAULT NULL,
  `unidade_id` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_chamados_solicitantes_email` (`email`),
  KEY `idx_chamados_solicitantes_telefone` (`telefone`),
  KEY `fk_chamados_solicitantes_unidade` (`unidade_id`),
  CONSTRAINT `fk_chamados_solicitantes_unidade` FOREIGN KEY (`unidade_id`) REFERENCES `unidades` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- chat_conversas
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `chat_conversas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` enum('direta','grupo') NOT NULL DEFAULT 'direta',
  `nome` varchar(150) DEFAULT NULL,
  `criado_por` int(11) DEFAULT NULL,
  `ultima_mensagem_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_chat_conversas_criador` (`criado_por`),
  CONSTRAINT `fk_chat_conversas_criador` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- chat_mencoes
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `chat_mencoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mensagem_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_chat_mencoes_usuario` (`usuario_id`),
  KEY `fk_chat_mencoes_mensagem` (`mensagem_id`),
  CONSTRAINT `fk_chat_mencoes_mensagem` FOREIGN KEY (`mensagem_id`) REFERENCES `chat_mensagens` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chat_mencoes_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- chat_mensagens
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `chat_mensagens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conversa_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `tipo` enum('texto','imagem','audio','documento') NOT NULL DEFAULT 'texto',
  `midia_path` varchar(255) DEFAULT NULL,
  `conteudo` text NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_chat_mensagens_conversa` (`conversa_id`,`id`),
  KEY `fk_chat_mensagens_usuario` (`usuario_id`),
  CONSTRAINT `fk_chat_mensagens_conversa` FOREIGN KEY (`conversa_id`) REFERENCES `chat_conversas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chat_mensagens_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- chat_participantes
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `chat_participantes` (
  `conversa_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `ultima_leitura_em` timestamp NULL DEFAULT NULL,
  `entrou_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`conversa_id`,`usuario_id`),
  KEY `idx_chat_participantes_usuario` (`usuario_id`),
  CONSTRAINT `fk_chat_participantes_conversa` FOREIGN KEY (`conversa_id`) REFERENCES `chat_conversas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chat_participantes_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- chat_reacoes
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `chat_reacoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mensagem_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `emoji` varchar(16) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chat_reacao` (`mensagem_id`,`usuario_id`,`emoji`),
  KEY `idx_chat_reacoes_usuario` (`usuario_id`),
  CONSTRAINT `fk_chat_reacoes_mensagem` FOREIGN KEY (`mensagem_id`) REFERENCES `chat_mensagens` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chat_reacoes_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- chat_socket_tokens
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `chat_socket_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `expira_em` datetime NOT NULL,
  `usado_em` datetime DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chat_socket_tokens_hash` (`token_hash`),
  KEY `idx_chat_socket_tokens_usuario` (`usuario_id`),
  CONSTRAINT `fk_chat_socket_tokens_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- config_backups
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `config_backups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` enum('manual','agendado') NOT NULL DEFAULT 'manual',
  `status` enum('executando','concluido','erro') NOT NULL DEFAULT 'executando',
  `arquivo` varchar(255) DEFAULT NULL,
  `tamanho_bytes` bigint(20) NOT NULL DEFAULT 0,
  `enviado_nuvem` tinyint(1) NOT NULL DEFAULT 0,
  `mensagem_erro` text DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `iniciado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `finalizado_em` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_config_backups_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- configuracao_deploy
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `configuracao_deploy` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `modulo` varchar(50) NOT NULL,
  `alteracoes_pendentes` tinyint(1) DEFAULT 0,
  `ultimo_deploy` datetime DEFAULT NULL,
  `ultimo_backup` varchar(255) DEFAULT NULL,
  `ultimo_usuario` varchar(100) DEFAULT NULL,
  `criado_em` timestamp NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_configuracao_deploy_modulo` (`modulo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- configuracoes
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `configuracoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chave` varchar(100) NOT NULL,
  `valor` text DEFAULT NULL,
  `atualizado_em` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `chave` (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- contratos
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contratos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fornecedor_id` int(11) NOT NULL,
  `numero` varchar(100) DEFAULT NULL,
  `descricao` text DEFAULT NULL,
  `data_inicio` date DEFAULT NULL,
  `data_termino` date DEFAULT NULL,
  `valor` decimal(12,2) DEFAULT NULL,
  `anexo_origem` enum('upload','samba') DEFAULT NULL,
  `anexo_caminho` varchar(500) DEFAULT NULL,
  `anexo_nome_original` varchar(255) DEFAULT NULL,
  `criado_por` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_contratos_fornecedor` (`fornecedor_id`),
  KEY `fk_contratos_criado_por` (`criado_por`),
  CONSTRAINT `fk_contratos_criado_por` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_contratos_fornecedor` FOREIGN KEY (`fornecedor_id`) REFERENCES `fornecedores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- cron_jobs
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cron_jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(120) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `expressao` varchar(60) NOT NULL,
  `usuario_execucao` varchar(60) NOT NULL DEFAULT 'root',
  `comando` text NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `ultima_execucao_em` timestamp NULL DEFAULT NULL,
  `ultima_execucao_sucesso` tinyint(1) DEFAULT NULL,
  `ultima_execucao_saida` text DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- db_conexoes
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `db_conexoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(120) NOT NULL,
  `host` varchar(255) NOT NULL,
  `porta` int(11) NOT NULL DEFAULT 3306,
  `usuario` varchar(120) NOT NULL,
  `senha_cifrada` text NOT NULL,
  `banco_padrao` varchar(120) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- ddns_contas
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ddns_contas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `provedor` enum('noip','dyndns','cloudflare','duckdns','freedns') NOT NULL,
  `apelido` varchar(100) NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `credenciais` text NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `ultimo_ip` varchar(45) DEFAULT NULL,
  `ultima_verificacao_em` timestamp NULL DEFAULT NULL,
  `ultima_atualizacao_em` timestamp NULL DEFAULT NULL,
  `ultimo_sucesso` tinyint(1) DEFAULT NULL,
  `ultima_mensagem` varchar(500) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- ddns_historico
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ddns_historico` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conta_id` int(11) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `sucesso` tinyint(1) NOT NULL,
  `mensagem` varchar(500) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ddns_historico_conta` (`conta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- deploy_pendencias
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `deploy_pendencias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `modulo` varchar(50) NOT NULL,
  `tipo` varchar(80) NOT NULL,
  `referencia` varchar(120) DEFAULT NULL,
  `descricao` text NOT NULL,
  `usuario` varchar(100) DEFAULT NULL,
  `criado_em` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- documentos
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `documentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `categoria_id` int(11) NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `descricao` text DEFAULT NULL,
  `anexo_origem` enum('upload','samba') DEFAULT NULL,
  `anexo_caminho` varchar(500) DEFAULT NULL,
  `anexo_nome_original` varchar(255) DEFAULT NULL,
  `versao` int(11) NOT NULL DEFAULT 1,
  `criado_por` int(11) DEFAULT NULL,
  `atualizado_por` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_documentos_categoria` (`categoria_id`),
  KEY `fk_documentos_criado_por` (`criado_por`),
  KEY `fk_documentos_atualizado_por` (`atualizado_por`),
  CONSTRAINT `fk_documentos_atualizado_por` FOREIGN KEY (`atualizado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_documentos_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `documentos_categorias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_documentos_criado_por` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- documentos_categorias
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `documentos_categorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(150) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_documentos_categoria_nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- documentos_permissoes
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `documentos_permissoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `categoria_id` int(11) NOT NULL,
  `sujeito_tipo` enum('usuario','grupo') NOT NULL,
  `sujeito_id` int(11) NOT NULL,
  `pode_visualizar` tinyint(1) NOT NULL DEFAULT 1,
  `pode_editar` tinyint(1) NOT NULL DEFAULT 0,
  `pode_excluir` tinyint(1) NOT NULL DEFAULT 0,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_documentos_permissao` (`categoria_id`,`sujeito_tipo`,`sujeito_id`),
  KEY `idx_documentos_permissoes_categoria` (`categoria_id`),
  CONSTRAINT `fk_documentos_permissoes_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `documentos_categorias` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- documentos_versoes
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `documentos_versoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `documento_id` int(11) NOT NULL,
  `versao` int(11) NOT NULL,
  `anexo_origem` enum('upload','samba') DEFAULT NULL,
  `anexo_caminho` varchar(500) DEFAULT NULL,
  `anexo_nome_original` varchar(255) DEFAULT NULL,
  `substituido_por` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_documentos_versoes_documento` (`documento_id`),
  KEY `fk_documentos_versoes_usuario` (`substituido_por`),
  CONSTRAINT `fk_documentos_versoes_documento` FOREIGN KEY (`documento_id`) REFERENCES `documentos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_documentos_versoes_usuario` FOREIGN KEY (`substituido_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- fornecedor_tipos_servico
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fornecedor_tipos_servico` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fornecedor_tipo_servico_nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- fornecedores
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fornecedores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `razao_social` varchar(200) NOT NULL,
  `nome_fantasia` varchar(150) NOT NULL,
  `cnpj_cpf` varchar(20) DEFAULT NULL,
  `inscricao_estadual` varchar(30) DEFAULT NULL,
  `inscricao_estadual_isento` tinyint(1) NOT NULL DEFAULT 0,
  `inscricao_municipal` varchar(30) DEFAULT NULL,
  `porte` enum('ME','EPP','Demais') DEFAULT NULL,
  `cep` varchar(10) DEFAULT NULL,
  `logradouro` varchar(200) DEFAULT NULL,
  `numero` varchar(20) DEFAULT NULL,
  `complemento` varchar(100) DEFAULT NULL,
  `bairro` varchar(100) DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `uf` char(2) DEFAULT NULL,
  `pais` varchar(60) NOT NULL DEFAULT 'Brasil',
  `tipo_servico_id` int(11) DEFAULT NULL,
  `contato_nome` varchar(150) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `telefone` varchar(30) DEFAULT NULL,
  `site` varchar(255) DEFAULT NULL,
  `canal_abertura_chamado` text DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fornecedor_cnpj_cpf` (`cnpj_cpf`),
  KEY `idx_fornecedores_tipo_servico` (`tipo_servico_id`),
  CONSTRAINT `fk_fornecedores_tipo_servico` FOREIGN KEY (`tipo_servico_id`) REFERENCES `fornecedor_tipos_servico` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- grupo_modulos
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `grupo_modulos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `grupo_id` int(11) NOT NULL,
  `modulo` varchar(60) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_grupo_modulo` (`grupo_id`,`modulo`),
  CONSTRAINT `fk_grupo_modulos_grupo` FOREIGN KEY (`grupo_id`) REFERENCES `grupos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- grupo_usuarios
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `grupo_usuarios` (
  `grupo_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`grupo_id`,`usuario_id`),
  KEY `idx_grupo_usuarios_usuario` (`usuario_id`),
  CONSTRAINT `fk_grupo_usuarios_grupo` FOREIGN KEY (`grupo_id`) REFERENCES `grupos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_grupo_usuarios_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- grupos
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `grupos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_grupo_nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- intelbras_dvr_credenciais
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `intelbras_dvr_credenciais` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) NOT NULL,
  `usuario` varchar(100) NOT NULL,
  `senha_cifrada` text NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_intelbras_dvr_credenciais_ip` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- ip_scanner_execucoes
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ip_scanner_execucoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cidr` varchar(255) NOT NULL,
  `executado_em` datetime NOT NULL,
  `executado_por` int(11) DEFAULT NULL,
  `total_hosts` int(11) NOT NULL DEFAULT 0,
  `hosts` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`hosts`)),
  PRIMARY KEY (`id`),
  KEY `fk_ip_scanner_execucoes_usuario` (`executado_por`),
  KEY `idx_ip_scanner_execucoes_cidr` (`cidr`,`executado_em`),
  CONSTRAINT `fk_ip_scanner_execucoes_usuario` FOREIGN KEY (`executado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- iptables_log_eventos
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `iptables_log_eventos` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `regra_id` int(11) NOT NULL,
  `ip_origem` varchar(45) DEFAULT NULL,
  `ip_destino` varchar(45) DEFAULT NULL,
  `protocolo` varchar(10) DEFAULT NULL,
  `porta_destino` varchar(10) DEFAULT NULL,
  `ocorrido_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_iptables_log_eventos_ip` (`ip_origem`,`ocorrido_em`),
  KEY `idx_iptables_log_eventos_ocorrido` (`ocorrido_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- iptables_regras
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `iptables_regras` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(150) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `tabela` enum('filter','nat') NOT NULL DEFAULT 'filter',
  `cadeia` enum('INPUT','OUTPUT','FORWARD','PREROUTING','POSTROUTING') NOT NULL DEFAULT 'INPUT',
  `acao` enum('ACCEPT','DROP','REJECT','MASQUERADE','DNAT','SNAT','LOG','NONE') NOT NULL DEFAULT 'ACCEPT',
  `protocolo` enum('tcp','udp','icmp','all') NOT NULL DEFAULT 'tcp',
  `porta_destino` varchar(20) DEFAULT NULL,
  `porta_origem` varchar(20) DEFAULT NULL,
  `ip_origem` varchar(64) DEFAULT NULL,
  `ip_destino` varchar(64) DEFAULT NULL,
  `interface_entrada` varchar(30) DEFAULT NULL,
  `interface_saida` varchar(30) DEFAULT NULL,
  `nat_destino` varchar(64) DEFAULT NULL,
  `extra` varchar(255) DEFAULT NULL,
  `registrar_log` tinyint(1) NOT NULL DEFAULT 0,
  `ordem` int(11) NOT NULL DEFAULT 100,
  `origem_template` varchar(60) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_iptables_tabela_cadeia_ordem` (`tabela`,`cadeia`,`ordem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- iptables_regras_historico
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `iptables_regras_historico` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `regra_id` int(11) NOT NULL,
  `pkts` bigint(20) unsigned NOT NULL DEFAULT 0,
  `bytes` bigint(20) unsigned NOT NULL DEFAULT 0,
  `coletado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_iptables_historico_regra_coletado` (`regra_id`,`coletado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- mapas_rede
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mapas_rede` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(150) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `dados` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`dados`)),
  `criado_em` datetime NOT NULL,
  `atualizado_em` datetime NOT NULL,
  `criado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_mapas_rede_usuario` (`criado_por`),
  CONSTRAINT `fk_mapas_rede_usuario` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- migrations_aplicadas
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `migrations_aplicadas` (
  `arquivo` varchar(180) NOT NULL,
  `aplicado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`arquivo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- passos_manuais_confirmacoes
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `passos_manuais_confirmacoes` (
  `chave` varchar(80) NOT NULL,
  `confirmado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `confirmado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- projetos
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `projetos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `area_id` int(11) NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `descricao` text DEFAULT NULL,
  `cliente` varchar(150) DEFAULT NULL,
  `status` enum('planejamento','em_andamento','pausado','concluido','cancelado') NOT NULL DEFAULT 'planejamento',
  `prioridade` enum('baixa','media','alta','urgente') NOT NULL DEFAULT 'media',
  `usa_fases` tinyint(1) NOT NULL DEFAULT 1,
  `data_inicio` date DEFAULT NULL,
  `data_fim_prevista` date DEFAULT NULL,
  `criado_por` int(11) DEFAULT NULL,
  `aberto_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `excluido_em` datetime DEFAULT NULL,
  `excluido_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_projetos_area` (`area_id`),
  KEY `idx_projetos_status` (`status`),
  KEY `fk_projetos_criado_por` (`criado_por`),
  KEY `idx_projetos_excluido_em` (`excluido_em`),
  KEY `fk_projetos_excluido_por` (`excluido_por`),
  CONSTRAINT `fk_projetos_area` FOREIGN KEY (`area_id`) REFERENCES `projetos_areas` (`id`),
  CONSTRAINT `fk_projetos_criado_por` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_projetos_excluido_por` FOREIGN KEY (`excluido_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- projetos_anexos
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `projetos_anexos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `projeto_id` int(11) NOT NULL,
  `tarefa_id` int(11) DEFAULT NULL,
  `comentario_id` int(11) DEFAULT NULL,
  `anexo_origem` enum('upload','samba') NOT NULL,
  `anexo_caminho` varchar(500) NOT NULL,
  `anexo_nome_original` varchar(255) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `participante_externo_id` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_projetos_anexos_projeto` (`projeto_id`),
  KEY `idx_projetos_anexos_tarefa` (`tarefa_id`),
  KEY `fk_projetos_anexos_comentario` (`comentario_id`),
  KEY `fk_projetos_anexos_usuario` (`usuario_id`),
  KEY `fk_projetos_anexos_participante` (`participante_externo_id`),
  CONSTRAINT `fk_projetos_anexos_comentario` FOREIGN KEY (`comentario_id`) REFERENCES `projetos_comentarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_projetos_anexos_participante` FOREIGN KEY (`participante_externo_id`) REFERENCES `projetos_participantes_externos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_projetos_anexos_projeto` FOREIGN KEY (`projeto_id`) REFERENCES `projetos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_projetos_anexos_tarefa` FOREIGN KEY (`tarefa_id`) REFERENCES `projetos_tarefas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_projetos_anexos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- projetos_areas
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `projetos_areas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_projetos_areas_nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- projetos_areas_gestores
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `projetos_areas_gestores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `area_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_projetos_areas_gestores` (`area_id`,`usuario_id`),
  KEY `idx_projetos_areas_gestores_usuario` (`usuario_id`),
  CONSTRAINT `fk_projetos_areas_gestores_area` FOREIGN KEY (`area_id`) REFERENCES `projetos_areas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_projetos_areas_gestores_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- projetos_comentarios
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `projetos_comentarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `projeto_id` int(11) NOT NULL,
  `tarefa_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `participante_externo_id` int(11) DEFAULT NULL,
  `tipo` enum('nota','sistema') NOT NULL DEFAULT 'nota',
  `conteudo` text NOT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_projetos_comentarios_projeto` (`projeto_id`),
  KEY `idx_projetos_comentarios_tarefa` (`tarefa_id`),
  KEY `fk_projetos_comentarios_usuario` (`usuario_id`),
  KEY `fk_projetos_comentarios_participante` (`participante_externo_id`),
  CONSTRAINT `fk_projetos_comentarios_participante` FOREIGN KEY (`participante_externo_id`) REFERENCES `projetos_participantes_externos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_projetos_comentarios_projeto` FOREIGN KEY (`projeto_id`) REFERENCES `projetos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_projetos_comentarios_tarefa` FOREIGN KEY (`tarefa_id`) REFERENCES `projetos_tarefas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_projetos_comentarios_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- projetos_fases
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `projetos_fases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `projeto_id` int(11) NOT NULL,
  `nome` varchar(150) NOT NULL,
  `ordem` int(11) NOT NULL DEFAULT 0,
  `data_inicio` date DEFAULT NULL,
  `data_fim_prevista` date DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_projetos_fases_projeto` (`projeto_id`),
  CONSTRAINT `fk_projetos_fases_projeto` FOREIGN KEY (`projeto_id`) REFERENCES `projetos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- projetos_paineis_tv
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `projetos_paineis_tv` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `area_id` int(11) NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `criado_por` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `revogado_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_projetos_paineis_tv_hash` (`token_hash`),
  KEY `idx_projetos_paineis_tv_area` (`area_id`),
  KEY `fk_projetos_paineis_tv_criado_por` (`criado_por`),
  CONSTRAINT `fk_projetos_paineis_tv_area` FOREIGN KEY (`area_id`) REFERENCES `projetos_areas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_projetos_paineis_tv_criado_por` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- projetos_participante_tokens
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `projetos_participante_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `participante_externo_id` int(11) NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `expira_em` datetime NOT NULL,
  `usado_em` datetime DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_projetos_participante_tokens_hash` (`token_hash`),
  KEY `idx_projetos_participante_tokens_participante` (`participante_externo_id`),
  CONSTRAINT `fk_projetos_participante_tokens_participante` FOREIGN KEY (`participante_externo_id`) REFERENCES `projetos_participantes_externos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- projetos_participantes_externos
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `projetos_participantes_externos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `telefone` varchar(30) DEFAULT NULL,
  `empresa` varchar(150) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_projetos_participantes_externos_email` (`email`),
  KEY `idx_projetos_participantes_externos_telefone` (`telefone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- projetos_tarefas
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `projetos_tarefas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `projeto_id` int(11) NOT NULL,
  `fase_id` int(11) DEFAULT NULL,
  `titulo` varchar(200) NOT NULL,
  `descricao` text DEFAULT NULL,
  `tag` varchar(60) DEFAULT NULL,
  `cor` varchar(20) DEFAULT NULL,
  `data_inicio` date DEFAULT NULL,
  `coluna` enum('a_fazer','em_andamento','aguardando_terceiro','concluido') NOT NULL DEFAULT 'a_fazer',
  `posicao` int(11) NOT NULL DEFAULT 0,
  `prazo` date DEFAULT NULL,
  `concluida_em` timestamp NULL DEFAULT NULL,
  `criado_por` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_projetos_tarefas_projeto` (`projeto_id`),
  KEY `idx_projetos_tarefas_fase` (`fase_id`),
  KEY `idx_projetos_tarefas_coluna` (`coluna`),
  KEY `fk_projetos_tarefas_criado_por` (`criado_por`),
  CONSTRAINT `fk_projetos_tarefas_criado_por` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_projetos_tarefas_fase` FOREIGN KEY (`fase_id`) REFERENCES `projetos_fases` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_projetos_tarefas_projeto` FOREIGN KEY (`projeto_id`) REFERENCES `projetos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- projetos_tarefas_externos
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `projetos_tarefas_externos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tarefa_id` int(11) NOT NULL,
  `participante_externo_id` int(11) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_projetos_tarefas_externos` (`tarefa_id`,`participante_externo_id`),
  KEY `idx_projetos_tarefas_externos_participante` (`participante_externo_id`),
  CONSTRAINT `fk_projetos_tarefas_externos_participante` FOREIGN KEY (`participante_externo_id`) REFERENCES `projetos_participantes_externos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_projetos_tarefas_externos_tarefa` FOREIGN KEY (`tarefa_id`) REFERENCES `projetos_tarefas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- projetos_tarefas_responsaveis
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `projetos_tarefas_responsaveis` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tarefa_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_projetos_tarefas_responsaveis` (`tarefa_id`,`usuario_id`),
  KEY `idx_projetos_tarefas_responsaveis_usuario` (`usuario_id`),
  CONSTRAINT `fk_projetos_tarefas_responsaveis_tarefa` FOREIGN KEY (`tarefa_id`) REFERENCES `projetos_tarefas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_projetos_tarefas_responsaveis_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- rede_trafego_historico
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rede_trafego_historico` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `interface` varchar(50) NOT NULL,
  `rx_bytes` bigint(20) unsigned NOT NULL DEFAULT 0,
  `tx_bytes` bigint(20) unsigned NOT NULL DEFAULT 0,
  `rx_packets` bigint(20) unsigned NOT NULL DEFAULT 0,
  `tx_packets` bigint(20) unsigned NOT NULL DEFAULT 0,
  `coletado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_rede_trafego_interface_coletado` (`interface`,`coletado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- redefinicao_senha_tokens
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `redefinicao_senha_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `expira_em` timestamp NOT NULL,
  `usado_em` timestamp NULL DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `fk_redefinicao_senha_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- samba_compartilhamento_portal_usuarios
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `samba_compartilhamento_portal_usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `compartilhamento_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_samba_portal_usuario` (`compartilhamento_id`,`usuario_id`),
  KEY `idx_samba_portal_usuario_usuario` (`usuario_id`),
  CONSTRAINT `fk_samba_portal_usuarios_compartilhamento` FOREIGN KEY (`compartilhamento_id`) REFERENCES `samba_compartilhamentos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_samba_portal_usuarios_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- samba_compartilhamento_usuarios
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `samba_compartilhamento_usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `compartilhamento_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `leitura` tinyint(1) DEFAULT 1,
  `escrita` tinyint(1) DEFAULT 1,
  `criado_em` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_share_usuario` (`compartilhamento_id`,`usuario_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `samba_compartilhamento_usuarios_ibfk_1` FOREIGN KEY (`compartilhamento_id`) REFERENCES `samba_compartilhamentos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `samba_compartilhamento_usuarios_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `samba_usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- samba_compartilhamentos
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `samba_compartilhamentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(80) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `caminho` varchar(255) NOT NULL,
  `grupo` varchar(80) NOT NULL,
  `somente_leitura` tinyint(1) NOT NULL DEFAULT 0,
  `lixeira` tinyint(1) NOT NULL DEFAULT 1,
  `bloqueio_extensoes` tinyint(1) NOT NULL DEFAULT 1,
  `backup_nuvem_ativo` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('ativo','desativado') NOT NULL DEFAULT 'ativo',
  `criado_em` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- samba_usuarios
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `samba_usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(120) NOT NULL,
  `login` varchar(60) NOT NULL,
  `departamento` varchar(80) NOT NULL,
  `ssh` tinyint(1) NOT NULL DEFAULT 0,
  `uid_linux` int(11) DEFAULT NULL,
  `status` enum('ativo','desativado') NOT NULL DEFAULT 'ativo',
  `criado_em` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `login` (`login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- seguranca_auditorias
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `seguranca_auditorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` enum('local','credenciais_padrao','forca_bruta_ssh') NOT NULL,
  `alvo` varchar(255) DEFAULT NULL,
  `resultado` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`resultado`)),
  `executado_em` datetime NOT NULL,
  `executado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_seguranca_auditorias_usuario` (`executado_por`),
  KEY `idx_seguranca_auditorias_tipo` (`tipo`,`executado_em`),
  CONSTRAINT `fk_seguranca_auditorias_usuario` FOREIGN KEY (`executado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- speedtest_historico
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `speedtest_historico` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `status` enum('concluido','erro') NOT NULL,
  `download_mbps` decimal(10,2) DEFAULT NULL,
  `upload_mbps` decimal(10,2) DEFAULT NULL,
  `ping_ms` decimal(10,2) DEFAULT NULL,
  `jitter_ms` decimal(10,2) DEFAULT NULL,
  `servidor` varchar(255) DEFAULT NULL,
  `isp` varchar(255) DEFAULT NULL,
  `mensagem_erro` varchar(500) DEFAULT NULL,
  `executado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- ssh_conexoes
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ssh_conexoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(150) NOT NULL,
  `host` varchar(255) NOT NULL,
  `porta` int(11) NOT NULL DEFAULT 22,
  `usuario` varchar(100) NOT NULL,
  `tipo_autenticacao` enum('senha','chave_privada','perguntar') NOT NULL DEFAULT 'senha',
  `senha_cifrada` text DEFAULT NULL,
  `chave_privada_cifrada` text DEFAULT NULL,
  `chave_privada_senha_cifrada` text DEFAULT NULL,
  `observacoes` varchar(255) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- unidades
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `unidades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(150) NOT NULL,
  `sigla` varchar(6) NOT NULL,
  `padrao` tinyint(1) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_unidades_sigla` (`sigla`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- usuario_modulos
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `usuario_modulos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `modulo` varchar(60) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuario_modulo` (`usuario_id`,`modulo`),
  CONSTRAINT `fk_usuario_modulos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- usuarios
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(120) NOT NULL,
  `login` varchar(60) NOT NULL,
  `email` varchar(190) DEFAULT NULL,
  `senha_hash` varchar(255) NOT NULL,
  `perfil` enum('admin','ti','consulta') NOT NULL DEFAULT 'ti',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NULL DEFAULT current_timestamp(),
  `ultimo_acesso` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `login` (`login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- vpn_ikev2_clientes
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vpn_ikev2_clientes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(64) NOT NULL,
  `senha` text NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `config_entregue` tinyint(1) NOT NULL DEFAULT 0,
  `config_entregue_em` timestamp NULL DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `revogado_em` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- vpn_ikev2_conexoes_saida
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vpn_ikev2_conexoes_saida` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(64) NOT NULL,
  `servidor_remoto` varchar(255) NOT NULL,
  `tipo_auth` enum('psk','eap') NOT NULL DEFAULT 'psk',
  `segredo` text NOT NULL,
  `usuario_eap` varchar(100) DEFAULT NULL,
  `subnet_remota` varchar(30) NOT NULL DEFAULT '0.0.0.0/0',
  `ca_remota` text DEFAULT NULL,
  `ativo_no_boot` tinyint(1) NOT NULL DEFAULT 0,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- vpn_ikev2_config
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vpn_ikev2_config` (
  `id` int(11) NOT NULL DEFAULT 1,
  `subnet_cidr` varchar(30) NOT NULL DEFAULT '10.10.0.0/24',
  `dns_push` varchar(100) DEFAULT NULL,
  `endpoint_publico` varchar(255) DEFAULT NULL,
  `pki_inicializada` tinyint(1) NOT NULL DEFAULT 0,
  `instalado` tinyint(1) NOT NULL DEFAULT 0,
  `exposto_internet` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- vpn_ikev2_trafego_historico
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vpn_ikev2_trafego_historico` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` int(11) NOT NULL,
  `rx_bytes` bigint(20) NOT NULL,
  `tx_bytes` bigint(20) NOT NULL,
  `coletado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vpn_ikev2_trafego_cliente` (`cliente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- vpn_openvpn_clientes
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vpn_openvpn_clientes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(64) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `config_entregue` tinyint(1) NOT NULL DEFAULT 0,
  `config_entregue_em` timestamp NULL DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `revogado_em` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- vpn_openvpn_conexoes_saida
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vpn_openvpn_conexoes_saida` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(64) NOT NULL,
  `arquivo_ovpn` text NOT NULL,
  `ativo_no_boot` tinyint(1) NOT NULL DEFAULT 0,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- vpn_openvpn_config
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vpn_openvpn_config` (
  `id` int(11) NOT NULL DEFAULT 1,
  `porta` int(11) NOT NULL DEFAULT 1194,
  `protocolo` enum('udp','tcp') NOT NULL DEFAULT 'udp',
  `subnet_cidr` varchar(30) NOT NULL DEFAULT '10.9.0.0/24',
  `dns_push` varchar(100) DEFAULT NULL,
  `endpoint_publico` varchar(255) DEFAULT NULL,
  `redirect_gateway` tinyint(1) NOT NULL DEFAULT 0,
  `pki_inicializada` tinyint(1) NOT NULL DEFAULT 0,
  `instalado` tinyint(1) NOT NULL DEFAULT 0,
  `exposto_internet` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- vpn_openvpn_trafego_historico
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vpn_openvpn_trafego_historico` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` int(11) NOT NULL,
  `rx_bytes` bigint(20) NOT NULL,
  `tx_bytes` bigint(20) NOT NULL,
  `conectado_desde` timestamp NULL DEFAULT NULL,
  `coletado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vpn_ovpn_trafego_cliente` (`cliente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- vpn_wireguard_conexoes_saida
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vpn_wireguard_conexoes_saida` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(15) NOT NULL,
  `arquivo_conf` text NOT NULL,
  `ativo_no_boot` tinyint(1) NOT NULL DEFAULT 0,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- vpn_wireguard_config
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vpn_wireguard_config` (
  `id` int(11) NOT NULL DEFAULT 1,
  `interface_nome` varchar(20) NOT NULL DEFAULT 'wg0',
  `porta` int(11) NOT NULL DEFAULT 51820,
  `subnet_cidr` varchar(30) NOT NULL DEFAULT '10.8.0.0/24',
  `servidor_ip_interno` varchar(45) NOT NULL DEFAULT '10.8.0.1',
  `chave_privada` text DEFAULT NULL,
  `chave_publica` text DEFAULT NULL,
  `dns_push` varchar(100) DEFAULT NULL,
  `endpoint_publico` varchar(255) DEFAULT NULL,
  `mtu` int(11) DEFAULT NULL,
  `exposto_internet` tinyint(1) NOT NULL DEFAULT 0,
  `instalado` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- vpn_wireguard_peers
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vpn_wireguard_peers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `chave_publica` varchar(64) NOT NULL,
  `ip_atribuido` varchar(45) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `config_entregue` tinyint(1) NOT NULL DEFAULT 0,
  `config_entregue_em` timestamp NULL DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `revogado_em` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chave_publica` (`chave_publica`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- vpn_wireguard_trafego_historico
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vpn_wireguard_trafego_historico` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `peer_id` int(11) NOT NULL,
  `rx_bytes` bigint(20) NOT NULL,
  `tx_bytes` bigint(20) NOT NULL,
  `ultimo_handshake` timestamp NULL DEFAULT NULL,
  `coletado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vpn_wg_trafego_peer` (`peer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- whatsapp_atendimentos
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `whatsapp_atendimentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contato_id` int(11) NOT NULL,
  `conexao_id` int(11) DEFAULT NULL,
  `setor_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `no_bot_atual_id` int(11) DEFAULT NULL,
  `chamado_id` int(11) DEFAULT NULL,
  `tentativas_invalidas_bot` int(11) NOT NULL DEFAULT 0,
  `status` enum('bot','fila','em_atendimento','aguardando_nps_atendente','aguardando_nps_resolucao','encerrado') NOT NULL DEFAULT 'bot',
  `aguardando_resposta` tinyint(1) NOT NULL DEFAULT 0,
  `aberto_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atribuido_em` timestamp NULL DEFAULT NULL,
  `encerrado_em` timestamp NULL DEFAULT NULL,
  `ultima_mensagem_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_whatsapp_atendimentos_contato` (`contato_id`),
  KEY `idx_whatsapp_atendimentos_status` (`status`),
  KEY `idx_whatsapp_atendimentos_setor` (`setor_id`),
  KEY `idx_whatsapp_atendimentos_usuario` (`usuario_id`),
  KEY `fk_whatsapp_atendimentos_no_bot` (`no_bot_atual_id`),
  KEY `fk_whatsapp_atendimentos_chamado` (`chamado_id`),
  KEY `fk_whatsapp_atendimentos_conexao` (`conexao_id`),
  KEY `idx_whatsapp_atendimentos_contato_conexao_status` (`contato_id`,`conexao_id`,`status`),
  CONSTRAINT `fk_whatsapp_atendimentos_chamado` FOREIGN KEY (`chamado_id`) REFERENCES `chamados` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_whatsapp_atendimentos_conexao` FOREIGN KEY (`conexao_id`) REFERENCES `whatsapp_conexoes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_whatsapp_atendimentos_contato` FOREIGN KEY (`contato_id`) REFERENCES `whatsapp_contatos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_whatsapp_atendimentos_no_bot` FOREIGN KEY (`no_bot_atual_id`) REFERENCES `whatsapp_chatbot_nos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_whatsapp_atendimentos_setor` FOREIGN KEY (`setor_id`) REFERENCES `whatsapp_setores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_whatsapp_atendimentos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- whatsapp_chatbot_nos
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `whatsapp_chatbot_nos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `no_pai_id` int(11) DEFAULT NULL,
  `ordem` int(11) NOT NULL DEFAULT 0,
  `rotulo` varchar(150) NOT NULL,
  `mensagem` text NOT NULL,
  `tipo` enum('menu','resposta_final','encaminhar_setor','abrir_chamado') NOT NULL DEFAULT 'menu',
  `setor_destino_id` int(11) DEFAULT NULL,
  `categoria_chamado_id` int(11) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_whatsapp_chatbot_nos_pai` (`no_pai_id`),
  KEY `fk_whatsapp_chatbot_nos_setor` (`setor_destino_id`),
  KEY `fk_whatsapp_chatbot_nos_categoria_chamado` (`categoria_chamado_id`),
  CONSTRAINT `fk_whatsapp_chatbot_nos_categoria_chamado` FOREIGN KEY (`categoria_chamado_id`) REFERENCES `chamados_categorias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_whatsapp_chatbot_nos_pai` FOREIGN KEY (`no_pai_id`) REFERENCES `whatsapp_chatbot_nos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_whatsapp_chatbot_nos_setor` FOREIGN KEY (`setor_destino_id`) REFERENCES `whatsapp_setores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- whatsapp_conexao_setores
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `whatsapp_conexao_setores` (
  `conexao_id` int(11) NOT NULL,
  `setor_id` int(11) NOT NULL,
  PRIMARY KEY (`conexao_id`,`setor_id`),
  KEY `fk_wpp_conexao_setores_setor` (`setor_id`),
  CONSTRAINT `fk_wpp_conexao_setores_conexao` FOREIGN KEY (`conexao_id`) REFERENCES `whatsapp_conexoes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wpp_conexao_setores_setor` FOREIGN KEY (`setor_id`) REFERENCES `whatsapp_setores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- whatsapp_conexoes
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `whatsapp_conexoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `porta` int(11) NOT NULL,
  `api_key_cifrada` text DEFAULT NULL,
  `diretorio_instalacao` varchar(255) NOT NULL,
  `usuario_sistema` varchar(100) NOT NULL,
  `unit_systemd` varchar(150) NOT NULL,
  `instalado` tinyint(1) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `padrao` tinyint(1) NOT NULL DEFAULT 0,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_whatsapp_conexoes_porta` (`porta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- whatsapp_contatos
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `whatsapp_contatos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numero` varchar(20) NOT NULL,
  `nome` varchar(150) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_whatsapp_contato_numero` (`numero`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- whatsapp_mensagens
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `whatsapp_mensagens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `atendimento_id` int(11) NOT NULL,
  `direcao` enum('entrada','saida') NOT NULL,
  `tipo` enum('texto','imagem','audio','documento','video','outro') NOT NULL DEFAULT 'texto',
  `contexto` enum('atendimento','nps') NOT NULL DEFAULT 'atendimento',
  `conteudo` text DEFAULT NULL,
  `midia_path` varchar(255) DEFAULT NULL,
  `origem` enum('cliente','usuario','bot') NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `whatsapp_message_id` varchar(100) DEFAULT NULL,
  `status_entrega` enum('enviado','entregue','lido','falhou') DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_whatsapp_mensagens_wamid` (`whatsapp_message_id`),
  KEY `idx_whatsapp_mensagens_atendimento` (`atendimento_id`),
  KEY `fk_whatsapp_mensagens_usuario` (`usuario_id`),
  CONSTRAINT `fk_whatsapp_mensagens_atendimento` FOREIGN KEY (`atendimento_id`) REFERENCES `whatsapp_atendimentos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_whatsapp_mensagens_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- whatsapp_mensagens_rapidas
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `whatsapp_mensagens_rapidas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `comando` varchar(50) NOT NULL,
  `mensagem` text NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_whatsapp_mensagens_rapidas_comando` (`comando`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- whatsapp_nps_respostas
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `whatsapp_nps_respostas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `atendimento_id` int(11) NOT NULL,
  `contato_id` int(11) NOT NULL,
  `setor_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `nota_atendente` tinyint(4) DEFAULT NULL,
  `resolvido` tinyint(1) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_whatsapp_nps_setor` (`setor_id`),
  KEY `idx_whatsapp_nps_atendimento` (`atendimento_id`),
  KEY `fk_whatsapp_nps_contato` (`contato_id`),
  KEY `fk_whatsapp_nps_usuario` (`usuario_id`),
  CONSTRAINT `fk_whatsapp_nps_atendimento` FOREIGN KEY (`atendimento_id`) REFERENCES `whatsapp_atendimentos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_whatsapp_nps_contato` FOREIGN KEY (`contato_id`) REFERENCES `whatsapp_contatos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_whatsapp_nps_setor` FOREIGN KEY (`setor_id`) REFERENCES `whatsapp_setores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_whatsapp_nps_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- whatsapp_permissao_encerrados
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `whatsapp_permissao_encerrados` (
  `usuario_id` int(11) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`usuario_id`),
  CONSTRAINT `fk_whatsapp_permissao_encerrados_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- whatsapp_permissao_nps
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `whatsapp_permissao_nps` (
  `usuario_id` int(11) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`usuario_id`),
  CONSTRAINT `fk_whatsapp_permissao_nps_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- whatsapp_setor_usuarios
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `whatsapp_setor_usuarios` (
  `setor_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `supervisor` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`setor_id`,`usuario_id`),
  KEY `idx_whatsapp_setor_usuarios_usuario` (`usuario_id`),
  CONSTRAINT `fk_whatsapp_setor_usuarios_setor` FOREIGN KEY (`setor_id`) REFERENCES `whatsapp_setores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_whatsapp_setor_usuarios_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- whatsapp_setores
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `whatsapp_setores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `nps_ativo` tinyint(1) NOT NULL DEFAULT 0,
  `visivel_equipe` tinyint(1) NOT NULL DEFAULT 0,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_whatsapp_setor_nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
