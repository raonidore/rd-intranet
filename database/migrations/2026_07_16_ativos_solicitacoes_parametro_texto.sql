-- A tela "Acesso as Maquinas" (restricao de login local via Entra ID)
-- gera um script PowerShell completo (secedit export/modify/reimport)
-- pra mandar como 'parametro' de executar_powershell -- passa fácil de
-- 500 caracteres (VARCHAR atual), diferente dos comandos curtos
-- digitados a mao que motivaram o limite original. TEXT cobre com
-- folga qualquer script gerado.
ALTER TABLE ativos_solicitacoes
    MODIFY COLUMN parametro TEXT NULL;
