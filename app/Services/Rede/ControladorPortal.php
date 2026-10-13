<?php

namespace App\Services\Rede;

/**
 * Equipamento que libera aparelhos do captive portal (o "dono" do Wi-Fi com
 * portal). Cada marca implementa; o portal do RD.Intranet só conversa com
 * esta interface -- marca nova = driver novo, sem mexer no portal.
 */
interface ControladorPortal
{
    public function nome(): string;

    public function disponivel(): bool;

    /** @return array{success: bool, message: string} */
    public function autorizar(string $mac, int $minutos, ?int $downKbps, ?int $upKbps, ?int $cotaMb, ?string $apMac): array;

    /** @return array{success: bool, message: string} */
    public function desautorizar(string $mac): array;

    /**
     * Aparelhos que o equipamento conhece como visitantes.
     * @return array<string, array{ip: ?string, bytes_down: int, bytes_up: int, ap_mac: ?string, autorizado: bool}> por MAC
     */
    public function convidados(): array;
}
