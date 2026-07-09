<?php

namespace App\Services;

use PDO;
use PDOException;

class DbConsoleService
{
    private const BANCOS_SISTEMA = ['information_schema', 'mysql', 'performance_schema', 'sys'];
    private const POR_PAGINA = 50;

    private DbConexaoService $conexaoService;

    public function __construct()
    {
        $this->conexaoService = new DbConexaoService();
    }

    public function listarBancos(int $conexaoId, bool $mostrarSistema = false): array
    {
        $pdo = $this->conexaoService->conectar($conexaoId);

        $bancos = $pdo->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN);

        if ($mostrarSistema) {
            return $bancos;
        }

        return array_values(array_diff($bancos, self::BANCOS_SISTEMA));
    }

    public function listarTabelas(int $conexaoId, string $banco): array
    {
        $pdo = $this->conexaoService->conectar($conexaoId, $banco);

        $stmt = $pdo->query('SHOW TABLE STATUS');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function estruturaTabela(int $conexaoId, string $banco, string $tabela): array
    {
        $pdo = $this->conexaoService->conectar($conexaoId, $banco);

        $criacao = $pdo->query('SHOW CREATE TABLE ' . $this->identificador($tabela))->fetch(PDO::FETCH_ASSOC);

        return [
            'colunas' => $this->listarColunas($conexaoId, $banco, $tabela),
            'create_table' => $criacao['Create Table'] ?? '',
        ];
    }

    public function navegarDados(int $conexaoId, string $banco, string $tabela, int $pagina = 1, string $busca = ''): array
    {
        $pdo = $this->conexaoService->conectar($conexaoId, $banco);

        $colunas = $this->listarColunas($conexaoId, $banco, $tabela);
        $nomesColunas = array_column($colunas, 'Field');

        [$whereSql, $params] = $this->whereBusca($nomesColunas, $busca);

        $total = (int)$this->prepararEExecutar(
            $pdo,
            'SELECT COUNT(*) FROM ' . $this->identificador($tabela) . $whereSql,
            $params
        )->fetchColumn();

        $pagina = max(1, $pagina);
        $offset = ($pagina - 1) * self::POR_PAGINA;

        $stmt = $this->prepararEExecutar(
            $pdo,
            'SELECT * FROM ' . $this->identificador($tabela) . $whereSql . ' LIMIT ' . self::POR_PAGINA . " OFFSET {$offset}",
            $params
        );
        $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'linhas' => $linhas,
            'colunas' => $nomesColunas,
            'colunas_info' => $colunas,
            'chaves_primarias' => array_column(array_filter($colunas, fn($c) => $c['Key'] === 'PRI'), 'Field'),
            'total' => $total,
            'pagina' => $pagina,
            'por_pagina' => self::POR_PAGINA,
            'total_paginas' => (int)ceil($total / self::POR_PAGINA),
            'busca' => $busca,
        ];
    }

    public function listarColunas(int $conexaoId, string $banco, string $tabela): array
    {
        $pdo = $this->conexaoService->conectar($conexaoId, $banco);

        return $pdo->query('DESCRIBE ' . $this->identificador($tabela))->fetchAll(PDO::FETCH_ASSOC);
    }

    public function chavesPrimarias(int $conexaoId, string $banco, string $tabela): array
    {
        $colunas = $this->listarColunas($conexaoId, $banco, $tabela);

        return array_values(array_column(array_filter($colunas, fn($c) => $c['Key'] === 'PRI'), 'Field'));
    }

    /**
     * @param array $def ['nome'=>, 'tipo'=>, 'nulo'=>bool, 'padrao'=>?string, 'auto_increment'=>bool, 'apos'=>?string]
     */
    public function adicionarColuna(int $conexaoId, string $banco, string $tabela, array $def): array
    {
        try {
            $pdo = $this->conexaoService->conectar($conexaoId, $banco);

            $erro = $this->validarDefinicaoColuna($def);
            if ($erro) {
                return ['success' => false, 'mensagem' => $erro];
            }

            $sql = "ALTER TABLE {$this->identificador($tabela)} ADD COLUMN " . $this->definicaoColunaSql($pdo, $def);
            if (!empty($def['apos'])) {
                $sql .= ' AFTER ' . $this->identificador($def['apos']);
            }

            $pdo->exec($sql);

            return ['success' => true, 'mensagem' => 'Coluna adicionada com sucesso.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'mensagem' => $e->getMessage()];
        }
    }

    public function alterarColuna(int $conexaoId, string $banco, string $tabela, string $nomeAntigo, array $def): array
    {
        try {
            $pdo = $this->conexaoService->conectar($conexaoId, $banco);

            $erro = $this->validarDefinicaoColuna($def);
            if ($erro) {
                return ['success' => false, 'mensagem' => $erro];
            }

            $sql = "ALTER TABLE {$this->identificador($tabela)} CHANGE COLUMN {$this->identificador($nomeAntigo)} "
                . $this->definicaoColunaSql($pdo, $def);

            $pdo->exec($sql);

            return ['success' => true, 'mensagem' => 'Coluna atualizada com sucesso.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'mensagem' => $e->getMessage()];
        }
    }

    public function removerColuna(int $conexaoId, string $banco, string $tabela, string $nome): array
    {
        try {
            $pdo = $this->conexaoService->conectar($conexaoId, $banco);

            $pdo->exec("ALTER TABLE {$this->identificador($tabela)} DROP COLUMN {$this->identificador($nome)}");

            return ['success' => true, 'mensagem' => 'Coluna removida com sucesso.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'mensagem' => $e->getMessage()];
        }
    }

    /**
     * So letras/numeros/espaco/parenteses/virgula/aspas simples/underscore --
     * cobre "VARCHAR(100)", "INT(11) UNSIGNED", "ENUM('a','b')" etc, e
     * bloqueia ponto-e-virgula, comentarios SQL e outros jeitos de escapar
     * do contexto de "definicao de tipo" pra outro comando.
     */
    private function validarDefinicaoColuna(array $def): ?string
    {
        if (trim($def['nome'] ?? '') === '') {
            return 'Informe o nome da coluna.';
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $def['nome'])) {
            return 'Nome de coluna inválido (use apenas letras, números e sublinhado).';
        }
        if (trim($def['tipo'] ?? '') === '') {
            return 'Informe o tipo da coluna.';
        }
        if (!preg_match('/^[a-zA-Z0-9_]+(\([a-zA-Z0-9_ ,\'\.]*\))?( unsigned)?( zerofill)?$/i', trim($def['tipo']))) {
            return 'Tipo de coluna inválido.';
        }

        return null;
    }

    private function definicaoColunaSql(PDO $pdo, array $def): string
    {
        $nome = $this->identificador($def['nome']);
        $tipo = trim($def['tipo']);

        $sql = "{$nome} {$tipo}";
        $sql .= !empty($def['nulo']) ? ' NULL' : ' NOT NULL';

        if (($def['padrao'] ?? '') !== '') {
            $padrao = trim($def['padrao']);
            if (preg_match('/^CURRENT_TIMESTAMP(\(\d*\))?$/i', $padrao) || strtoupper($padrao) === 'NULL') {
                $sql .= ' DEFAULT ' . strtoupper($padrao);
            } else {
                $sql .= ' DEFAULT ' . $pdo->quote($padrao);
            }
        }

        if (!empty($def['auto_increment'])) {
            $sql .= ' AUTO_INCREMENT';
        }

        return $sql;
    }

    public function buscarLinha(int $conexaoId, string $banco, string $tabela, array $pkValores): ?array
    {
        $pdo = $this->conexaoService->conectar($conexaoId, $banco);

        [$whereSql, $params] = $this->whereIgualdade($pkValores);

        $stmt = $this->prepararEExecutar(
            $pdo,
            'SELECT * FROM ' . $this->identificador($tabela) . $whereSql . ' LIMIT 1',
            $params
        );

        $linha = $stmt->fetch(PDO::FETCH_ASSOC);

        return $linha ?: null;
    }

    /**
     * @param array $valores coluna => valor (valor null vira NULL de verdade)
     */
    public function inserirLinha(int $conexaoId, string $banco, string $tabela, array $valores): array
    {
        try {
            $pdo = $this->conexaoService->conectar($conexaoId, $banco);

            $colunas = array_keys($valores);
            $placeholders = implode(', ', array_fill(0, count($colunas), '?'));
            $colunasSql = implode(', ', array_map(fn($c) => $this->identificador($c), $colunas));

            $sql = "INSERT INTO {$this->identificador($tabela)} ({$colunasSql}) VALUES ({$placeholders})";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_values($valores));

            return ['success' => true, 'mensagem' => 'Registro inserido com sucesso.', 'id' => $pdo->lastInsertId()];
        } catch (\Throwable $e) {
            return ['success' => false, 'mensagem' => $e->getMessage()];
        }
    }

    /**
     * @param array $valores coluna => valor novo
     * @param array $pkValoresAntigos coluna_pk => valor antigo (localiza a linha mesmo se a PK for editada)
     */
    public function atualizarLinha(int $conexaoId, string $banco, string $tabela, array $valores, array $pkValoresAntigos): array
    {
        try {
            $pdo = $this->conexaoService->conectar($conexaoId, $banco);

            $sets = implode(', ', array_map(fn($c) => $this->identificador($c) . ' = ?', array_keys($valores)));
            [$whereSql, $paramsWhere] = $this->whereIgualdade($pkValoresAntigos);

            $sql = "UPDATE {$this->identificador($tabela)} SET {$sets} {$whereSql} LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([...array_values($valores), ...$paramsWhere]);

            return ['success' => true, 'mensagem' => 'Registro atualizado com sucesso.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'mensagem' => $e->getMessage()];
        }
    }

    public function excluirLinha(int $conexaoId, string $banco, string $tabela, array $pkValores): array
    {
        try {
            $pdo = $this->conexaoService->conectar($conexaoId, $banco);

            [$whereSql, $params] = $this->whereIgualdade($pkValores);

            $stmt = $pdo->prepare("DELETE FROM {$this->identificador($tabela)} {$whereSql} LIMIT 1");
            $stmt->execute($params);

            return ['success' => true, 'mensagem' => 'Registro excluído com sucesso.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'mensagem' => $e->getMessage()];
        }
    }

    /**
     * Duplica uma linha: copia todos os valores menos a(s) coluna(s) de
     * chave primaria (deixa o banco gerar uma nova, tipicamente auto_increment).
     */
    public function duplicarLinha(int $conexaoId, string $banco, string $tabela, array $pkValores): array
    {
        try {
            $linha = $this->buscarLinha($conexaoId, $banco, $tabela, $pkValores);
        } catch (\Throwable $e) {
            return ['success' => false, 'mensagem' => $e->getMessage()];
        }

        if (!$linha) {
            return ['success' => false, 'mensagem' => 'Registro original não encontrado.'];
        }

        foreach (array_keys($pkValores) as $colPk) {
            unset($linha[$colPk]);
        }

        return $this->inserirLinha($conexaoId, $banco, $tabela, $linha);
    }

    /**
     * Devolve todas as linhas (sem paginacao) pra exportacao em CSV.
     */
    public function exportarLinhas(int $conexaoId, string $banco, string $tabela, string $busca = ''): array
    {
        $pdo = $this->conexaoService->conectar($conexaoId, $banco);

        $colunas = array_column($this->listarColunas($conexaoId, $banco, $tabela), 'Field');
        [$whereSql, $params] = $this->whereBusca($colunas, $busca);

        $stmt = $this->prepararEExecutar(
            $pdo,
            'SELECT * FROM ' . $this->identificador($tabela) . $whereSql,
            $params
        );

        return ['colunas' => $colunas, 'linhas' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    }

    private function prepararEExecutar(PDO $pdo, string $sql, array $params): \PDOStatement
    {
        if (empty($params)) {
            return $pdo->query($sql);
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt;
    }

    /**
     * @return array{0: string, 1: array} [SQL " WHERE ...", params]
     */
    private function whereIgualdade(array $valores): array
    {
        if (empty($valores)) {
            // nunca deve rodar sem filtro (evita UPDATE/DELETE sem WHERE) --
            // forca uma condicao sempre falsa
            return [' WHERE 1 = 0', []];
        }

        $partes = [];
        $params = [];
        foreach ($valores as $coluna => $valor) {
            if ($valor === null) {
                $partes[] = $this->identificador($coluna) . ' IS NULL';
            } else {
                $partes[] = $this->identificador($coluna) . ' = ?';
                $params[] = $valor;
            }
        }

        return [' WHERE ' . implode(' AND ', $partes), $params];
    }

    /**
     * @return array{0: string, 1: array} [SQL " WHERE ..." ou "", params]
     */
    private function whereBusca(array $colunas, string $busca): array
    {
        $busca = trim($busca);
        if ($busca === '' || empty($colunas)) {
            return ['', []];
        }

        $partes = array_map(fn($c) => $this->identificador($c) . ' LIKE ?', $colunas);
        $params = array_fill(0, count($colunas), '%' . $busca . '%');

        return [' WHERE (' . implode(' OR ', $partes) . ')', $params];
    }

    /**
     * Roda uma unica instrucao SQL crua contra a conexao/banco escolhidos.
     * Sem bloqueio de comandos destrutivos (ferramenta de DBA) -- a friccao
     * fica no modal de confirmacao do lado do cliente e no log de auditoria
     * feito pelo controller.
     */
    public function executarSql(int $conexaoId, ?string $banco, string $sql): array
    {
        $sql = trim($sql);

        if ($sql === '') {
            return ['success' => false, 'mensagem' => 'Informe um comando SQL.'];
        }

        try {
            $pdo = $this->conexaoService->conectar($conexaoId, $banco);
            $stmt = $pdo->query($sql);

            if ($stmt->columnCount() > 0) {
                $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);

                return [
                    'success' => true,
                    'tipo' => 'linhas',
                    'colunas' => $linhas ? array_keys($linhas[0]) : [],
                    'linhas' => $linhas,
                    'total' => count($linhas),
                ];
            }

            return [
                'success' => true,
                'tipo' => 'afetadas',
                'linhas_afetadas' => $stmt->rowCount(),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'mensagem' => $e->getMessage()];
        }
    }

    private function identificador(string $nome): string
    {
        return '`' . str_replace('`', '``', $nome) . '`';
    }
}
