-- Explorador de arquivos e gerenciador de processos remoto, na ficha do
-- ativo. Duas naturezas de operacao:
--
-- 1) Leitura com resposta (listar arquivos de uma pasta, listar
--    processos rodando) -- pedido fica pendente aqui, o agente responde
--    no heartbeat seguinte (poucos segundos), resultado fica gravado em
--    JSON pra tela consultar via polling.
CREATE TABLE IF NOT EXISTS ativos_solicitacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ativo_id INT NOT NULL,
    tipo ENUM('listar_arquivos','listar_processos') NOT NULL,
    parametro VARCHAR(500) NULL,
    status ENUM('pendente','concluido','erro') NOT NULL DEFAULT 'pendente',
    resultado LONGTEXT NULL,
    erro_mensagem VARCHAR(500) NULL,
    solicitado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    respondido_em TIMESTAMP NULL DEFAULT NULL,
    KEY idx_ativos_solicitacoes_ativo (ativo_id),
    CONSTRAINT fk_ativos_solicitacoes_ativo FOREIGN KEY (ativo_id) REFERENCES ativos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2) Acao sem resposta (executar um arquivo, encerrar um processo) --
--    reaproveita o sistema de comandos ja existente (desligar/reiniciar/
--    desinstalar), mesma entrega via checkin, mesmo historico.
ALTER TABLE ativos_comandos
    MODIFY COLUMN comando ENUM(
        'desligar',
        'reiniciar',
        'desinstalar_atualizacao',
        'desinstalar_programa',
        'executar_arquivo',
        'encerrar_processo'
    ) NOT NULL;
