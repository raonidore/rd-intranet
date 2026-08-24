<?php

namespace App\Services;

use App\Repositories\AtivoTipoRepository;

class AtivoTipoService
{
    private AtivoTipoRepository $repository;

    /** Ícones oferecidos no cadastro -- lista curada (Bootstrap Icons) pra não depender do admin saber o nome da classe de cor. */
    public const ICONES_DISPONIVEIS = [
        'bi-pc-display' => 'Computador',
        'bi-hdd-rack' => 'Servidor',
        'bi-display' => 'Monitor',
        'bi-printer' => 'Impressora',
        'bi-hdd-network' => 'Switch',
        'bi-router' => 'Roteador',
        'bi-camera-video' => 'Câmera',
        'bi-tablet' => 'Tablet',
        'bi-phone' => 'Telefone',
        'bi-projector' => 'Projetor',
        'bi-box-seam' => 'Genérico',
    ];

    public function __construct()
    {
        $this->repository = new AtivoTipoRepository();
    }

    public function listar(): array
    {
        return $this->repository->listar();
    }

    public function listarAtivos(): array
    {
        return $this->repository->listarAtivos();
    }

    public function buscar(int $id): ?array
    {
        return $this->repository->buscarPorId($id);
    }

    public function buscarPorSlug(string $slug): ?array
    {
        return $this->repository->buscarPorSlug($slug);
    }

    private function validar(string $nome, string $sigla, string $icone): bool
    {
        if ($nome === '') {
            NotificationService::error('Informe o nome do tipo de ativo.');
            return false;
        }

        if (!preg_match('/^[A-Z]{2,6}$/', $sigla)) {
            NotificationService::error('A sigla deve ter de 2 a 6 letras (sem números ou símbolos).');
            return false;
        }

        if (!isset(self::ICONES_DISPONIVEIS[$icone])) {
            NotificationService::error('Ícone inválido.');
            return false;
        }

        return true;
    }

    public function criar(string $nome, string $sigla, string $icone, bool $snmpElegivel): bool
    {
        $nome = trim($nome);
        $sigla = strtoupper(trim($sigla));

        if (!$this->validar($nome, $sigla, $icone)) {
            return false;
        }

        if ($this->repository->existeNome($nome)) {
            NotificationService::error('Já existe um tipo de ativo chamado "' . $nome . '".');
            return false;
        }

        if ($this->repository->existeSigla($sigla)) {
            NotificationService::error('Já existe um tipo de ativo com a sigla "' . $sigla . '".');
            return false;
        }

        $this->repository->criar($nome, $sigla, $icone, $snmpElegivel);

        AuditService::registrar('Ativos', 'Cadastro', 'Tipo de ativo "' . $nome . '" (' . $sigla . ') criado.');

        NotificationService::success('Tipo de ativo cadastrado com sucesso.');

        return true;
    }

    public function atualizar(int $id, string $nome, string $sigla, string $icone, bool $snmpElegivel): bool
    {
        $item = $this->repository->buscarPorId($id);
        if (!$item) {
            NotificationService::error('Tipo de ativo não encontrado.');
            return false;
        }

        $nome = trim($nome);
        $sigla = strtoupper(trim($sigla));

        if (!$this->validar($nome, $sigla, $icone)) {
            return false;
        }

        if ($this->repository->existeOutroNome($nome, $id)) {
            NotificationService::error('Já existe um tipo de ativo chamado "' . $nome . '".');
            return false;
        }

        if ($this->repository->existeOutraSigla($sigla, $id)) {
            NotificationService::error('Já existe um tipo de ativo com a sigla "' . $sigla . '".');
            return false;
        }

        $this->repository->atualizar($id, $nome, $sigla, $icone, $snmpElegivel);

        AuditService::registrar('Ativos', 'Cadastro', 'Tipo de ativo "' . $item['nome'] . '" atualizado para "' . $nome . '" (' . $sigla . ').');

        NotificationService::success('Tipo de ativo atualizado com sucesso.');

        return true;
    }

    public function excluir(int $id): bool
    {
        $item = $this->repository->buscarPorId($id);
        if (!$item) {
            NotificationService::error('Tipo de ativo não encontrado.');
            return false;
        }

        $usos = $this->repository->contarUsos($id);
        if ($usos > 0) {
            NotificationService::error('Não é possível excluir: ' . $usos . ' ativo(s) usam este tipo. Troque-os antes de excluir.');
            return false;
        }

        $this->repository->excluir($id);

        AuditService::registrar('Ativos', 'Cadastro', 'Tipo de ativo "' . $item['nome'] . '" removido.');

        NotificationService::success('Tipo de ativo removido.');

        return true;
    }
}
