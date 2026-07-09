<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\DbConexaoService;
use App\Services\DbConsoleService;
use App\Services\NotificationService;

class DbConsoleController extends Controller
{
    private DbConsoleService $service;
    private DbConexaoService $conexaoService;

    public function __construct()
    {
        $this->service = new DbConsoleService();
        $this->conexaoService = new DbConexaoService();
    }

    public function bancos(): void
    {
        AuthMiddleware::checkModulo('bd_mysql');

        $conexaoId = (int)($_GET['conexao'] ?? 0);
        $conexao = $this->conexaoService->buscar($conexaoId);

        if (!$conexao) {
            header('Location: ' . url('/banco-dados/conexoes'));
            exit;
        }

        $erro = null;
        $bancos = [];

        try {
            $bancos = $this->service->listarBancos($conexaoId);
        } catch (\Throwable $e) {
            $erro = $e->getMessage();
        }

        $this->view('database/console_bancos', [
            'conexao' => $conexao,
            'bancos' => $bancos,
            'erro' => $erro,
        ]);
    }

    public function tabelas(): void
    {
        AuthMiddleware::checkModulo('bd_mysql');

        $conexaoId = (int)($_GET['conexao'] ?? 0);
        $banco = trim($_GET['banco'] ?? '');
        $conexao = $this->conexaoService->buscar($conexaoId);

        if (!$conexao || $banco === '') {
            header('Location: ' . url('/banco-dados/conexoes'));
            exit;
        }

        $erro = null;
        $tabelas = [];

        try {
            $tabelas = $this->service->listarTabelas($conexaoId, $banco);
        } catch (\Throwable $e) {
            $erro = $e->getMessage();
        }

        $this->view('database/console_tabelas', [
            'conexao' => $conexao,
            'banco' => $banco,
            'tabelas' => $tabelas,
            'erro' => $erro,
        ]);
    }

    public function estrutura(): void
    {
        AuthMiddleware::checkModulo('bd_mysql');

        $conexaoId = (int)($_GET['conexao'] ?? 0);
        $banco = trim($_GET['banco'] ?? '');
        $tabela = trim($_GET['tabela'] ?? '');
        $conexao = $this->conexaoService->buscar($conexaoId);

        if (!$conexao || $banco === '' || $tabela === '') {
            header('Location: ' . url('/banco-dados/conexoes'));
            exit;
        }

        $erro = null;
        $estrutura = ['colunas' => [], 'create_table' => ''];

        try {
            $estrutura = $this->service->estruturaTabela($conexaoId, $banco, $tabela);
        } catch (\Throwable $e) {
            $erro = $e->getMessage();
        }

        $this->view('database/console_estrutura', [
            'conexao' => $conexao,
            'banco' => $banco,
            'tabela' => $tabela,
            'estrutura' => $estrutura,
            'erro' => $erro,
        ]);
    }

    public function colunaNovaForm(): void
    {
        AuthMiddleware::checkModulo('bd_mysql');

        [$conexao, $banco, $tabela] = $this->exigirContexto();

        try {
            $colunasExistentes = $this->service->listarColunas($conexao['id'], $banco, $tabela);
        } catch (\Throwable $e) {
            NotificationService::error('Não foi possível ler a estrutura da tabela.', $e->getMessage());
            header('Location: ' . url("/banco-dados/console/estrutura?conexao={$conexao['id']}&banco=" . urlencode($banco) . '&tabela=' . urlencode($tabela)));
            exit;
        }

        $this->view('database/console_coluna_form', [
            'conexao' => $conexao,
            'banco' => $banco,
            'tabela' => $tabela,
            'coluna' => null,
            'colunasExistentes' => $colunasExistentes,
        ]);
    }

    public function colunaNova(): void
    {
        AuthMiddleware::checkModulo('bd_mysql');

        [$conexaoId, $banco, $tabela] = $this->contextoDoPost();
        $def = $this->definicaoColunaDoPost();

        $resultado = $this->service->adicionarColuna($conexaoId, $banco, $tabela, $def);

        AuditService::registrar('Banco de Dados', 'Adicionar coluna', "{$banco}.{$tabela}.{$def['nome']}: " . ($resultado['success'] ? 'ok' : $resultado['mensagem']));

        $this->notificarEVoltarEstrutura($resultado, $conexaoId, $banco, $tabela);
    }

