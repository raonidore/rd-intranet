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
        'vpn_wireguard_saida' => ['label' => 'WireGuard - Conexões de Saída', 'grupo' => 'VPN'],
        'vpn_openvpn_servidor' => ['label' => 'OpenVPN - Servidor', 'grupo' => 'VPN'],
        'vpn_openvpn_clientes' => ['label' => 'OpenVPN - Clientes', 'grupo' => 'VPN'],
        'vpn_openvpn_trafego' => ['label' => 'OpenVPN - Tráfego', 'grupo' => 'VPN'],
        'vpn_openvpn_saida' => ['label' => 'OpenVPN - Conexões de Saída', 'grupo' => 'VPN'],
        'vpn_ikev2_servidor' => ['label' => 'IKEv2 - Servidor', 'grupo' => 'VPN'],
        'vpn_ikev2_clientes' => ['label' => 'IKEv2 - Clientes', 'grupo' => 'VPN'],
        'vpn_ikev2_trafego' => ['label' => 'IKEv2 - Tráfego', 'grupo' => 'VPN'],
        'vpn_ikev2_saida' => ['label' => 'IKEv2 - Conexões de Saída', 'grupo' => 'VPN'],
        'apache_dashboard' => ['label' => 'Dashboard Apache', 'grupo' => 'Apache'],
        'apache_sites' => ['label' => 'Sites (VirtualHosts)', 'grupo' => 'Apache'],
        'apache_modulos' => ['label' => 'Módulos Apache', 'grupo' => 'Apache'],
        'apache_config' => ['label' => 'Config. Global Apache', 'grupo' => 'Apache'],
        'bd_mysql' => ['label' => 'MySQL/MariaDB', 'grupo' => 'Banco de Dados'],
        'auditoria' => ['label' => 'Auditoria', 'grupo' => 'Segurança'],
        'seguranca_antivirus' => ['label' => 'Antivírus', 'grupo' => 'Segurança'],
        'ativos_dashboard' => ['label' => 'Ativos - Dashboard', 'grupo' => 'Ativos'],
        'ativos_lista' => ['label' => 'Ativos - Lista', 'grupo' => 'Ativos'],
        'ativos_novo' => ['label' => 'Ativos - Novo/Editar', 'grupo' => 'Ativos'],
        'ativos_cadastros' => ['label' => 'Ativos - Cadastros (Setor/Localização)', 'grupo' => 'Ativos'],
        'ativos_acesso_remoto' => ['label' => 'Ativos - Acesso Remoto', 'grupo' => 'Ativos'],
        'ativos_etiqueta_config' => ['label' => 'Ativos - Configurações de Etiqueta', 'grupo' => 'Ativos'],
        'entra_dashboard' => ['label' => 'Entra - Dashboard', 'grupo' => 'Microsoft Entra'],
        'entra_usuarios' => ['label' => 'Entra - Usuários', 'grupo' => 'Microsoft Entra'],
        'entra_configuracao' => ['label' => 'Entra - Configuração', 'grupo' => 'Microsoft Entra'],
        'entra_dispositivos' => ['label' => 'Entra - Dispositivos (Intune)', 'grupo' => 'Microsoft Entra'],
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

    /*
     |---------------------------------------------------------
     | Grupos habilitáveis por instalação -- diferente do picker de
     | módulos por usuário (usuario_modulos), isto liga/desliga o grupo
     | INTEIRO pra todo mundo, admin incluso (menos poluição de menu em
     | clientes que não usam um módulo). 'Sistema' nunca entra aqui --
     | senão dava pra se trancar fora da própria tela que reabilita
     | grupos.
     |---------------------------------------------------------
     */

    public const GRUPOS_TOGGLEAVEIS = [
        'Apache', 'Banco de Dados', 'Ativos', 'Infraestrutura', 'VPN', 'Samba', 'Segurança', 'Microsoft Entra',
    ];

    /** Grupos que nascem desligados em instalações novas -- opt-in, não fazem parte do uso típico. */
    private const GRUPOS_DESABILITADOS_POR_PADRAO = ['Microsoft Entra'];

    private const CHAVE_CONFIG_GRUPOS = 'sistema_grupos_habilitados';

    public static function grupoDoModulo(string $modulo): ?string
    {
        return self::MODULOS[$modulo]['grupo'] ?? null;
    }

    /**
     * Ausente na config = todos os grupos "de sempre" habilitados
     * (instalação existente não perde nada num update), exceto os
     * listados em GRUPOS_DESABILITADOS_POR_PADRAO, que só ficam
     * visíveis depois de habilitados explicitamente em Sistema > Módulos.
     */
    public static function gruposHabilitados(): array
    {
        $bruto = ConfigService::get(self::CHAVE_CONFIG_GRUPOS);

        if ($bruto === null || $bruto === '') {
            return array_values(array_diff(self::GRUPOS_TOGGLEAVEIS, self::GRUPOS_DESABILITADOS_POR_PADRAO));
        }

        $decodificado = json_decode($bruto, true);

        return is_array($decodificado) ? $decodificado : [];
    }

    public static function grupoHabilitado(string $grupo): bool
    {
        if (!in_array($grupo, self::GRUPOS_TOGGLEAVEIS, true)) {
            return true; // grupo nao togglavel (ex: "Sistema") -- sempre visivel
        }

        return in_array($grupo, self::gruposHabilitados(), true);
    }

    public static function salvarGruposHabilitados(array $grupos): void
    {
        $validos = array_values(array_intersect($grupos, self::GRUPOS_TOGGLEAVEIS));

        ConfigService::set(self::CHAVE_CONFIG_GRUPOS, json_encode($validos));
    }
}
