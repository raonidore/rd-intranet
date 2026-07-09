<?php

namespace App\Services;

use App\Repositories\DbConexaoRepository;
use PDO;
use PDOException;

class DbConexaoService
{
    private DbConexaoRepository $repository;

    public function __construct()
    {
        $this->repository = new DbConexaoRepository();
    }

    public function listar(): array
    {
        return $this->repository->listar();
    }

    public function buscar(int $id): ?array
    {
        $item = $this->repository->buscarPorId($id);

        if ($item) {
            unset($item['senha_cifrada']);
        }

        return $item;
    }

    public function criar(array $dados): bool
    {
        [$nome, $host, $porta, $usuario, $senha] = $this->validarDadosBasicos($dados);

        if ($nome === null) {
            return false;
        }

        if ($senha === '') {
            NotificationService::error('Informe a senha da conexão.');
            return false;
        }

        $this->repository->criar([
            'nome' => $nome,
            'host' => $host,
            'porta' => $porta,
            'usuario' => $usuario,
            'senha_cifrada' => CryptoService::encriptar($senha),
            'banco_padrao' => trim($dados['banco_padrao'] ?? ''),
        ]);

        return true;
    }

    public function atualizar(int $id, array $dados): bool
    {
        [$nome, $host, $porta, $usuario] = $this->validarDadosBasicos($dados);

        if ($nome === null) {
            return false;
        }

        if (!$this->repository->buscarPorId($id)) {
            NotificationService::error('Conexão não encontrada.');
            return false;
        }

        $this->repository->atualizar($id, [
            'nome' => $nome,
            'host' => $host,
            'porta' => $porta,
            'usuario' => $usuario,
            'banco_padrao' => trim($dados['banco_padrao'] ?? ''),
        ]);

        return true;
    }

    public function redefinirSenha(int $id, string $senha): bool
    {
        if ($senha === '') {
            NotificationService::error('Informe a nova senha.');
            return false;
        }

        if (!$this->repository->buscarPorId($id)) {
            NotificationService::error('Conexão não encontrada.');
            return false;
        }

        $this->repository->atualizarSenha($id, CryptoService::encriptar($senha));

        return true;
    }

    public function ativar(int $id): void
    {
        $this->repository->definirAtivo($id, true);
    }

    public function desativar(int $id): void
    {
        $this->repository->definirAtivo($id, false);
    }

    public function excluir(int $id): bool
    {
        return $this->repository->excluir($id);
    }

    /**
     * Abre um PDO efêmero pra conexão salva. Usado tanto pelo teste de
     * conexão quanto pelo console (nunca fica aberto entre requisições).
     */
    public function conectar(int $id, ?string $banco = null): PDO
    {
        $conexao = $this->repository->buscarPorId($id);

        if (!$conexao) {
            throw new PDOException('Conexão não encontrada.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;charset=utf8mb4',
            $conexao['host'],
            $conexao['porta']
        );

        if ($banco) {
            $dsn .= ';dbname=' . $banco;
        } elseif ($conexao['banco_padrao']) {
            $dsn .= ';dbname=' . $conexao['banco_padrao'];
        }

        $senha = CryptoService::decriptar($conexao['senha_cifrada']);

        $pdo = new PDO($dsn, $conexao['usuario'], $senha, [
            PDO::ATTR_TIMEOUT => 5,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        return $pdo;
    }

    public function testar(int $id): array
    {
        try {
            $pdo = $this->conectar($id);
            $pdo->query('SELECT 1');

            return ['success' => true, 'mensagem' => 'Conexão bem-sucedida.'];
        } catch (PDOException $e) {
            return ['success' => false, 'mensagem' => 'Falha ao conectar: ' . $e->getMessage()];
        }
    }

    private function validarDadosBasicos(array $dados): array
    {
        $nome = trim($dados['nome'] ?? '');
        $host = trim($dados['host'] ?? '');
        $porta = (int)($dados['porta'] ?? 3306);
        $usuario = trim($dados['usuario'] ?? '');
        $senha = $dados['senha'] ?? '';

        if ($nome === '' || $host === '' || $usuario === '') {
            NotificationService::error('Preencha nome, host e usuário.');
            return [null, null, null, null, null];
        }

        if ($porta < 1 || $porta > 65535) {
            NotificationService::error('Porta inválida.');
            return [null, null, null, null, null];
        }

        return [$nome, $host, $porta, $usuario, $senha];
    }
}
