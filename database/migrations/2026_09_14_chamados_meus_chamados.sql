-- "Meus Chamados" (Chamados > Meus Chamados + card do Dashboard): até
-- aqui não havia como saber, entre os chamados abertos pelo painel
-- (chamados.canal_abertura = 'painel'), QUEM (qual usuário logado)
-- preencheu o formulário -- só existia usuario_id (o ATENDENTE
-- designado, setado só quando alguém assume na Fila) e solicitante_id
-- (contato solto em chamados_solicitantes, nem sempre ligado a um login
-- do sistema). usuario_abertura_id fecha essa lacuna: gravado uma única
-- vez, na abertura (ChamadoService::abrir()), nunca mudado depois.
ALTER TABLE chamados
    ADD COLUMN usuario_abertura_id INT NULL AFTER usuario_id,
    ADD KEY idx_chamados_usuario_abertura (usuario_abertura_id),
    ADD CONSTRAINT fk_chamados_usuario_abertura FOREIGN KEY (usuario_abertura_id) REFERENCES usuarios (id) ON DELETE SET NULL;
