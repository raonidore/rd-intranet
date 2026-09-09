-- Canal de abertura "sistema" -- chamados abertos automaticamente pelo
-- proprio RD.Intranet (ex: alerta de canal de DVR/NVR sem sinal), sem
-- humano por tras da abertura.
ALTER TABLE chamados MODIFY canal_abertura enum('painel','email','whatsapp','portal','sistema') NOT NULL DEFAULT 'painel';

-- Garante a categoria "DVR/NVR" em qualquer instancia -- idempotente, se
-- ja foi criada manualmente (como no caso do Patrimonial Pneus) so mantem
-- o que ja existe, sem sobrescrever o setor padrao configurado.
INSERT INTO chamados_categorias (nome, setor_padrao_id)
SELECT 'DVR/NVR', NULL
WHERE NOT EXISTS (SELECT 1 FROM chamados_categorias WHERE nome = 'DVR/NVR');
