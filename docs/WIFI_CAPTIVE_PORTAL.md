# Wi-Fi / Captive Portal

Portal próprio de acesso ao Wi-Fi (colaboradores, fornecedores, visitantes e outros perfis), com cadastro, liberação, controle de banda, filtro de sites, HTTPS e LGPD. Construído e testado em 13/10/2026 no enzilabnto (UDM Pro + U6+).

Status: **pronto, desligado por padrão**. Primeiro cliente previsto: Laboratório Maurílio, quando o UniFi U6+ for comprado e adotado pelo UCG.

---

## 1. Como funciona

```
aparelho conecta no SSID aberto do portal
  -> UniFi (portal externo) redireciona para http://<IP do servidor>/guest/s/<site>/?id=<mac>&ap=..&url=..
  -> RD.Intranet guarda o contexto; se o HTTPS estiver ativo, segue assinado (HMAC)
     para https://wifi-<cliente>.rd.inf.br/guest/?c=...
  -> pessoa entra (e-mail + senha) ou se cadastra conforme o perfil
  -> RD.Intranet manda o UniFi liberar o MAC (cmd/stamgr authorize-guest)
     com o tempo, a banda e a cota do perfil
  -> sessão registrada (wifi_sessoes) -> manutenção a cada 5 min
     (consumo, vencimentos, LGPD, filtro)
```

- **Quem libera o aparelho** é o driver do equipamento (`app/Services/Rede/`). Hoje existe só o UniFi. Outra marca = nova classe implementando `ControladorPortal`, registrada em `RedeDrivers::CONTROLADORES`.
- **Switches de outra marca no caminho** (ex.: TP-Link SG3428 entre o gateway UniFi e o AP) recebem a VLAN tagueada pelo assistente via `DriverSwitch` (TP-Link pela CLI SSH).
- **Filtro de sites**: AdGuard Home no próprio servidor. Ele é o DNS da VLAN do portal, e cada pessoa conectada vira um "cliente" do AdGuard (pelo IP da sessão) com as regras do perfil, as exceções dela e o filtro global.
- **HTTPS**: nome `wifi-<cliente>.rd.inf.br` apontando para o **IP interno** do servidor. O certificado é Let's Encrypt, validado por **DNS-01** (TXT criado e apagado pela API do cPanel da ValueHost), com renovação automática pelo certbot.

## 2. Telas e permissões

O grupo **Wi-Fi** nasce desligado: ligar em *Sistema > Módulos*. As permissões ficam em *Usuários*.

| Módulo | Tela | Para quem |
|---|---|---|
| `wifi_painel` | Conectados agora (consumo, desconectar) | TI |
| `wifi_liberar` | Liberações (liberar/recusar), bloquear pessoa/aparelho, desconectar. Recebe e-mail de cadastro pendente | TI / RH |
| `wifi_pessoas` | Cadastros: criar (já liberado, senha por e-mail), editar, exceções de regra, reenviar senha | TI / RH |
| `wifi_regras` | Perfis e regras: acesso, liberação, domínios de e-mail, validade, sessão, banda, cota, aparelhos, lembrar aparelho, horário, campos do cadastro, termos versionados, filtro | TI |
| `wifi_relatorios` | Histórico de conexões + CSV, sites consultados (log do AdGuard), exportar/excluir dados do titular (LGPD) | TI / DPO |
| `wifi_configuracoes` | Portal (liga/desliga, título, cor, IP, proteção de login), LGPD (DPO, retenções), Filtro, HTTPS, Assistente de rede | TI |

Portal público: `/guest/` (`public/guest/index.php`, sessão própria `RDWIFI`, sem CSS/JS externo, porque o aparelho ainda não tem internet).
Telas do portal: entrar, cadastro por perfil, termos, troca de senha, limite de aparelhos, aguardando, conectado, esqueci/redefinir, privacidade.

## 3. Regras (tudo configurável)

Perfis iniciais (seeds da migration, editáveis):

