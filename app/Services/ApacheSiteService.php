<?php

namespace App\Services;

class ApacheSiteService
{
    private LinuxService $linux;

    public function __construct()
    {
        $this->linux = new LinuxService();
    }

    public function listar(): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/apache_sites_listar_web.sh');

        $sites = [];

        foreach (explode("\n", trim($resultado['output'])) as $linha) {
            $linha = trim($linha);

            if ($linha === '') {
                continue;
            }

            [$nome, $estado, $docroot, $serverName] = array_pad(explode('|', $linha, 4), 4, '-');

            $sites[] = [
                'nome' => $nome,
                'habilitado' => $estado === 'habilitado',
                'docroot' => $docroot,
                'server_name' => $serverName,
                'atual' => $serverName === ($_SERVER['SERVER_NAME'] ?? null),
            ];
        }

        return $sites;
    }

    public function ver(string $nome): ?string
    {
        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/apache_site_ver_web.sh',
            [$nome]
        );

        return $resultado['success'] ? $resultado['output'] : null;
    }

    public function habilitar(string $nome): bool
    {
        return $this->toggle($nome, 'enable');
    }

    public function desabilitar(string $nome): bool
    {
        foreach ($this->listar() as $site) {
            if ($site['nome'] === $nome && $site['atual']) {
                NotificationService::error(
                    "Este site ('{$nome}') é o que está servindo a RD Intranet agora mesmo. " .
                    'Desabilitá-lo por aqui derrubaria o próprio painel — não é permitido.'
                );
                return false;
            }
        }

        return $this->toggle($nome, 'disable');
    }

    private function toggle(string $nome, string $acao): bool
    {
        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/apache_site_toggle_web.sh',
            [$nome, $acao]
        );

        if (!$resultado['success']) {
            NotificationService::error("Erro ao alterar o site '{$nome}'.", $resultado['output']);
            return false;
        }

        AuditService::registrar('Apache', 'Site', "Site {$nome} " . ($acao === 'enable' ? 'habilitado' : 'desabilitado') . '.');
        NotificationService::success("Site '{$nome}' " . ($acao === 'enable' ? 'habilitado' : 'desabilitado') . ' com sucesso.', $resultado['output']);

        return true;
    }
}
