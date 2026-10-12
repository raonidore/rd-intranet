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
     * integrações quebram em silêncio. IGUAIS em todos os servidores: nome,
     * sigla, ícone e SNMP são fixos aqui, garantidos/corrigidos na instalação
     * e a cada atualização (garantirPadrao()) e travados na tela de Cadastros
     * (não editam nem excluem). Valores iguais aos das migrations.
     * Visto no enzilabprime: "Access Point" criado à mão minutos antes da
     * migration do tipo padrão -- ela adotou o cadastro manual (mesma sigla)
     * e o tipo ficou com nome/ícone/SNMP diferentes dos outros servidores.
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

    public static function ehPadrao(?string $slug): bool
    {
        return isset(self::TIPOS_PADRAO[$slug ?? '']);
    }

    /**
     * Recria o tipo padrão que faltar e devolve nome/sigla/ícone/SNMP aos
     * valores oficiais quando alguém mudou (instalação nova, edição feita
     * antes da trava existir). Idempotente.
     *
     * @return string[] slugs criados ou corrigidos agora
     */
    public function garantirPadrao(): array
    {
        $alterados = [];
        $conflitos = [];

        foreach (self::TIPOS_PADRAO as $slug => [$nome, $sigla, $icone, $snmp]) {
            $criado = $this->repository->garantirPadrao($slug, $nome, $sigla, $icone, $snmp);
            $resultado = $this->repository->padronizar($slug, $nome, $sigla, $icone, $snmp);

            if ($criado || $resultado === 'corrigido') {
                $alterados[] = $slug;
            }
            if ($resultado === 'conflito') {
                $conflitos[] = "{$nome} ({$sigla})";
            }
        }

        if ($alterados) {
            AuditService::registrar('Ativos', 'Cadastro', 'Tipos de ativo padrão criados/corrigidos para os valores oficiais: ' . implode(', ', $alterados) . '.');
        }
        if ($conflitos) {
            // Tipo criado pelo cliente ocupa o nome/sigla oficial -- não renomeia nada sozinho.
            error_log('Tipos de ativo padrão não corrigidos (nome/sigla já usados por outro tipo): ' . implode(', ', $conflitos));
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

        if (self::ehPadrao($item['slug'] ?? null)) {
            NotificationService::error('O tipo "' . $item['nome'] . '" é padrão do sistema e é igual em todas as instalações (as integrações dependem dele) -- não pode ser editado.');
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

        if (self::ehPadrao($item['slug'] ?? null)) {
            NotificationService::error('O tipo "' . $item['nome'] . '" é padrão do sistema (usado pelas integrações) e não pode ser excluído.');
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
