<?php

namespace App\Services;

use App\Repositories\UnidadeRepository;

class UnidadeService
{
    private UnidadeRepository $repository;

    public function __construct()
    {
        $this->repository = new UnidadeRepository();
    }

    public function listar(): array
    {
        return $this->repository->listar();
    }

    public function listarAtivas(): array
    {
        return $this->repository->listarAtivas();
    }

    public function buscar(int $id): ?array
    {
        return $this->repository->buscarPorId($id);
    }

    /** Unidade usada como fallback pra ativo que não teve unidade escolhida (ex: cadastro via agente). */
    public function padrao(): ?array
    {
        return $this->repository->buscarPadrao();
    }

    private function validarSigla(string $sigla): bool
    {
        if (!preg_match('/^[A-Z]{2,6}$/', $sigla)) {
            NotificationService::error('A sigla da unidade deve ter de 2 a 6 letras (sem números ou símbolos).');
            return false;
        }

        return true;
    }

    public function criar(string $nome, string $sigla): bool
    {
        $nome = trim($nome);
        $sigla = strtoupper(trim($sigla));

        if ($nome === '') {
            NotificationService::error('Informe o nome da unidade.');
            return false;
        }

        if (!$this->validarSigla($sigla)) {
            return false;
        }

        if ($this->repository->existeSigla($sigla)) {
            NotificationService::error('Já existe uma unidade com a sigla "' . $sigla . '".');
            return false;
        }

        $this->repository->criar($nome, $sigla);

        AuditService::registrar('Administração', 'Unidades', 'Unidade "' . $nome . '" (' . $sigla . ') criada.');

        NotificationService::success('Unidade cadastrada com sucesso.');

        return true;
    }

    public function atualizar(int $id, string $nome, string $sigla): bool
    {
        $item = $this->repository->buscarPorId($id);
        if (!$item) {
            NotificationService::error('Unidade não encontrada.');
            return false;
        }

        $nome = trim($nome);
        $sigla = strtoupper(trim($sigla));

        if ($nome === '') {
            NotificationService::error('Informe o nome da unidade.');
            return false;
        }

        if (!$this->validarSigla($sigla)) {
            return false;
        }

        if ($this->repository->existeOutraSigla($sigla, $id)) {
            NotificationService::error('Já existe uma unidade com a sigla "' . $sigla . '".');
            return false;
        }

        $this->repository->atualizar($id, $nome, $sigla);

        AuditService::registrar('Administração', 'Unidades', 'Unidade "' . $item['nome'] . '" atualizada para "' . $nome . '" (' . $sigla . ').');

        NotificationService::success('Unidade atualizada com sucesso.');

        return true;
    }

    public function excluir(int $id): bool
    {
        $item = $this->repository->buscarPorId($id);
        if (!$item) {
            NotificationService::error('Unidade não encontrada.');
            return false;
        }

        if ((int)$item['padrao'] === 1) {
            NotificationService::error('Não é possível excluir a unidade padrão. Cadastre outra unidade e marque-a antes, se precisar substituir esta.');
            return false;
        }

        $usos = $this->repository->contarUsos($id);
        if ($usos > 0) {
            NotificationService::error('Não é possível excluir: ' . $usos . ' ativo(s) usam esta unidade. Troque-os antes de excluir.');
            return false;
        }

        $this->repository->excluir($id);

        AuditService::registrar('Administração', 'Unidades', 'Unidade "' . $item['nome'] . '" removida.');

        NotificationService::success('Unidade removida.');

        return true;
    }
}
