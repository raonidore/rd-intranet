<?php

namespace App\Services;

class PermissionService
{
    public static function temAcesso(string $modulo): bool
    {
        // Grupo desligado pra instalação inteira (Sistema > Módulos) --
        // ninguém vê, nem admin. É um toggle de "esse cliente usa esse
        // módulo", não uma permissão individual, então vem antes de
        // qualquer bypass.
        $grupo = ModuloCatalogo::grupoDoModulo($modulo);
        if ($grupo !== null && !ModuloCatalogo::grupoHabilitado($grupo)) {
            return false;
        }

        $usuario = $_SESSION['usuario'] ?? null;

        if (!$usuario) {
            return false;
        }

        if (($usuario['perfil'] ?? null) === 'admin') {
            return true;
        }

        return in_array($modulo, $usuario['modulos'] ?? [], true);
    }

    public static function ehAdmin(): bool
    {
        return ($_SESSION['usuario']['perfil'] ?? null) === 'admin';
    }
}
