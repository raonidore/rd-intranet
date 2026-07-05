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
        $senha = $dados['senha'] ?? '';
        $perfil = $dados['perfil'] ?? '';

        if ($nome === '' || $login === '' || $senha === '') {
            NotificationService::error('Preencha nome, login e senha.');
            return false;
        }

        if (strlen($senha) < 8) {
            NotificationService::error('A senha deve ter pelo menos 8 caracteres.');
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
            $perfil
        );

        $this->repository->salvarModulos($id, $this->modulosValidos($dados['modulos'] ?? []));

        return true;
    }

    public function atualizar(int $id, array $dados): bool
    {
        $nome = trim($dados['nome'] ?? '');
        $perfil = $dados['perfil'] ?? '';

        if ($nome === '') {
            NotificationService::error('Informe o nome do usuário.');
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

        $this->repository->atualizar($id, $nome, $perfil);
        $this->repository->salvarModulos($id, $this->modulosValidos($dados['modulos'] ?? []));

        return true;
    }

    public function redefinirSenha(int $id, string $senha, string $confirmacao): bool
    {
        if (strlen($senha) < 8) {
            NotificationService::error('A senha deve ter pelo menos 8 caracteres.');
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

    private function ehUsuarioLogado(int $id): bool
    {
        return $id === (int)($_SESSION['usuario']['id'] ?? 0);
    }

    private function modulosValidos(array $modulos): array
    {
        return array_values(array_intersect($modulos, ModuloCatalogo::chaves()));
    }
}
