-- Detalhe por arquivo de cada execucao do Backup em Nuvem (Backup > Historico,
-- botao "Ver arquivos"): o que foi enviado como novo, o que foi atualizado
-- (com tamanho anterior/novo) e o que foi excluido localmente e por isso
-- saiu da pasta ativa no destino (indo pra .versoes/, nunca apagado de
-- verdade -- ver comentario em backup_executar_web.sh).
CREATE TABLE IF NOT EXISTS backup_execucao_arquivos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    execucao_id INT NOT NULL,
    compartilhamento VARCHAR(100) NOT NULL,
    caminho_relativo VARCHAR(1024) NOT NULL,
    tipo ENUM('novo', 'atualizado', 'excluido') NOT NULL,
    tamanho_anterior BIGINT NULL,
    tamanho_novo BIGINT NULL,
    KEY idx_backup_execucao_arquivos_execucao (execucao_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
