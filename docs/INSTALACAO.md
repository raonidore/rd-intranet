# Instalação em um servidor novo

> Guia validado numa instalação real (servidor `srvarquivos`, 2026-07-14) —
> essa instalação revelou que `database/schema.sql` estava desatualizado (só
> 14 das 46 tabelas reais), corrigido nesse mesmo commit. Veja
> "Mantendo o schema.sql atualizado" mais abaixo: rode
> `scripts/gerar_schema.sh` sempre que uma migration nova criar tabela,
> senão servidores novos ficam sem ela — `scripts/install.sh` marca todas as
> migrations como já aplicadas ao carregar o `schema.sql`, sem checar se ele
> reflete de verdade o que está em `database/migrations/`.

## Pré-requisitos

- Servidor com **Ubuntu 24.04 LTS** e acesso root (SSH).
- Esse usuário (`ti` ou outro, é o `REPO_USER` do passo seguinte) precisa
  existir no sistema antes de rodar o script (`adduser ti`).
- Uma **deploy key** do GitHub com acesso de leitura ao repositório
  `rd-intranet`, configurada no servidor para esse usuário -- sem isso o
  `git clone` do passo 2 do script falha.

### Gerando e cadastrando a deploy key

Como o `REPO_USER` (ex: `ti`), **no servidor novo** (não no seu computador
nem em outro servidor):

```bash
ssh-keygen -t ed25519 -C "nome-do-servidor" -f ~/.ssh/id_ed25519_rd_intranet -N ""

cat >> ~/.ssh/config << 'EOF'
Host github.com
    HostName github.com
    User git
    IdentityFile ~/.ssh/id_ed25519_rd_intranet
    IdentitiesOnly yes
EOF
chmod 600 ~/.ssh/config

cat ~/.ssh/id_ed25519_rd_intranet.pub
```

(`-N ""` gera sem senha na chave -- necessário porque as atualizações
automáticas depois rodam sozinhas, sem alguém pra digitar a senha; troque
`"nome-do-servidor"` só pelo comentário, não afeta nada funcionalmente.)

Copie a saída do último comando (começa com `ssh-ed25519 AAAA...`) e
cadastre em **GitHub > repositório `rd-intranet` > Settings > Deploy keys
> Add deploy key**:

- **Title**: qualquer nome que identifique o servidor (ex: o hostname).
- **Key**: cole o conteúdo da chave **pública** copiado acima.
- **Allow write access**: deixe **desmarcado** -- só precisa ler
  (`git clone`/`git pull`), nunca dar push a partir de um servidor de
  produção.

Teste antes de seguir pro próximo passo:

```bash
ssh -T git@github.com
```

Deve responder algo como `Hi raonidore/rd-intranet! You've successfully
authenticated, but GitHub does not provide shell access.` -- se der
`Permission denied`, confira se a chave certa foi colada (a `.pub`, não a
privada) e se o `~/.ssh/config` está com o `IdentityFile` apontando pro
arquivo certo.

## Passo a passo

A deploy key fica no `~/.ssh` do `REPO_USER` (ex: `ti`), não no do root --
por isso este primeiro clone (só pra pegar o `scripts/install.sh`) roda
como esse usuário (`sudo -u`), e só o script em si roda como root:

```bash
sudo -u ti git clone git@github.com:raonidore/rd-intranet.git /tmp/rd-intranet-instalador
cd /tmp/rd-intranet-instalador
sudo DOMINIO=intranet.suaempresa.com.br bash scripts/install.sh
```

Ou defina as variáveis antes, se quiser fugir dos padrões:

```bash
sudo REPO_USER=ti REPO_DIR=/var/www/rd.intranet DOMINIO=intranet.suaempresa.com.br \
    bash scripts/install.sh
```

O script (`scripts/install.sh`) faz, nessa ordem:

