-- Wi-Fi / Captive Portal: o UniFi (portal externo) manda o aparelho pro
-- RD.Intranet, que identifica a pessoa, aplica as regras do perfil e libera
-- o acesso pela API do equipamento. Plano completo:
-- https://claude.ai/artifact/82ZzJdKxS82bdfw1wPPJjs

-- Perfis: TODAS as regras ficam aqui, configuráveis pela tela (nada fixo no código).
CREATE TABLE IF NOT EXISTS `wifi_perfis` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(40) NOT NULL,
  `nome` varchar(80) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `cor` varchar(20) NOT NULL DEFAULT '#0d6efd',
  `icone` varchar(40) NOT NULL DEFAULT 'bi-person',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  -- 'senha': entra com e-mail + senha (recebida por e-mail), 'cadastro': só se identifica (visitante)
  `tipo_acesso` enum('senha','cadastro') NOT NULL DEFAULT 'senha',
  `autocadastro` tinyint(1) NOT NULL DEFAULT 1,
  `exige_liberacao` tinyint(1) NOT NULL DEFAULT 1,
  -- [{"campo":"empresa","rotulo":"Empresa","obrigatorio":true}, ...]
  `campos` text DEFAULT NULL,
  -- um domínio por linha (ex: mauriliounimed.com.br), vazio = qualquer e-mail
  `dominios_email` text DEFAULT NULL,
  `validade_dias` int(11) DEFAULT NULL,
  `sessao_minutos` int(11) NOT NULL DEFAULT 480,
  `banda_down_kbps` int(11) DEFAULT NULL,
  `banda_up_kbps` int(11) DEFAULT NULL,
  `cota_mb` int(11) DEFAULT NULL,
  `max_aparelhos` int(11) NOT NULL DEFAULT 1,
  `lembrar_dias` int(11) NOT NULL DEFAULT 0,
  -- {"dias":[1,2,3,4,5],"inicio":"07:00","fim":"19:00"}, vazio = qualquer horário
  `horarios` text DEFAULT NULL,
  -- {"servicos":["tiktok"],"categorias":["adulto"],"bloquear":["site.com"],"liberar":[]}
  `filtro` text DEFAULT NULL,
  `ordem` int(11) NOT NULL DEFAULT 0,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wifi_perfis_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Termos de uso/privacidade versionados por perfil (o aceite aponta pra versão exata).
CREATE TABLE IF NOT EXISTS `wifi_termos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `perfil_id` int(11) NOT NULL,
  `versao` int(11) NOT NULL,
  `texto` mediumtext NOT NULL,
  `criado_por` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wifi_termos_versao` (`perfil_id`,`versao`),
  CONSTRAINT `fk_wifi_termos_perfil` FOREIGN KEY (`perfil_id`) REFERENCES `wifi_perfis` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wifi_pessoas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `perfil_id` int(11) NOT NULL,
  `nome` varchar(150) NOT NULL,
  `email` varchar(190) DEFAULT NULL,
  `telefone` varchar(30) DEFAULT NULL,
  `documento` varchar(30) DEFAULT NULL,
  `empresa` varchar(150) DEFAULT NULL,
  `cnpj` varchar(20) DEFAULT NULL,
  `setor` varchar(100) DEFAULT NULL,
  `anfitriao` varchar(150) DEFAULT NULL,
  `dados_extra` text DEFAULT NULL,
  `senha_hash` varchar(255) DEFAULT NULL,
  `trocar_senha` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('pendente','ativo','recusado','bloqueado','expirado','anonimizado') NOT NULL DEFAULT 'pendente',
  `motivo` varchar(255) DEFAULT NULL,
  `valido_ate` datetime DEFAULT NULL,
  -- exceções desta pessoa sobre as regras do perfil (mesmas chaves do perfil)
  `regras` text DEFAULT NULL,
  `origem` enum('admin','portal') NOT NULL DEFAULT 'portal',
  `liberado_por` int(11) DEFAULT NULL,
  `liberado_em` datetime DEFAULT NULL,
  `aviso_vencimento_em` datetime DEFAULT NULL,
  `ultimo_acesso` datetime DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_wifi_pessoas_email` (`email`),
  KEY `idx_wifi_pessoas_status` (`status`),
  CONSTRAINT `fk_wifi_pessoas_perfil` FOREIGN KEY (`perfil_id`) REFERENCES `wifi_perfis` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wifi_aparelhos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pessoa_id` int(11) NOT NULL,
  `mac` varchar(17) NOT NULL,
  `lembrado_ate` datetime DEFAULT NULL,
  `bloqueado` tinyint(1) NOT NULL DEFAULT 0,
  `ultimo_uso` datetime DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wifi_aparelhos` (`pessoa_id`,`mac`),
  KEY `idx_wifi_aparelhos_mac` (`mac`),
  CONSTRAINT `fk_wifi_aparelhos_pessoa` FOREIGN KEY (`pessoa_id`) REFERENCES `wifi_pessoas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wifi_aceites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pessoa_id` int(11) NOT NULL,
  `termo_id` int(11) NOT NULL,
  `versao` int(11) NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `mac` varchar(17) DEFAULT NULL,
  `aceito_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_wifi_aceites_pessoa` (`pessoa_id`),
  CONSTRAINT `fk_wifi_aceites_pessoa` FOREIGN KEY (`pessoa_id`) REFERENCES `wifi_pessoas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Registro de conexão (Marco Civil / LGPD): guardado pelo prazo configurado e depois anonimizado.
