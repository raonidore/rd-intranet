<?php

namespace App\Services;

use phpseclib3\Net\SSH2;

/**
 * Modo alternativo da integração TP-Link/Omada: lê os dados direto do
 * switch pela CLI via SSH, sem Omada Controller nenhum -- pensado pra
 * sites com switch em modo standalone onde instalar o Controller (Java +
 * MongoDB) e adotar o switch (o Controller sobrescreve a config dele) não
 * compensa o risco.
 *
 * SÓ LEITURA, por construção: os únicos comandos que esta classe sabe
 * mandar estão em COMANDOS_PERMITIDOS (todos `show ...`, mais o `enable`
 * que a CLI exige antes deles) -- não existe caminho pra mandar texto
 * arbitrário. A recomendação (passo a passo da tela) é ainda usar um
 * usuário de privilégio `user` no switch, que nem tem `configure`.
 *
 * Peculiaridades confirmadas ao vivo num SG3428 (fw 2.30.0):
 * 1. O Enter da CLI é `\r` -- com `\n` o switch ecoa o comando e nunca
 *    responde (foi isso que fez parecer que o SSH "não funcionava").
 * 2. Usuário `user` só tem `show` depois do `enable` (no prompt `>` todo
 *    `show` dá "Bad command"); o `enable` dele não pede senha.
 * 3. Saída longa pagina com "Press any key to continue (Q to quit)" --
 *    respondido com espaço até voltar o prompt.
 * 4. Servidor SSH antigo (banner "SSH-1.99-IPSSH-6.6.0"), mas negocia
 *    normal com o phpseclib.
 */
class OmadaSshService
{
    private const CHAVE_USUARIO = 'omada_ssh_usuario';
    private const CHAVE_SENHA_CIFRADA = 'omada_ssh_senha_cifrada';
    private const CHAVE_PORTA = 'omada_ssh_porta';

    private const COMANDOS_PERMITIDOS = [
        'enable',
        'show system-info',
        'show interface status',
        'show cpu-utilization',
        'show memory-utilization',
        'show mac address-table count',
        'show vlan brief',
        'show mac address-table dynamic',
        'show arp',
    ];

    private const MARCA_PAGINACAO = 'Press any key to continue (Q to quit)';
    private const TIMEOUT_SEGUNDOS = 10;

    public function configurado(): bool
    {
        return $this->usuarioAtual() !== ''
            && (ConfigService::get(self::CHAVE_SENHA_CIFRADA, '') ?: '') !== '';
    }

    public function usuarioAtual(): string
    {
        return trim((string)(ConfigService::get(self::CHAVE_USUARIO, '') ?: ''));
    }

    public function portaAtual(): int
    {
        $porta = (int)(ConfigService::get(self::CHAVE_PORTA, '') ?: 0);

        return $porta > 0 ? $porta : 22;
    }

    public function salvarConfiguracao(string $usuario, string $senha, string $porta): bool
    {
        $usuario = trim($usuario);
        $porta = (int)trim($porta);

        if ($usuario === '') {
            NotificationService::error('Informe o usuário SSH do switch.');
            return false;
        }

        if ($porta < 1 || $porta > 65535) {
            $porta = 22;
        }

        // Senha em branco mantém a atual -- só exige uma nova quando ainda não havia nenhuma.
        if ($senha === '' && !$this->configurado()) {
            NotificationService::error('Informe a senha SSH do switch.');
            return false;
        }

        ConfigService::set(self::CHAVE_USUARIO, $usuario);
        ConfigService::set(self::CHAVE_PORTA, (string)$porta);

        if ($senha !== '') {
            ConfigService::set(self::CHAVE_SENHA_CIFRADA, CryptoService::encriptar($senha));
        }

        AuditService::registrar('Omada Controller', 'Configuração', "Modo SSH direto no switch: usuário \"{$usuario}\", porta {$porta}.");
        NotificationService::success('Configuração salva -- use "Testar conexão" com o IP de um switch pra validar.');

        return true;
    }

    public function removerConfiguracao(): void
    {
        ConfigService::set(self::CHAVE_USUARIO, '');
        ConfigService::set(self::CHAVE_SENHA_CIFRADA, '');
        ConfigService::set(self::CHAVE_PORTA, '');
    }

    private function senhaAtual(): ?string
    {
        $cifrada = ConfigService::get(self::CHAVE_SENHA_CIFRADA, '');

        if (!$cifrada) {
            return null;
        }

        try {
            return CryptoService::decriptar($cifrada);
        } catch (\RuntimeException $e) {
            return null;
        }
    }

    public function testarConexao(string $ip): array
    {
        $ip = trim($ip);

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return ['success' => false, 'message' => 'Informe um IP válido de switch pra testar.'];
        }

        $resultado = $this->coletar($ip);

        if (!$resultado['success']) {
            return $resultado;
        }

        $d = $resultado['dados'];
        AuditService::registrar('Omada Controller', 'Testar conexão', "SSH direto no switch {$ip} validado ({$d['modelo']}).");

