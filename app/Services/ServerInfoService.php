<?php

namespace App\Services;

class ServerInfoService
{
    public function snapshot(): array
    {
        $cpu      = $this->cpu();
        $memoria  = $this->memoria();
        $disco    = $this->disco();
        $carga    = $this->cargaMedia($cpu['nucleos']);
        $temperatura = $this->temperatura();
        $servicos = $this->servicos();

        $saude = $this->calcularSaude([
            'cpu'         => $cpu,
            'memoria'     => $memoria,
            'disco'       => $disco,
            'carga'       => $carga,
            'temperatura' => $temperatura,
            'servicos'    => $servicos,
        ]);

        return [
            'gerado_em'   => date('d/m/Y H:i:s'),
            'host'        => $this->hostInfo(),
            'uptime'      => $this->uptime(),
            'cpu'         => $cpu,
            'memoria'     => $memoria,
            'disco'       => $disco,
            'carga'       => $carga,
            'temperatura' => $temperatura,
            'rede'        => $this->rede(),
            'usuarios'    => $this->usuariosLogados(),
            'servicos'    => $servicos,
            'saude'       => $saude,
        ];
    }

    private function hostInfo(): array
    {
        $hostname = trim(shell_exec('hostname 2>/dev/null') ?? '') ?: php_uname('n');

        $os = php_uname('s');
        if (is_readable('/etc/os-release')) {
            $dados = parse_ini_file('/etc/os-release') ?: [];
            $os = $dados['PRETTY_NAME'] ?? $os;
        }

        return [
            'hostname' => $hostname,
            'os'       => $os,
            'kernel'   => php_uname('r'),
            'arch'     => php_uname('m'),
        ];
    }

    private function uptime(): array
    {
        $raw     = @file_get_contents('/proc/uptime');
        $segundos = $raw ? (int)floatval(explode(' ', trim($raw))[0]) : 0;

        $dias    = intdiv($segundos, 86400);
        $horas   = intdiv($segundos % 86400, 3600);
        $minutos = intdiv($segundos % 3600, 60);

        $partes = [];
        if ($dias > 0) $partes[] = "{$dias}d";
        if ($horas > 0) $partes[] = "{$horas}h";
        $partes[] = "{$minutos}m";

        return [
            'segundos' => $segundos,
            'texto'    => implode(' ', $partes),
        ];
    }

    private function numCores(): int
    {
        $cpuinfo = @file_get_contents('/proc/cpuinfo');
        return $cpuinfo ? max(1, substr_count($cpuinfo, 'processor')) : 1;
    }

    private function cpu(): array
    {
        $ler = function (): array {
            $linha = @file('/proc/stat')[0] ?? '';
            $partes = preg_split('/\s+/', trim($linha));
            array_shift($partes); // remove o rotulo "cpu"
            return array_map('intval', $partes);
        };

        $modelo = trim(shell_exec("grep -m1 'model name' /proc/cpuinfo 2>/dev/null | cut -d: -f2") ?? '');

        $s1 = $ler();
        usleep(200000);
        $s2 = $ler();

        $idle1  = ($s1[3] ?? 0) + ($s1[4] ?? 0);
        $idle2  = ($s2[3] ?? 0) + ($s2[4] ?? 0);
        $total1 = array_sum($s1);
        $total2 = array_sum($s2);

        $deltaTotal = $total2 - $total1;
        $deltaIdle  = $idle2 - $idle1;

        $percentual = $deltaTotal > 0 ? round((1 - $deltaIdle / $deltaTotal) * 100, 1) : 0.0;

        return [
            'percentual' => max(0.0, min(100.0, $percentual)),
            'nucleos'    => $this->numCores(),
            'modelo'     => $modelo,
        ];
    }

    private function memoria(): array
    {
        $linhas = @file('/proc/meminfo') ?: [];
        $dados  = [];

        foreach ($linhas as $linha) {
            if (preg_match('/^(\w+):\s+(\d+)/', $linha, $m)) {
                $dados[$m[1]] = (int)$m[2]; // valores em KB
            }
        }

        $total       = $dados['MemTotal'] ?? 0;
        $disponivel  = $dados['MemAvailable'] ?? ($dados['MemFree'] ?? 0);
        $usado       = max(0, $total - $disponivel);
        $percentual  = $total > 0 ? round(($usado / $total) * 100, 1) : 0.0;

        return [
            'percentual'      => $percentual,
            'total_fmt'       => $this->formatBytes($total * 1024),
            'usado_fmt'       => $this->formatBytes($usado * 1024),
            'disponivel_fmt'  => $this->formatBytes($disponivel * 1024),
        ];
    }

