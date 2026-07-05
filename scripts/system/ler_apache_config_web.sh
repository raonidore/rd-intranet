#!/bin/bash
# ler_apache_config_web.sh — mostra os valores efetivos das diretivas
# gerenciadas. Le primeiro do arquivo proprio da RD Intranet
# (conf-available/rd-intranet.conf); se ainda nao existir, cai pro
# apache2.conf padrao da distro (pra mostrar o valor real em vigor).

ARQUIVO_RD="/etc/apache2/conf-available/rd-intranet.conf"

if [ -f "$ARQUIVO_RD" ]; then
  grep -E "^\s*(ServerName|Timeout|KeepAlive|MaxKeepAliveRequests|KeepAliveTimeout|ServerTokens|ServerSignature|LogLevel)\s" "$ARQUIVO_RD"
else
  grep -E "^\s*(ServerName|Timeout|KeepAlive|MaxKeepAliveRequests|KeepAliveTimeout|ServerTokens|ServerSignature|LogLevel)\s" /etc/apache2/apache2.conf
fi
