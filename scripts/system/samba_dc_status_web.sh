#!/bin/bash
# samba_dc_status_web.sh
#
# Unica fonte de verdade de "este servidor ja e um Controlador de Dominio
# (Active Directory)?" -- nunca confiar numa flag salva em banco, sempre ler
# o estado real do sistema (mesmo espirito que SambaGlobalConfigService ja
# usa pro [global] standalone, que tambem nunca cacheia o smb.conf em
# banco). "e DC" exige as DUAS condicoes: server role reportado pelo
# testparm E a existencia do banco do dominio (sam.ldb) -- so a segunda
# poderia sobreviver a uma reversao manual malfeita, so a primeira poderia
# vir de um smb.conf editado a mao sem provisionamento de verdade.

set -u

SERVER_ROLE=$(testparm -s --parameter-name="server role" 2>/dev/null | tr -d '\n')
REALM=$(testparm -s --parameter-name="realm" 2>/dev/null | tr -d '\n')
WORKGROUP=$(testparm -s --parameter-name="workgroup" 2>/dev/null | tr -d '\n')
HOSTNAME_CURTO=$(hostname)
HOSTNAME_FQDN=$(hostname -f 2>/dev/null || echo "$HOSTNAME_CURTO")

SAM_LDB_EXISTE="false"
[ -f /var/lib/samba/private/sam.ldb ] && SAM_LDB_EXISTE="true"

status_servico() {
  systemctl is-active "$1" 2>/dev/null || echo "unknown"
}

SMBD=$(status_servico smbd)
NMBD=$(status_servico nmbd)
SAMBA_AD_DC=$(status_servico samba-ad-dc)
WINBIND=$(status_servico winbind)

php -r '
    $serverRole = trim($argv[1]);
    $samLdbExiste = $argv[2] === "true";
    $ehDc = $samLdbExiste && stripos($serverRole, "domain controller") !== false;

    echo json_encode([
        "is_dc" => $ehDc,
        "server_role" => $serverRole,
        "sam_ldb_existe" => $samLdbExiste,
        "realm" => trim($argv[3]),
        "workgroup" => trim($argv[4]),
        "hostname" => trim($argv[5]),
        "fqdn" => trim($argv[6]),
        "servicos" => [
            "smbd" => trim($argv[7]),
            "nmbd" => trim($argv[8]),
            "samba-ad-dc" => trim($argv[9]),
            "winbind" => trim($argv[10]),
        ],
    ]);
' -- "$SERVER_ROLE" "$SAM_LDB_EXISTE" "$REALM" "$WORKGROUP" "$HOSTNAME_CURTO" "$HOSTNAME_FQDN" "$SMBD" "$NMBD" "$SAMBA_AD_DC" "$WINBIND"
