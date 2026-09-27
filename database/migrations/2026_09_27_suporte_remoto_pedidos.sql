-- Pedidos de ajuda feitos pelo botão "Pedir ajuda" do agente Windows.
-- Aparecem em Chamados > Suporte Remoto até alguém marcar como atendido.
CREATE TABLE IF NOT EXISTS suporte_remoto_pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ativo_id INT NOT NULL,
    usuario VARCHAR(150) NULL DEFAULT NULL,
    mensagem VARCHAR(500) NULL DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'aberto',
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atendido_em TIMESTAMP NULL DEFAULT NULL,
    atendido_por VARCHAR(100) NULL DEFAULT NULL,
    KEY idx_suporte_pedidos_status (status, criado_em),
    CONSTRAINT fk_suporte_pedidos_ativo FOREIGN KEY (ativo_id) REFERENCES ativos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
