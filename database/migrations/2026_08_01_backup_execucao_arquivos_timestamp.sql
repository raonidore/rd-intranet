-- Guarda o timestamp exato da pasta de versao (.versoes/<compartilhamento>/<timestamp>/)
-- em que a versao ANTERIOR de cada arquivo atualizado/excluido foi preservada --
-- sem isso, restaurar um arquivo exigiria reconstruir esse timestamp a partir de
-- backup_execucoes.iniciado_em, que pode divergir por alguns segundos do timestamp
-- real gerado dentro do script (risco de apontar pra uma pasta remota inexistente).
ALTER TABLE backup_execucao_arquivos
    ADD COLUMN timestamp_versao VARCHAR(20) NULL AFTER tipo;
