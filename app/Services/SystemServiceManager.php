<?php

namespace App\Services;

class SystemServiceManager
{
    private LinuxService $linux;

    public function __construct()
    {
        $this->linux = new LinuxService();
    }

    public function status(string $service): array
    {
        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/services_web.sh',
            [$service, 'status']
        );

        $dados = [
            'service' => $service,
            'unit' => '-',
            'status' => 'unknown',
            'enabled' => 'unknown',
            'uptime' => '-',
            'raw' => $resultado['output']
        ];

        foreach (explode("\n", $resultado['output']) as $linha) {
            if (!str_contains($linha, '=')) {
                continue;
            }

            [$chave, $valor] = explode('=', $linha, 2);

            match ($chave) {
                'SERVICE' => $dados['service'] = $valor,
                'UNIT' => $dados['unit'] = $valor,
                'STATUS' => $dados['status'] = $valor,
                'ENABLED' => $dados['enabled'] = $valor,
                'UPTIME' => $dados['uptime'] = $valor,
                default => null,
            };
        }

        return $dados;
    }

    public function listarServicos(): array
    {
        return [
            'samba' => 'Samba',
            'apache' => 'Apache',
            'mariadb' => 'MariaDB',
            'ssh' => 'SSH'
        ];
    }

    public function reiniciar(string $service): void
    {
        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/services_web.sh',
            [$service, 'restart']
        );

        if ($resultado['success']) {
            AuditService::registrar('Serviços', 'Reiniciar', "Serviço {$service} reiniciado.");
            NotificationService::success("Serviço {$service} reiniciado com sucesso.", $resultado['output']);
        } else {
            NotificationService::error("Erro ao reiniciar {$service}.", $resultado['output']);
        }
    }

    public function recarregar(string $service): void
    {
        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/services_web.sh',
            [$service, 'reload']
        );

        if ($resultado['success']) {
            AuditService::registrar('Serviços', 'Recarregar', "Serviço {$service} recarregado.");
            NotificationService::success("Serviço {$service} recarregado com sucesso.", $resultado['output']);
        } else {
            NotificationService::error("Erro ao recarregar {$service}.", $resultado['output']);
        }
    }

    public function logs(string $service): array
    {
        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/services_web.sh',
            [$service, 'logs']
        );

        return [
            'success' => $resultado['success'],
            'output' => $resultado['output']
        ];
    }
}