    public function colunaEditarForm(): void
    {
        AuthMiddleware::checkModulo('bd_mysql');

        [$conexao, $banco, $tabela] = $this->exigirContexto();
        $nomeColuna = trim($_GET['coluna'] ?? '');

        try {
            $colunas = $this->service->listarColunas($conexao['id'], $banco, $tabela);
        } catch (\Throwable $e) {
            NotificationService::error('Não foi possível ler a estrutura da tabela.', $e->getMessage());
            header('Location: ' . url("/banco-dados/console/estrutura?conexao={$conexao['id']}&banco=" . urlencode($banco) . '&tabela=' . urlencode($tabela)));
            exit;
        }

        $coluna = null;
        foreach ($colunas as $c) {
            if ($c['Field'] === $nomeColuna) {
                $coluna = $c;
                break;
            }
        }

        if (!$coluna) {
            header('Location: ' . url("/banco-dados/console/estrutura?conexao={$conexao['id']}&banco=" . urlencode($banco) . '&tabela=' . urlencode($tabela)));
            exit;
        }

        $this->view('database/console_coluna_form', [
            'conexao' => $conexao,
            'banco' => $banco,
            'tabela' => $tabela,
            'coluna' => $coluna,
            'colunasExistentes' => $colunas,
        ]);
    }

    public function colunaEditar(): void
    {
        AuthMiddleware::checkModulo('bd_mysql');

        [$conexaoId, $banco, $tabela] = $this->contextoDoPost();
        $nomeAntigo = trim($_POST['nome_antigo'] ?? '');
        $def = $this->definicaoColunaDoPost();

        $resultado = $this->service->alterarColuna($conexaoId, $banco, $tabela, $nomeAntigo, $def);

        AuditService::registrar('Banco de Dados', 'Editar coluna', "{$banco}.{$tabela}.{$nomeAntigo}: " . ($resultado['success'] ? 'ok' : $resultado['mensagem']));

        $this->notificarEVoltarEstrutura($resultado, $conexaoId, $banco, $tabela);
    }

    public function colunaRemover(): void
    {
        AuthMiddleware::checkModulo('bd_mysql');
        header('Content-Type: application/json');

        $conexaoId = (int)($_POST['conexao'] ?? 0);
        $banco = trim($_POST['banco'] ?? '');
        $tabela = trim($_POST['tabela'] ?? '');
        $nome = trim($_POST['nome'] ?? '');

        $resultado = $this->service->removerColuna($conexaoId, $banco, $tabela, $nome);

        AuditService::registrar('Banco de Dados', 'Remover coluna', "{$banco}.{$tabela}.{$nome}: " . ($resultado['success'] ? 'ok' : $resultado['mensagem']));

        echo json_encode($resultado);
    }

    /**
     * Caixa de SQL rapida, embutida em outras telas do console (estrutura,
     * tabelas, dados) -- mesma logica de executarSql(), so que devolve JSON
     * em vez de renderizar a pagina inteira de novo.
     */
    public function sqlExecutarAjax(): void
    {
        AuthMiddleware::checkModulo('bd_mysql');
        header('Content-Type: application/json');

        $conexaoId = (int)($_POST['conexao'] ?? 0);
        $banco = trim($_POST['banco'] ?? '');
        $sql = $_POST['sql'] ?? '';

        $resultado = $this->service->executarSql($conexaoId, $banco ?: null, $sql);

        $resumo = $resultado['success']
            ? ($resultado['tipo'] === 'linhas' ? $resultado['total'] . ' linha(s) retornada(s).' : $resultado['linhas_afetadas'] . ' linha(s) afetada(s).')
            : 'Erro: ' . $resultado['mensagem'];

        AuditService::registrar('Banco de Dados', 'Executar SQL (rápido)', "{$banco}: " . substr($sql, 0, 200) . " -- {$resumo}");

        echo json_encode($resultado);
    }

