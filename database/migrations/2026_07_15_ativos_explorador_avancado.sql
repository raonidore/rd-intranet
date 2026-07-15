-- Segunda fase do explorador de arquivos/comandos remotos: renomear,
-- baixar, enviar arquivo, e execucao de comandos CMD/PowerShell (com ou
-- sem elevacao), todos com auditoria de quem solicitou.

ALTER TABLE ativos_comandos
    MODIFY COLUMN comando ENUM(
        'desligar',
        'reiniciar',
        'desinstalar_atualizacao',
        'desinstalar_programa',
        'executar_arquivo',
        'encerrar_processo',
        'renomear_arquivo',
        'enviar_arquivo'
    ) NOT NULL,
    -- Caminho do arquivo temporario no servidor com o conteudo enviado
    -- pelo admin (upload), pro agente baixar quando processar o comando
    -- 'enviar_arquivo'. So usado por esse tipo de comando.
    ADD COLUMN arquivo_anexo VARCHAR(500) NULL DEFAULT NULL AFTER alvo_label;

ALTER TABLE ativos_solicitacoes
    MODIFY COLUMN tipo ENUM(
        'listar_arquivos',
        'listar_processos',
        'baixar_arquivo',
        'executar_cmd',
        'executar_powershell'
    ) NOT NULL,
    ADD COLUMN solicitado_por VARCHAR(150) NULL DEFAULT NULL AFTER parametro,
    ADD COLUMN elevado TINYINT(1) NOT NULL DEFAULT 0 AFTER solicitado_por,
    -- Caminho do arquivo temporario no servidor com o conteudo que o
    -- agente devolveu (download), pra resposta de 'baixar_arquivo'. Os
    -- outros tipos usam a coluna `resultado` (JSON/texto) normalmente.
    ADD COLUMN arquivo_resultado VARCHAR(500) NULL DEFAULT NULL AFTER resultado;
