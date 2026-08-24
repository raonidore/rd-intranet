<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Documento dentro de uma categoria. Anexo tem a mesma dupla origem
 * de ContratoService (upload novo em storage/documentos/, ou
 * referência a um arquivo já existente no Samba). Toda vez que o
 * anexo é substituído, a versão anterior vira uma linha em
 * documentos_versoes -- histórico de quem trocou o quê, sem perder o
 * arquivo antigo (upload antigo continua em disco, só não é mais o
 * "atual").
 */
class DocumentoService
{
    public const TAMANHO_MAXIMO = 16 * 1024 * 1024; // 16MB

    private const EXTENSOES_POR_MIME = [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif',
        'application/pdf' => 'pdf', 'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'text/plain' => 'txt', 'application/zip' => 'zip',
    ];

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public static function diretorio(): string
    {
        $dir = __DIR__ . '/../../storage/documentos';

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
        return uniqid('documento_', true) . '.' . $extensao;
    }

    public static function caminhoCompletoUpload(string $nomeArquivo): string
    {
        return self::diretorio() . '/' . basename($nomeArquivo);
    }

    public function listarPorCategoria(int $categoriaId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM documentos WHERE categoria_id = ? ORDER BY titulo');
        $stmt->execute([$categoriaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT d.*, c.nome AS categoria_nome FROM documentos d JOIN documentos_categorias c ON c.id = d.categoria_id WHERE d.id = ?'
        );
        $stmt->execute([$id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function versoes(int $documentoId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT dv.*, u.nome AS substituido_por_nome
             FROM documentos_versoes dv
             LEFT JOIN usuarios u ON u.id = dv.substituido_por
             WHERE dv.documento_id = ? ORDER BY dv.versao DESC'
        );
        $stmt->execute([$documentoId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{success: bool, message: string, id?: int} */
    public function criar(array $dados): array
    {
        $categoriaId = (int)($dados['categoria_id'] ?? 0);
        $titulo = trim($dados['titulo'] ?? '');

        if (!$categoriaId) {
            return ['success' => false, 'message' => 'Selecione a categoria.'];
        }
        if ($titulo === '') {
            return ['success' => false, 'message' => 'Informe o título do documento.'];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO documentos (categoria_id, titulo, descricao, criado_por, atualizado_por) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $categoriaId,
            $titulo,
            trim($dados['descricao'] ?? '') ?: null,
            $dados['usuario_id'] ?? null,
            $dados['usuario_id'] ?? null,
        ]);

        return ['success' => true, 'message' => 'Documento cadastrado.', 'id' => (int)$this->pdo->lastInsertId()];
    }

    /** @return array{success: bool, message: string} */
    public function atualizar(int $id, array $dados): array
    {
        $titulo = trim($dados['titulo'] ?? '');
        if ($titulo === '') {
            return ['success' => false, 'message' => 'Informe o título do documento.'];
        }

        $stmt = $this->pdo->prepare(
            'UPDATE documentos SET titulo = ?, descricao = ?, atualizado_por = ? WHERE id = ?'
        );
        $stmt->execute([$titulo, trim($dados['descricao'] ?? '') ?: null, $dados['usuario_id'] ?? null, $id]);

        return ['success' => true, 'message' => 'Documento atualizado.'];
    }

    /** @return array{success: bool, message: string} */
    public function excluir(int $id): array
    {
        $documento = $this->buscar($id);
        if ($documento && $documento['anexo_origem'] === 'upload' && $documento['anexo_caminho']) {
            @unlink(self::caminhoCompletoUpload($documento['anexo_caminho']));
        }

        foreach ($this->versoes($id) as $versao) {
            if ($versao['anexo_origem'] === 'upload' && $versao['anexo_caminho']) {
                @unlink(self::caminhoCompletoUpload($versao['anexo_caminho']));
            }
        }

        $this->pdo->prepare('DELETE FROM documentos WHERE id = ?')->execute([$id]);

        return ['success' => true, 'message' => 'Documento removido.'];
    }

    /** @return array{success: bool, message: string} */
    public function definirAnexoUpload(int $id, array $arquivo, ?int $usuarioId): array
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
        $caminhoCompleto = self::caminhoCompletoUpload($nomeArquivo);

        if (!move_uploaded_file($arquivo['tmp_name'], $caminhoCompleto)) {
            return ['success' => false, 'message' => 'Falha ao salvar o arquivo.'];
        }

        $this->arquivarVersaoAnterior($id, $usuarioId);

        $stmt = $this->pdo->prepare(
            "UPDATE documentos SET anexo_origem = 'upload', anexo_caminho = ?, anexo_nome_original = ?, versao = versao + 1, atualizado_por = ? WHERE id = ?"
        );
        $stmt->execute([$nomeArquivo, $arquivo['name'], $usuarioId, $id]);

        return ['success' => true, 'message' => 'Arquivo enviado.'];
    }

    /** @return array{success: bool, message: string} */
    public function definirAnexoSamba(int $id, string $caminhoCompleto, string $nomeOriginal, ?int $usuarioId): array
    {
        $this->arquivarVersaoAnterior($id, $usuarioId);

        $stmt = $this->pdo->prepare(
            "UPDATE documentos SET anexo_origem = 'samba', anexo_caminho = ?, anexo_nome_original = ?, versao = versao + 1, atualizado_por = ? WHERE id = ?"
        );
        $stmt->execute([$caminhoCompleto, $nomeOriginal, $usuarioId, $id]);

        return ['success' => true, 'message' => 'Arquivo vinculado.'];
    }

    private function arquivarVersaoAnterior(int $id, ?int $usuarioId): void
    {
        $documento = $this->buscar($id);
        if (!$documento || !$documento['anexo_origem']) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO documentos_versoes (documento_id, versao, anexo_origem, anexo_caminho, anexo_nome_original, substituido_por)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $id,
            $documento['versao'],
            $documento['anexo_origem'],
            $documento['anexo_caminho'],
            $documento['anexo_nome_original'],
            $usuarioId,
        ]);
    }
}