| | Colaboradores | Fornecedores | Visitantes |
|---|---|---|---|
| Acesso | e-mail + senha | e-mail + senha | cadastro rápido, sem senha |
| Liberação | sim | sim | não |
| Validade | sem vencimento | 30 dias | até o fim do dia |
| Sessão | 12 h | 8 h | 2 h |
| Banda ↓/↑ | 20/5 Mbps | 10/2 Mbps | 5/1 Mbps |
| Aparelhos | 2 (lembra 30 dias) | 2 | 1 |
| Horário | livre | seg–sex 07–19 h | livre |
| Filtro | adulto, apostas, malware | + TikTok, Netflix | + YouTube |

- A senha provisória vai por e-mail e a troca é obrigatória no 1º acesso. "Esqueci a senha" envia um link de uso único que vale 1 hora.
- **Exceções por pessoa** (banda, sessão, aparelhos, cota, filtro próprio) sobrepõem o perfil.
- Alterar o texto dos termos cria uma nova versão, e todos aceitam de novo no próximo acesso. Os aceites antigos ficam guardados (IP, MAC, data).
- Proteção de login: 5 erros em 15 min bloqueiam o e-mail e o MAC (configurável).

## 4. LGPD

- **Registros de conexão**: 365 dias (configurável, mínimo 180, por causa do Marco Civil). Depois disso a manutenção anonimiza MAC, IP e pessoa.
- **Sites consultados**: no máximo 7 dias (configurável de 1 a 90), só para diagnóstico. Toda consulta fica na auditoria.
- **Pedido do titular**: o acesso é encerrado e os dados não obrigatórios são apagados na hora. Nome e e-mail ficam só enquanto houver registro de conexão dentro do prazo legal (art. 16, I) e depois são anonimizados automaticamente.
- **Exportação**: JSON com cadastro, aparelhos, aceites e conexões.
- A página `/guest/privacidade` usa o DPO e os prazos configurados.

## 5. Checklist para ligar num cliente

Pré-requisitos: integração UniFi configurada (API Key) e AP adotado no gateway; SMTP configurado (*Sistema > E-mail*); certbot instalado (*Infraestrutura > Dependências*); servidor com IP fixo na LAN.

1. *Sistema > Módulos*: ligar o grupo **Wi-Fi** e dar as permissões (`wifi_liberar` para quem libera, RH incluso).
2. *Wi-Fi > Configurações > Filtro*: **Instalar** o AdGuard. Antes, confira se nada mais usa a porta 53 no IP da LAN. Depois marque *Filtro ativo* e defina as regras globais.
3. *Wi-Fi > Configurações > Assistente de rede*:
   - criar a VLAN do portal (o DHCP já entrega o DNS do filtro), o SSID aberto com portal e apontar o portal do UniFi para o servidor;
   - marcar as portas dos switches TP-Link por onde a VLAN passa. As portas com vizinho LLDP já vêm sugeridas, e o assistente só acrescenta a VLAN tagueada, sem retirar nada.
4. **Gateway: bloquear DNS externo (UDP/TCP 53 e 853) na VLAN do portal, exceto para o servidor.** Isso ainda é manual (ver pendências). Sem esse bloqueio, quem trocar o DNS do aparelho escapa do filtro.
5. *Configurações > HTTPS*:
   - nome `wifi-<cliente>.rd.inf.br`, zona `rd.inf.br`, URL `https://cpanel.rd.inf.br`, usuário `rd`, token da API do cPanel. O token "rd-intranet-wifi" existe no cPanel, mas o valor dele não fica guardado no repositório. Se não estiver à mão, crie outro em *cPanel > Segurança > Gerenciar tokens de API*. No servidor, ele fica cifrado no banco e numa cópia root em `/etc/rd-intranet/wifi/`;
   - **Testar cPanel** e depois **Criar DNS e emitir certificado**. A emissão roda em segundo plano, leva de 1 a 4 min e a tela acompanha.
6. *Configurações > LGPD*: nome e e-mail do encarregado (DPO); conferir as retenções.
7. *Perfis e regras*: revisar os perfis, os domínios de e-mail dos colaboradores e o texto dos termos.
8. *Configurações > Portal*: título, cor e **Portal ligado**. Isso publica o `/guest` e cria o cron `rd wifi:manutencao`, que roda a cada 5 min.
9. Testar com um celular: conectar no SSID e passar pelo fluxo completo.

