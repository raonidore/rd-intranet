#!/bin/bash
# permissoes_pasta_samba_web.sh ler <caminho relativo>
# permissoes_pasta_samba_web.sh aplicar <caminho relativo> <json em base64>
#   json: {"entradas":[{"sid":"S-1-...","nivel":"ler|gravar|modificar|total"}],"subpastas":true}
#
# Permissões de uma pasta DENTRO de um compartilhamento (a raiz de cada
# compartilhamento continua sendo da tela Compartilhamentos > Usuários,
# aplicar_acl_compartilhamento_web.sh).
#
# Trabalha com a ACL do Windows que o Samba guarda (vfs acl_xattr) pelas
# bindings Python do próprio Samba (smbd.get_nt_acl/set_nt_acl), não com
# setfacl: o set_nt_acl grava a ACL do Windows E traduz para a ACL POSIX, e
# é a ACL do Windows que permite "gravar sem excluir" (DELETE é um bit à
# parte), coisa que a POSIX não tem.
#
# Por que não smbcacls --propagate-inheritance para as subpastas: ele só
# ACRESCENTA as entradas herdadas, mantendo as entradas próprias que cada
# arquivo já tinha. Arquivo copiado direto no disco (sem passar pelo Samba)
# nasce com uma ACL própria montada a partir da POSIX, com todo mundo do
# compartilhamento -- testado no Maurílio em 21/10/2026: depois de restringir
# a pasta, o arquivo de dentro continuava liberado para todos. Aqui cada item
# de dentro recebe SÓ as entradas herdadas da pasta (o "substituir todas as
# permissões dos objetos filho" do Windows), num único processo Python
# (rápido: dezenas de milhares de itens em segundos).
#
# A pasta alvo fica "protegida" (não herda da pasta de cima); assim um novo
# salvamento em Compartilhamentos > Usuários não passa por cima dela.

exec python3 - "$@" <<'PY'
import base64, grp, json, os, pwd, re, sys, time, hashlib

BASE = '/srv/samba/Compartilhamentos'
STATUS_DIR = '/var/www/rd.intranet/storage/samba_acl_status'

MASCARAS = {
    'ler': 0x1200a9,        # ler, listar, atravessar
    'gravar': 0x1201bf,     # + criar e alterar, sem DELETE
    'modificar': 0x1301bf,  # + excluir/renomear
    'total': 0x1f01ff,      # + alterar permissões e dono
}


def sair(dados, codigo=0):
    print(json.dumps(dados, ensure_ascii=False))
    sys.exit(codigo)


if len(sys.argv) < 3 or sys.argv[1] not in ('ler', 'aplicar'):
    sair({'success': False, 'message': 'Uso: ler|aplicar <caminho>'}, 1)

acao = sys.argv[1]
rel = sys.argv[2].strip('/')
alvo = os.path.realpath(os.path.join(BASE, rel))
if not alvo.startswith(BASE + '/') or not os.path.exists(alvo) or os.path.islink(os.path.join(BASE, rel)):
    sair({'success': False, 'message': 'Pasta não encontrada.'}, 1)

partes = os.path.relpath(alvo, BASE).split('/')
if len(partes) < 2:
    sair({'success': False, 'message': 'A raiz do compartilhamento é configurada em Samba > Compartilhamentos > Usuários.'}, 1)
raiz_share = os.path.join(BASE, partes[0])
eh_pasta = os.path.isdir(alvo)

try:
    from samba import param
    from samba.dcerpc import security
    from samba.samba3 import param as s3param, passdb, smbd
    from samba.auth_util import system_session_unix
except Exception as e:
    sair({'success': False, 'message': 'Bindings Python do Samba indisponíveis: ' + str(e)}, 1)

lp = param.LoadParm()
lp.load_default()
s3 = s3param.get_context()
s3.load(lp.configfile)
passdb.set_secrets_dir(lp.get('private dir'))
dominio = str(passdb.get_global_sam_sid())
pdb = passdb.PDB(lp.get('passdb backend'))
sessao = system_session_unix()

share = None
for s in s3.services():
    caminho = s3.get('path', s) or ''
    if caminho and os.path.realpath(caminho) == raiz_share:
        share = s
        break
if share is None:
    sair({'success': False, 'message': 'Compartilhamento desta pasta não está ativo no Samba.'}, 1)

FL_DACL = security.SECINFO_DACL


def ler_sd(caminho):
    return smbd.get_nt_acl(caminho, security.SECINFO_OWNER | security.SECINFO_GROUP | FL_DACL,
                           session_info=sessao, service=share)


