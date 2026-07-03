<?php

namespace App\Services;

class SambaServicoService
{
    private LinuxService $linux;

    public function __construct()
    {
        $this->linux = new LinuxService();
    }

    public function status(): array
    {
        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/status_samba_web.sh'
        );

        $dados = [
            'smbd' => 'unknown',
            'nmbd' => 'unknown',
            'enabled_smbd' => 'unknown',
            'enabled_nmbd' => 'unknown',
            'version' => '-',
            'uptime' => '-',
            'raw' => $resultado['output']
        ];

        foreach (explode("\n", $resultado['output']) as $linha) {
            if (!str_contains($linha, '=')) {
                continue;
            }

            [$chave, $valor] = explode('=', $linha, 2);

            match ($chave) {
                'STATUS_SMBD' => $dados['smbd'] = $valor,
                'STATUS_NMBD' => $dados['nmbd'] = $valor,
                'ENABLED_SMBD' => $dados['enabled_smbd'] = $valor,
                'ENABLED_NMBD' => $dados['enabled_nmbd'] = $valor,
                'VERSION' => $dados['version'] = $valor,
                'UPTIME' => $dados['uptime'] = $valor,
                default => null,
            };
        }

        return $dados;
    }

    public function reiniciar(): void
    {
        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/restart_samba_web.sh'
        );

        if ($resultado['success']) {
            AuditService::registrar('Samba', 'Reiniciar serviço', 'Serviço Samba reiniciado.');
            NotificationService::success('Serviço Samba reiniciado com sucesso.', $resultado['output']);
        } else {
            NotificationService::error('Erro ao reiniciar o serviço Samba.', $resultado['output']);
        }
    }

    public function recarregar(): void
    {
        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/reload_samba_web.sh'
        );

        if ($resultado['success']) {
            AuditService::registrar('Samba', 'Recarregar configuração', 'Configuração do Samba recarregada.');
            NotificationService::success('Configuração do Samba recarregada com sucesso.', $resultado['output']);
        } else {
            NotificationService::error('Erro ao recarregar configuração do Samba.', $resultado['output']);
        }
    }

    public function validarConfiguracao(): void
    {
        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/testparm_web.sh'
        );

        if ($resultado['success']) {
            AuditService::registrar('Samba', 'Validar configuração', 'Configuração Samba validada com testparm.');
            NotificationService::success('Configuração Samba validada com sucesso.', $resultado['output']);
        } else {
            NotificationService::error('Erro na validação da configuração Samba.', $resultado['output']);
        }
    }
}