1. Instala os pacotes mínimos pra clonar e rodar PHP.
2. Clona o repositório em `REPO_DIR` (branch `main`), como `REPO_USER`.
3. Instala o resto dos pacotes do sistema (Apache, MariaDB, Samba,
   iptables, etc.) a partir da mesma lista que a tela
   **Infraestrutura > Dependências** usa (`app/Services/DependenciaCatalogo.php`)
   — uma lista só, não duplicada aqui.
4. Cria `/opt/rdtecnologia/{scripts,logs}` e sincroniza
   `scripts/system/*.sh` pra lá (mesmo mecanismo usado depois de cada
   atualização, ver `scripts/sync-system-scripts.sh`). Em seguida roda os
   scripts de setup que criam conta/chave/serviço privilegiado (fuso
   horário do sistema, ACL do Samba, chave de criptografia do Console
   SQL, persistência de iptables/rotas extras) — de propósito fora do
   sudoers automático do `www-data`, então precisam ser chamados aqui
   explicitamente. O fuso horário (`America/Sao_Paulo`) é importante:
   o PHP já assume `America/Recife` (mesmo horário, fixo no código) e o
   MariaDB usa `time_zone=SYSTEM` — se o SO ficar em UTC (padrão comum
   de imagem de nuvem), qualquer "há quanto tempo" calculado a partir de
   um timestamp do banco (ex: status Ligado/Desligado de um ativo) sai
   errado em exatas 3 horas.
5. Cria o banco `rd_intranet`, o usuário do MySQL (senha aleatória, exibida
   só uma vez no final) e `app/Config/database.php` a partir do
   `app/Config/database.example.php`.
6. Roda `composer install`, gera o `[global]` do `smb.conf` (mesmo template
   da tela Samba > Config. Global) com `include = /etc/samba/shares.conf`
   e cria esse `shares.conf` vazio — sem isso, aplicar compartilhamentos
   pela tela Deploy falha com `NT_STATUS_BAD_NETWORK_NAME` mesmo o
   compartilhamento existindo no banco. Carrega `database/schema.sql` (o
   estado atual completo do banco — não é o histórico de
   `database/migrations/`, que tem `ALTER TABLE`s não seguros de reaplicar
   do zero), marca essas migrations como já aplicadas, cria o usuário
   admin padrão (login `admin`, senha `rd.intranet` — só se ainda não
   existir nenhum admin) e cadastra o cron nativo de coleta de tráfego de
   rede (`*/5 * * * *`, roda como `www-data`) — sem ele,
   `rede_trafego_historico` fica vazia pra sempre num servidor novo.
7. Libera `www-data` via sudo (sem senha) pra rodar qualquer script já
   publicado em `/opt/rdtecnologia/scripts/*.sh` — arquivo novo, sem
   histórico anterior pra preservar (diferente de
   `scripts/grant-sudo-atualizacao.sh`, que é só pra servidores que já
   existiam antes do módulo de Atualizações).
8. Ajusta dono/permissão do checkout (`REPO_USER` grava, `www-data` só
   lê) -- com exceção de `storage/uploads`, `storage/cache` e
   `storage/logs`, que ficam com `www-data` como dono, porque são
   escritos em tempo de execução pela própria aplicação (upload de
   arquivo, cache, log), não pelo deploy. Também sobe os limites de
   upload do PHP (`upload_max_filesize`/`post_max_size`, padrão do PHP é
   só 2M/8M -- pequeno demais pro instalador do agente, do .NET Desktop
   Runtime ou pra arquivos do Samba) via
   `/etc/php/*/apache2/conf.d/99-rd-intranet-uploads.ini`.
9. Cria o vhost do Apache em HTTP, mais um `Alias /rd.intranet` apontando
   pra `public/` — a aplicação usa `/rd.intranet` como prefixo de URL por
   padrão (`base_url` em `configuracoes`), e o `.htaccess` (`RewriteBase
   /rd.intranet/`) só funciona corretamente com esse Alias presente; sem
   ele, qualquer rota dá "500 Internal Server Error" por loop de
   redirecionamento (`AH00124`).

## Depois de rodar o script

