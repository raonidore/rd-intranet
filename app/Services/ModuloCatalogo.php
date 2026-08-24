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
        'infra_speedtest' => ['label' => 'Teste de Velocidade', 'grupo' => 'Infraestrutura'],
        'infra_ddns' => ['label' => 'DNS Dinâmico', 'grupo' => 'Infraestrutura'],
        'infra_tuneis' => ['label' => 'Túneis', 'grupo' => 'Infraestrutura'],
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
        'ssh_conexoes' => ['label' => 'Conexões SSH', 'grupo' => 'SSH'],
        'auditoria' => ['label' => 'Auditoria', 'grupo' => 'Segurança'],
        'seguranca_antivirus' => ['label' => 'Antivírus', 'grupo' => 'Segurança'],
        'ativos_dashboard' => ['label' => 'Ativos - Dashboard', 'grupo' => 'Ativos'],
        'ativos_lista' => ['label' => 'Ativos - Lista', 'grupo' => 'Ativos'],
        'ativos_novo' => ['label' => 'Ativos - Novo/Editar', 'grupo' => 'Ativos'],
        'ativos_cadastros' => ['label' => 'Ativos - Cadastros (Setor/Localização)', 'grupo' => 'Ativos'],
        'ativos_acesso_remoto' => ['label' => 'Ativos - Acesso Remoto', 'grupo' => 'Ativos'],
        'ativos_rdp' => ['label' => 'Ativos - RDP', 'grupo' => 'Ativos'],
        'ativos_etiqueta_config' => ['label' => 'Ativos - Configurações de Etiqueta', 'grupo' => 'Ativos'],
        'ativos_politicas' => ['label' => 'Ativos - Regras de Segurança', 'grupo' => 'Ativos'],
        'entra_dashboard' => ['label' => 'Entra - Dashboard', 'grupo' => 'Microsoft Entra'],
        'entra_usuarios' => ['label' => 'Entra - Usuários', 'grupo' => 'Microsoft Entra'],
        'entra_configuracao' => ['label' => 'Entra - Configuração', 'grupo' => 'Microsoft Entra'],
        'entra_dispositivos' => ['label' => 'Entra - Dispositivos (Intune)', 'grupo' => 'Microsoft Entra'],
        'entra_perfis_configuracao' => ['label' => 'Entra - Perfis de Configuração (Intune)', 'grupo' => 'Microsoft Entra'],
        'backup_configuracao' => ['label' => 'Configuração', 'grupo' => 'Backup'],
        'backup_historico' => ['label' => 'Histórico', 'grupo' => 'Backup'],
        'base_conhecimento_visualizar' => ['label' => 'Base de Conhecimento - Visualizar', 'grupo' => 'Base de Conhecimento'],
        'base_conhecimento_criar' => ['label' => 'Base de Conhecimento - Criar/Gerenciar', 'grupo' => 'Base de Conhecimento'],
        'whatsapp_atendimentos' => ['label' => 'WhatsApp - Atendimentos', 'grupo' => 'WhatsApp'],
        'whatsapp_fila' => ['label' => 'WhatsApp - Fila', 'grupo' => 'WhatsApp'],
        'whatsapp_chatbot' => ['label' => 'WhatsApp - Chatbot', 'grupo' => 'WhatsApp'],
        'whatsapp_setores' => ['label' => 'WhatsApp - Setores', 'grupo' => 'WhatsApp'],
        'whatsapp_estatisticas' => ['label' => 'WhatsApp - Estatísticas', 'grupo' => 'WhatsApp'],
        'whatsapp_configuracoes' => ['label' => 'WhatsApp - Configurações', 'grupo' => 'WhatsApp'],
        'chamados_atendimentos' => ['label' => 'Chamados - Atendimentos', 'grupo' => 'Chamados'],
        'chamados_fila' => ['label' => 'Chamados - Fila', 'grupo' => 'Chamados'],
        'chamados_categorias' => ['label' => 'Chamados - Categorias', 'grupo' => 'Chamados'],
        'chamados_setores' => ['label' => 'Chamados - Setores', 'grupo' => 'Chamados'],
        'chamados_estatisticas' => ['label' => 'Chamados - Estatísticas', 'grupo' => 'Chamados'],
        'chamados_configuracoes' => ['label' => 'Chamados - Configurações', 'grupo' => 'Chamados'],
        'chat_conversas' => ['label' => 'Chat Interno', 'grupo' => 'Chat'],
        'fornecedores_gerenciar' => ['label' => 'Fornecedores e Contratos', 'grupo' => 'Fornecedores'],
        'documentos_acessar' => ['label' => 'Documentos - Acessar', 'grupo' => 'Documentos'],
        'documentos_categorias' => ['label' => 'Documentos - Categorias e Permissões', 'grupo' => 'Documentos'],
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

        ksort($grupos, SORT_FLAG_CASE | SORT_STRING);

        return $grupos;
    }

    /** Mesmo ícone usado pelo grupo no menu lateral (app/Views/layouts/main.php) -- só pra dar identidade visual em telas que listam os grupos (Novo Usuário, Módulos). */
    private const ICONES_GRUPO = [
        'Apache' => 'bi-server',
        'Ativos' => 'bi-boxes',
        'Backup' => 'bi-cloud-arrow-up',
        'Banco de Dados' => 'bi-database',
        'Base de Conhecimento' => 'bi-journal-text',
        'Chamados' => 'bi-ticket-perforated',
        'Chat' => 'bi-chat-dots-fill',
        'Documentos' => 'bi-folder2-open',
        'Fornecedores' => 'bi-truck',
        'Infraestrutura' => 'bi-diagram-3',
        'Microsoft Entra' => 'bi-microsoft',
        'Samba' => 'bi-hdd-network-fill',
        'Segurança' => 'bi-shield-lock',
        'SSH' => 'bi-hdd-network',
        'VPN' => 'bi-shield-shaded',
        'WhatsApp' => 'bi-whatsapp',
    ];

    public static function iconeDoGrupo(string $grupo): string
    {
        return self::ICONES_GRUPO[$grupo] ?? 'bi-grid-3x3-gap';
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
        'Apache', 'Banco de Dados', 'Ativos', 'Infraestrutura', 'VPN', 'Samba', 'Segurança', 'Microsoft Entra', 'Backup', 'SSH', 'Base de Conhecimento', 'WhatsApp', 'Chamados', 'Chat', 'Fornecedores', 'Documentos',
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
