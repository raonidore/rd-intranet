<?php

namespace App\Services;

use App\Repositories\ConfiguracaoRepository;

class ConfigService
{
    private static ?ConfiguracaoRepository $repository = null;

    private static function repo(): ConfiguracaoRepository
    {
        if (self::$repository === null) {
            self::$repository = new ConfiguracaoRepository();
        }

        return self::$repository;
    }

    public static function get(string $chave, ?string $padrao = null): ?string
    {
        return self::repo()->buscar($chave, $padrao);
    }

    public static function all(): array
    {
        return self::repo()->todos();
    }

    public static function set(string $chave, string $valor): void
    {
        self::repo()->atualizar($chave, $valor);
    }
}