    public function dados(): void
    {
        AuthMiddleware::checkModulo('bd_mysql');

        $conexaoId = (int)($_GET['conexao'] ?? 0);
        $banco = trim($_GET['banco'] ?? '');
        $tabela = trim($_GET['tabela'] ?? '');
        $pagina = (int)($_GET['pagina'] ?? 1);
        $busca = trim($_GET['busca'] ?? '');
        $conexao = $this->conexaoService->buscar($conexaoId);

        if (!$conexao || $banco === '' || $tabela === '') {
            header('Location: ' . url('/banco-dados/conexoes'));
            exit;
        }

        $erro = null;
        $resultado = ['linhas' => [], 'colunas' => [], 'colunas_info' => [], 'chaves_primarias' => [], 'total' => 0, 'pagina' => 1, 'por_pagina' => 50, 'total_paginas' => 0, 'busca' => $busca];

        try {
            $resultado = $this->service->navegarDados($conexaoId, $banco, $tabela, $pagina, $busca);
        } catch (\Throwable $e) {
            $erro = $e->getMessage();
        }

        $this->view('database/console_dados', [
            'conexao' => $conexao,
            'banco' => $banco,
            'tabela' => $tabela,
            'resultado' => $resultado,
            'erro' => $erro,
        ]);
    }

    public function dadosInserirForm(): void
    {
        AuthMiddleware::checkModulo('bd_mysql');

        [$conexao, $banco, $tabela] = $this->exigirContexto();

        try {
            $colunas = $this->service->listarColunas($conexao['id'], $banco, $tabela);
        } catch (\Throwable $e) {
            NotificationService::error('Não foi possível ler a estrutura da tabela.', $e->getMessage());
            header('Location: ' . url("/banco-dados/console/tabelas?conexao={$conexao['id']}&banco=" . urlencode($banco)));
            exit;
        }

        $this->view('database/console_dados_form', [
            'conexao' => $conexao,
            'banco' => $banco,
            'tabela' => $tabela,
            'colunas' => $colunas,
            'linha' => null,
            'pkAntigo' => [],
        ]);
    }

    public function dadosInserir(): void
    {
        AuthMiddleware::checkModulo('bd_mysql');

        $conexaoId = (int)($_POST['conexao'] ?? 0);
        $banco = trim($_POST['banco'] ?? '');
        $tabela = trim($_POST['tabela'] ?? '');
        $conexao = $this->conexaoService->buscar($conexaoId);

        if (!$conexao || $banco === '' || $tabela === '') {
            header('Location: ' . url('/banco-dados/conexoes'));
            exit;
        }

        try {
            $valores = $this->valoresDoPost($conexaoId, $banco, $tabela);
        } catch (\Throwable $e) {
            NotificationService::error('Não foi possível ler a estrutura da tabela.', $e->getMessage());
            header('Location: ' . url("/banco-dados/console/dados?conexao={$conexaoId}&banco=" . urlencode($banco) . '&tabela=' . urlencode($tabela)));
            exit;
        }

        $resultado = $this->service->inserirLinha($conexaoId, $banco, $tabela, $valores);

        AuditService::registrar('Banco de Dados', 'Inserir registro', "Conexão {$conexao['nome']}, {$banco}.{$tabela}: " . ($resultado['success'] ? 'ok' : $resultado['mensagem']));

        if ($resultado['success']) {
            NotificationService::success($resultado['mensagem']);
            header('Location: ' . url("/banco-dados/console/dados?conexao={$conexaoId}&banco=" . urlencode($banco) . '&tabela=' . urlencode($tabela)));
            exit;
        }

        NotificationService::error($resultado['mensagem']);
        header('Location: ' . url("/banco-dados/console/dados/inserir?conexao={$conexaoId}&banco=" . urlencode($banco) . '&tabela=' . urlencode($tabela)));
        exit;
    }

