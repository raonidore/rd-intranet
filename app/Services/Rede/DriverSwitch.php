<?php

namespace App\Services\Rede;

/**
 * Operações de VLAN num switch (ou no gateway, no caso UniFi). As telas pedem
 * "crie a VLAN 30" / "leve a VLAN 30 até esta porta" e o driver traduz para a
 * API ou para os comandos da marca. capacidades() diz o que o driver sabe
 * fazer, pra tela só oferecer o que o equipamento suporta.
 */
interface DriverSwitch
{
    public const CRIAR_VLAN = 'criar_vlan';
    public const EXCLUIR_VLAN = 'excluir_vlan';
    public const VLAN_PORTA = 'vlan_porta';
    public const ADICIONAR_TAG = 'adicionar_tag';
    public const VIZINHOS_LLDP = 'vizinhos_lldp';

    public function nome(): string;

    /** @return string[] constantes acima */
    public function capacidades(): array;

    /** @return array{success: bool, message: string} */
    public function criarVlan(array $ativo, int $vlan, string $nome): array;

    /** @return array{success: bool, message: string} acrescenta a VLAN tagueada na porta, sem tirar nada */
    public function adicionarVlanTagueada(array $ativo, string $porta, int $vlan): array;
}
