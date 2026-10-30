-- Exceção ao bloqueio de extensões perigosas numa pasta de um compartilhamento.
--
-- O "veto files" do Samba vale pro compartilhamento inteiro e compara só o
-- nome do arquivo (não aceita caminho), então não há como liberar uma
-- extensão só numa subpasta do mesmo compartilhamento. A exceção vira um
-- segundo compartilhamento (nome_compartilhamento) apontando pra pasta, com
-- as mesmas permissões do pai e as extensões em `extensoes` (CSV) fora do
-- veto -- ver SambaConfigGenerator::generate().
CREATE TABLE IF NOT EXISTS `samba_compartilhamento_excecoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `compartilhamento_id` int(11) NOT NULL,
  `pasta` varchar(255) NOT NULL,
  `nome_compartilhamento` varchar(80) NOT NULL,
  `extensoes` varchar(1000) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_samba_excecao_nome` (`nome_compartilhamento`),
  KEY `idx_samba_excecao_compartilhamento` (`compartilhamento_id`),
  CONSTRAINT `fk_samba_excecao_compartilhamento` FOREIGN KEY (`compartilhamento_id`) REFERENCES `samba_compartilhamentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
