-- Permite marcar uma regra de firewall pra registrar no log do kernel (via
-- alvo LOG do iptables, com prefixo unico "RD-FW-<id>:") os pacotes que
-- batem nela -- assim da pra ver quais IPs estao sendo bloqueados/liberados
-- por aquela regra especificamente (o iptables so guarda contador agregado,
-- nao quem gerou cada pacote).
ALTER TABLE iptables_regras
    ADD COLUMN registrar_log TINYINT(1) NOT NULL DEFAULT 0 AFTER extra;
