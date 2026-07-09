-- Gestao de jobs de cron do sistema via web (tela Infraestrutura > Cron).
-- Cada linha ativa vira uma entrada no arquivo /etc/cron.d/rd-intranet,
-- regenerado por completo a cada criacao/edicao/exclusao/toggle (ver
-- CronService::regenerarArquivo() + scripts/system/cron_aplicar_web.sh).
-- sincronizado_em/ultimo_erro_sync nao ficam aqui pois sao globais ao
-- arquivo inteiro, nao por job -- guardados em `configuracoes` (chaves
-- cron_sync_em / cron_sync_erro).
CREATE TABLE IF NOT EXISTS cron_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    descricao VARCHAR(255) NULL,
    expressao VARCHAR(60) NOT NULL,
    usuario_execucao VARCHAR(60) NOT NULL DEFAULT 'root',
    comando TEXT NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    ultima_execucao_em TIMESTAMP NULL,
    ultima_execucao_sucesso TINYINT(1) NULL,
    ultima_execucao_saida TEXT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Modulo infra_cron, liberado automaticamente pra admins (mesma logica das
-- migrations anteriores de modulo).
INSERT IGNORE INTO usuario_modulos (usuario_id, modulo)
SELECT u.id, 'infra_cron'
FROM usuarios u
WHERE u.perfil = 'admin';
