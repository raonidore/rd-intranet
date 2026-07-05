<?php

namespace App\Middleware;

use App\Services\PermissionService;

class AuthMiddleware
{
    public static function check(): void
    {
        if (!isset($_SESSION['usuario'])) {
            header('Location: ' . url('/login'));
            exit;
        }
    }

    public static function checkModulo(string $modulo): void
    {
        self::check();

        if (!PermissionService::temAcesso($modulo)) {
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
