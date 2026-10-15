-- Programas confiáveis em TODAS as máquinas do cliente (Central de Segurança >
-- Exceções). Caminho exato ou com * (ex.: C:\Program Files (x86)\ShiftApp\*):
-- o portal descarta os alertas de processo/inicialização/conexão desses
-- programas e o agente (1.0.66+) recebe a lista no heartbeat e para de acusar.
-- Origem: ShiftApp (Java do ERP) acusado em 8 máquinas do Enzilab Prime.
CREATE TABLE IF NOT EXISTS `seguranca_confiaveis` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `padrao` varchar(500) NOT NULL,
  `descricao` varchar(300) NOT NULL,
  `criado_por` varchar(150) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
