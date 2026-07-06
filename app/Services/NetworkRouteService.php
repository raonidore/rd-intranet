<?php

namespace App\Services;

class NetworkRouteService
{
    private LinuxService $linux;

    public function __construct()
    {
        $this->linux = new LinuxService();
    }

    /**
     * Rotas atuais (ip route show) com uma flag indicando se são
     * gerenciadas pela RD Intranet (presentes em rotas-extras.conf) --
     * só essas podem ser excluídas pela tela.
     */
    public function listar(): array
    {
        $rotas = (new ServerInfoService())->snapshot()['rede']['rotas'];
        $gerenciadas = $this->destinosGerenciados();

        return array_map(function (array $r) use ($gerenciadas) {
            $r['gerenciada'] = in_array($r['destino'], $gerenciadas, true);
            return $r;
        }, $rotas);
    }

    public function interfacesValidas(): array
    {
        return (new NetworkConfigService())->interfacesValidas();
    }

    public function aplicar(string $destino, string $via, string $dev): array
    {
        if (!$this->validarCidr($destino)) {
            return ['success' => false, 'message' => 'Destino inválido. Use o formato 203.0.113.0/24.'];
        }

        if (!$this->validarIp($via)) {
            return ['success' => false, 'message' => 'Gateway (via) inválido.'];
        }

        if (!in_array($dev, $this->interfacesValidas(), true)) {
            return ['success' => false, 'message' => 'Interface inválida.'];
        }

        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/rota_aplicar_web.sh',
            [$destino, $via, $dev]
        );

        return $this->decodificar($resultado);
    }

    public function confirmar(): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/rota_confirmar_web.sh');

        return $this->decodificar($resultado);
    }

    public function excluir(string $destino): array
    {
        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/rota_excluir_web.sh',
            [$destino]
        );

        return $this->decodificar($resultado);
    }

    public function testar(string $via): array
    {
        return (new NetworkToolsService())->ping($via);
    }

    public function statusRollback(): array
    {
        $resultado = $this->linux->executar('systemctl is-active rd-rota-rollback.timer 2>/dev/null');

        if (trim($resultado['output']) !== 'active') {
            return ['pendente' => false, 'segundos_restantes' => 0];
        }

        // --on-active cria timer monotônico (sem timestamp absoluto simples
        // de ler via systemctl show); o prazo é gravado à parte por
        // rota_aplicar_web.sh.
        $deadline = @file_get_contents('/etc/rd-intranet/.rota-deadline');
        $restante = $deadline !== false ? max(0, (int)trim($deadline) - time()) : 0;

        return ['pendente' => $restante > 0, 'segundos_restantes' => $restante];
    }

    private function destinosGerenciados(): array
    {
        $resultado = $this->linux->executar('cat /etc/rd-intranet/rotas-extras.conf 2>/dev/null');

        $destinos = [];
        foreach (explode("\n", trim($resultado['output'])) as $linha) {
            $partes = preg_split('/\s+/', trim($linha));
            if (!empty($partes[0])) {
                $destinos[] = $partes[0];
            }
        }

        return $destinos;
    }

    private function decodificar(array $resultado): array
    {
        $dados = json_decode(trim($resultado['output']), true);

        return is_array($dados) ? $dados : ['success' => false, 'message' => 'Resposta inesperada: ' . $resultado['output']];
    }

    private function validarCidr(string $valor): bool
    {
        if (!preg_match('#^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})/(\d{1,2})$#', $valor, $m)) {
            return false;
        }
        for ($i = 1; $i <= 4; $i++) {
            if ((int)$m[$i] > 255) {
                return false;
            }
        }
        return (int)$m[5] <= 32;
    }

    private function validarIp(string $valor): bool
    {
        if (!preg_match('/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/', $valor, $m)) {
            return false;
        }
        for ($i = 1; $i <= 4; $i++) {
            if ((int)$m[$i] > 255) {
                return false;
            }
        }
        return true;
    }
}
