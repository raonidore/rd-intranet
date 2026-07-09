<?php

namespace App\Services;

use RuntimeException;

class CryptoService
{
    private const CHAVE_PATH = '/etc/rd-intranet/db_secret.key';
    private const CIFRA = 'aes-256-gcm';

    private static ?string $chave = null;

    private static function chave(): string
    {
        if (self::$chave !== null) {
            return self::$chave;
        }

        if (!is_readable(self::CHAVE_PATH)) {
            throw new RuntimeException(
                'Chave de criptografia não encontrada em ' . self::CHAVE_PATH .
                '. Rode scripts/system/setup_db_secret_key.sh como root.'
            );
        }

        self::$chave = base64_decode(trim(file_get_contents(self::CHAVE_PATH)));

        return self::$chave;
    }

    public static function encriptar(string $texto): string
    {
        $iv = random_bytes(12);
        $tag = '';

        $cifrado = openssl_encrypt($texto, self::CIFRA, self::chave(), OPENSSL_RAW_DATA, $iv, $tag);

        return base64_encode($iv . $tag . $cifrado);
    }

    public static function decriptar(string $cifradoBase64): string
    {
        $dados = base64_decode($cifradoBase64);

        $iv = substr($dados, 0, 12);
        $tag = substr($dados, 12, 16);
        $cifrado = substr($dados, 28);

        $texto = openssl_decrypt($cifrado, self::CIFRA, self::chave(), OPENSSL_RAW_DATA, $iv, $tag);

        if ($texto === false) {
            throw new RuntimeException('Não foi possível decifrar o valor (chave incorreta ou dado corrompido).');
        }

        return $texto;
    }
}