# ── nomes ────────────────────────────────────────────────────────────────
usuarios = {}   # sid -> login
for u in pdb.search_users(0):
    if u['account_name'] == 'svc_acl_admin':
        continue
    usuarios['%s-%d' % (dominio, u['rid'])] = u['account_name']
grupos = {}     # sid -> nome
for g in grp.getgrall():
    if 1000 <= g.gr_gid < 60000:
        grupos['S-1-22-2-%d' % g.gr_gid] = g.gr_name

ESPECIAIS = {
    'S-1-1-0': 'Todos (Everyone)', 'S-1-3-0': 'Criador/dono', 'S-1-3-1': 'Grupo do criador',
    'S-1-22-1-0': 'root', 'S-1-5-32-546': 'Convidados', '%s-501' % dominio: 'Convidado (anônimo)',
}


def nome_do_sid(sid):
    if sid in usuarios:
        return usuarios[sid], 'usuario'
    if sid in grupos:
        return grupos[sid], 'grupo'
    if sid in ESPECIAIS:
        return ESPECIAIS[sid], 'especial'
    m = re.match(r'^S-1-22-1-(\d+)$', sid)
    if m:
        try:
            return pwd.getpwuid(int(m.group(1))).pw_name, 'usuario'
        except KeyError:
            pass
    m = re.match(r'^S-1-22-2-(\d+)$', sid)
    if m:
        try:
            return grp.getgrgid(int(m.group(1))).gr_name, 'grupo'
        except KeyError:
            pass
    return sid, 'especial'


def nivel_da_mascara(m):
    if m & 0x40000:
        return 'total'
    if m & 0x10000:
        return 'modificar'
    if m & 0x2:
        return 'gravar'
    if m & 0x1:
        return 'ler'
    return 'nenhum'


def acessos(sd):
    """sid -> {'mascara', 'herdado', 'negado'} das entradas que valem para o próprio item."""
    r = {}
    if sd.dacl is None:
        return r
    for ace in sd.dacl.aces:
        if ace.flags & security.SEC_ACE_FLAG_INHERIT_ONLY:
            continue
        sid = str(ace.trustee)
        e = r.setdefault(sid, {'mascara': 0, 'herdado': True, 'negado': False})
        if ace.type == security.SEC_ACE_TYPE_ACCESS_DENIED:
            e['negado'] = True
            continue
        if ace.type != security.SEC_ACE_TYPE_ACCESS_ALLOWED:
            continue
        e['mascara'] |= ace.access_mask
        if not ace.flags & security.SEC_ACE_FLAG_INHERITED_ACE:
            e['herdado'] = False
    return {s: e for s, e in r.items() if e['mascara'] or e['negado']}


def grupos_do_usuario(login):
    sids = set()
    try:
        p = pwd.getpwnam(login)
        sids.add('S-1-22-2-%d' % p.pw_gid)
    except KeyError:
        pass
    for g in grp.getgrall():
        if login in g.gr_mem:
            sids.add('S-1-22-2-%d' % g.gr_gid)
    return sids


# ── ler ──────────────────────────────────────────────────────────────────
if acao == 'ler':
    sd = ler_sd(alvo)
    entradas = []
    for sid, e in acessos(sd).items():
        nome, tipo = nome_do_sid(sid)
        entradas.append({
            'sid': sid, 'nome': nome, 'tipo': tipo, 'nivel': nivel_da_mascara(e['mascara']),
            'mascara': '0x%x' % e['mascara'], 'herdado': e['herdado'], 'negado': e['negado'],
        })
    entradas.sort(key=lambda x: (x['tipo'] != 'grupo', x['tipo'] != 'usuario', x['nome'].lower()))

    # Quem passa por todas as pastas de cima até chegar aqui (com "hide unreadable"
    # a pasta nem aparece para quem não lê a de cima).
    ancestrais = []
    atual = os.path.dirname(alvo)
    while atual.startswith(raiz_share):
        ancestrais.append(acessos(ler_sd(atual)))
        atual = os.path.dirname(atual)

    def chega(sids):
        return all(any(s in a and a[s]['mascara'] & 0x1 for s in sids) for a in ancestrais)

    validos = s3.get('valid users', share) or []
    if isinstance(validos, str):
        validos = validos.split()

    def no_compartilhamento(login, grupos_sids):
        if not validos:
            return True
        nomes_grupos = {grupos.get(s, '') for s in grupos_sids}
        return any(v == login or (v.startswith('@') and v[1:] in nomes_grupos) for v in validos)

    lista_usuarios = []
    for sid, login in sorted(usuarios.items(), key=lambda x: x[1].lower()):
        gs = grupos_do_usuario(login)
        lista_usuarios.append({'sid': sid, 'nome': login, 'chega': chega({sid} | gs),
                               'no_compartilhamento': no_compartilhamento(login, gs)})
    lista_grupos = [{'sid': sid, 'nome': nome, 'chega': chega({sid}), 'no_compartilhamento': (not validos) or ('@' + nome) in validos}
                    for sid, nome in sorted(grupos.items(), key=lambda x: x[1].lower())]

    sair({
        'success': True, 'compartilhamento': share, 'pasta': eh_pasta,
        'protegida': bool(sd.type & security.SEC_DESC_DACL_PROTECTED),
        'entradas': entradas, 'usuarios': lista_usuarios, 'grupos': lista_grupos,
        'convidado_no_compartilhamento': (s3.get('guest ok', share) in (True, 'yes', 'Yes', 1)),
    })

