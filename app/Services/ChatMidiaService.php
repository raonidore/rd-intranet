<?php

namespace App\Services;

/**
 * Guarda/serve os anexos trocados no chat -- fora de public/ de
 * propósito (sem URL direta "adivinhável"; quem quiser ver precisa
 * passar pela rota autenticada em ChatController::midiaApi(), que
 * confere se quem pede é participante da conversa). Mesmo padrão de
 * WhatsAppMidiaService, mesma lista de tipos aceitos.
 */
class ChatMidiaService
{
    public const TAMANHO_MAXIMO = 16 * 1024 * 1024; // 16MB

    private const EXTENSOES_POR_MIME = [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif',
        'audio/ogg' => 'ogg', 'audio/mpeg' => 'mp3', 'audio/mp4' => 'm4a', 'audio/wav' => 'wav',
        'application/pdf' => 'pdf', 'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'text/plain' => 'txt', 'application/zip' => 'zip',
    ];

    public static function diretorio(): string
    {
        $dir = __DIR__ . '/../../storage/chat';

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    public static function extensaoPorMimetype(string $mimetype): string
    {
        return self::EXTENSOES_POR_MIME[$mimetype] ?? 'bin';
    }

    public static function tipoPorMimetype(string $mimetype): ?string
    {
        if (str_starts_with($mimetype, 'image/')) {
            return 'imagem';
        }
        if (str_starts_with($mimetype, 'audio/')) {
            return 'audio';
        }

        return isset(self::EXTENSOES_POR_MIME[$mimetype]) ? 'documento' : null;
    }

    public static function gerarNomeArquivo(string $mimetype): string
    {
        return uniqid('chat_', true) . '.' . self::extensaoPorMimetype($mimetype);
    }

    public static function caminhoCompleto(string $nomeArquivo): string
    {
        return self::diretorio() . '/' . basename($nomeArquivo);
    }
}
