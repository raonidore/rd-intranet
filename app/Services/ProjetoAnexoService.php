<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Anexo de projeto/tarefa -- cópia do bloco de anexo de
 * ChamadoExternoService (upload/samba/excluir/renomear/baixar), com
 * `heic`/`heif` adicionados à lista aceita (formato padrão da câmera
 * do iPhone, relevante agora que existe captura direto pelo celular).
 */
class ProjetoAnexoService
{
    public const TAMANHO_MAXIMO = 16 * 1024 * 1024; // 16MB

    private const EXTENSOES_POR_MIME = [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif',
        'image/heic' => 'heic', 'image/heif' => 'heif',
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
        $dir = __DIR__ . '/../../storage/projetos';

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    public static function extensaoPorMimetype(string $mimetype): ?string
    {
        return self::EXTENSOES_POR_MIME[$mimetype] ?? null;
    }

    /** Mesma lógica de ChamadoExternoService::mimetypeParaVisualizar() -- só pro pop-up de visualização de anexo vindo do Samba. */
    public static function mimetypeParaVisualizar(string $extensao): string
    {
        $mapa = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
            'gif' => 'image/gif', 'webp' => 'image/webp', 'bmp' => 'image/bmp',
            'heic' => 'image/heic', 'heif' => 'image/heif',
        ];

        return $mapa[strtolower($extensao)] ?? 'application/octet-stream';
    }

    public static function gerarNomeArquivo(string $extensao): string
    {
        return uniqid('proj_', true) . '.' . $extensao;
    }

    public static function caminhoCompletoUpload(string $nomeArquivo): string
    {
        return self::diretorio() . '/' . basename($nomeArquivo);
    }

    public function porProjeto(int $projetoId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.*, u.nome AS usuario_nome, pe.nome AS participante_nome, t.titulo AS tarefa_titulo
             FROM projetos_anexos a
             LEFT JOIN usuarios u ON u.id = a.usuario_id
             LEFT JOIN projetos_participantes_externos pe ON pe.id = a.participante_externo_id
             LEFT JOIN projetos_tarefas t ON t.id = a.tarefa_id
             WHERE a.projeto_id = ?
             ORDER BY a.criado_em DESC'
        );
        $stmt->execute([$projetoId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cria o anexo já ligado a um comentário (usado pelo composer
     * único de celular -- comentário + foto na mesma requisição).
     *
     * @return array{success: bool, message: string, id?: int}
     */
    public function anexarUploadComComentario(int $projetoId, ?int $tarefaId, int $comentarioId, array $arquivo, ?int $usuarioId, ?int $participanteExternoId): array
    {
        $resultado = $this->salvarUpload($arquivo);
        if (!$resultado['success']) {
            return $resultado;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO projetos_anexos (projeto_id, tarefa_id, comentario_id, anexo_origem, anexo_caminho, anexo_nome_original, usuario_id, participante_externo_id)
             VALUES (?, ?, ?, 'upload', ?, ?, ?, ?)"
        );
        $stmt->execute([$projetoId, $tarefaId, $comentarioId, $resultado['nome_arquivo'], $arquivo['name'], $usuarioId, $participanteExternoId]);

        return ['success' => true, 'message' => 'Anexo enviado.', 'id' => (int)$this->pdo->lastInsertId()];
    }

    /** @return array{success: bool, message: string} */
    public function anexarUpload(int $projetoId, ?int $tarefaId, array $arquivo, ?int $usuarioId): array
    {
        $resultado = $this->salvarUpload($arquivo);
        if (!$resultado['success']) {
            return $resultado;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO projetos_anexos (projeto_id, tarefa_id, anexo_origem, anexo_caminho, anexo_nome_original, usuario_id)
             VALUES (?, ?, 'upload', ?, ?, ?)"
        );
        $stmt->execute([$projetoId, $tarefaId, $resultado['nome_arquivo'], $arquivo['name'], $usuarioId]);

        (new ProjetoComentarioService())->registrarSistema($projetoId, $tarefaId, 'Anexou o arquivo "' . $arquivo['name'] . '".', $usuarioId, null);

        return ['success' => true, 'message' => 'Anexo enviado.'];
    }

    /** @return array{success: bool, message: string} */
    public function anexarSamba(int $projetoId, ?int $tarefaId, string $caminhoCompleto, string $nomeOriginal, ?int $usuarioId): array
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO projetos_anexos (projeto_id, tarefa_id, anexo_origem, anexo_caminho, anexo_nome_original, usuario_id)
             VALUES (?, ?, 'samba', ?, ?, ?)"
        );
        $stmt->execute([$projetoId, $tarefaId, $caminhoCompleto, $nomeOriginal, $usuarioId]);

        (new ProjetoComentarioService())->registrarSistema($projetoId, $tarefaId, 'Vinculou o arquivo "' . $nomeOriginal . '" (Samba).', $usuarioId, null);

        return ['success' => true, 'message' => 'Anexo vinculado.'];
    }

    public function buscarAnexo(int $anexoId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM projetos_anexos WHERE id = ?');
        $stmt->execute([$anexoId]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    /** @return array{success: bool, message: string} */
    public function excluirAnexo(int $anexoId, ?int $usuarioId = null): array
    {
        $anexo = $this->buscarAnexo($anexoId);
        if (!$anexo) {
            return ['success' => false, 'message' => 'Anexo não encontrado.'];
        }

        if ($anexo['anexo_origem'] === 'upload') {
            @unlink(self::caminhoCompletoUpload($anexo['anexo_caminho']));
        }

        $this->pdo->prepare('DELETE FROM projetos_anexos WHERE id = ?')->execute([$anexoId]);

        (new ProjetoComentarioService())->registrarSistema((int)$anexo['projeto_id'], $anexo['tarefa_id'] !== null ? (int)$anexo['tarefa_id'] : null, 'Removeu o anexo "' . $anexo['anexo_nome_original'] . '".', $usuarioId, null);

        return ['success' => true, 'message' => 'Anexo removido.'];
    }

    /** @return array{success: bool, message: string} */
    public function renomearAnexo(int $anexoId, string $novoNome, ?int $usuarioId = null): array
    {
        $novoNome = trim($novoNome);
        if ($novoNome === '') {
            return ['success' => false, 'message' => 'Informe o novo nome do anexo.'];
        }

        $anexo = $this->buscarAnexo($anexoId);
        if (!$anexo) {
            return ['success' => false, 'message' => 'Anexo não encontrado.'];
        }

        $nomeAntigo = $anexo['anexo_nome_original'];

        $this->pdo->prepare('UPDATE projetos_anexos SET anexo_nome_original = ? WHERE id = ?')->execute([$novoNome, $anexoId]);

        (new ProjetoComentarioService())->registrarSistema((int)$anexo['projeto_id'], $anexo['tarefa_id'] !== null ? (int)$anexo['tarefa_id'] : null, 'Renomeou o anexo "' . $nomeAntigo . '" para "' . $novoNome . '".', $usuarioId, null);

        return ['success' => true, 'message' => 'Anexo renomeado.'];
    }

    /** @return array{success: bool, message: string, nome_arquivo?: string} */
    private function salvarUpload(array $arquivo): array
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

        return ['success' => true, 'message' => 'ok', 'nome_arquivo' => $nomeArquivo];
    }
}
