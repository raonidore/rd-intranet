-- Permite um peer WireGuard representar uma sub-rede inteira atras dele
-- (site-to-site), nao so o proprio IP /32 -- caso do RD.Bridge em modo
-- VPN: o peer eh o coletor, mas o AllowedIPs no servidor precisa cobrir
-- a rede local da unidade remota tambem, senao o servidor nunca roteia
-- pacote nenhum pra ela. Formato: lista de CIDR separada por virgula
-- (ex: "192.168.10.0/24"), vazio = comportamento de sempre (so /32).
ALTER TABLE `vpn_wireguard_peers`
  ADD COLUMN `rotas_extras` varchar(255) DEFAULT NULL AFTER `ip_atribuido`;
