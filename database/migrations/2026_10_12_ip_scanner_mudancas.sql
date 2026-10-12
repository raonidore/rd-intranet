-- Quem entrou/saiu da rede em cada varredura do IP Scanner (manual ou
-- automática). Antes só a lista completa de hosts era guardada e o
-- "N novos / N sumiram" era calculado na hora e descartado -- dava pra ver
-- o número, mas não QUEM. NULL = primeira varredura da faixa (sem base).
ALTER TABLE `ip_scanner_execucoes`
  ADD COLUMN IF NOT EXISTS `novos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `sumiram` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL;
