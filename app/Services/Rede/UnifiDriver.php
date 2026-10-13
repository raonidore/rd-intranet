<?php

namespace App\Services\Rede;

use App\Services\UnifiService;

/**
 * UniFi (UDM/UCG/UniFi OS) pela API do Controller com a API Key da
 * integração. Libera visitantes do portal e, no papel de switch, a VLAN é do
 * site inteiro: criar a rede já a disponibiliza em todos os switches/APs, e as
 * portas no padrão levam todas as VLANs -- por isso "adicionar tag" é nada a fazer.
 */
class UnifiDriver implements ControladorPortal, DriverSwitch
{
    private UnifiService $unifi;

    public function __construct(?UnifiService $unifi = null)
    {
        $this->unifi = $unifi ?? new UnifiService();
    }

    public function nome(): string
    {
        return 'UniFi';
    }

    public function disponivel(): bool
    {
        return $this->unifi->configurado();
    }

    public function autorizar(string $mac, int $minutos, ?int $downKbps, ?int $upKbps, ?int $cotaMb, ?string $apMac): array
    {
        return $this->unifi->autorizarConvidado($mac, $minutos, $downKbps, $upKbps, $cotaMb, $apMac);
    }

    public function desautorizar(string $mac): array
    {
        return $this->unifi->desautorizarConvidado($mac);
    }

    public function convidados(): array
    {
        $ips = [];
        foreach ($this->unifi->listarClientesLegado() as $c) {
            $ips[strtolower($c['mac'] ?? '')] = $c['ip'] ?? null;
        }
        $lista = [];
        foreach ($this->unifi->listarConvidados() as $g) {
            $mac = strtolower($g['mac'] ?? '');
            if ($mac === '') {
                continue;
            }
            $lista[$mac] = [
                'ip' => $ips[$mac] ?? ($g['ip'] ?? null),
                'bytes_down' => (int)($g['tx_bytes'] ?? 0), // do ponto de vista do AP: tx = o que desceu pro aparelho
                'bytes_up' => (int)($g['rx_bytes'] ?? 0),
                'ap_mac' => $g['ap_mac'] ?? null,
                'autorizado' => empty($g['expired']) && (int)($g['end'] ?? 0) > time(),
            ];
        }

        return $lista;
    }

    public function capacidades(): array
    {
        return [self::CRIAR_VLAN, self::EXCLUIR_VLAN, self::VLAN_PORTA, self::ADICIONAR_TAG, self::VIZINHOS_LLDP];
    }

    public function criarVlan(array $ativo, int $vlan, string $nome): array
    {
        return ['success' => false, 'message' => 'No UniFi a VLAN do portal é criada como rede de visitantes pelo assistente.'];
    }

    public function adicionarVlanTagueada(array $ativo, string $porta, int $vlan): array
    {
        return ['success' => true, 'message' => 'UniFi: a porta já leva todas as VLANs do site (padrão).'];
    }
}
