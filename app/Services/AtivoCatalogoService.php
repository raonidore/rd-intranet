<?php

namespace App\Services;

use App\Repositories\AtivoCatalogoRepository;

class AtivoCatalogoService
{
    private AtivoCatalogoRepository $repository;

    public const TIPOS = [
        'setor' => 'Setor',
        'localizacao' => 'Localização',
    ];

    public function __construct()
    {
        $this->repository = new AtivoCatalogoRepository();
    }

    public function listarSetores(): array
    {
        return $this->repository->listarPorTipo('setor');
    }

    public function listarLocalizacoes(): array
    {
        return $this->repository->listarPorTipo('localizacao');
    }

    public function criar(string $tipo, string $nome): bool
    {
        if (!isset(self::TIPOS[$tipo])) {
            NotificationService::error('Tipo de cadastro inválido.');
            return false;
        }

        $nome = trim($nome);
        if ($nome === '') {
            NotificationService::error('Informe um nome.');
            return false;
        }

        if ($this->repository->existe($tipo, $nome)) {
            NotificationService::error(self::TIPOS[$tipo] . ' "' . $nome . '" já existe.');
            return false;
        }

        $this->repository->criar($tipo, $nome);

        AuditService::registrar('Ativos', 'Cadastro', self::TIPOS[$tipo] . ' "' . $nome . '" criado.');

        NotificationService::success(self::TIPOS[$tipo] . ' cadastrado com sucesso.');

        return true;
    }

    public function atualizar(int $id, string $nome): bool
    {
        $item = $this->repository->buscarPorId($id);
        if (!$item) {
            NotificationService::error('Registro não encontrado.');
            return false;
        }

        $nome = trim($nome);
        if ($nome === '') {
            NotificationService::error('Informe um nome.');
            return false;
        }

        if ($nome === $item['nome']) {
            return true;
        }

        if ($this->repository->existeOutro($item['tipo'], $nome, $id)) {
            NotificationService::error((self::TIPOS[$item['tipo']] ?? $item['tipo']) . ' "' . $nome . '" já existe.');
            return false;
        }

        $this->repository->atualizar($id, $nome);

        AuditService::registrar('Ativos', 'Cadastro', (self::TIPOS[$item['tipo']] ?? $item['tipo']) . ' "' . $item['nome'] . '" renomeado para "' . $nome . '".');

        NotificationService::success('Atualizado com sucesso.');

        return true;
    }

    public function excluir(int $id): bool
    {
        $item = $this->repository->buscarPorId($id);
        if (!$item) {
            NotificationService::error('Registro não encontrado.');
            return false;
        }

        $usos = $this->repository->contarUsos($id);
        if ($usos > 0) {
            NotificationService::error('Não é possível excluir: ' . $usos . ' ativo(s) usam este cadastro. Troque-os antes de excluir.');
            return false;
        }

        $this->repository->excluir($id);

        AuditService::registrar('Ativos', 'Cadastro', (self::TIPOS[$item['tipo']] ?? $item['tipo']) . ' "' . $item['nome'] . '" removido.');

        NotificationService::success('Removido com sucesso.');

        return true;
    }
}