    private function disco(): array
    {
        $saida  = shell_exec('df -hP -x tmpfs -x devtmpfs -x squashfs -x overlay 2>/dev/null') ?? '';
        $linhas = array_filter(array_map('trim', explode("\n", trim($saida))));
        array_shift($linhas); // cabecalho

        $discos = [];
        foreach ($linhas as $linha) {
            $p = preg_split('/\s+/', $linha);
            if (count($p) < 6) continue;

            $discos[] = [
                'dispositivo' => $p[0],
                'total'       => $p[1],
                'usado'       => $p[2],
                'disponivel'  => $p[3],
                'percentual'  => (int)rtrim($p[4], '%'),
                'ponto'       => $p[5],
            ];
        }

        $principal = null;
        foreach ($discos as $d) {
            if ($d['ponto'] === '/') { $principal = $d; break; }
        }
        $principal = $principal ?? ($discos[0] ?? ['percentual' => 0, 'total' => '-', 'usado' => '-', 'disponivel' => '-', 'ponto' => '-']);

        return ['discos' => $discos, 'principal' => $principal];
    }

    private function cargaMedia(int $nucleos): array
    {
        $raw    = @file_get_contents('/proc/loadavg');
        $partes = $raw ? explode(' ', trim($raw)) : ['0', '0', '0'];

        $carga1 = (float)($partes[0] ?? 0);

        return [
            '1min'       => $carga1,
            '5min'       => (float)($partes[1] ?? 0),
            '15min'      => (float)($partes[2] ?? 0),
            'nucleos'    => $nucleos,
            'percentual' => round(($carga1 / $nucleos) * 100, 1),
        ];
    }

    private function temperatura(): ?array
    {
        $leituras = [];

        foreach (glob('/sys/class/thermal/thermal_zone*/temp') ?: [] as $arquivo) {
            $valor = @file_get_contents($arquivo);
            if ($valor === false || !is_numeric(trim($valor))) continue;

            $arquivoTipo = dirname($arquivo) . '/type';
            $tipo = is_readable($arquivoTipo) ? trim(file_get_contents($arquivoTipo)) : 'zona';

            $leituras[] = ['zona' => $tipo, 'celsius' => round(((int)trim($valor)) / 1000, 1)];
        }

        if (empty($leituras) && trim(shell_exec('command -v sensors 2>/dev/null') ?? '') !== '') {
            $saida = shell_exec('sensors -A 2>/dev/null') ?? '';
            if (preg_match_all('/^(.+?):\s+\+?(-?[\d.]+)°?C/m', $saida, $m, PREG_SET_ORDER)) {
                foreach ($m as $item) {
                    $leituras[] = ['zona' => trim($item[1]), 'celsius' => (float)$item[2]];
                }
            }
        }

        return empty($leituras) ? null : $leituras;
    }

    private function rede(): array
    {
        $interfaces = [];

        foreach (explode("\n", trim(shell_exec('ip -o addr show 2>/dev/null') ?? '')) as $linha) {
            if (!preg_match('/^\d+:\s+(\S+?)(?:@\S+)?\s+(inet6?)\s+([0-9a-fA-F.:]+\/\d+)/', $linha, $m)) continue;

            [$_, $nome, $tipo, $endereco] = $m;
            $interfaces[$nome] ??= ['nome' => $nome, 'ipv4' => [], 'ipv6' => [], 'estado' => '-', 'mac' => '-'];

            if ($tipo === 'inet') {
                $interfaces[$nome]['ipv4'][] = $endereco;
            } else {
                $interfaces[$nome]['ipv6'][] = $endereco;
            }
        }

        foreach (explode("\n", trim(shell_exec('ip -o link show 2>/dev/null') ?? '')) as $linha) {
            if (!preg_match('/^\d+:\s+(\S+?):\s+<([^>]*)>.*link\/\S+\s+([0-9a-f:]{17})/', $linha, $m)) continue;

            [$_, $nome, $flags, $mac] = $m;
            $interfaces[$nome] ??= ['nome' => $nome, 'ipv4' => [], 'ipv6' => [], 'estado' => '-', 'mac' => '-'];
            $interfaces[$nome]['estado'] = str_contains($flags, 'UP') ? 'up' : 'down';
            $interfaces[$nome]['mac']    = $mac;
        }

        foreach ($interfaces as $nome => &$dados) {
            $rx = (int)trim(@file_get_contents("/sys/class/net/{$nome}/statistics/rx_bytes") ?: '0');
            $tx = (int)trim(@file_get_contents("/sys/class/net/{$nome}/statistics/tx_bytes") ?: '0');
            $rxPacotes = (int)trim(@file_get_contents("/sys/class/net/{$nome}/statistics/rx_packets") ?: '0');
            $txPacotes = (int)trim(@file_get_contents("/sys/class/net/{$nome}/statistics/tx_packets") ?: '0');

            $dados['rx_bytes']   = $rx;
            $dados['tx_bytes']   = $tx;
            $dados['rx_fmt']     = $this->formatBytes($rx);
            $dados['tx_fmt']     = $this->formatBytes($tx);
            $dados['rx_packets'] = $rxPacotes;
            $dados['tx_packets'] = $txPacotes;
        }
        unset($dados);

        $rotas = [];
        foreach (explode("\n", trim(shell_exec('ip route show 2>/dev/null') ?? '')) as $linha) {
            $linha = trim($linha);
            if ($linha === '') continue;

            preg_match('/^(\S+)/', $linha, $mDestino);
            preg_match('/via (\S+)/', $linha, $mVia);
            preg_match('/dev (\S+)/', $linha, $mDev);
            preg_match('/src (\S+)/', $linha, $mSrc);

            $rotas[] = [
                'destino' => $mDestino[1] ?? '-',
                'via'     => $mVia[1] ?? '-',
                'dev'     => $mDev[1] ?? '-',
                'src'     => $mSrc[1] ?? '-',
            ];
        }

        return ['interfaces' => array_values($interfaces), 'rotas' => $rotas];
    }

