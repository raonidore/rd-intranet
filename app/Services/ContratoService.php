<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Contratos vinculados a um fornecedor. Vigência/valor são colunas de
 * verdade (não texto livre), pensando em "contratos vencendo em 30
 * dias" como evolução natural, sem precisar remodelar nada.
 *
 * Anexo tem duas origens possíveis (ver SambaAnexoService): upload
 * novo (guardado em storage/contratos/, mesmo padrão de
 * ChamadoAnexoService) ou referência a um arquivo já existente num
 * compartilhamento Samba (só guarda o caminho, nunca copia o arquivo
 * -- por isso "anexar" não duplica nada).
 */
class ContratoService
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
        $dir = __DIR__ . '/../../storage/contratos';

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
        return uniqid('contrato_', true) . '.' . $extensao;
    }

    public static function caminhoCompletoUpload(string $nomeArquivo): string
    {
        return self::diretorio() . '/' . basename($nomeArquivo);
    }

    public function listarPorFornecedor(int $fornecedorId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM contratos WHERE fornecedor_id = ? ORDER BY data_termino IS NULL, data_termino ASC, id DESC');
        $stmt->execute([$fornecedorId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.*, f.nome_fantasia AS fornecedor_nome FROM contratos c JOIN fornecedores f ON f.id = c.fornecedor_id WHERE c.id = ?'
        );
        $stmt->execute([$id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    /** 'vigente' | 'a_vencer' (30 dias) | 'vencido' | 'sem_data' */
    public static function status(array $contrato): string
    {
        if (empty($contrato['data_termino'])) {
            return 'sem_data';
        }

        $hoje = new \DateTimeImmutable('today');
        $termino = new \DateTimeImmutable($contrato['data_termino']);
        $diasRestantes = (int)$hoje->diff($termino)->format('%r%a');

        if ($diasRestantes < 0) {
            return 'vencido';
        }
        if ($diasRestantes <= 30) {
            return 'a_vencer';
        }

        return 'vigente';
    }

    /** @return array{success: bool, message: string, id?: int} */
    public function criar(array $dados): array
    {
        $fornecedorId = (int)($dados['fornecedor_id'] ?? 0);
        if (!$fornecedorId) {
            return ['success' => false, 'message' => 'Selecione o fornecedor.'];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO contratos (fornecedor_id, numero, descricao, data_inicio, data_termino, valor, criado_por) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $fornecedorId,
            trim($dados['numero'] ?? '') ?: null,
            trim($dados['descricao'] ?? '') ?: null,
            $dados['data_inicio'] ?: null,
            $dados['data_termino'] ?: null,
            $dados['valor'] !== '' && $dados['valor'] !== null ? (float)$dados['valor'] : null,
            $dados['criado_por'] ?? null,
        ]);

        return ['success' => true, 'message' => 'Contrato cadastrado com sucesso.', 'id' => (int)$this->pdo->lastInsertId()];
    }

    /** @return array{success: bool, message: string} */
    public function atualizar(int $id, array $dados): array
    {
        $stmt = $this->pdo->prepare(
            'UPDATE contratos SET numero = ?, descricao = ?, data_inicio = ?, data_termino = ?, valor = ? WHERE id = ?'
        );
        $stmt->execute([
            trim($dados['numero'] ?? '') ?: null,
            trim($dados['descricao'] ?? '') ?: null,
            $dados['data_inicio'] ?: null,
            $dados['data_termino'] ?: null,
            $dados['valor'] !== '' && $dados['valor'] !== null ? (float)$dados['valor'] : null,
            $id,
        ]);

        return ['success' => true, 'message' => 'Contrato atualizado com sucesso.'];
    }

    /** @return array{success: bool, message: string} */
    public function excluir(int $id): array
    {
        $contrato = $this->buscar($id);
        if ($contrato && $contrato['anexo_origem'] === 'upload' && $contrato['anexo_caminho']) {
            @unlink(self::caminhoCompletoUpload($contrato['anexo_caminho']));
        }

        $this->pdo->prepare('DELETE FROM contratos WHERE id = ?')->execute([$id]);

        return ['success' => true, 'message' => 'Contrato removido.'];
    }

    /** @return array{success: bool, message: string} */
    public function definirAnexoUpload(int $id, array $arquivo): array
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

        $this->limparAnexoAnterior($id);

        $stmt = $this->pdo->prepare(
            "UPDATE contratos SET anexo_origem = 'upload', anexo_caminho = ?, anexo_nome_original = ? WHERE id = ?"
        );
        $stmt->execute([$nomeArquivo, $arquivo['name'], $id]);

        return ['success' => true, 'message' => 'Anexo enviado.'];
    }

    /** @return array{success: bool, message: string} */
    public function definirAnexoSamba(int $id, string $caminhoCompleto, string $nomeOriginal): array
    {
        $this->limparAnexoAnterior($id);

        $stmt = $this->pdo->prepare(
            "UPDATE contratos SET anexo_origem = 'samba', anexo_caminho = ?, anexo_nome_original = ? WHERE id = ?"
        );
        $stmt->execute([$caminhoCompleto, $nomeOriginal, $id]);

        return ['success' => true, 'message' => 'Anexo vinculado.'];
    }

    private function limparAnexoAnterior(int $id): void
    {
        $contrato = $this->buscar($id);
        if ($contrato && $contrato['anexo_origem'] === 'upload' && $contrato['anexo_caminho']) {
            @unlink(self::caminhoCompletoUpload($contrato['anexo_caminho']));
        }
    }
}
