<?php

namespace App\Services;

class ModuloCatalogo
{
    private const MODULOS = [
        'samba_dashboard' => ['label' => 'Dashboard Samba', 'grupo' => 'Samba'],
        'samba_usuarios' => ['label' => 'Usuários Samba', 'grupo' => 'Samba'],
        'samba_grupos' => ['label' => 'Grupos Samba', 'grupo' => 'Samba'],
        'samba_compartilhamentos' => ['label' => 'Compartilhamentos', 'grupo' => 'Samba'],
        'samba_monitor' => ['label' => 'Monitor', 'grupo' => 'Samba'],
        'samba_arquivos' => ['label' => 'Arquivos', 'grupo' => 'Samba'],
        'samba_diagnostico' => ['label' => 'Diagnóstico', 'grupo' => 'Samba'],
        'samba_lixeira' => ['label' => 'Lixeira Administrativa', 'grupo' => 'Samba'],
        'deploy' => ['label' => 'Central de Configurações', 'grupo' => 'Samba'],
        'samba_config' => ['label' => 'Config. Global Samba', 'grupo' => 'Samba'],
        'infra_servidor' => ['label' => 'Servidor (dashboard)', 'grupo' => 'Infraestrutura'],
        'infra_hardware' => ['label' => 'Hardware', 'grupo' => 'Infraestrutura'],
        'infra_rede' => ['label' => 'Network', 'grupo' => 'Infraestrutura'],
        'infra_servicos' => ['label' => 'Serviços', 'grupo' => 'Infraestrutura'],
        'infra_cron' => ['label' => 'Cron', 'grupo' => 'Infraestrutura'],
        'infra_iptables' => ['label' => 'Firewall (iptables)', 'grupo' => 'Infraestrutura'],
        'infra_certificado' => ['label' => 'Certificado Digital', 'grupo' => 'Infraestrutura'],
        'infra_dependencias' => ['label' => 'Checklist de Dependências', 'grupo' => 'Infraestrutura'],
        'infra_speedtest' => ['label' => 'Teste de Velocidade', 'grupo' => 'Infraestrutura'],
        'infra_ddns' => ['label' => 'DNS Dinâmico', 'grupo' => 'Infraestrutura'],
        'vpn_dashboard' => ['label' => 'Dashboard', 'grupo' => 'VPN'],
        'vpn_wireguard_servidor' => ['label' => 'WireGuard - Servidor', 'grupo' => 'VPN'],
        'vpn_wireguard_peers' => ['label' => 'WireGuard - Peers', 'grupo' => 'VPN'],
        'vpn_wireguard_trafego' => ['label' => 'WireGuard - Tráfego', 'grupo' => 'VPN'],
        'vpn_openvpn_servidor' => ['label' => 'OpenVPN - Servidor', 'grupo' => 'VPN'],
        'vpn_openvpn_clientes' => ['label' => 'OpenVPN - Clientes', 'grupo' => 'VPN'],
        'vpn_openvpn_trafego' => ['label' => 'OpenVPN - Tráfego', 'grupo' => 'VPN'],
        'vpn_openvpn_saida' => ['label' => 'OpenVPN - Conexões de Saída', 'grupo' => 'VPN'],
        'apache_dashboard' => ['label' => 'Dashboard Apache', 'grupo' => 'Apache'],
        'apache_sites' => ['label' => 'Sites (VirtualHosts)', 'grupo' => 'Apache'],
        'apache_modulos' => ['label' => 'Módulos Apache', 'grupo' => 'Apache'],
        'apache_config' => ['label' => 'Config. Global Apache', 'grupo' => 'Apache'],
        'bd_mysql' => ['label' => 'MySQL/MariaDB', 'grupo' => 'Banco de Dados'],
        'auditoria' => ['label' => 'Auditoria', 'grupo' => 'Segurança'],
        'seguranca_antivirus' => ['label' => 'Antivírus', 'grupo' => 'Segurança'],
    ];

    public static function chaves(): array
    {
        return array_keys(self::MODULOS);
    }

    public static function label(string $modulo): string
    {
        return self::MODULOS[$modulo]['label'] ?? $modulo;
    }

    public static function agrupados(): array
    {
        $grupos = [];

        foreach (self::MODULOS as $chave => $info) {
            $grupos[$info['grupo']][$chave] = $info['label'];
        }

        return $grupos;
    }
}