    private function usuariosLogados(): array
    {
        $usuarios = [];

        foreach (explode("\n", trim(shell_exec('LANG=C.UTF-8 who 2>/dev/null') ?? '')) as $linha) {
            if (trim($linha) === '') continue;
            if (!preg_match('/^(\S+)\s+(\S+)\s+([\d-]+\s+[\d:]+)\s*(?:\(([^)]+)\))?/', $linha, $m)) continue;

            $usuarios[] = [
                'usuario'  => $m[1],
                'terminal' => $m[2],
                'desde'    => trim($m[3]),
                'origem'   => $m[4] ?? '-',
            ];
        }

        return $usuarios;
    }

    private function servicos(): array
    {
        $rodando  = array_filter(array_map('trim', explode("\n", trim(shell_exec("systemctl list-units --type=service --state=running --no-legend --plain 2>/dev/null") ?? ''))));
        $falhados = array_filter(array_map('trim', explode("\n", trim(shell_exec("systemctl list-units --type=service --state=failed --no-legend --plain 2>/dev/null") ?? ''))));

        $lista = [];
        foreach ($rodando as $linha) {
            if (preg_match('/^(\S+)\s+\S+\s+\S+\s+\S+\s+(.*)$/', $linha, $m)) {
                $lista[] = ['unidade' => $m[1], 'descricao' => trim($m[2])];
            }
        }

        $falhas = [];
        foreach ($falhados as $linha) {
            $partes = preg_split('/\s+/', $linha);
            $falhas[] = $partes[0] ?? $linha;
        }

        return [
            'rodando'  => count($lista),
            'falharam' => count($falhas),
            'lista'    => $lista,
            'falhas'   => $falhas,
        ];
    }

    private function calcularSaude(array $m): array
    {
        $pontos  = 100;
        $motivos = [];

        $cpu = $m['cpu']['percentual'];
        if ($cpu >= 90) { $pontos -= 20; $motivos[] = "CPU em uso crítico ({$cpu}%)"; }
        elseif ($cpu >= 75) { $pontos -= 8; $motivos[] = "CPU com uso elevado ({$cpu}%)"; }

        $mem = $m['memoria']['percentual'];
        if ($mem >= 90) { $pontos -= 20; $motivos[] = "Memória em uso crítico ({$mem}%)"; }
        elseif ($mem >= 75) { $pontos -= 8; $motivos[] = "Memória com uso elevado ({$mem}%)"; }

        $disco = $m['disco']['principal']['percentual'] ?? 0;
        if ($disco >= 90) { $pontos -= 20; $motivos[] = "Disco principal quase cheio ({$disco}%)"; }
        elseif ($disco >= 75) { $pontos -= 10; $motivos[] = "Disco principal com uso elevado ({$disco}%)"; }

        $cargaPct = $m['carga']['percentual'];
        if ($cargaPct >= 150) { $pontos -= 15; $motivos[] = 'Carga do sistema muito acima da capacidade de CPU'; }
        elseif ($cargaPct >= 100) { $pontos -= 7; $motivos[] = 'Carga do sistema acima da capacidade de CPU'; }

        if (!empty($m['servicos']['falharam'])) {
            $qtd = $m['servicos']['falharam'];
            $pontos -= min(20, $qtd * 10);
            $motivos[] = "{$qtd} serviço(s) do sistema em estado de falha";
        }

        if (!empty($m['temperatura'])) {
            $maxTemp = max(array_column($m['temperatura'], 'celsius'));
            if ($maxTemp >= 85) { $pontos -= 15; $motivos[] = "Temperatura crítica ({$maxTemp}°C)"; }
            elseif ($maxTemp >= 70) { $pontos -= 7; $motivos[] = "Temperatura elevada ({$maxTemp}°C)"; }
        }

        $pontos = max(0, min(100, $pontos));

        if (empty($motivos)) {
            $motivos[] = 'Todos os indicadores dentro da normalidade.';
        }

        return ['percentual' => $pontos, 'motivos' => $motivos];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) return '-';
        $unidades = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = min((int)floor(log($bytes, 1024)), count($unidades) - 1);
        return round($bytes / (1024 ** $i), 1) . ' ' . $unidades[$i];
    }
}