Maurílio: planejados o SSID "MDA - Equipamentos" (WPA2, sem portal, para os aparelhos que hoje usam o Wi-Fi do modem Claro) e o "MDA - Wi-Fi" (portal). O SMTP já está configurado lá (`intranet@rd.inf.br`). Nome previsto: `wifi-maurilio.rd.inf.br`.

## 6. Pendências

- **Regra de firewall no gateway** bloqueando DNS externo/DoT na VLAN do portal, feita pelo assistente (API v2 de firewall por zonas do UniFi; ainda não testada).
- Drivers de outras marcas (Omada Controller, MikroTik etc.) quando aparecer um cliente.
- Teste com aparelho real (celular) no SSID com portal: os testes de 13/10 usaram MAC fictício e redirecionamento simulado.

## 7. Comportamentos descobertos ao vivo

- **cPanel da ValueHost**: em `https://rd.inf.br:2083`, o IP de um servidor de cliente recebe uma página anti-bot ("One moment, please...", openresty). **Use `https://cpanel.rd.inf.br`** (porta 443). A autenticação é pelo header `Authorization: cpanel rd:<TOKEN>` (token de API, criado em *cPanel > Gerenciar tokens de API*). Senha com basic auth devolve 401.
- **`DNS::parse_zone`** devolve nomes ora absolutos (`x.rd.inf.br.`), ora relativos (`x`): sempre normalizar antes de comparar, senão o registro é duplicado. A zona tem wildcard `*`, por isso o registro A do portal é obrigatório.
- **UniFi**:
  - o `guest_access` já bloqueia as redes privadas para visitantes (`restricted_subnet_1..3`) e o portal libera só o servidor (`allowed_subnet_1 = IP/32`). Por isso a VLAN do portal **não** usa "isolar rede", que cortaria o portal e o DNS;
  - `unauthorize-guest` deixa `end == start` e não marca `expired`;
  - o consumo (`stat/guest`) vem do ponto de vista do AP: `tx_bytes` é o download do aparelho.
- **AdGuard Home** (versão fixada `v0.107.52` em `wifi_filtro_instalar_web.sh`):
  - a API e o painel ficam só em `127.0.0.1:3010`, e o DNS no IP da LAN;
  - as regras do RD ficam num bloco marcado dentro das regras do usuário, então o que a TI escrever à mão é preservado;
  - o upstream padrão (Quad9 DoH) demorou na 1ª consulta, então a instalação define Cloudflare, Quad9, 1.1.1.1 e 8.8.8.8.
- **Certbot**: a emissão dentro da requisição web morreu no reload do Apache. Por isso ela roda em segundo plano, com status em `/var/lib/rd-intranet/wifi-https.json`, e o reload na renovação fica em `/etc/letsencrypt/renewal-hooks/deploy/rd-intranet-wifi.sh`. Os hooks DNS-01 e o token ficam em `/etc/rd-intranet/wifi/` (somente root).

## 8. Arquivos

- Banco: `database/migrations/2026_10_13_wifi_portal.sql`, com as tabelas `wifi_perfis`, `wifi_termos`, `wifi_pessoas`, `wifi_aparelhos`, `wifi_aceites`, `wifi_sessoes`, `wifi_tokens` e `wifi_tentativas`, e os seeds dos 3 perfis e dos termos v1.
- Núcleo:
  - `WifiPortalService`: login, cadastro, liberação, sessões, e-mails, manutenção e LGPD;
  - `WifiPerfilService`;
  - `WifiConfigService`: configurações com prefixo `wifi_`;
  - `WifiRepository`.
- Filtro: `WifiFiltroService` e `scripts/system/wifi_filtro_instalar_web.sh`.
- HTTPS: `WifiHttpsService`, `scripts/system/wifi_https_web.sh` e `scripts/system/wifi_portal_apache_web.sh` (Alias `/guest`).
- Rede: `WifiRedeService` (assistente), além de `app/Services/Rede/` (`ControladorPortal`, `DriverSwitch`, `UnifiDriver`, `TplinkSshDriver`, `RedeDrivers`).
- Telas: `WifiController` com `app/Views/wifi/`, e `WifiPortalController` com `app/Views/wifi_portal/` e `public/guest/`.
- CLI e cron: `rd wifi:manutencao`, com o cron criado por `AtualizacaoService::garantirCronWifi()` (só onde o portal está ligado).
