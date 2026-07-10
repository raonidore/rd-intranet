# Instalação em um servidor novo

> Primeira versão deste guia — cobre o caminho feliz num Ubuntu 24.04 limpo.
> Ainda não foi validado num servidor de teste de verdade (só existe o
> servidor de produção atual). Revise o `scripts/install.sh` antes de rodar
> em um servidor real.

## Pré-requisitos

- Servidor com **Ubuntu 24.04 LTS** e acesso root (SSH).
- Uma **deploy key** do GitHub com acesso de leitura ao repositório
  `rd-intranet`, já configurada no servidor para o usuário que vai ser dono
  do checkout (`REPO_USER`, padrão `ti`) — sem isso o `git clone` do passo 2
  do script falha. Isso é o mesmo tipo de chave já usada em produção
  (`~/.ssh/id_ed25519_rd_intranet` + `~/.ssh/config` apontando pra
  `github.com`), só que gerada pra este servidor novo e cadastrada como
  "Deploy key" (somente leitura) no GitHub.
- Esse usuário (`ti` ou outro) precisa existir no sistema antes de rodar o
  script (`adduser ti`).

## Passo a passo

```bash
# como root
git clone <url-do-repo> /tmp/rd-intranet-instalador
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
   scripts de setup que criam conta/chave/serviço privilegiado (ACL do
   Samba, chave de criptografia do Console SQL, persistência de
   iptables/rotas extras) — de propósito fora do sudoers automático do
   `www-data`, então precisam ser chamados aqui explicitamente.
5. Cria o banco `rd_intranet`, o usuário do MySQL (senha aleatória, exibida
   só uma vez no final) e `app/Config/database.php` a partir do
   `app/Config/database.example.php`.
6. Roda `composer install` e carrega `database/schema.sql` (o estado atual
   completo do banco — não é o histórico de `database/migrations/`, que
   tem `ALTER TABLE`s não seguros de reaplicar do zero), marca essas
   migrations como já aplicadas e cria o usuário admin padrão (login
   `admin`, senha `rd.intranet`) — só se ainda não existir nenhum admin.
7. Libera `www-data` via sudo (sem senha) pra rodar qualquer script já
   publicado em `/opt/rdtecnologia/scripts/*.sh` — arquivo novo, sem
   histórico anterior pra preservar (diferente de
   `scripts/grant-sudo-atualizacao.sh`, que é só pra servidores que já
   existiam antes do módulo de Atualizações).
8. Ajusta dono/permissão do checkout.
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
