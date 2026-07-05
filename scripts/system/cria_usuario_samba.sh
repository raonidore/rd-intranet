#!/bin/bash

# ==========================================================
# RD Tecnologia
# Criação de Usuário Samba
# Versão: 1.0
# ==========================================================

clear

echo "====================================================="
echo "      RD Tecnologia - Criação de Usuário Samba"
echo "====================================================="
echo

read -p "Nome completo: " NOME
read -p "Login (somente letras e números): " LOGIN

echo
echo "Departamento:"
echo "1 - TI"
echo "2 - Financeiro"
echo "3 - Cobrança"
echo

read -p "Escolha uma opção: " OPCAO

case $OPCAO in
    1)
        GRUPO="ti"
        ;;
    2)
        GRUPO="financeiro"
        ;;
    3)
        GRUPO="cobranca"
        ;;
    *)
        echo
        echo "Opção inválida!"
        exit 1
        ;;
esac

echo
echo "Criando usuário..."

sudo adduser \
    --shell /usr/sbin/nologin \
    --gecos "$NOME,,," \
    "$LOGIN"

echo
echo "Adicionando aos grupos..."

sudo usermod -aG smbusers "$LOGIN"
sudo usermod -aG "$GRUPO" "$LOGIN"

echo
echo "Criando senha do Samba..."

sudo smbpasswd -a "$LOGIN"
sudo smbpasswd -e "$LOGIN"

echo
echo "=============================================="
echo "Usuário criado com sucesso!"
echo "=============================================="
echo
echo "Nome.........: $NOME"
echo "Login........: $LOGIN"
echo "Departamento.: $GRUPO"
echo
echo "Grupos:"
id "$LOGIN"
echo
echo "Usuário Samba:"
sudo pdbedit -L | grep "^$LOGIN:"
echo
echo "Processo concluído com sucesso."
