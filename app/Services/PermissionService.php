<?php

namespace App\Services;

class PermissionService
{
    public static function temAcesso(string $modulo): bool
    {
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
