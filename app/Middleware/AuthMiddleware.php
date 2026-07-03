<?php

namespace App\Middleware;

class AuthMiddleware
{
    public static function check(): void
    {
        if (!isset($_SESSION['usuario'])) {
            header('Location: ' . url('/login'));
            exit;
        }
    }
}
