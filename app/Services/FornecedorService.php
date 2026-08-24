<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class FornecedorService
{
    private const PORTES_VALIDOS = ['ME', 'EPP', 'Demais'];

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listar(): array
    {
        return $this->pdo->query(
            "SELECT f.*, t.nome AS tipo_servico_nome, COUNT(c.id) AS total_contratos
             FROM fornecedores f
             LEFT JOIN fornecedor_tipos_servico t ON t.id = f.tipo_servico_id
             LEFT JOIN contratos c ON c.fornecedor_id = f.id
             GROUP BY f.id
             ORDER BY f.nome_fantasia"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarAtivos(): array
    {
        return $this->pdo->query("SELECT * FROM fornecedores WHERE ativo = 1 ORDER BY nome_fantasia")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT f.*, t.nome AS tipo_servico_nome
             FROM fornecedores f
             LEFT JOIN fornecedor_tipos_servico t ON t.id = f.tipo_servico_id
             WHERE f.id = ?"
        );
        $stmt->execute([$id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    /** @return array{success: bool, message: string, id?: int} */
    public function criar(array $dados): array
    {
        $erro = $this->validar($dados);
        if ($erro) {
            return ['success' => false, 'message' => $erro];
        }

        $cnpjCpf = trim($dados['cnpj_cpf'] ?? '') ?: null;
        if ($cnpjCpf !== null && $this->cnpjCpfEmUso($cnpjCpf)) {
            return ['success' => false, 'message' => 'Já existe um fornecedor com esse CNPJ/CPF.'];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO fornecedores (
                razao_social, nome_fantasia, cnpj_cpf, inscricao_estadual, inscricao_estadual_isento,
                inscricao_municipal, porte, cep, logradouro, numero, complemento, bairro, cidade, uf, pais,
                tipo_servico_id, contato_nome, email, telefone, site, canal_abertura_chamado
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute($this->parametros($dados, $cnpjCpf));

        return ['success' => true, 'message' => 'Fornecedor cadastrado com sucesso.', 'id' => (int)$this->pdo->lastInsertId()];
    }

    /** @return array{success: bool, message: string} */
    public function atualizar(int $id, array $dados): array
    {
        $erro = $this->validar($dados);
        if ($erro) {
            return ['success' => false, 'message' => $erro];
        }

        $cnpjCpf = trim($dados['cnpj_cpf'] ?? '') ?: null;
        if ($cnpjCpf !== null && $this->cnpjCpfEmUso($cnpjCpf, $id)) {
            return ['success' => false, 'message' => 'Já existe um fornecedor com esse CNPJ/CPF.'];
        }

        $stmt = $this->pdo->prepare(
            'UPDATE fornecedores SET
                razao_social = ?, nome_fantasia = ?, cnpj_cpf = ?, inscricao_estadual = ?, inscricao_estadual_isento = ?,
                inscricao_municipal = ?, porte = ?, cep = ?, logradouro = ?, numero = ?, complemento = ?, bairro = ?,
                cidade = ?, uf = ?, pais = ?, tipo_servico_id = ?, contato_nome = ?, email = ?, telefone = ?, site = ?,
                canal_abertura_chamado = ?, ativo = ?
             WHERE id = ?'
        );
        $stmt->execute([...$this->parametros($dados, $cnpjCpf), !empty($dados['ativo']) ? 1 : 0, $id]);

        return ['success' => true, 'message' => 'Fornecedor atualizado com sucesso.'];
    }

    /** @return array{success: bool, message: string} */
    public function excluir(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM contratos WHERE fornecedor_id = ?');
        $stmt->execute([$id]);

        if ((int)$stmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'Esse fornecedor tem contratos cadastrados -- exclua ou mova os contratos antes.'];
        }

        $this->pdo->prepare('DELETE FROM fornecedores WHERE id = ?')->execute([$id]);

        return ['success' => true, 'message' => 'Fornecedor removido.'];
    }

    private function validar(array $dados): ?string
    {
        if (trim($dados['razao_social'] ?? '') === '') {
            return 'Informe a razão social.';
        }
        if (trim($dados['nome_fantasia'] ?? '') === '') {
            return 'Informe o nome fantasia.';
        }
        if (!empty($dados['porte']) && !in_array($dados['porte'], self::PORTES_VALIDOS, true)) {
            return 'Porte inválido.';
        }

        return null;
    }

    private function parametros(array $dados, ?string $cnpjCpf): array
    {
        return [
            trim($dados['razao_social']),
            trim($dados['nome_fantasia']),
            $cnpjCpf,
            trim($dados['inscricao_estadual'] ?? '') ?: null,
            !empty($dados['inscricao_estadual_isento']) ? 1 : 0,
            trim($dados['inscricao_municipal'] ?? '') ?: null,
            !empty($dados['porte']) ? $dados['porte'] : null,
            trim($dados['cep'] ?? '') ?: null,
            trim($dados['logradouro'] ?? '') ?: null,
            trim($dados['numero'] ?? '') ?: null,
            trim($dados['complemento'] ?? '') ?: null,
            trim($dados['bairro'] ?? '') ?: null,
            trim($dados['cidade'] ?? '') ?: null,
            trim($dados['uf'] ?? '') ?: null,
            trim($dados['pais'] ?? '') ?: 'Brasil',
            !empty($dados['tipo_servico_id']) ? (int)$dados['tipo_servico_id'] : null,
            trim($dados['contato_nome'] ?? '') ?: null,
            trim($dados['email'] ?? '') ?: null,
            trim($dados['telefone'] ?? '') ?: null,
            trim($dados['site'] ?? '') ?: null,
            trim($dados['canal_abertura_chamado'] ?? '') ?: null,
        ];
    }

    private function somenteDigitos(string $valor): ?string
    {
        $digitos = preg_replace('/\D/', '', $valor);

        return $digitos !== '' ? $digitos : null;
    }

    /** Compara por dígitos (ignora pontuação) -- "12.345.678/0001-90" e "12345678000190" são o mesmo CNPJ. */
    private function cnpjCpfEmUso(string $cnpjCpf, ?int $ignorarId = null): bool
    {
        $digitosAlvo = $this->somenteDigitos($cnpjCpf);
        if ($digitosAlvo === null) {
            return false;
        }

        $sql = 'SELECT id, cnpj_cpf FROM fornecedores WHERE cnpj_cpf IS NOT NULL';
        $params = [];

        if ($ignorarId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $ignorarId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $linha) {
            if ($this->somenteDigitos($linha['cnpj_cpf']) === $digitosAlvo) {
                return true;
            }
        }

        return false;
    }
}