1. **Trocar a senha do admin padrão.** Login `admin` / senha `rd.intranet`
   — troque assim que logar, pela tela **Administração > Usuários do
   Sistema**. É uma senha conhecida/fixa de propósito, só serve pra dar o
   primeiro acesso.

2. **HTTPS**: faça login e emita o certificado pela tela
   **Infraestrutura > Certificado Digital** (autoassinado ou Let's Encrypt,
   conforme o domínio já estiver apontando pro servidor ou não).

3. **Atualizações automáticas**: a tela **Administração > Atualizações**
   já funciona (o sudoers do passo 7 cobre os scripts dela). Considere
   ativar a verificação diária pela própria tela.

## Como as atualizações seguintes chegam nesse servidor

Depois da instalação, esse servidor passa a se atualizar sozinho: alguém
merge uma mudança em `main` no GitHub, um admin abre
**Administração > Atualizações**, clica em "Verificar agora" e depois
"Atualizar agora" (ou espera a verificação diária avisar). Não é mais
necessário repetir este guia — ele serve só pra levantar um servidor do
zero.

## Mantendo o `schema.sql` atualizado

`database/schema.sql` é uma foto da estrutura final do banco (só
`CREATE TABLE`, sem dados) — é o que `scripts/install.sh` carrega num
servidor novo, **antes** de marcar todo `database/migrations/*.sql` como
já aplicado. Ou seja: se uma migration nova cria uma tabela e ninguém
regenera o `schema.sql`, um servidor instalado do zero fica **sem essa
tabela pra sempre** (a migration nunca roda de verdade, porque já foi
marcada como aplicada) — foi exatamente isso que quebrou a tela
**Administração > Atualizações** no `srvarquivos` logo após a instalação
(faltava a tabela `passos_manuais_confirmacoes`, entre outras 31).

Sempre que adicionar uma migration que cria tabela nova, regenere o
schema antes de dar merge:

```bash
bash scripts/gerar_schema.sh
git diff database/schema.sql   # confira que só apareceu o que era esperado
```

Dados semeados por migration (`INSERT IGNORE` de config padrão, grants
em `usuario_modulos`) não entram no `schema.sql` — mas isso não costuma
ser crítico: `ConfigService::get($chave, $padrao)` sempre tem um valor
padrão no próprio código PHP, e o usuário `admin` padrão bypassa a
checagem de `usuario_modulos` por ter `perfil = 'admin'`. O que
realmente importa manter em dia são as tabelas.

## Rodando atrás de nginx

Se o servidor já tem nginx segurando as portas 80/443 (servindo outros
sites do cliente, por exemplo), rode o instalador apontando o Apache da
RD Intranet pra uma porta interna em vez de brigar pela 80:

```bash
sudo APACHE_PORT=8080 DOMINIO=intranet.suaempresa.com.br bash scripts/install.sh
```

Isso faz o Apache escutar só em `127.0.0.1:8080` (o `.htaccess`/`Alias`
continuam funcionando normalmente, é só a porta que muda) e desativa o
site padrão do Apache, que só responde na 80. Depois, adicione um bloco
`server` no nginx do cliente fazendo proxy pra essa porta — pode ser um
subdomínio dedicado (recomendado, mais simples que caminho compartilhado
com outro site) ou combinar com um domínio já existente do cliente:

```nginx
server {
    listen 80;
    server_name intranet.suaempresa.com.br;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

A URL final fica `http://intranet.suaempresa.com.br/rd.intranet/login`
(o prefixo `/rd.intranet/` continua, é o `base_url` padrão da aplicação —
ver `app/Helpers/url.php`). Pra HTTPS nesse cenário, o certificado é
coisa do nginx (`certbot --nginx` ou o que o cliente já usar pra gerenciar
os certificados dos outros sites dele) — **não** use o módulo
Infraestrutura > Certificado Digital aqui, ele gerencia o certificado do
Apache interno, que não é o que fica exposto pra internet nesse modo.