    public function dadosEditarForm(): void
    {
        AuthMiddleware::checkModulo('bd_mysql');

        [$conexao, $banco, $tabela] = $this->exigirContexto();

        $pk = $this->pkDaQuery($_GET['pk'] ?? []);

        try {
            $linha = $this->service->buscarLinha($conexao['id'], $banco, $tabela, $pk);
            $colunas = $this->service->listarColunas($conexao['id'], $banco, $tabela);
        } catch (\Throwable $e) {
            NotificationService::error('Não foi possível ler o registro.', $e->getMessage());
            header('Location: ' . url("/banco-dados/console/dados?conexao={$conexao['id']}&banco=" . urlencode($banco) . '&tabela=' . urlencode($tabela)));
            exit;
        }

        if (!$linha) {
            header('Location: ' . url("/banco-dados/console/dados?conexao={$conexao['id']}&banco=" . urlencode($banco) . '&tabela=' . urlencode($tabela)));
            exit;
        }

        $this->view('database/console_dados_form', [
            'conexao' => $conexao,
            'banco' => $banco,
            'tabela' => $tabela,
            'colunas' => $colunas,
            'linha' => $linha,
            'pkAntigo' => $pk,
        ]);
    }

    public function dadosEditar(): void
    {
        AuthMiddleware::checkModulo('bd_mysql');

        $conexaoId = (int)($_POST['conexao'] ?? 0);
        $banco = trim($_POST['banco'] ?? '');
        $tabela = trim($_POST['tabela'] ?? '');
        $conexao = $this->conexaoService->buscar($conexaoId);

        if (!$conexao || $banco === '' || $tabela === '') {
            header('Location: ' . url('/banco-dados/conexoes'));
            exit;
        }

        $pkAntigo = $this->pkDaQuery($_POST['pk_antigo'] ?? []);

        try {
            $valores = $this->valoresDoPost($conexaoId, $banco, $tabela);
        } catch (\Throwable $e) {
            NotificationService::error('Não foi possível ler a estrutura da tabela.', $e->getMessage());
            header('Location: ' . url("/banco-dados/console/dados?conexao={$conexaoId}&banco=" . urlencode($banco) . '&tabela=' . urlencode($tabela)));
            exit;
        }

        $resultado = $this->service->atualizarLinha($conexaoId, $banco, $tabela, $valores, $pkAntigo);

        AuditService::registrar('Banco de Dados', 'Editar registro', "Conexão {$conexao['nome']}, {$banco}.{$tabela}: " . ($resultado['success'] ? 'ok' : $resultado['mensagem']));

        if ($resultado['success']) {
            NotificationService::success($resultado['mensagem']);
        } else {
            NotificationService::error($resultado['mensagem']);
        }

        header('Location: ' . url("/banco-dados/console/dados?conexao={$conexaoId}&banco=" . urlencode($banco) . '&tabela=' . urlencode($tabela)));
        exit;
    }

    /**
     * Edicao inline (duplo-clique numa celula da tabela de dados) -- reusa
     * atualizarLinha() com um unico par coluna/valor.
     */
    public function dadosAtualizarCelula(): void
    {
        AuthMiddleware::checkModulo('bd_mysql');
        header('Content-Type: application/json');

        $conexaoId = (int)($_POST['conexao'] ?? 0);
        $banco = trim($_POST['banco'] ?? '');
        $tabela = trim($_POST['tabela'] ?? '');
        $coluna = trim($_POST['coluna'] ?? '');
        $ehNulo = isset($_POST['nulo']);
        $valor = $_POST['valor'] ?? '';
        $pk = $this->pkDaQuery($_POST['pk'] ?? []);

        if ($coluna === '' || empty($pk)) {
            echo json_encode(['success' => false, 'mensagem' => 'Requisição inválida.']);
            return;
        }

        $resultado = $this->service->atualizarLinha($conexaoId, $banco, $tabela, [$coluna => $ehNulo ? null : $valor], $pk);

        AuditService::registrar('Banco de Dados', 'Editar célula', "{$banco}.{$tabela}.{$coluna}: " . ($resultado['success'] ? 'ok' : $resultado['mensagem']));

        echo json_encode($resultado);
    }

    public function dadosExcluir(): void
    {
        AuthMiddleware::checkModulo('bd_mysql');
        header('Content-Type: application/json');

        $conexaoId = (int)($_POST['conexao'] ?? 0);
        $banco = trim($_POST['banco'] ?? '');
        $tabela = trim($_POST['tabela'] ?? '');
        $pk = $this->pkDaQuery($_POST['pk'] ?? []);

        $resultado = $this->service->excluirLinha($conexaoId, $banco, $tabela, $pk);

        AuditService::registrar('Banco de Dados', 'Excluir registro', "{$banco}.{$tabela}: " . ($resultado['success'] ? 'ok' : $resultado['mensagem']));

        echo json_encode($resultado);
    }

