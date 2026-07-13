-- Dados de hardware mais ricos, coletados pelo agente Windows a cada
-- checkin: rede (MAC/IP por adaptador), volumes logicos (uso por
-- unidade, diferente do "Armazenamento" resumido que ja existia -- esse
-- e o disco fisico, isso aqui e a particao/unidade), portas fisicas
-- (USB conectado + seriais). Todas seguem o mesmo padrao de
-- "substituir a cada checkin" (snapshot atual, nao historico) ja usado
-- em ativos_programas.
CREATE TABLE IF NOT EXISTS ativos_redes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ativo_id INT NOT NULL,
    nome_adaptador VARCHAR(150) NULL,
    mac VARCHAR(20) NULL,
    ip VARCHAR(45) NULL,
    coletado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ativos_redes_ativo (ativo_id),
    CONSTRAINT fk_ativos_redes_ativo FOREIGN KEY (ativo_id) REFERENCES ativos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ativos_volumes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ativo_id INT NOT NULL,
    unidade VARCHAR(10) NOT NULL,
    total_gb DECIMAL(10,1) NULL,
    usado_gb DECIMAL(10,1) NULL,
    coletado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ativos_volumes_ativo (ativo_id),
    CONSTRAINT fk_ativos_volumes_ativo FOREIGN KEY (ativo_id) REFERENCES ativos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dados do disco fisico associado a cada volume logico (nem sempre o
-- Windows preenche fabricante/serial de forma confiavel via WMI --
-- ficam NULL quando o driver do disco nao informa). Fica aqui (não em
-- 2026_07_13_ativos_memoria_disco_fisico.sql, apesar do nome) porque
-- precisa da tabela ativos_volumes já criada acima -- em servidor novo
-- as migrations rodam em ordem alfabética do arquivo, não cronológica,
-- e "memoria_disco_fisico" ordena antes de "rede_volumes_portas".
ALTER TABLE ativos_volumes
    ADD COLUMN modelo_disco VARCHAR(150) NULL AFTER usado_gb,
    ADD COLUMN fabricante_disco VARCHAR(100) NULL AFTER modelo_disco,
    ADD COLUMN serial_disco VARCHAR(100) NULL AFTER fabricante_disco;

-- tipo: 'usb' (dispositivo USB atualmente conectado) ou 'serial' (porta
-- COM disponivel). Portas de video (HDMI/DP/VGA) ficam de fora --
-- o Windows nao expoe isso via WMI de forma padronizada entre
-- fabricantes, entao nao da pra coletar de forma confiavel.
CREATE TABLE IF NOT EXISTS ativos_portas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ativo_id INT NOT NULL,
    tipo VARCHAR(30) NOT NULL,
    descricao VARCHAR(255) NOT NULL,
    coletado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ativos_portas_ativo (ativo_id),
    CONSTRAINT fk_ativos_portas_ativo FOREIGN KEY (ativo_id) REFERENCES ativos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE ativos_programas
    ADD COLUMN data_instalacao DATE NULL AFTER versao;
