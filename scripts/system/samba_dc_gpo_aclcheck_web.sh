#!/bin/bash
# samba_dc_gpo_aclcheck_web.sh
#
# "samba-tool gpo aclcheck" -- verifica se as permissoes do SYSVOL estao
# corretas (causa nº1 de "a GPO existe mas nao aplica"). So leitura,
# nao corrige nada sozinho.

set -u

SAIDA=$(samba-tool gpo aclcheck 2>&1)
CODIGO=$?

php -r 'echo json_encode(["success" => $argv[2] === "0", "message" => $argv[1] !== "" ? $argv[1] : "SYSVOL ACL ok."]);' -- "$SAIDA" "$CODIGO"
