<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Anexos de chamado (print de erro, nota fiscal etc.) -- mesmo padrão
 * de armazenamento do WhatsAppMidiaService (fora de public/, servido
 * só por rota autenticada que confere posse), tipo aceito mais amplo
 * (qualquer documento/imagem comum de escritório).
 */
class ChamadoAnexoService
{
    public const TAMANHO_MAXIMO = 16 * 1024 * 1024; // 16MB

    private const EXTENSOES_POR_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'text/plain' => 'txt',
        'application/zip' => 'zip',
    ];

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public static function diretorio(): string
    {
        $dir = __DIR__ . '/../../storage/chamados';

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    public static function extensaoPorMimetype(string $mimetype): ?string
    {
        return self::EXTENSOES_POR_MIME[$mimetype] ?? null;
    }

    public static function gerarNomeArquivo(string $extensao): string
    {
        return uniqid('anexo_', true) . '.' . $extensao;
    }

    /** @return array{success: bool, message: string} */
    public function salvar(int $chamadoId, array $arquivo, ?int $usuarioId, ?int $comentarioId = null): array
    {
        if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Selecione um arquivo.'];
        }

        if ((int)$arquivo['size'] > self::TAMANHO_MAXIMO) {
            return ['success' => false, 'message' => 'Arquivo maior que 16MB.'];
        }

        $mimetype = mime_content_type($arquivo['tmp_name']) ?: 'application/octet-stream';
        $extensao = self::extensaoPorMimetype($mimetype);

        if ($extensao === null) {
            return ['success' => false, 'message' => 'Tipo de arquivo não suportado.'];
        }

        $nomeArquivo = self::gerarNomeArquivo($extensao);
        $caminhoCompleto = self::diretorio() . '/' . $nomeArquivo;

        if (!move_uploaded_file($arquivo['tmp_name'], $caminhoCompleto)) {
            return ['success' => false, 'message' => 'Falha ao salvar o arquivo.'];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO chamados_anexos (chamado_id, comentario_id, caminho_arquivo, nome_original, tipo_mime, tamanho_bytes, usuario_id) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$chamadoId, $comentarioId, $nomeArquivo, $arquivo['name'], $mimetype, (int)$arquivo['size'], $usuarioId]);

        return ['success' => true, 'message' => 'Anexo enviado.'];
    }

    public function listarPorChamado(int $chamadoId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.*, u.nome AS usuario_nome FROM chamados_anexos a LEFT JOIN usuarios u ON u.id = a.usuario_id WHERE a.chamado_id = ? ORDER BY a.id ASC'
        );
        $stmt->execute([$chamadoId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM chamados_anexos WHERE id = ?');
        $stmt->execute([$id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function caminhoCompleto(string $nomeArquivo): string
    {
        return self::diretorio() . '/' . basename($nomeArquivo);
    }
}
