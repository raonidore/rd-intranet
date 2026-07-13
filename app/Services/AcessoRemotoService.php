<?php

namespace App\Services;

/**
 * Acesso remoto via MeshCentral (self-hosted, Apache 2.0,
 * https://github.com/Ylianst/MeshCentral) -- NÃO é construído do zero.
 * Roda como serviço systemd próprio (scripts/system/meshcentral_instalar_web.sh),
 * numa porta própria, com o MeshAgent instalado nas máquinas Windows
 * separadamente do nosso agente de inventário.
 */
class AcessoRemotoService
{
    private LinuxService $linux;

    public function __construct()
    {
        $this->linux = new LinuxService();
    }

    public function porta(): int
    {
        return (int)(ConfigService::get('meshcentral_porta', '4430') ?? 4430);
    }

    public function instalado(): bool
    {
        $resultado = $this->linux->executar('systemctl list-unit-files meshcentral.service --no-legend 2>/dev/null');

        return $resultado['success'] && str_contains($resultado['output'], 'meshcentral.service');
    }

    public function rodando(): bool
    {
        $resultado = $this->linux->executar('systemctl is-active meshcentral 2>/dev/null');

        return trim($resultado['output']) === 'active';
    }

    public function instalar(): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/meshcentral_instalar_web.sh');

        $dados = json_decode($resultado['output'], true);

        if (!is_array($dados)) {
            return ['success' => false, 'message' => 'Resposta inesperada do instalador: ' . $resultado['output']];
        }

        if (!empty($dados['success'])) {
            AuditService::registrar('Ativos', 'Acesso Remoto', 'MeshCentral instalado.');
        }

        return $dados;
    }

    public function urlConsole(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $host = explode(':', $host)[0];

        return "https://{$host}:{$this->porta()}/";
    }
}
