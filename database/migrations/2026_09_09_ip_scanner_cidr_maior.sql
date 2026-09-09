-- A varredura combinada de varias faixas (2026_09_09) guarda todas
-- separadas por virgula na mesma coluna "cidr" (ex: 4 faixas /24 passam
-- de 60 caracteres) -- o VARCHAR(20) original so cabia uma faixa sozinha
-- ("255.255.255.255/32" = 18 chars). Sem isso, salvar o resultado de uma
-- varredura multi-faixa estoura o limite (erro 22001) e quebra a resposta
-- inteira do "Escanear" -- inclusive o casamento com Ativos ja cadastrados,
-- que depende dessa mesma resposta pra chegar ate a tela.
ALTER TABLE ip_scanner_execucoes MODIFY cidr VARCHAR(255) NOT NULL;