# ── aplicar ──────────────────────────────────────────────────────────────
if len(sys.argv) < 4:
    sair({'success': False, 'message': 'Faltam as permissões.'}, 1)
try:
    dados = json.loads(base64.b64decode(sys.argv[3]).decode())
except Exception:
    sair({'success': False, 'message': 'Permissões inválidas.'}, 1)

novas = []
for e in dados.get('entradas', []):
    sid, nivel = str(e.get('sid', '')), str(e.get('nivel', ''))
    if not re.match(r'^S-1-[0-9]+(-[0-9]+)+$', sid) or nivel not in MASCARAS:
        sair({'success': False, 'message': 'Entrada inválida: %s %s' % (sid, nivel)}, 1)
    novas.append((sid, MASCARAS[nivel]))
if not novas:
    sair({'success': False, 'message': 'Pelo menos um usuário ou grupo precisa ter acesso.'}, 1)
subpastas = bool(dados.get('subpastas')) and eh_pasta

os.makedirs(STATUS_DIR, exist_ok=True)
arquivo_status = os.path.join(STATUS_DIR, 'pasta-' + hashlib.md5(rel.encode()).hexdigest() + '.json')


def status(estado, feitos, total, mensagem=''):
    tmp = arquivo_status + '.tmp'
    with open(tmp, 'w') as f:
        json.dump({'status': estado, 'processados': feitos, 'total': total,
                   'percentual': int(feitos * 100 / total) if total else 100,
                   'mensagem': mensagem, 'atualizado_em': int(time.time())}, f, ensure_ascii=False)
    os.chmod(tmp, 0o644)
    os.replace(tmp, arquivo_status)


def montar(flags, protegida):
    sddl = 'D:' + ('PAI' if protegida else 'AI') + ''.join('(A;%s;0x%x;;;%s)' % (flags, m, s) for s, m in novas)
    return security.descriptor.from_sddl(sddl, security.dom_sid(dominio))


total = 1
if subpastas:
    for _, dirs, files in os.walk(alvo):
        total += len(dirs) + len(files)
status('rodando', 0, total)

erros = []
try:
    smbd.set_nt_acl(alvo, FL_DACL, montar('OICI' if eh_pasta else '', True), session_info=sessao, service=share)
except Exception as e:
    status('erro', 0, total, str(e))
    sair({'success': False, 'message': 'Falha ao gravar a permissão: ' + str(e)}, 1)

feitos = 1
if subpastas:
    sd_pasta = montar('OICIID', False)
    sd_arquivo = montar('ID', False)
    for raiz, dirs, files in os.walk(alvo):
        for nome in dirs + files:
            caminho = os.path.join(raiz, nome)
            feitos += 1
            if os.path.islink(caminho):
                continue
            try:
                smbd.set_nt_acl(caminho, FL_DACL, sd_pasta if nome in dirs else sd_arquivo,
                                session_info=sessao, service=share)
            except Exception as e:
                if len(erros) < 20:
                    erros.append('%s: %s' % (os.path.relpath(caminho, alvo), e))
            if feitos % 500 == 0:
                status('rodando', feitos, total)

mensagem = ('%d itens não puderam ser ajustados: ' % len(erros) + '; '.join(erros[:5])) if erros else ''
status('concluido', total, total, mensagem)
sair({'success': True, 'message': 'Permissões aplicadas.' + (' ' + mensagem if mensagem else ''), 'itens': total})
PY
