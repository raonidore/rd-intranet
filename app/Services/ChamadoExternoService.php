<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Chamado aberto COM um fornecedor (operadora, prestador de
 * manutenção) pra resolver um problema interno -- direção oposta do
 * chamado interno (aqui somos o cliente). Timeline em
 * chamados_externos_comentarios mistura notas manuais ('nota') com
 * linhas automáticas de mudança de status ('sistema'), então o
 * histórico de "o que foi feito" fica completo sem esforço extra de
 * quem está usando.
 */
class ChamadoExternoService
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

    private const STATUS_VALIDOS = ['aberto', 'aguardando_fornecedor', 'em_andamento', 'resolvido', 'fechado'];
    private const STATUS_LABEL = [
        'aberto' => 'Aberto',
        'aguardando_fornecedor' => 'Aguardando fornecedor',
        'em_andamento' => 'Em andamento',
        'resolvido' => 'Resolvido',
        'fechado' => 'Fechado',
    ];
    private const PRIORIDADES_VALIDAS = ['baixa', 'media', 'alta', 'urgente'];

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public static function statusLabel(string $status): string
    {
        return self::STATUS_LABEL[$status] ?? $status;
    }

    /** @return array<string, string> */
    public static function statusLabelTodos(): array
    {
        return self::STATUS_LABEL;
    }

    public static function diretorio(): string
    {
        $dir = __DIR__ . '/../../storage/chamados_externos';

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
        return uniqid('chext_', true) . '.' . $extensao;
    }

    public static function caminhoCompletoUpload(string $nomeArquivo): string
    {
        return self::diretorio() . '/' . basename($nomeArquivo);
    }

    /** @param array{status?:string, fornecedor_id?:int, categoria_id?:int, ativo_id?:int} $filtros */
    public function listar(array $filtros = []): array
    {
        $sql = "SELECT ce.*, f.nome_fantasia AS fornecedor_nome, cat.nome AS categoria_nome, a.codigo_patrimonio AS ativo_patrimonio
                FROM chamados_externos ce
                JOIN fornecedores f ON f.id = ce.fornecedor_id
                LEFT JOIN chamados_externos_categorias cat ON cat.id = ce.categoria_id
                LEFT JOIN ativos a ON a.id = ce.ativo_id
                WHERE 1=1";
        $params = [];

        if (!empty($filtros['status'])) {
            $sql .= ' AND ce.status = ?';
            $params[] = $filtros['status'];
        }
        if (!empty($filtros['fornecedor_id'])) {
            $sql .= ' AND ce.fornecedor_id = ?';
            $params[] = (int)$filtros['fornecedor_id'];
        }
        if (!empty($filtros['categoria_id'])) {
            $sql .= ' AND ce.categoria_id = ?';
            $params[] = (int)$filtros['categoria_id'];
        }
        if (!empty($filtros['ativo_id'])) {
            $sql .= ' AND ce.ativo_id = ?';
            $params[] = (int)$filtros['ativo_id'];
        }

        $sql .= ' ORDER BY FIELD(ce.status, "aberto","aguardando_fornecedor","em_andamento","resolvido","fechado"), ce.aberto_em DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT ce.*, f.nome_fantasia AS fornecedor_nome, f.telefone AS fornecedor_telefone,
                    f.email AS fornecedor_email, f.canal_abertura_chamado AS fornecedor_canal_abertura,
                    cat.nome AS categoria_nome, a.codigo_patrimonio AS ativo_patrimonio, a.nome AS ativo_nome
             FROM chamados_externos ce
             JOIN fornecedores f ON f.id = ce.fornecedor_id
             LEFT JOIN chamados_externos_categorias cat ON cat.id = ce.categoria_id
             LEFT JOIN ativos a ON a.id = ce.ativo_id
             WHERE ce.id = ?"
        );
        $stmt->execute([$id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    /** @return array{success: bool, message: string, id?: int} */
    public function criar(array $dados): array
    {
        $titulo = trim($dados['titulo'] ?? '');
        $fornecedorId = (int)($dados['fornecedor_id'] ?? 0);

        if ($titulo === '') {
            return ['success' => false, 'message' => 'Informe o título do chamado.'];
        }
        if (!$fornecedorId) {
            return ['success' => false, 'message' => 'Selecione o fornecedor.'];
        }

        $prioridade = in_array($dados['prioridade'] ?? '', self::PRIORIDADES_VALIDAS, true) ? $dados['prioridade'] : 'media';

        $stmt = $this->pdo->prepare(
            'INSERT INTO chamados_externos (titulo, descricao, fornecedor_id, categoria_id, ativo_id, protocolo_fornecedor, prioridade, criado_por)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $titulo,
            trim($dados['descricao'] ?? '') ?: null,
            $fornecedorId,
            !empty($dados['categoria_id']) ? (int)$dados['categoria_id'] : null,
            !empty($dados['ativo_id']) ? (int)$dados['ativo_id'] : null,
            trim($dados['protocolo_fornecedor'] ?? '') ?: null,
            $prioridade,
            $dados['usuario_id'] ?? null,
        ]);

        $id = (int)$this->pdo->lastInsertId();
        $this->registrarSistema($id, 'Chamado aberto.', $dados['usuario_id'] ?? null);

        return ['success' => true, 'message' => 'Chamado externo criado.', 'id' => $id];
    }

    /** @return array{success: bool, message: string} */
    public function atualizar(int $id, array $dados): array
    {
        $titulo = trim($dados['titulo'] ?? '');
        if ($titulo === '') {
            return ['success' => false, 'message' => 'Informe o título do chamado.'];
        }

        $prioridade = in_array($dados['prioridade'] ?? '', self::PRIORIDADES_VALIDAS, true) ? $dados['prioridade'] : 'media';

        $stmt = $this->pdo->prepare(
            'UPDATE chamados_externos SET titulo = ?, descricao = ?, categoria_id = ?, ativo_id = ?, protocolo_fornecedor = ?, prioridade = ? WHERE id = ?'
        );
        $stmt->execute([
            $titulo,
            trim($dados['descricao'] ?? '') ?: null,
            !empty($dados['categoria_id']) ? (int)$dados['categoria_id'] : null,
            !empty($dados['ativo_id']) ? (int)$dados['ativo_id'] : null,
            trim($dados['protocolo_fornecedor'] ?? '') ?: null,
            $prioridade,
            $id,
        ]);

        return ['success' => true, 'message' => 'Chamado atualizado.'];
    }

    /** @return array{success: bool, message: string} */
    public function mudarStatus(int $id, string $novoStatus, ?int $usuarioId): array
    {
        if (!in_array($novoStatus, self::STATUS_VALIDOS, true)) {
            return ['success' => false, 'message' => 'Status inválido.'];
        }

        $chamado = $this->buscar($id);
        if (!$chamado) {
            return ['success' => false, 'message' => 'Chamado não encontrado.'];
        }
        if ($chamado['status'] === $novoStatus) {
            return ['success' => true, 'message' => 'Status já era esse.'];
        }

        $campos = ['status = ?'];
        $params = [$novoStatus];

        if ($novoStatus === 'resolvido' && !$chamado['resolvido_em']) {
            $campos[] = 'resolvido_em = NOW()';
        }
        if ($novoStatus === 'fechado' && !$chamado['fechado_em']) {
            $campos[] = 'fechado_em = NOW()';
        }
        if (in_array($novoStatus, ['aberto', 'aguardando_fornecedor', 'em_andamento'], true)) {
            $campos[] = 'resolvido_em = NULL';
            $campos[] = 'fechado_em = NULL';
        }

        $params[] = $id;
        $stmt = $this->pdo->prepare('UPDATE chamados_externos SET ' . implode(', ', $campos) . ' WHERE id = ?');
        $stmt->execute($params);

        $this->registrarSistema(
            $id,
            sprintf('Status alterado de "%s" para "%s".', self::statusLabel($chamado['status']), self::statusLabel($novoStatus)),
            $usuarioId
        );

        return ['success' => true, 'message' => 'Status atualizado.'];
    }

    /** @return array{success: bool, message: string} */
    public function excluir(int $id): array
    {
        foreach ($this->anexos($id) as $anexo) {
            if ($anexo['anexo_origem'] === 'upload') {
                @unlink(self::caminhoCompletoUpload($anexo['anexo_caminho']));
            }
        }

        $this->pdo->prepare('DELETE FROM chamados_externos WHERE id = ?')->execute([$id]);

        return ['success' => true, 'message' => 'Chamado removido.'];
    }

    public function timeline(int $id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.*, u.nome AS usuario_nome
             FROM chamados_externos_comentarios c
             LEFT JOIN usuarios u ON u.id = c.usuario_id
             WHERE c.chamado_externo_id = ?
             ORDER BY c.criado_em ASC, c.id ASC'
        );
        $stmt->execute([$id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{success: bool, message: string} */
    public function comentar(int $id, string $conteudo, ?int $usuarioId): array
    {
        $conteudo = trim($conteudo);
        if ($conteudo === '') {
            return ['success' => false, 'message' => 'Escreva algo antes de salvar.'];
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO chamados_externos_comentarios (chamado_externo_id, usuario_id, tipo, conteudo) VALUES (?, ?, 'nota', ?)"
        );
        $stmt->execute([$id, $usuarioId, $conteudo]);

        return ['success' => true, 'message' => 'Nota adicionada.'];
    }

    private function registrarSistema(int $id, string $conteudo, ?int $usuarioId): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO chamados_externos_comentarios (chamado_externo_id, usuario_id, tipo, conteudo) VALUES (?, ?, 'sistema', ?)"
        );
        $stmt->execute([$id, $usuarioId, $conteudo]);
    }

    public function anexos(int $id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.*, u.nome AS usuario_nome
             FROM chamados_externos_anexos a
             LEFT JOIN usuarios u ON u.id = a.usuario_id
             WHERE a.chamado_externo_id = ?
             ORDER BY a.criado_em DESC'
        );
        $stmt->execute([$id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{success: bool, message: string} */
    public function anexarUpload(int $id, array $arquivo, ?int $usuarioId): array
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

        $stmt = $this->pdo->prepare(
            "INSERT INTO chamados_externos_anexos (chamado_externo_id, anexo_origem, anexo_caminho, anexo_nome_original, usuario_id)
             VALUES (?, 'upload', ?, ?, ?)"
        );
        $stmt->execute([$id, $nomeArquivo, $arquivo['name'], $usuarioId]);

        $this->registrarSistema($id, 'Anexou o arquivo "' . $arquivo['name'] . '".', $usuarioId);

        return ['success' => true, 'message' => 'Anexo enviado.'];
    }

    /** @return array{success: bool, message: string} */
    public function anexarSamba(int $id, string $caminhoCompleto, string $nomeOriginal, ?int $usuarioId): array
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO chamados_externos_anexos (chamado_externo_id, anexo_origem, anexo_caminho, anexo_nome_original, usuario_id)
             VALUES (?, 'samba', ?, ?, ?)"
        );
        $stmt->execute([$id, $caminhoCompleto, $nomeOriginal, $usuarioId]);

        $this->registrarSistema($id, 'Vinculou o arquivo "' . $nomeOriginal . '" (Samba).', $usuarioId);

        return ['success' => true, 'message' => 'Anexo vinculado.'];
    }

    public function buscarAnexo(int $anexoId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM chamados_externos_anexos WHERE id = ?');
        $stmt->execute([$anexoId]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    /** @return array{success: bool, message: string} */
    public function excluirAnexo(int $anexoId): array
    {
        $anexo = $this->buscarAnexo($anexoId);
        if (!$anexo) {
            return ['success' => false, 'message' => 'Anexo não encontrado.'];
        }

        if ($anexo['anexo_origem'] === 'upload') {
            @unlink(self::caminhoCompletoUpload($anexo['anexo_caminho']));
        }

        $this->pdo->prepare('DELETE FROM chamados_externos_anexos WHERE id = ?')->execute([$anexoId]);

        return ['success' => true, 'message' => 'Anexo removido.'];
    }
}