    public function dadosDuplicar(): void
    {
        AuthMiddleware::checkModulo('bd_mysql');
        header('Content-Type: application/json');

        $conexaoId = (int)($_POST['conexao'] ?? 0);
        $banco = trim($_POST['banco'] ?? '');
        $tabela = trim($_POST['tabela'] ?? '');
        $pk = $this->pkDaQuery($_POST['pk'] ?? []);

        $resultado = $this->service->duplicarLinha($conexaoId, $banco, $tabela, $pk);

        AuditService::registrar('Banco de Dados', 'Duplicar registro', "{$banco}.{$tabela}: " . ($resultado['success'] ? 'ok' : $resultado['mensagem']));

        echo json_encode($resultado);
    }

    public function dadosExportar(): void
    {
        AuthMiddleware::checkModulo('bd_mysql');

        [$conexao, $banco, $tabela] = $this->exigirContexto();
        $busca = trim($_GET['busca'] ?? '');

        try {
            $dados = $this->service->exportarLinhas($conexao['id'], $banco, $tabela, $busca);
        } catch (\Throwable $e) {
            NotificationService::error('Não foi possível exportar os dados.', $e->getMessage());
            header('Location: ' . url("/banco-dados/console/dados?conexao={$conexao['id']}&banco=" . urlencode($banco) . '&tabela=' . urlencode($tabela)));
            exit;
        }

        AuditService::registrar('Banco de Dados', 'Exportar CSV', "{$banco}.{$tabela} (" . count($dados['linhas']) . ' linhas)');

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $tabela . '.csv"');

