<?php

namespace App\Services;

use App\Repositories\UserRepository;

class UserService
{
    private const PERFIS_VALIDOS = ['admin', 'ti', 'consulta'];

    private UserRepository $repository;

    public function __construct()
    {
        $this->repository = new UserRepository();
    }

    public function listar(): array
    {
        return $this->repository->listar();
    }

    public function buscar(int $id): ?array
    {
        return $this->repository->buscarPorId($id);
    }

    public function modulosDoUsuario(int $id): array
    {
        return $this->repository->modulosDoUsuario($id);
    }

    public function criar(array $dados): bool
    {
        $nome = trim($dados['nome'] ?? '');
        $login = trim($dados['login'] ?? '');
        $email = trim($dados['email'] ?? '');
        $senha = $dados['senha'] ?? '';
        $perfil = $dados['perfil'] ?? '';

        if ($nome === '' || $login === '' || $senha === '') {
            NotificationService::error('Preencha nome, login e senha.');
            return false;
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            NotificationService::error('E-mail inválido.');
            return false;
        }

        $errosSenha = PasswordPolicyService::validar($senha, ['nome' => $nome, 'login' => $login, 'email' => $email]);
        if ($errosSenha) {
            NotificationService::error($errosSenha[0]);
            return false;
        }

        if (!in_array($perfil, self::PERFIS_VALIDOS, true)) {
            NotificationService::error('Perfil inválido.');
            return false;
        }

        if ($this->repository->buscarPorLogin($login)) {
            NotificationService::error('Já existe um usuário com este login.');
            return false;
        }

        $id = $this->repository->criar(
            $nome,
            $login,
            password_hash($senha, PASSWORD_DEFAULT),
            $perfil,
            $email !== '' ? $email : null
        );

        $this->repository->salvarModulos($id, $this->modulosValidos($dados['modulos'] ?? []));

        return true;
    }

    public function atualizar(int $id, array $dados): bool
    {
        $nome = trim($dados['nome'] ?? '');
        $email = trim($dados['email'] ?? '');
        $perfil = $dados['perfil'] ?? '';

        if ($nome === '') {
            NotificationService::error('Informe o nome do usuário.');
            return false;
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            NotificationService::error('E-mail inválido.');
            return false;
        }

        if (!in_array($perfil, self::PERFIS_VALIDOS, true)) {
            NotificationService::error('Perfil inválido.');
            return false;
        }

        $usuario = $this->repository->buscarPorId($id);

        if (!$usuario) {
            NotificationService::error('Usuário não encontrado.');
            return false;
        }

        if ($usuario['perfil'] === 'admin' && $perfil !== 'admin' && $this->repository->contarAdmins() <= 1) {
            NotificationService::error('Não é possível remover o último administrador do sistema.');
            return false;
        }

        $this->repository->atualizar($id, $nome, $perfil, $email !== '' ? $email : null);
        $this->repository->salvarModulos($id, $this->modulosValidos($dados['modulos'] ?? []));

        return true;
    }

    public function redefinirSenha(int $id, string $senha, string $confirmacao): bool
    {
        $usuario = $this->repository->buscarPorId($id);

        $errosSenha = PasswordPolicyService::validar($senha, [
            'nome' => $usuario['nome'] ?? '',
            'login' => $usuario['login'] ?? '',
            'email' => $usuario['email'] ?? '',
        ]);
        if ($errosSenha) {
            NotificationService::error($errosSenha[0]);
            return false;
        }

        if ($senha !== $confirmacao) {
            NotificationService::error('As senhas não conferem.');
            return false;
        }

        $this->repository->atualizarSenha($id, password_hash($senha, PASSWORD_DEFAULT));

        return true;
    }

    public function ativar(int $id): void
    {
        $this->repository->definirAtivo($id, true);
    }

    public function desativar(int $id): bool
    {
        if ($this->ehUsuarioLogado($id)) {
            NotificationService::error('Você não pode desativar seu próprio usuário.');
            return false;
        }

        $usuario = $this->repository->buscarPorId($id);

        if ($usuario && $usuario['perfil'] === 'admin' && $this->repository->contarAdminsAtivos() <= 1) {
            NotificationService::error('Não é possível desativar o último administrador ativo.');
            return false;
        }

        $this->repository->definirAtivo($id, false);

        return true;
    }

    public function excluir(int $id): bool
    {
        if ($this->ehUsuarioLogado($id)) {
            NotificationService::error('Você não pode excluir seu próprio usuário.');
            return false;
        }

        $usuario = $this->repository->buscarPorId($id);

        if ($usuario && $usuario['perfil'] === 'admin' && $this->repository->contarAdmins() <= 1) {
            NotificationService::error('Não é possível excluir o último administrador do sistema.');
            return false;
        }

        $this->repository->excluir($id);

        return true;
    }

    public function atualizarNomeProprio(int $id, string $nome): bool
    {
        $nome = trim($nome);

        if ($nome === '') {
            NotificationService::error('Informe o nome.');
            return false;
        }

        $this->repository->atualizarNome($id, $nome);
        $_SESSION['usuario']['nome'] = $nome;

        return true;
    }

    public function alterarSenhaProprio(int $id, string $atual, string $nova, string $confirmacao): bool
    {
        $hashAtual = $this->repository->buscarHashSenha($id);

        if (!$hashAtual || !password_verify($atual, $hashAtual)) {
            NotificationService::error('Senha atual incorreta.');
            return false;
        }

        $usuario = $this->repository->buscarPorId($id);

        $errosSenha = PasswordPolicyService::validar($nova, [
            'nome' => $usuario['nome'] ?? '',
            'login' => $usuario['login'] ?? '',
            'email' => $usuario['email'] ?? '',
        ]);
        if ($errosSenha) {
            NotificationService::error($errosSenha[0]);
            return false;
        }

        if ($nova !== $confirmacao) {
            NotificationService::error('As senhas não conferem.');
            return false;
        }

        $this->repository->atualizarSenha($id, password_hash($nova, PASSWORD_DEFAULT));

        return true;
    }

    private function ehUsuarioLogado(int $id): bool
    {
        return $id === (int)($_SESSION['usuario']['id'] ?? 0);
    }

    private function modulosValidos(array $modulos): array
    {
        return array_values(array_intersect($modulos, ModuloCatalogo::chaves()));
    }
}
