<?php

namespace App\Services;

class SystemServiceManager
{
    private const CHAVE_CONFIG = 'servicos_gerenciados';
    private const PADRAO = ['smbd', 'apache2', 'mariadb', 'ssh'];

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
        $unidades = $this->unidadesGerenciadas();
        $servicos = [];

        foreach ($unidades as $unidade) {
            $servicos[$unidade] = $this->nomeAmigavel($unidade);
        }

        return $servicos;
    }

    /**
     * Todas as unidades .service instaladas no sistema, para a tela de seleção.
     */
    public function catalogoDisponivel(): array
    {
        $resultado  = $this->linux->executar("systemctl list-unit-files --type=service --no-legend --plain 2>/dev/null");
        $gerenciadas = $this->unidadesGerenciadas();

        $catalogo = [];
        foreach (explode("\n", trim($resultado['output'])) as $linha) {
            $linha = trim($linha);
            if ($linha === '') continue;

            $partes = preg_split('/\s+/', $linha);
            $unidade = preg_replace('/\.service$/', '', $partes[0] ?? '');
            if ($unidade === '' || str_contains($unidade, '@')) continue;

            $catalogo[] = [
                'unidade'    => $unidade,
                'nome'       => $this->nomeAmigavel($unidade),
                'gerenciado' => in_array($unidade, $gerenciadas, true),
            ];
        }

        usort($catalogo, fn($a, $b) => strcmp($a['unidade'], $b['unidade']));

        return $catalogo;
    }

    /**
     * Persiste a seleção de serviços gerenciados, validando contra as unidades reais do sistema.
     */
    public function salvarSelecao(array $unidadesEscolhidas): bool
    {
        $validas = array_column($this->catalogoDisponivel(), 'unidade');

        $selecao = array_values(array_intersect($unidadesEscolhidas, $validas));

        $efetiva = !empty($selecao) ? $selecao : self::PADRAO;

        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/salvar_permitidos_web.sh',
            $efetiva
        );

        if (!$resultado['success']) {
            return false;
        }

        ConfigService::set(self::CHAVE_CONFIG, json_encode($selecao));

        return true;
    }

    private function unidadesGerenciadas(): array
    {
        $bruto = ConfigService::get(self::CHAVE_CONFIG);

        if ($bruto === null || $bruto === '') {
            return self::PADRAO;
        }

        $decodificado = json_decode($bruto, true);

        return is_array($decodificado) && !empty($decodificado) ? $decodificado : self::PADRAO;
    }

    private function nomeAmigavel(string $unidade): string
    {
        $resultado = $this->linux->executar(
            "systemctl show " . escapeshellarg($unidade) . ".service --property=Description --value 2>/dev/null"
        );

        $descricao = trim($resultado['output']);

        return $descricao !== '' ? $descricao : $unidade;
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
