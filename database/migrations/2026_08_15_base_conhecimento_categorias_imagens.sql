-- Categorias/subcategorias (taxonomia LOCAL desta instalação -- ao propor
-- um artigo pra base central, só o NOME da categoria vai junto como texto
-- livre, já que cada instalação pode ter sua própria taxonomia) e imagens
-- anexadas aos artigos.

CREATE TABLE IF NOT EXISTS `base_conhecimento_categorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bc_categoria_nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `base_conhecimento_subcategorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `categoria_id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_bc_subcategoria_categoria` (`categoria_id`),
  UNIQUE KEY `uq_bc_subcategoria_nome` (`categoria_id`, `nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `base_conhecimento_imagens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `artigo_id` int(11) NOT NULL,
  `arquivo` varchar(255) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_bc_imagem_artigo` (`artigo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `base_conhecimento`
  DROP COLUMN `categoria`,
  ADD COLUMN `categoria_id` int(11) DEFAULT NULL AFTER `visibilidade`,
  ADD COLUMN `subcategoria_id` int(11) DEFAULT NULL AFTER `categoria_id`;
