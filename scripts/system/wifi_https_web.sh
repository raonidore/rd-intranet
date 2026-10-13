#!/bin/bash
# wifi_https_web.sh emitir <dominio> <email> <cpanel_url> <cpanel_usuario> <zona>   (token da API do cPanel pelo stdin)
# wifi_https_web.sh status <dominio>
#
# HTTPS do portal do Wi-Fi. O nome (ex: wifi-cliente.rd.inf.br) aponta para
# o IP INTERNO do servidor, então a validação HTTP do Let's Encrypt não
# alcança -- usamos DNS-01: o certbot chama os hooks abaixo, que criam e
# apagam o TXT _acme-challenge pela API (UAPI) do cPanel que hospeda a zona.
# Os hooks ficam em /etc/rd-intranet/wifi (root), gerados por este script --
# nunca executam nada do diretório do repositório. A renovação automática do
# certbot reaproveita os mesmos hooks e o token salvo ali.

set -u

ACAO="${1:-}"
DIR_CONF="/etc/rd-intranet/wifi"
REPO_DIR="/var/www/rd.intranet"

# O resultado da emissão também vai para um arquivo de status: a emissão roda em
# segundo plano (pode levar minutos esperando o DNS) e a tela acompanha por ele.
STATUS="/var/lib/rd-intranet/wifi-https.json"
json() {
  python3 -c 'import json,sys,time; d={"success": sys.argv[1] == "1", "message": sys.argv[2], "quando": time.strftime("%Y-%m-%d %H:%M:%S")}; print(json.dumps(d))' "$1" "$2" | tee "${STATUS_SAIDA:-/dev/null}"
}

valida_dominio() { [[ "$1" =~ ^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$ ]]; }

if [ "$ACAO" = "status" ]; then
  DOMINIO="${2:-}"
  valida_dominio "$DOMINIO" || { json 0 "Domínio inválido."; exit 1; }
  CERT="/etc/letsencrypt/live/${DOMINIO}/cert.pem"
  if [ ! -f "$CERT" ]; then json 0 "Sem certificado para ${DOMINIO}."; exit 0; fi
  VALIDADE="$(openssl x509 -enddate -noout -in "$CERT" | cut -d= -f2)"
  VHOST="nao"; [ -f /etc/apache2/sites-enabled/rd-intranet-wifi-ssl.conf ] && VHOST="sim"
  python3 -c 'import json,sys; print(json.dumps({"success": True, "message": "ok", "validade": sys.argv[1], "vhost": sys.argv[2] == "sim"}))' "$VALIDADE" "$VHOST"
  exit 0
fi

if [ "$ACAO" != "emitir" ]; then json 0 "Ação inválida."; exit 1; fi

