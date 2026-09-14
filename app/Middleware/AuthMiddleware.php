<?php

namespace App\Middleware;

use App\Services\PermissionService;
use App\Services\UsuarioOnlineService;

class AuthMiddleware
{
    public static function check(): void
    {
        if (!isset($_SESSION['usuario'])) {
            header('Location: ' . url('/login'));
            exit;
        }

        (new UsuarioOnlineService())->registrarAcesso((int)$_SESSION['usuario']['id']);
    }

    public static function checkModulo(string $modulo): void
    {
        self::check();

        if (!PermissionService::temAcesso($modulo)) {
            self::negarAcesso();
        }
    }

    /** Libera se o usuário tiver QUALQUER UM dos módulos listados (ex: tela que serve tanto quem atende quanto quem só abre chamado). */
    public static function checkQualquerModulo(array $modulos): void
    {
        self::check();

        foreach ($modulos as $modulo) {
            if (PermissionService::temAcesso($modulo)) {
                return;
            }
        }

        self::negarAcesso();
    }

    /** Ver PermissionService::temAcessoRestrito() -- sem bypass de admin. */
    public static function checkModuloRestrito(string $modulo): void
    {
        self::check();

        if (!PermissionService::temAcessoRestrito($modulo)) {
            self::negarAcesso();
        }
    }

    public static function checkAdmin(): void
    {
        self::check();

        if (!PermissionService::ehAdmin()) {
            self::negarAcesso();
        }
    }

    private static function negarAcesso(): void
    {
        http_response_code(403);
        require __DIR__ . '/../Views/erros/403.php';
        exit;
    }
}
