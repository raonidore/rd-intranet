#!/bin/bash
# sudo_probe_web.sh
# Nao faz nada alem de confirmar que o www-data consegue rodar, sem
# senha, um script recem-publicado em /opt/rdtecnologia/scripts. Serve
# so de sonda pra tela Administracao > Atualizacoes detectar sozinha se
# a regra coringa de sudoers (scripts/grant-sudo-wildcard.sh) ja foi
# aplicada nesse servidor, sem precisar que o admin confirme na mao.
echo "OK"
