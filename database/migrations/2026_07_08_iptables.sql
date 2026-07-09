-- Firewall (iptables) gerenciado via web (Infraestrutura > Firewall).
-- Cada linha ativa vira uma regra real no ruleset (filter/nat), regenerado
-- por completo a cada criacao/edicao/exclusao/toggle/template/politica (ver
-- IptablesService::regenerarRuleset()). Toda aplicacao passa por
-- backup + auto-rollback em N segundos se nao for confirmada -- mesmo
-- padrao ja usado pra configuracao de rede (NetworkConfigService).
CREATE TABLE IF NOT EXISTS iptables_regras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    descricao VARCHAR(255) NULL,
    tabela ENUM('filter','nat') NOT NULL DEFAULT 'filter',
    cadeia ENUM('INPUT','OUTPUT','FORWARD','PREROUTING','POSTROUTING') NOT NULL DEFAULT 'INPUT',
    acao ENUM('ACCEPT','DROP','REJECT','MASQUERADE','DNAT','SNAT','LOG','NONE') NOT NULL DEFAULT 'ACCEPT',
    protocolo ENUM('tcp','udp','icmp','all') NOT NULL DEFAULT 'tcp',
    porta_destino VARCHAR(20) NULL,
    porta_origem VARCHAR(20) NULL,
    ip_origem VARCHAR(64) NULL,
    ip_destino VARCHAR(64) NULL,
    interface_entrada VARCHAR(30) NULL,
    interface_saida VARCHAR(30) NULL,
    nat_destino VARCHAR(64) NULL,
    extra VARCHAR(255) NULL,
    ordem INT NOT NULL DEFAULT 100,
    origem_template VARCHAR(60) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_iptables_tabela_cadeia_ordem (tabela, cadeia, ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Modulo infra_iptables, liberado automaticamente pra admins.
INSERT IGNORE INTO usuario_modulos (usuario_id, modulo)
SELECT u.id, 'infra_iptables'
FROM usuarios u
WHERE u.perfil = 'admin';
