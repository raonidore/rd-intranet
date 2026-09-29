-- Categoria pode não ter SLA (ex.: pedidos internos, sugestões): chamados
-- dela abrem sem prazo de 1ª resposta/resolução. As 4 linhas de
-- chamados_slas continuam guardadas, pra voltar ao ligar de novo.
-- Subcategoria com "prazos próprios" continua tendo prazo mesmo assim.

ALTER TABLE `chamados_categorias`
  ADD COLUMN IF NOT EXISTS `usa_sla` tinyint(1) NOT NULL DEFAULT 1 AFTER `exige_subcategoria`;