        $saida = fopen('php://output', 'w');
        fputcsv($saida, $dados['colunas'], ';');
        foreach ($dados['linhas'] as $linha) {
            fputcsv($saida, $linha, ';');
        }
        fclose($saida);
        exit;
    }

    public function arvoreBancos(): void
    {
        AuthMiddleware::checkModulo('bd_mysql');
        header('Content-Type: application/json');

        $conexaoId = (int)($_GET['conexao'] ?? 0);

        try {
            echo json_encode(['success' => true, 'bancos' => $this->service->listarBancos($conexaoId)]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'mensagem' => $e->getMessage()]);
        }
    }

    public function arvoreTabelas(): void
    {
        AuthMiddleware::checkModulo('bd_mysql');
        header('Content-Type: application/json');

        $conexaoId = (int)($_GET['conexao'] ?? 0);
        $banco = trim($_GET['banco'] ?? '');

        try {
            $tabelas = array_column($this->service->listarTabelas($conexaoId, $banco), 'Name');
            echo json_encode(['success' => true, 'tabelas' => $tabelas]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'mensagem' => $e->getMessage()]);
        }
    }

    public function sqlForm(): void
    {
        AuthMiddleware::checkModulo('bd_mysql');

        $conexaoId = (int)($_GET['conexao'] ?? 0);
        $banco = trim($_GET['banco'] ?? '');
        $conexao = $this->conexaoService->buscar($conexaoId);

        if (!$conexao) {
            header('Location: ' . url('/banco-dados/conexoes'));
            exit;
        }

        $this->view('database/console_sql', [
            'conexao' => $conexao,
            'banco' => $banco,
            'sql' => '',
            'resultado' => null,
        ]);
    }

    public function sqlExecutar(): void
    {
        AuthMiddleware::checkModulo('bd_mysql');

        $conexaoId = (int)($_POST['conexao'] ?? 0);
        $banco = trim($_POST['banco'] ?? '');
        $sql = $_POST['sql'] ?? '';
        $conexao = $this->conexaoService->buscar($conexaoId);

        if (!$conexao) {
            header('Location: ' . url('/banco-dados/conexoes'));
            exit;
        }

        $resultado = $this->service->executarSql($conexaoId, $banco ?: null, $sql);

        $resumo = $resultado['success']
            ? ($resultado['tipo'] === 'linhas' ? $resultado['total'] . ' linha(s) retornada(s).' : $resultado['linhas_afetadas'] . ' linha(s) afetada(s).')
            : 'Erro: ' . $resultado['mensagem'];

        AuditService::registrar(
            'Banco de Dados',
            'Executar SQL',
            "Conexão {$conexao['nome']}" . ($banco ? ", banco {$banco}" : '') . ": " . substr($sql, 0, 200) . " -- {$resumo}"
        );

        $this->view('database/console_sql', [
            'conexao' => $conexao,
            'banco' => $banco,
            'sql' => $sql,
            'resultado' => $resultado,
        ]);
    }

    private function exigirContexto(): array
    {
        $conexaoId = (int)($_GET['conexao'] ?? 0);
        $banco = trim($_GET['banco'] ?? '');
        $tabela = trim($_GET['tabela'] ?? '');
        $conexao = $this->conexaoService->buscar($conexaoId);

        if (!$conexao || $banco === '' || $tabela === '') {
            header('Location: ' . url('/banco-dados/conexoes'));
            exit;
        }

        return [$conexao, $banco, $tabela];
    }

    /**
     * Versao POST de exigirContexto() -- so pega o id da conexao (nao o
     * array inteiro), pra usar direto nas chamadas ao service.
     */
    private function contextoDoPost(): array
    {
        $conexaoId = (int)($_POST['conexao'] ?? 0);
        $banco = trim($_POST['banco'] ?? '');
        $tabela = trim($_POST['tabela'] ?? '');

        if (!$this->conexaoService->buscar($conexaoId) || $banco === '' || $tabela === '') {
            header('Location: ' . url('/banco-dados/conexoes'));
            exit;
        }

        return [$conexaoId, $banco, $tabela];
    }

    private function definicaoColunaDoPost(): array
    {
        return [
            'nome' => trim($_POST['nome'] ?? ''),
            'tipo' => trim($_POST['tipo'] ?? ''),
            'nulo' => isset($_POST['nulo']),
            'padrao' => trim($_POST['padrao'] ?? ''),
            'auto_increment' => isset($_POST['auto_increment']),
            'apos' => trim($_POST['apos'] ?? ''),
        ];
    }

    private function notificarEVoltarEstrutura(array $resultado, int $conexaoId, string $banco, string $tabela): void
    {
        if ($resultado['success']) {
            NotificationService::success($resultado['mensagem']);
        } else {
            NotificationService::error($resultado['mensagem']);
        }

        header('Location: ' . url("/banco-dados/console/estrutura?conexao={$conexaoId}&banco=" . urlencode($banco) . '&tabela=' . urlencode($tabela)));
        exit;
    }

    private function pkDaQuery($pk): array
    {
        return is_array($pk) ? $pk : [];
    }

    /**
     * Monta coluna => valor a partir do POST (campo[Coluna]=valor,
     * nulo[Coluna]=1 pra forcar NULL). Colunas auto_increment deixadas em
     * branco (e sem marcar NULL) sao omitidas, pro banco gerar sozinho.
     *
     * Checa "nulo" ANTES de exigir que "campo" exista: o input de valor fica
     * disabled via JS quando o admin marca NULL, e campos disabled nao sao
     * enviados no POST -- se a ordem fosse invertida, marcar NULL faria a
     * coluna inteira ser ignorada (nem nula nem com o valor antigo).
     */
    private function valoresDoPost(int $conexaoId, string $banco, string $tabela): array
    {
        $colunas = $this->service->listarColunas($conexaoId, $banco, $tabela);
        $campos = $_POST['campo'] ?? [];
        $nulos = $_POST['nulo'] ?? [];

        $valores = [];
        foreach ($colunas as $col) {
            $nome = $col['Field'];
            $ehNulo = isset($nulos[$nome]);

            if (!$ehNulo && !array_key_exists($nome, $campos)) continue;

            $valor = $campos[$nome] ?? '';
            $autoIncrement = str_contains($col['Extra'] ?? '', 'auto_increment');

            if (!$ehNulo && $valor === '' && $autoIncrement) {
                continue;
            }

            $valores[$nome] = $ehNulo ? null : $valor;
        }

        return $valores;
    }
}
