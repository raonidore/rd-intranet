#!/bin/bash
# Encerra uma sessão smbd pelo PID do processo filho
PID="$1"

if [[ ! "$PID" =~ ^[0-9]+$ ]]; then
    echo '{"success":false,"message":"PID inválido"}'; exit 1
fi

PROC_NAME=$(ps -p "$PID" -o comm= 2>/dev/null)
if [ -z "$PROC_NAME" ]; then
    echo '{"success":false,"message":"Processo não encontrado"}'; exit 1
fi

if [ "$PROC_NAME" != "smbd" ]; then
    echo "{\"success\":false,\"message\":\"Processo $PID não é smbd\"}"; exit 1
fi

kill "$PID" 2>/dev/null
if [ $? -eq 0 ]; then
    echo '{"success":true,"message":"Sessão encerrada com sucesso"}'
else
    echo '{"success":false,"message":"Falha ao encerrar processo"}'; exit 1
fi
