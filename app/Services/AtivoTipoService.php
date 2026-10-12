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
        'bi-wifi' => 'Ponto de Acesso',
        'bi-camera-video' => 'Câmera',
        'bi-camera-reels' => 'DVR/NVR',
        'bi-tablet' => 'Tablet',
        'bi-phone' => 'Telefone',
        'bi-projector' => 'Projetor',
        'bi-usb-plug' => 'Periférico',
        'bi-box-seam' => 'Genérico',
    ];

    /**
     * Tipos que o próprio sistema usa pelo slug (agente registra máquina
     * como 'computador', coletas UniFi/Omada/SNMP/Intelbras/USR, campos
     * técnicos de AtivoService::CAMPOS_DETALHES etc.) -- sem eles essas
     * integrações quebram em silêncio. Garantidos na instalação e a cada
     * atualização (garantirPadrao()) e protegidos contra exclusão.
     * Valores iguais aos das migrations que criaram cada um.
     *
     * slug => [nome, sigla, ícone, elegível a SNMP]
     */
    public const TIPOS_PADRAO = [
        'computador' => ['Computador', 'PC', 'bi-pc-display', true],
        'servidor' => ['Servidor', 'SRV', 'bi-hdd-rack', true],
        'monitor' => ['Monitor', 'MON', 'bi-display', false],
        'impressora' => ['Impressora', 'IMP', 'bi-printer', true],
        'switch' => ['Switch', 'SW', 'bi-hdd-network', true],
        'ponto_acesso' => ['Ponto de Acesso', 'AP', 'bi-wifi', false],
        'roteador' => ['Roteador/Gateway', 'GW', 'bi-router', false],
        'dvr_nvr' => ['DVR/NVR', 'DVR', 'bi-camera-video', true],
        'periferico' => ['Periféricos', 'PER', 'bi-usb-plug', true],
    ];

    public function __construct()
    {
        $this->repository = new AtivoTipoRepository();
    }

    /**
     * Recria qualquer tipo padrão que esteja faltando (instalação nova,
     * exclusão antiga feita antes da proteção existir). Idempotente.
     *
     * @return string[] slugs que foram criados/adotados agora
     */
    public function garantirPadrao(): array
    {
        $alterados = [];

        foreach (self::TIPOS_PADRAO as $slug => [$nome, $sigla, $icone, $snmp]) {
            if ($this->repository->garantirPadrao($slug, $nome, $sigla, $icone, $snmp)) {
                $alterados[] = $slug;
            }
        }

        return $alterados;
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

        if (isset(self::TIPOS_PADRAO[$item['slug'] ?? ''])) {
            NotificationService::error('O tipo "' . $item['nome'] . '" é padrão do sistema (usado pelas integrações) e não pode ser excluído -- nome, sigla e ícone podem ser editados.');
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
