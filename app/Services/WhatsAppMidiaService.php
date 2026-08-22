<?php

namespace App\Services;

/**
 * Guarda/serve os arquivos de anexo (imagem/áudio/documento) trocados
 * no atendimento -- fora de public/ de propósito (não é pra ter URL
 * direta e "adivinhável"; quem quiser ver precisa passar pela rota
 * autenticada em WhatsAppAtendimentoController::midia(), que confere
 * se o anexo é de um atendimento do usuário logado).
 */
class WhatsAppMidiaService
{
    public const TAMANHO_MAXIMO = 16 * 1024 * 1024; // 16MB -- mesmo teto de imagem/áudio/documento comuns do WhatsApp

    private const EXTENSOES_POR_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'audio/ogg' => 'ogg',
        'audio/ogg; codecs=opus' => 'ogg',
        'audio/mpeg' => 'mp3',
        'audio/mp4' => 'm4a',
        'audio/aac' => 'aac',
        'audio/wav' => 'wav',
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'text/plain' => 'txt',
        'application/zip' => 'zip',
    ];

    public static function diretorio(): string
    {
        $dir = __DIR__ . '/../../storage/whatsapp';

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    public static function extensaoPorMimetype(string $mimetype): string
    {
        return self::EXTENSOES_POR_MIME[$mimetype] ?? 'bin';
    }

    /**
     * 'imagem'/'audio'/'documento' (mesmos valores do enum `tipo` de
     * whatsapp_mensagens) a partir do mimetype real do arquivo -- null
     * pra qualquer coisa fora da lista de documento reconhecida (não
     * aceita "qualquer" mimetype como documento, só os catalogados).
     */
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

    /**
     * Nome de arquivo seguro (sem path traversal, sem depender do
     * nome que o cliente mandou) -- sempre gerado aqui, nunca aceito
     * cru de fora.
     */
    public static function gerarNomeArquivo(string $mimetype): string
    {
        return uniqid('wpp_', true) . '.' . self::extensaoPorMimetype($mimetype);
    }

    public static function caminhoCompleto(string $nomeArquivo): string
    {
        return self::diretorio() . '/' . basename($nomeArquivo);
    }

    public static function salvarBase64(string $base64, string $mimetype): ?string
    {
        $bytes = base64_decode($base64, true);

        if ($bytes === false || $bytes === '' || strlen($bytes) > self::TAMANHO_MAXIMO) {
            return null;
        }

        $nomeArquivo = self::gerarNomeArquivo($mimetype);

        if (file_put_contents(self::caminhoCompleto($nomeArquivo), $bytes) === false) {
            return null;
        }

        return $nomeArquivo;
    }
}
