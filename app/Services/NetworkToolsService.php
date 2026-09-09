<?php

namespace App\Services;

use App\Core\Database;

class NetworkToolsService
{
    private const SCANNER_STATUS_DIR = '/var/www/rd.intranet/storage/ip_scanner_status';

    private LinuxService $linux;

    public function __construct()
    {
        $this->linux = new LinuxService();
    }

    public function arp(): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/arp_listar_web.sh');

        $linhas = [];

        foreach (explode("\n", trim($resultado['output'])) as $linha) {
            $linha = trim($linha);

            if ($linha === '') {
                continue;
            }

            if (preg_match('/^(\S+)\s+dev\s+(\S+)(?:\s+lladdr\s+(\S+))?\s+(\S+)$/', $linha, $m)) {
                $linhas[] = [
                    'ip' => $m[1],
                    'dev' => $m[2],
                    'mac' => $m[3] ?? '-',
                    'estado' => $m[4],
                ];
            } else {
                $linhas[] = ['ip' => $linha, 'dev' => '-', 'mac' => '-', 'estado' => '-'];
            }
        }

        return $linhas;
    }

    /**
     * Valida hostname (RFC 1123) ou IPv4/IPv6 literal. Chamado antes de
     * qualquer script que toque ping/traceroute -- nunca confia só na
     * validação do bash do lado de lá.
     */
    public function validarDestino(string $destino): bool
    {
        if ($destino === '' || strlen($destino) > 253) {
            return false;
        }

        if (filter_var($destino, FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        return (bool)preg_match(
            '/^[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?(\.[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*$/',
            $destino
        );
    }

    public function ping(string $destino): array
    {
        if (!$this->validarDestino($destino)) {
            return ['success' => false, 'output' => 'Destino inválido.'];
        }

        return $this->linux->executarScript('/opt/rdtecnologia/scripts/ping_web.sh', [$destino]);
    }

    public function traceroute(string $destino): array
    {
        if (!$this->validarDestino($destino)) {
            return ['success' => false, 'output' => 'Destino inválido.'];
        }

        return $this->linux->executarScript('/opt/rdtecnologia/scripts/traceroute_web.sh', [$destino]);
    }

    public function mtr(string $destino): array
    {
        if (!$this->validarDestino($destino)) {
            return ['success' => false, 'output' => 'Destino inválido.'];
        }

        return $this->linux->executarScript('/opt/rdtecnologia/scripts/mtr_web.sh', [$destino]);
    }

    /**
     * Só aceita hostname (não IP) -- resolução DNS de um IP literal não
     * faz sentido nesse diagnóstico.
     */
    public function validarDominio(string $dominio): bool
    {
        if ($dominio === '' || strlen($dominio) > 253) {
            return false;
        }

        return (bool)preg_match(
            '/^[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?(\.[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*$/',
            $dominio
        );
    }

    public function verificarDns(string $dominio): array
    {
        if (!$this->validarDominio($dominio)) {
            return ['success' => false, 'output' => 'Domínio inválido.'];
        }

        return $this->linux->executarScript('/opt/rdtecnologia/scripts/dns_check_web.sh', [$dominio]);
    }

    /**
     * Converte a saída crua do traceroute -A (uma linha de texto por salto)
     * em uma lista estruturada [ttl, host, ip, as, ms, timeout] pra tabela.
     */
    public function parsearTraceroute(string $output): array
    {
        $saltos = [];

        foreach (explode("\n", $output) as $linha) {
            $linha = rtrim($linha);

            if ($linha === '' || !preg_match('/^\s*\d+\s/', $linha)) {
                continue;
            }

            if (preg_match('/^\s*(\d+)\s+\*\s*$/', $linha, $m)) {
                $saltos[] = [
                    'ttl' => (int)$m[1],
                    'host' => null,
                    'ip' => null,
                    'as' => null,
                    'ms' => null,
                    'timeout' => true,
                ];
                continue;
            }

            if (preg_match('/^\s*(\d+)\s+(\S+)\s+\(([^)]+)\)\s+\[([^\]]*)\]\s+([\d.]+)\s*ms/', $linha, $m)) {
                $as = $m[4] === '*' || $m[4] === '' ? null : $m[4];

                $saltos[] = [
                    'ttl' => (int)$m[1],
                    'host' => $m[2] === $m[3] ? null : $m[2],
                    'ip' => $m[3],
                    'as' => $as,
                    'ms' => (float)$m[5],
                    'timeout' => false,
                ];
                continue;
            }

            // Linha reconhecida (começa com numero) mas em formato
            // inesperado (ex: sem -A funcionando) -- guarda mesmo assim.
            if (preg_match('/^\s*(\d+)\s+(.*)$/', $linha, $m)) {
                $saltos[] = [
                    'ttl' => (int)$m[1],
                    'host' => null,
                    'ip' => null,
                    'as' => null,
                    'ms' => null,
                    'timeout' => false,
                    'bruto' => trim($m[2]),
                ];
            }
        }

        return $saltos;
    }

    /**
     * Converte a saída de "mtr -r -w -b" (relatório não-interativo, uma
     * linha por salto) em uma lista estruturada [hop, host, ip,
     * perda_pct, enviados, ultimo_ms, media_ms, melhor_ms, pior_ms,
     * desvio_ms] pra tabela -- mesmo padrão de parsearTraceroute().
     */
    public function parsearMtr(string $output): array
    {
        $saltos = [];

        foreach (explode("\n", $output) as $linha) {
            $linha = rtrim($linha);

            if (!preg_match(
                '/^\s*(\d+)\.\|--\s+(\S+)(?:\s+\(([^)]+)\))?\s+([\d.]+)%\s+(\d+)\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)/',
                $linha,
                $m
            )) {
                continue;
            }

            $saltos[] = [
                'hop' => (int)$m[1],
                'host' => $m[3] !== '' && isset($m[3]) ? $m[2] : null,
                'ip' => $m[3] !== '' && isset($m[3]) ? $m[3] : $m[2],
                'perda_pct' => (float)$m[4],
                'enviados' => (int)$m[5],
                'ultimo_ms' => (float)$m[6],
                'media_ms' => (float)$m[7],
                'melhor_ms' => (float)$m[8],
                'pior_ms' => (float)$m[9],
                'desvio_ms' => (float)$m[10],
            ];
        }

        return $saltos;
    }

    /**
     * Converte a saída de dns_check_web.sh (linhas "RESOLVCONF|..." e
     * "RESOLVER|nome|servidor|status|tempo_ms|resposta") em
     * ['resolvconf' => string, 'resolvers' => [...]] pra tela.
     */
    public function parsearDns(string $output): array
    {
        $resolvconf = '';
        $resolvers = [];

        foreach (explode("\n", $output) as $linha) {
            $linha = rtrim($linha);

            if (str_starts_with($linha, 'RESOLVCONF|')) {
                $resolvconf = substr($linha, strlen('RESOLVCONF|'));
                continue;
            }

            if (!str_starts_with($linha, 'RESOLVER|')) {
                continue;
            }

            $campos = explode('|', substr($linha, strlen('RESOLVER|')));

            $resolvers[] = [
                'nome' => $campos[0] ?? '',
                'servidor' => $campos[1] ?? '-',
                'status' => $campos[2] ?? 'falha',
                'tempo_ms' => isset($campos[3]) ? (int)$campos[3] : null,
                'resposta' => $campos[4] ?? '',
            ];
        }

        return ['resolvconf' => $resolvconf, 'resolvers' => $resolvers];
    }

    public function trafegoInterfaces(): array
    {
        $interfaces = (new ServerInfoService())->snapshot()['rede']['interfaces'];

        return array_map(function (array $i) {
            return [
                'nome' => $i['nome'],
                'rx_bytes' => $i['rx_bytes'],
                'tx_bytes' => $i['tx_bytes'],
            ];
        }, $interfaces);
    }

    // ── IP Scanner ───────────────────────────────────────────────────────

    /**
     * Sugere a faixa da própria rede do servidor como valor inicial do
     * formulário -- calcula o endereço de rede a partir do primeiro
     * IPv4/CIDR de interface válido (NetworkConfigService já expõe isso
     * pronto, "192.168.1.10/24"), pra o admin não precisar digitar nada.
     */
    public function sugerirFaixaPadrao(): ?string
    {
        $rede = new NetworkConfigService();

        foreach ($rede->interfacesValidas() as $iface) {
            $config = $rede->configuracaoAtual($iface);

            foreach (($config['ipv4'] ?? []) as $ipCidr) {
                $redeCidr = $this->calcularRedeCidr((string)$ipCidr);

                if ($redeCidr !== null && $this->validarFaixaScan($redeCidr)['valido']) {
                    return $redeCidr;
                }
            }
        }

        return null;
    }

    private function calcularRedeCidr(string $ipCidr): ?string
    {
        [$ip, $prefixoStr] = array_pad(explode('/', $ipCidr, 2), 2, null);

        if ($ip === null || $prefixoStr === null || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return null;
        }

        $prefixo = (int)$prefixoStr;
        if ($prefixo < 0 || $prefixo > 32) {
            return null;
        }

        $ipLong = ip2long($ip);
        if ($ipLong === false) {
            return null;
        }

        $mascara = $prefixo === 0 ? 0 : (~0 << (32 - $prefixo)) & 0xFFFFFFFF;
        $redeLong = $ipLong & $mascara;

        return long2ip($redeLong) . '/' . $prefixo;
    }

    /**
     * Redundante à validação que o próprio script faz de novo (defesa em
     * profundidade) -- exige faixa privada (RFC1918) ou link-local, e no
     * máximo /22 (1024 endereços), mantendo a ferramenta no escopo "minha
     * rede", não um scanner de internet.
     */
    public function validarFaixaScan(string $cidr): array
    {
        if (!preg_match('#^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})/(\d{1,2})$#', $cidr, $m)) {
            return ['valido' => false, 'mensagem' => 'Faixa de IP inválida.'];
        }

        for ($i = 1; $i <= 4; $i++) {
            if ((int)$m[$i] > 255) {
                return ['valido' => false, 'mensagem' => 'Faixa de IP inválida.'];
            }
        }

        $octeto1 = (int)$m[1];
        $octeto2 = (int)$m[2];
        $prefixo = (int)$m[5];

        $privada = ($octeto1 === 10)
            || ($octeto1 === 172 && $octeto2 >= 16 && $octeto2 <= 31)
            || ($octeto1 === 192 && $octeto2 === 168)
            || ($octeto1 === 169 && $octeto2 === 254);

        if (!$privada) {
            return ['valido' => false, 'mensagem' => 'Só é permitido varrer faixas de rede privada (RFC1918) ou link-local.'];
        }

        if ($prefixo < 22 || $prefixo > 32) {
            return ['valido' => false, 'mensagem' => 'Faixa grande demais -- use no máximo /22 (1024 endereços).'];
        }

        return ['valido' => true, 'mensagem' => ''];
    }

    /**
     * Aceita uma ou várias faixas no mesmo campo (separadas por vírgula,
     * ponto-e-vírgula ou quebra de linha) -- nmap escaneia todas juntas
     * numa única chamada (não uma varredura por faixa em sequência), então
     * o resultado já sai combinado, com um progresso só. Útil pra clientes
     * com várias VLANs/sub-redes (ex: Infra/Interno/Funcionários/Diretoria).
     */
    public function iniciarScan(string $cidrsRaw): array
    {
        $cidrs = $this->parsearFaixas($cidrsRaw);

        if (empty($cidrs)) {
            return ['success' => false, 'message' => 'Informe pelo menos uma faixa de IP.'];
        }

        if (count($cidrs) > 8) {
            return ['success' => false, 'message' => 'Máximo de 8 faixas por varredura.'];
        }

        foreach ($cidrs as $cidr) {
            $validacao = $this->validarFaixaScan($cidr);
            if (!$validacao['valido']) {
                return ['success' => false, 'message' => "{$cidr}: {$validacao['mensagem']}"];
            }
        }

        $execucaoId = bin2hex(random_bytes(8));

        $this->linux->executarScriptEmSegundoPlano(
            '/opt/rdtecnologia/scripts/ip_scanner_web.sh',
            array_merge([$execucaoId], $cidrs)
        );

        AuditService::registrar('Rede', 'IP Scanner', 'Varredura iniciada em ' . implode(', ', $cidrs) . '.');

        return ['success' => true, 'execucao_id' => $execucaoId];
    }

    private function parsearFaixas(string $raw): array
    {
        $partes = preg_split('/[\s,;]+/', trim($raw)) ?: [];

        return array_values(array_unique(array_filter(array_map('trim', $partes))));
    }

    public function statusScan(string $execucaoId): array
    {
        $id = preg_replace('/[^a-f0-9]/', '', $execucaoId);
        $arquivo = self::SCANNER_STATUS_DIR . "/{$id}.json";

        if ($id === '' || !is_file($arquivo)) {
            return ['status' => 'desconhecido'];
        }

        $dados = json_decode((string)file_get_contents($arquivo), true);

        return is_array($dados) ? $dados : ['status' => 'desconhecido'];
    }

    public function escanearPortas(string $ip): array
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return ['success' => false, 'message' => 'IP inválido.'];
        }

        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/ip_scanner_portas_web.sh', [$ip]);
        $dados = json_decode(trim($resultado['output']), true);

        if (!is_array($dados)) {
            return ['success' => false, 'message' => $resultado['output']];
        }

        // nmap (acima) só varre TCP -- SNMP é UDP/161, checado à parte com a
        // mesma community padrão usada na coleta de Ativos. Só sob demanda
        // aqui (não na varredura da faixa inteira), porque UDP sem resposta
        // não tem "fechado" rápido feito TCP -- deixaria o scan de /22 lento.
        if ($dados['success'] ?? false) {
            $comunidade = (new AtivoService())->comunidadePadrao();
            $dados['snmp'] = (new SnmpService())->disponivel($ip, $comunidade);
        }

        return $dados;
    }

    public function enviarWol(string $mac): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/ip_scanner_wol_web.sh', [$mac]);
        $dados = json_decode(trim($resultado['output']), true);

        if (is_array($dados) && $dados['success']) {
            AuditService::registrar('Rede', 'Wake-on-LAN', "Magic packet enviado para {$mac}.");
        }

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }

    /**
     * Compara o resultado atual com a última varredura salva pra essa
     * MESMA faixa, e só depois grava o atual como a nova "última" -- nessa
     * ordem, senão a comparação seria sempre contra si mesma. Chamado uma
     * única vez pelo controller (endpoint de finalizar, não pelo polling
     * de status, que pode ser chamado várias vezes sem efeito colateral).
     */
    public function registrarResultadoEComparar(string $cidr, array $hosts, ?int $usuarioId): array
    {
        $comparacao = $this->compararComUltimaExecucao($cidr, $hosts);
        $this->salvarExecucao($cidr, $hosts, $usuarioId);

        return $comparacao;
    }

    private function compararComUltimaExecucao(string $cidr, array $hostsAtual): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare("SELECT hosts FROM ip_scanner_execucoes WHERE cidr = ? ORDER BY executado_em DESC LIMIT 1");
        $stmt->execute([$cidr]);
        $anteriorJson = $stmt->fetchColumn();

        if ($anteriorJson === false) {
            return ['novos' => [], 'sumiram' => [], 'primeira_execucao' => true];
        }

        $anteriores = json_decode((string)$anteriorJson, true) ?: [];
        $ipsAnteriores = array_column($anteriores, 'ip');
        $ipsAtuais = array_column($hostsAtual, 'ip');

        $novos = array_values(array_filter($hostsAtual, fn (array $h) => !in_array($h['ip'], $ipsAnteriores, true)));
        $sumiram = array_values(array_filter($anteriores, fn (array $h) => !in_array($h['ip'], $ipsAtuais, true)));

        return ['novos' => $novos, 'sumiram' => $sumiram, 'primeira_execucao' => false];
    }

    private function salvarExecucao(string $cidr, array $hosts, ?int $usuarioId): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare("INSERT INTO ip_scanner_execucoes (cidr, executado_em, executado_por, total_hosts, hosts) VALUES (?, NOW(), ?, ?, ?)");
        $stmt->execute([$cidr, $usuarioId, count($hosts), json_encode($hosts)]);
    }

    /**
     * Última varredura de cada faixa distinta, mais recente primeiro --
     * alimenta o menu "Varreduras recentes" (atalho pra re-escanear sem
     * digitar o CIDR de novo). Uma linha por CIDR (não o histórico
     * completo), então re-escanear a mesma faixa várias vezes não
     * polui a lista com repetições.
     */
    public function listarExecucoesRecentes(int $limite = 8): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare("
            SELECT e.id, e.cidr, e.executado_em, e.total_hosts
            FROM ip_scanner_execucoes e
            INNER JOIN (
                SELECT cidr, MAX(executado_em) AS ultima
                FROM ip_scanner_execucoes
                GROUP BY cidr
            ) u ON u.cidr = e.cidr AND u.ultima = e.executado_em
            ORDER BY e.executado_em DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limite, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Uma execução específica salva (pra "ver" uma varredura antiga sem rodar de novo). */
    public function buscarExecucao(int $id): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare("SELECT id, cidr, executado_em, total_hosts, hosts FROM ip_scanner_execucoes WHERE id = ?");
        $stmt->execute([$id]);
        $linha = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$linha) {
            return null;
        }

        $linha['hosts'] = json_decode($linha['hosts'], true) ?: [];

        return $linha;
    }

    /**
     * Cruza os hosts descobertos com o cadastro de Ativos (por IP exato
     * ou pelo nome curto, sem o sufixo de domínio que o reverse DNS
     * costuma trazer, ex: "EP-RCP-01.localdomain" -> "EP-RCP-01") -- uma
     * consulta só, comparação em PHP, evita N+1 pra cada host da faixa.
     * Cada host ganha 'ativo' => null (não cadastrado) ou um resumo do
     * ativo já cadastrado (id, codigo_patrimonio, nome).
     */
    public function relacionarComAtivos(array $hosts): array
    {
        if (empty($hosts)) {
            return $hosts;
        }

        $pdo = Database::connection();
        $ativos = $pdo->query("SELECT id, codigo_patrimonio, nome, ip FROM ativos")->fetchAll(\PDO::FETCH_ASSOC);

        $porIp = [];
        $porNome = [];
        foreach ($ativos as $a) {
            if (!empty($a['ip'])) {
                $porIp[$a['ip']] = $a;
            }
            $nomeCurto = strtolower(explode('.', $a['nome'])[0]);
            if ($nomeCurto !== '') {
                $porNome[$nomeCurto] = $a;
            }
        }

        foreach ($hosts as &$host) {
            $achado = $porIp[$host['ip']] ?? null;

            if (!$achado && !empty($host['hostname'])) {
                $nomeCurto = strtolower(explode('.', $host['hostname'])[0]);
                $achado = $porNome[$nomeCurto] ?? null;
            }

            $host['ativo'] = $achado ? [
                'id' => (int)$achado['id'],
                'codigo_patrimonio' => $achado['codigo_patrimonio'],
                'nome' => $achado['nome'],
            ] : null;
        }
        unset($host);

        return $hosts;
    }
}
