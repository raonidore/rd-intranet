<?php

namespace App\Services\Rede;

use App\Services\AtivoService;
use App\Services\WifiConfigService;

/**
 * Registro dos drivers de rede. Para suportar uma marca nova: criar a classe
 * implementando ControladorPortal e/ou DriverSwitch e registrar aqui -- as
 * telas (portal, assistente de rede) não mudam.
 */
class RedeDrivers
{
    /** Controladores de portal disponíveis: chave => [rótulo, classe]. */
    public const CONTROLADORES = [
        'unifi' => ['UniFi (UDM / UCG / Cloud Key)', UnifiDriver::class],
    ];

    public static function controladorPortal(): ?ControladorPortal
    {
        $chave = (new WifiConfigService())->get('controlador');
        $classe = self::CONTROLADORES[$chave][1] ?? null;
        $driver = $classe ? new $classe() : null;

        return $driver && $driver->disponivel() ? $driver : null;
    }

    /** Driver de switch pelo que a coleta do ativo encontrou (UniFi ou TP-Link). */
    public static function paraSwitch(array $ativo): ?DriverSwitch
    {
        $detalhes = is_array($ativo['detalhes'] ?? null) ? $ativo['detalhes'] : (json_decode($ativo['detalhes'] ?? '', true) ?: []);

        return match (AtivoService::fonteVlanSwitch($ativo, $detalhes)) {
            'unifi' => new UnifiDriver(),
            'omada' => new TplinkSshDriver(),
            default => null,
        };
    }
}
