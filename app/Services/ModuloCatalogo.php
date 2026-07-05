<?php

namespace App\Services;

class ModuloCatalogo
{
    private const MODULOS = [
        'samba_dashboard' => ['label' => 'Dashboard Samba', 'grupo' => 'Samba'],
        'samba_usuarios' => ['label' => 'Usuários Samba', 'grupo' => 'Samba'],
        'samba_compartilhamentos' => ['label' => 'Compartilhamentos', 'grupo' => 'Samba'],
        'samba_monitor' => ['label' => 'Monitor', 'grupo' => 'Samba'],
        'samba_arquivos' => ['label' => 'Arquivos', 'grupo' => 'Samba'],
        'samba_diagnostico' => ['label' => 'Diagnóstico', 'grupo' => 'Samba'],
        'samba_lixeira' => ['label' => 'Lixeira Administrativa', 'grupo' => 'Samba'],
        'deploy' => ['label' => 'Central de Configurações', 'grupo' => 'Samba'],
        'samba_config' => ['label' => 'Config. Global Samba', 'grupo' => 'Samba'],
        'infra_servidor' => ['label' => 'Servidor', 'grupo' => 'Infraestrutura'],
        'infra_servicos' => ['label' => 'Serviços', 'grupo' => 'Infraestrutura'],
        'auditoria' => ['label' => 'Auditoria', 'grupo' => 'Segurança'],
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