CREATE TABLE IF NOT EXISTS `wifi_sessoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pessoa_id` int(11) DEFAULT NULL,
  `perfil_id` int(11) DEFAULT NULL,
  `mac` varchar(17) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `ap_mac` varchar(17) DEFAULT NULL,
  `ssid` varchar(64) DEFAULT NULL,
  `inicio` datetime NOT NULL,
  `fim_previsto` datetime DEFAULT NULL,
  `fim` datetime DEFAULT NULL,
  `motivo_fim` varchar(60) DEFAULT NULL,
  `bytes_down` bigint(20) NOT NULL DEFAULT 0,
  `bytes_up` bigint(20) NOT NULL DEFAULT 0,
  `anonimizado` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_wifi_sessoes_mac` (`mac`),
  KEY `idx_wifi_sessoes_ativas` (`fim`),
  KEY `idx_wifi_sessoes_pessoa` (`pessoa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Links de "esqueci a senha" (guarda só o hash do token).
CREATE TABLE IF NOT EXISTS `wifi_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pessoa_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expira_em` datetime NOT NULL,
  `usado_em` datetime DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wifi_tokens_hash` (`token_hash`),
  CONSTRAINT `fk_wifi_tokens_pessoa` FOREIGN KEY (`pessoa_id`) REFERENCES `wifi_pessoas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tentativas de login (proteção contra tentativa e erro).
CREATE TABLE IF NOT EXISTS `wifi_tentativas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chave` varchar(190) NOT NULL,
  `sucesso` tinyint(1) NOT NULL DEFAULT 0,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_wifi_tentativas_chave` (`chave`,`criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Perfis iniciais (sugestão do plano -- tudo editável em Wi-Fi > Perfis e regras).
INSERT IGNORE INTO `wifi_perfis` (`slug`, `nome`, `descricao`, `cor`, `icone`, `tipo_acesso`, `autocadastro`, `exige_liberacao`, `campos`, `validade_dias`, `sessao_minutos`, `banda_down_kbps`, `banda_up_kbps`, `max_aparelhos`, `lembrar_dias`, `horarios`, `filtro`, `ordem`) VALUES
('colaboradores', 'Colaboradores', 'Quem trabalha na empresa, uso diário.', '#2563a8', 'bi-person-badge', 'senha', 1, 1,
 '[{"campo":"setor","rotulo":"Setor","obrigatorio":true},{"campo":"telefone","rotulo":"Celular","obrigatorio":false}]',
 NULL, 720, 20000, 5000, 2, 30, NULL, '{"servicos":[],"categorias":["adulto","apostas","malware"],"bloquear":[],"liberar":[]}', 1),
('fornecedores', 'Fornecedores', 'Técnicos e representantes que vêm com frequência.', '#8a5a0a', 'bi-truck', 'senha', 1, 1,
 '[{"campo":"empresa","rotulo":"Empresa","obrigatorio":true},{"campo":"cnpj","rotulo":"CNPJ","obrigatorio":false},{"campo":"telefone","rotulo":"Celular","obrigatorio":true},{"campo":"anfitriao","rotulo":"Quem vai te receber","obrigatorio":true}]',
 30, 480, 10000, 2000, 2, 30, '{"dias":[1,2,3,4,5],"inicio":"07:00","fim":"19:00"}', '{"servicos":["tiktok","netflix"],"categorias":["adulto","apostas","malware"],"bloquear":[],"liberar":[]}', 2),
('visitantes', 'Visitantes', 'Pacientes e acompanhantes.', '#6b4fa8', 'bi-people', 'cadastro', 1, 0,
 '[{"campo":"telefone","rotulo":"Celular","obrigatorio":false}]',
 1, 120, 5000, 1000, 1, 0, NULL, '{"servicos":["tiktok","netflix","youtube"],"categorias":["adulto","apostas","malware"],"bloquear":[],"liberar":[]}', 3);

INSERT IGNORE INTO `wifi_termos` (`perfil_id`, `versao`, `texto`)
SELECT p.id, 1, CONCAT(
'Ao usar esta rede Wi-Fi você concorda com o seguinte:\n\n',
'1. O acesso é pessoal e intransferível, para uso compatível com a lei e com as regras da empresa.\n',
'2. Para dar acesso e cumprir a guarda de registros de conexão (Marco Civil da Internet, Lei 12.965/2014), guardamos: seu nome e contato, o aparelho (endereço MAC), o endereço IP, o horário de início e fim de cada conexão e o volume de dados. O conteúdo das páginas acessadas não é registrado.\n',
'3. Esses registros são mantidos pelo prazo legal e depois anonimizados. Eles só são usados para dar o acesso, para segurança da rede e quando exigido por autoridade.\n',
'4. Alguns sites e serviços podem estar bloqueados conforme a política da empresa.\n',
'5. Você pode pedir a qualquer momento acesso, correção ou exclusão dos seus dados (Lei 13.709/2018 - LGPD) pelo contato informado no rodapé desta página.')
FROM `wifi_perfis` p WHERE p.slug IN ('colaboradores', 'fornecedores', 'visitantes');