        return [
            'success' => true,
            'message' => "Conectado via SSH -- {$d['nome']} ({$d['modelo']}, firmware {$d['firmware']}), {$d['portas_ativas']} de {$d['portas_total']} portas com link.",
        ];
    }

    /**
     * Abre uma sessão, roda os `show` da lista fixa e devolve tudo já
     * interpretado.
     *
     * @return array{success:bool, message:string, dados?:array}
     */
    public function coletar(string $ip): array
    {
        if (!$this->configurado()) {
            return ['success' => false, 'message' => 'Credencial SSH dos switches ainda não configurada -- veja Integrações > TP-Link Omada.'];
        }

        $senha = $this->senhaAtual();

        if ($senha === null) {
            return ['success' => false, 'message' => 'Não foi possível ler a senha SSH salva -- salve a credencial de novo.'];
        }

        try {
            $ssh = new SSH2($ip, $this->portaAtual(), self::TIMEOUT_SEGUNDOS);

            if (!@$ssh->login($this->usuarioAtual(), $senha)) {
                return ['success' => false, 'message' => "O switch {$ip} recusou o usuário/senha SSH."];
            }

            $ssh->setTimeout(self::TIMEOUT_SEGUNDOS);
            $ssh->enablePTY();
            $ssh->setWindowSize(200, 1000);

            $inicio = $this->lerAtePrompt($ssh);

            if (!preg_match('/[>#]\s*$/', $inicio)) {
                return ['success' => false, 'message' => "Conectou via SSH em {$ip}, mas não reconheceu a linha de comando de um switch TP-Link."];
            }

            $saidas = [];
            foreach (self::COMANDOS_PERMITIDOS as $comando) {
                $saidas[$comando] = $this->executar($ssh, $comando);
            }

            $ssh->disconnect();
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => "Falha ao conectar via SSH em {$ip}: " . $e->getMessage()];
        }

        $sistema = $this->interpretarSystemInfo($saidas['show system-info']);

        if (empty($sistema['Hardware Version'])) {
            return ['success' => false, 'message' => "Conectou via SSH em {$ip}, mas o equipamento não respondeu \"show system-info\" como um switch TP-Link."];
        }

        $portas = $this->interpretarPortas($saidas['show interface status']);
        $arp = $this->interpretarArp($saidas['show arp']);

        // Cada porta ganha a lista de MACs aprendidos nela, já com o IP quando
        // a ARP do próprio switch conhece -- o resto é completado por
        // AtivoService (ARP do servidor, IP Scanner, cadastro de Ativos).
        $macsPorPorta = $this->interpretarTabelaMac($saidas['show mac address-table dynamic']);
        foreach ($portas as &$porta) {
            $porta['dispositivos'] = array_map(
                fn ($mac) => ['mac' => $mac, 'ip' => $arp[$mac] ?? '', 'ip_origem' => isset($arp[$mac]) ? 'switch' : ''],
                $macsPorPorta[$porta['porta']] ?? []
            );
        }
        unset($porta);

        return [
            'success' => true,
            'message' => 'Dados lidos via SSH.',
            'dados' => [
                'nome' => $sistema['System Name'] ?? '',
                'descricao' => $sistema['System Description'] ?? '',
                'modelo' => $sistema['Hardware Version'] ?? '',
                'firmware' => $sistema['Software Version'] ?? '',
                'serial' => $sistema['Serial Number'] ?? '',
                'mac' => $sistema['Mac Address'] ?? '',
                'uptime' => $this->formatarUptime($sistema['Running Time'] ?? ''),
                'cpu_pct' => $this->extrairCpu($saidas['show cpu-utilization']),
                'mem_pct' => $this->extrairPrimeiroPercentual($saidas['show memory-utilization']),
                'mac_total' => preg_match('/Total MAC Addresses\s*:\s*(\d+)/i', $saidas['show mac address-table count'], $m) ? (int)$m[1] : null,
                'vlans' => $this->interpretarVlans($saidas['show vlan brief']),
                'portas' => $portas,
                'portas_total' => count($portas),
                'portas_ativas' => count(array_filter($portas, fn ($p) => $p['link'])),
            ],
        ];
    }

    private function executar(SSH2 $ssh, string $comando): string
    {
        if (!in_array($comando, self::COMANDOS_PERMITIDOS, true)) {
            throw new \LogicException("Comando fora da lista de leitura: {$comando}");
        }

        $ssh->write($comando . "\r");
        $saida = $this->lerAtePrompt($ssh);

        // Tira o eco do próprio comando (primeira linha) e o prompt do fim.
        $linhas = explode("\n", $saida);
        array_shift($linhas);
        array_pop($linhas);

        return implode("\n", $linhas);
    }

    /** Lê até o prompt (`nome>`/`nome#`), respondendo a paginação com espaço. */
    private function lerAtePrompt(SSH2 $ssh): string
    {
        $acumulado = '';

        for ($i = 0; $i < 50; $i++) {
            $pedaco = $ssh->read('/(' . preg_quote(self::MARCA_PAGINACAO, '/') . '|[>#]\s*$)/', SSH2::READ_REGEX);

            if ($pedaco === false || $pedaco === true) {
                break;
            }

            $acumulado .= $pedaco;

            if (str_contains($pedaco, self::MARCA_PAGINACAO)) {
                $ssh->write(' ');
                continue;
            }

            break;
        }

        // Limpa o resto da paginação (marca + espaços/backspaces que o switch usa pra apagá-la).
        $acumulado = str_replace([self::MARCA_PAGINACAO, "\r", "\x00"], ['', '', ''], $acumulado);
        $acumulado = preg_replace('/\x1b\[[0-9;]*[A-Za-z]/', '', $acumulado);

        return preg_replace('/^[ \x08]+/m', ' ', $acumulado);
    }

    /** "Chave   - valor" por linha. */
    private function interpretarSystemInfo(string $saida): array
    {
        $info = [];

        foreach (explode("\n", $saida) as $linha) {
            if (preg_match('/^\s*([A-Za-z][A-Za-z ]+?)\s+-\s+(.*?)\s*$/', $linha, $m)) {
                $info[$m[1]] = $m[2];
            }
        }

        return $info;
    }

    /** @return list<array{porta:string, link:bool, velocidade:string, duplex:string, meio:string, descricao:string}> */
    private function interpretarPortas(string $saida): array
    {
        $portas = [];

        foreach (explode("\n", $saida) as $linha) {
            if (preg_match('/^\s*((?:Gi|Te|Tw|Fa|Ten|Two)\S*\d)\s+(LinkUp|LinkDown)\s+(\S+)\s+(\S+)\s+\S+\s+(\S+)\s*(.*?)\s*$/', $linha, $m)) {
                $portas[] = [
                    'porta' => $m[1],
                    'link' => $m[2] === 'LinkUp',
                    'velocidade' => $m[3] === 'N/A' ? '' : $m[3],
                    'duplex' => $m[4] === 'N/A' ? '' : $m[4],
                    'meio' => $m[5] === 'Copper' ? 'Cobre' : ($m[5] === 'Fiber' ? 'Fibra' : $m[5]),
                    'descricao' => $m[6],
                ];
            }
        }

        return $portas;
    }

    /** MAC normalizado (minúsculo, com ":") -- mesmo formato em todas as fontes que cruzam com ele. */
    public static function normalizarMac(string $mac): string
    {
        $hex = strtolower(preg_replace('/[^0-9a-fA-F]/', '', $mac));

        return strlen($hex) === 12 ? implode(':', str_split($hex, 2)) : '';
    }

    /** "MAC  VLAN  Porta  Tipo  Aging" -> [porta => [mac, ...]] */
    private function interpretarTabelaMac(string $saida): array
    {
        $porPorta = [];

        foreach (explode("\n", $saida) as $linha) {
            if (preg_match('/^\s*([0-9a-f]{2}(?::[0-9a-f]{2}){5})\s+\d+\s+(\S+)\s+/i', $linha, $m)) {
                $porPorta[$m[2]][] = self::normalizarMac($m[1]);
            }
        }

        return $porPorta;
    }

    /** "VLAN1  192.168.0.10  00:e0:4c:fa:01:43  DYNAMIC" -> [mac => ip] */
    private function interpretarArp(string $saida): array
    {
        $arp = [];

        foreach (explode("\n", $saida) as $linha) {
            if (preg_match('/\s(\d{1,3}(?:\.\d{1,3}){3})\s+([0-9a-f]{2}(?::[0-9a-f]{2}){5})\s/i', $linha, $m)) {
                $arp[self::normalizarMac($m[2])] = $m[1];
            }
        }

        return $arp;
    }

    /** @return list<array{id:int, nome:string}> */
    private function interpretarVlans(string $saida): array
    {
        $vlans = [];

        foreach (explode("\n", $saida) as $linha) {
            if (preg_match('/^(\d+)\s+(.+?)\s+(active|inactive)\b/i', $linha, $m)) {
                $vlans[] = ['id' => (int)$m[1], 'nome' => $m[2]];
            }
        }

        return $vlans;
    }

    /** "59 day - 21 hour - 29 min - 35 sec" -> "59d 21h 29m 35s" (mesmo estilo do uptime do Controller). */
    private function formatarUptime(string $texto): string
    {
        $mapa = ['day' => 'd', 'hour' => 'h', 'min' => 'm', 'sec' => 's'];
        $partes = [];

        if (preg_match_all('/(\d+)\s*(day|hour|min|sec)/i', $texto, $m, PREG_SET_ORDER)) {
            foreach ($m as $p) {
                $partes[] = $p[1] . $mapa[strtolower($p[2])];
            }
        }

        return $partes ? implode(' ', $partes) : $texto;
    }

    /** Linha da unidade 1: "1 | 15% 19% 18%" -> média de 1 minuto (segundo valor). */
    private function extrairCpu(string $saida): ?int
    {
        if (preg_match('/^\s*1\s*\|\s*(\d+)%\s+(\d+)%/m', $saida, $m)) {
            return (int)$m[2];
        }

        return null;
    }

    private function extrairPrimeiroPercentual(string $saida): ?int
    {
        return preg_match('/(\d+)%/', $saida, $m) ? (int)$m[1] : null;
    }
}
