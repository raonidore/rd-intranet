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
   atualização, ver `scripts/sync-system-scripts.sh`).
5. Cria o banco `rd_intranet`, o usuário do MySQL (senha aleatória, exibida
   só uma vez no final) e `app/Config/database.php` a partir do
   `app/Config/database.example.php`.
6. Roda `composer install` e carrega `database/schema.sql` (o estado atual
   completo do banco — não é o histórico de `database/migrations/`, que
   tem `ALTER TABLE`s não seguros de reaplicar do zero) e marca essas
   migrations como já aplicadas.
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

1. **Criar o primeiro usuário admin.** Ainda não existe uma tela pra isso
   sem estar logado — insira direto no banco:

   ```sql
   INSERT INTO usuarios (nome, login, senha_hash, perfil, ativo)
   VALUES ('Seu Nome', 'seu.login', '<hash>', 'admin', 1);
   ```

   Gere o hash com:

   ```bash
   php -r "echo password_hash('sua-senha', PASSWORD_DEFAULT), \"\n\";"
   ```

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