DOMINIO="${2:-}"; EMAIL="${3:-}"; CPANEL_URL="${4:-}"; CPANEL_USUARIO="${5:-}"; ZONA="${6:-}"
IFS= read -r TOKEN || true
mkdir -p /var/lib/rd-intranet
STATUS_SAIDA="$STATUS"
valida_dominio "$DOMINIO" || { json 0 "Domínio inválido."; exit 1; }
valida_dominio "$ZONA" || { json 0 "Zona inválida."; exit 1; }
[[ "$DOMINIO" == *".${ZONA}" ]] || { json 0 "O domínio precisa estar dentro da zona ${ZONA}."; exit 1; }
[[ "$EMAIL" =~ ^[^@[:space:]]+@[^@[:space:]]+\.[^@[:space:]]+$ ]] || { json 0 "E-mail inválido."; exit 1; }
[[ "$CPANEL_URL" =~ ^https://[a-zA-Z0-9.-]+(:[0-9]+)?/?$ ]] || { json 0 "URL do cPanel inválida."; exit 1; }
[[ "$CPANEL_USUARIO" =~ ^[a-zA-Z0-9_.-]+$ ]] || { json 0 "Usuário do cPanel inválido."; exit 1; }
[[ "$TOKEN" =~ ^[A-Z0-9]{20,64}$ ]] || { json 0 "Token da API do cPanel inválido."; exit 1; }
command -v certbot >/dev/null 2>&1 || { json 0 "certbot não está instalado (Infraestrutura > Dependências)."; exit 1; }
python3 -c 'import json,sys,time; print(json.dumps({"success": None, "message": "Emitindo o certificado para %s (pode levar alguns minutos)..." % sys.argv[1], "quando": time.strftime("%Y-%m-%d %H:%M:%S")}))' "$DOMINIO" > "$STATUS"
chmod 644 "$STATUS"

mkdir -p "$DIR_CONF"
chmod 700 "$DIR_CONF"
umask 077
printf '%s' "$TOKEN" > "$DIR_CONF/cpanel-token"
printf 'URL=%s\nUSUARIO=%s\nZONA=%s\n' "${CPANEL_URL%/}" "$CPANEL_USUARIO" "$ZONA" > "$DIR_CONF/cpanel.conf"

# Hook único: "auth" cria o TXT e espera ele aparecer nos DNS da zona; "cleanup" apaga.
cat > "$DIR_CONF/cpanel-dns-hook.sh" <<'HOOK'
#!/bin/bash
# Gerado por wifi_https_web.sh -- hook DNS-01 do certbot via UAPI do cPanel.
exec python3 - "$1" <<'PY'
import base64, json, os, subprocess, sys, time, urllib.parse, urllib.request

acao = sys.argv[1]
conf = dict(l.strip().split('=', 1) for l in open('/etc/rd-intranet/wifi/cpanel.conf') if '=' in l)
token = open('/etc/rd-intranet/wifi/cpanel-token').read().strip()
zona = conf['ZONA']
dominio = os.environ['CERTBOT_DOMAIN']
valor = os.environ['CERTBOT_VALIDATION']
fqdn = '_acme-challenge.' + dominio + '.'
relativo = fqdn[:-(len(zona) + 2)]

def uapi(funcao, **q):
    url = conf['URL'] + '/execute/DNS/' + funcao + '?' + urllib.parse.urlencode(q)
    req = urllib.request.Request(url, headers={'Authorization': 'cpanel %s:%s' % (conf['USUARIO'], token)})
    r = json.load(urllib.request.urlopen(req, timeout=60))
    if not r.get('status'):
        sys.exit('cPanel recusou %s: %s' % (funcao, r.get('errors')))
    return r.get('data')

def zona_atual():
    serial, txts = None, []
    for rec in uapi('parse_zone', zone=zona):
        if rec.get('type') != 'record':
            continue
        dados = [base64.b64decode(x).decode() for x in rec.get('data_b64', [])]
        nome = base64.b64decode(rec.get('dname_b64', '')).decode()
        # o cPanel devolve uns nomes absolutos ("x.zona.") e outros relativos ("x")
        nome = nome if nome.endswith('.') else nome + '.' + zona + '.'
        if rec['record_type'] == 'SOA':
            serial = dados[2]
        elif rec['record_type'] == 'TXT' and nome == fqdn:
            txts.append((rec['line_index'], ''.join(dados)))
    return serial, txts

if acao == 'auth':
    serial, _ = zona_atual()
    uapi('mass_edit_zone', zone=zona, serial=serial,
         add=json.dumps({'dname': relativo, 'ttl': 60, 'record_type': 'TXT', 'data': [valor]}))
    # Espera o TXT nos servidores autoritativos (até 3 min).
    try:
        ns = subprocess.run(['dig', '+short', 'NS', zona], capture_output=True, text=True, timeout=15).stdout.split()
    except (FileNotFoundError, subprocess.TimeoutExpired):
        ns = []
    if not ns:
        time.sleep(60)
        sys.exit(0)
    for _ in range(36):
        ok = all(valor in subprocess.run(['dig', '+short', 'TXT', fqdn, '@' + n], capture_output=True, text=True, timeout=15).stdout for n in ns)
        if ok:
            time.sleep(5)
            sys.exit(0)
        time.sleep(5)
    sys.exit(0)

if acao == 'cleanup':
    while True:
        serial, txts = zona_atual()
        alvo = [i for i, d in txts if d == valor]
        if not alvo:
            break
        uapi('mass_edit_zone', zone=zona, serial=serial, remove=alvo[0])
PY
HOOK
chmod 700 "$DIR_CONF/cpanel-dns-hook.sh"
printf '#!/bin/bash\nexec %s auth\n' "$DIR_CONF/cpanel-dns-hook.sh" > "$DIR_CONF/hook-auth.sh"
printf '#!/bin/bash\nexec %s cleanup\n' "$DIR_CONF/cpanel-dns-hook.sh" > "$DIR_CONF/hook-cleanup.sh"
chmod 700 "$DIR_CONF/hook-auth.sh" "$DIR_CONF/hook-cleanup.sh"

# Recarregar o Apache só na renovação (na 1ª emissão quem recarrega é este script, no fim).
mkdir -p /etc/letsencrypt/renewal-hooks/deploy
printf '#!/bin/bash\n# Gerado por wifi_https_web.sh\nsystemctl reload apache2\n' > /etc/letsencrypt/renewal-hooks/deploy/rd-intranet-wifi.sh
chmod 755 /etc/letsencrypt/renewal-hooks/deploy/rd-intranet-wifi.sh

SAIDA="$(mktemp)"
if ! certbot certonly --manual --preferred-challenges dns \
    --manual-auth-hook "$DIR_CONF/hook-auth.sh" --manual-cleanup-hook "$DIR_CONF/hook-cleanup.sh" \
\
    -d "$DOMINIO" --non-interactive --agree-tos -m "$EMAIL" --no-eff-email --keep-until-expiring >"$SAIDA" 2>&1; then
  ERRO="$(tail -15 "$SAIDA" | tr '\n' ' ')"
  rm -f "$SAIDA"
  json 0 "Erro ao obter o certificado: ${ERRO}"
  exit 1
fi
rm -f "$SAIDA"

LIVE="/etc/letsencrypt/live/${DOMINIO}"
[ -f "$LIVE/fullchain.pem" ] || { json 0 "O certbot terminou mas o certificado não foi encontrado."; exit 1; }

# Vhost 443 só para o nome do portal (SNI); o painel continua como está.
a2enmod ssl >/dev/null 2>&1
a2enmod rewrite >/dev/null 2>&1
umask 022
cat > /etc/apache2/sites-available/rd-intranet-wifi-ssl.conf <<EOF
# Gerado por wifi_https_web.sh -- portal do Wi-Fi em HTTPS.
<VirtualHost *:443>
    ServerName ${DOMINIO}
    DocumentRoot ${REPO_DIR}/public
    <Directory ${REPO_DIR}/public>
        AllowOverride All
        Require all granted
    </Directory>
    SSLEngine on
    SSLCertificateFile ${LIVE}/fullchain.pem
    SSLCertificateKeyFile ${LIVE}/privkey.pem
    # Só o portal é servido por este nome.
    RewriteEngine On
    RewriteCond %{REQUEST_URI} !^/guest
    RewriteRule ^ /guest/ [R=302,L]
    ErrorLog \${APACHE_LOG_DIR}/rd-intranet-wifi-error.log
    CustomLog \${APACHE_LOG_DIR}/rd-intranet-wifi-access.log combined
</VirtualHost>
EOF
a2ensite rd-intranet-wifi-ssl >/dev/null 2>&1
if ! apache2ctl configtest >/dev/null 2>&1; then
  a2dissite rd-intranet-wifi-ssl >/dev/null 2>&1
  json 0 "Certificado obtido, mas a configuração do Apache ficou inválida: $(apache2ctl configtest 2>&1 | tail -3 | tr '\n' ' ')"
  exit 1
fi
systemctl reload apache2
json 1 "Certificado emitido para ${DOMINIO} e HTTPS ativo. Renovação automática pelo certbot."
