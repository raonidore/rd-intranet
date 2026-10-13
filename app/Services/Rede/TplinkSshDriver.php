<?php

namespace App\Services\Rede;

use App\Services\OmadaSshService;

/** Switch TP-Link (SG3428 e família) pela CLI via SSH -- ver OmadaSshService. */
class TplinkSshDriver implements DriverSwitch
{
    private OmadaSshService $ssh;

    public function __construct(?OmadaSshService $ssh = null)
    {
        $this->ssh = $ssh ?? new OmadaSshService();
    }

    public function nome(): string
    {
        return 'TP-Link (SSH)';
    }

    public function capacidades(): array
    {
        return $this->ssh->manutencaoDisponivel()
            ? [self::CRIAR_VLAN, self::EXCLUIR_VLAN, self::VLAN_PORTA, self::ADICIONAR_TAG, self::VIZINHOS_LLDP]
            : [self::VIZINHOS_LLDP];
    }

    public function criarVlan(array $ativo, int $vlan, string $nome): array
    {
        return $this->ssh->salvarVlan((string)$ativo['ip'], $vlan, $nome);
    }

    public function adicionarVlanTagueada(array $ativo, string $porta, int $vlan): array
    {
        return $this->ssh->adicionarVlanTagueada((string)$ativo['ip'], $porta, $vlan);
    }
}
