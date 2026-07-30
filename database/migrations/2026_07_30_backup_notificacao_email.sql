-- Notificacao por e-mail do modulo Backup em Nuvem: cada destino pode
-- disparar um relatorio ao concluir com sucesso e/ou um alerta ao falhar
-- (ver EmailService/BackupService::enviarNotificacoes()). O SMTP que
-- efetivamente envia fica configurado uma unica vez em Sistema > E-mail
-- (ConfigService/CryptoService, sem tabela propria -- mesmo esquema do
-- Cloudflare Tunnel).
ALTER TABLE backup_destinos
    ADD COLUMN relatorio_diario_ativo TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN alerta_falha_ativo TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN email_notificacao VARCHAR(255) NULL;
