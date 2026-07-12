<?php

namespace App\Services;

use App\Repositories\IptablesRegraRepository;
use App\Repositories\VpnWireguardRepository;

class VpnWireguardService
{
    private const ORIGEM_TEMPLATE = 'vpn_wireguard';

    private LinuxService $linux;
    private VpnWireguardRepository $repo;

    public function __construct()
    {
        $this->linux = new LinuxService();
        $this->repo = new VpnWireguardRepository();
    }

    public function config(): array
    {
        return $this->repo->config();
    }

    public function instalado(): bool
    {
        $config = $this->repo->config();

        return (bool)($config['instalado'] ?? false);
    }

    public function instalar(): array
    {
        set_time_limit(120);

        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/vpn_wireguard_instalar_web.sh');
        $dados = json_decode(trim($resultado['output']), true);
        $dados = is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];

        if (!empty($dados['success'])) {
            $this->repo->marcarInstalado();
        }

        return $dados;
    }

    public function status(): array
    {
        $config = $this->repo->config();
        $peers = $this->repo->listarPeers();

        $aoVivo = $this->statusAoVivo($config['interface_nome'] ?? 'wg0');

        $porChave = [];
        foreach ($aoVivo['peers'] as $p) {
            $porChave[$p['chave_publica']] = $p;
        }

        $peersComStatus = [];
        foreach ($peers as $peer) {
            $live = $porChave[$peer['chave_publica']] ?? null;
            $handshake = $live['ultimo_handshake'] ?? 0;

            $peer['online'] = $handshake > 0 && (time() - $handshake) < 180;
            $peer['ultimo_handshake'] = $handshake > 0 ? date('Y-m-d H:i:s', $handshake) : null;
            $peer['rx_bytes'] = $live['rx_bytes'] ?? 0;
            $peer['tx_bytes'] = $live['tx_bytes'] ?? 0;
            $peersComStatus[] = $peer;
        }

        return [
            'config' => $config,
            'interface_up' => $aoVivo['interface_up'],
            'peers' => $peersComStatus,
        ];
    }

    private function statusAoVivo(string $interface): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/vpn_wireguard_status_web.sh', [$interface]);

        $ifaceUp = false;
        $peers = [];

        foreach (explode("\n", trim($resultado['output'])) as $linha) {
            $linha = trim($linha);
            if ($linha === '' || !str_contains($linha, '|')) continue;

            $partes = explode('|', $linha);

            if ($partes[0] === 'IFACE_UP') {
                $ifaceUp = ($partes[1] ?? '0') === '1';
                continue;
            }
            if ($partes[0] === 'PEER') {
                $peers[] = [
                    'chave_publica' => $partes[1] ?? '',
                    'endpoint' => ($partes[2] ?? '(none)') !== '(none)' ? $partes[2] : null,
                    'ultimo_handshake' => (int)($partes[3] ?? 0),
                    'rx_bytes' => (int)($partes[4] ?? 0),
                    'tx_bytes' => (int)($partes[5] ?? 0),
                ];
            }
        }

        return ['interface_up' => $ifaceUp, 'peers' => $peers];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function salvarConfigServidor(array $dados): array
    {
        $erro = $this->validarConfig($dados);
        if ($erro !== null) {
            return ['success' => false, 'message' => $erro];
        }

        if (!$this->instalado()) {
            return ['success' => false, 'message' => 'Instale o WireGuard antes de salvar a configuração.'];
        }

        $config = $this->repo->config();

        if (empty($config['chave_privada'])) {
            [$privada, $publica] = $this->gerarParDeChaves();
            if ($privada === '' || $publica === '') {
                return ['success' => false, 'message' => 'Falha ao gerar as chaves do servidor (binário "wg" indisponível?).'];
            }
            $this->repo->salvarChaves($privada, $publica);
        }

        $this->repo->salvarConfig($dados);

        return $this->reaplicarConfig();
    }

    private function reaplicarConfig(): array
    {
        $config = $this->repo->config();
        $peers = $this->repo->listarPeers(true);

        $conteudo = $this->gerarConteudoServidor($config, $peers);

        return (new WireguardConfigDeployService())->deploy($conteudo, $config['interface_nome']);
    }

    private function gerarConteudoServidor(array $config, array $peers): string
    {
        $prefixo = explode('/', $config['subnet_cidr'])[1] ?? '24';

        $linhas = [
            '[Interface]',
            "PrivateKey = {$config['chave_privada']}",
            "Address = {$config['servidor_ip_interno']}/{$prefixo}",
            "ListenPort = {$config['porta']}",
        ];
        if (!empty($config['mtu'])) {
            $linhas[] = "MTU = {$config['mtu']}";
        }

        foreach ($peers as $peer) {
            $linhas[] = '';
            $linhas[] = "# {$peer['nome']}";
            $linhas[] = '[Peer]';
            $linhas[] = "PublicKey = {$peer['chave_publica']}";
            $linhas[] = "AllowedIPs = {$peer['ip_atribuido']}/32";
        }

        return implode("\n", $linhas) . "\n";
    }

    /**
     * @return array{success: bool, message: string, peer_id?: int, config_texto?: string, qr_base64?: ?string}
     */
    public function criarPeer(string $nome): array
    {
        $config = $this->repo->config();
        if (empty($config['chave_privada'])) {
            return ['success' => false, 'message' => 'Configure e salve o servidor antes de criar peers.'];
        }

        $nome = trim($nome);
        if ($nome === '') {
            return ['success' => false, 'message' => 'Informe um nome para o peer.'];
        }

        $ip = $this->proximoIpDisponivel($config);
        if ($ip === null) {
            return ['success' => false, 'message' => 'Não há mais endereços disponíveis nessa subnet.'];
        }

        [$privada, $publica] = $this->gerarParDeChaves();

        $peerId = $this->repo->criarPeer($nome, $publica, $ip);

        $resultadoDeploy = $this->reaplicarConfig();
        if (!$resultadoDeploy['success']) {
            $this->repo->revogarPeer($peerId);

            return $resultadoDeploy;
        }

        $textoCliente = $this->gerarConteudoCliente($config, $privada, $ip);

        return [
            'success' => true,
            'message' => 'Peer criado com sucesso.',
            'peer_id' => $peerId,
            'config_texto' => $textoCliente,
            'qr_base64' => $this->gerarQrCodeBase64($textoCliente),
        ];
    }

    private function gerarConteudoCliente(array $config, string $chavePrivadaPeer, string $ip): string
    {
        $endpointHost = trim($config['endpoint_publico'] ?? '');
        if ($endpointHost === '') {
            $endpointHost = (new PublicIpService())->obter() ?? '<configure o endereco publico do servidor>';
        }

        $linhas = [
            '[Interface]',
            "PrivateKey = {$chavePrivadaPeer}",
            "Address = {$ip}/32",
        ];
        if (!empty($config['dns_push'])) {
            $linhas[] = "DNS = {$config['dns_push']}";
        }
        $linhas[] = '';
        $linhas[] = '[Peer]';
        $linhas[] = "PublicKey = {$config['chave_publica']}";
        $linhas[] = "Endpoint = {$endpointHost}:{$config['porta']}";
        $linhas[] = 'AllowedIPs = 0.0.0.0/0, ::/0';
        $linhas[] = 'PersistentKeepalive = 25';

        return implode("\n", $linhas) . "\n";
    }

    public function marcarConfigEntregue(int $id): void
    {
        $this->repo->marcarConfigEntregue($id);
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function revogarPeer(int $id): array
    {
        $this->repo->revogarPeer($id);

        return $this->reaplicarConfig();
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function exporConexaoInternet(bool $expor): array
    {
        $config = $this->repo->config();
        $iptablesRepo = new IptablesRegraRepository();
        $iptablesService = new IptablesService();

        $existentes = $iptablesRepo->buscarPorOrigemTemplate(self::ORIGEM_TEMPLATE);

        if (!$expor) {
            foreach ($existentes as $regra) {
                $iptablesRepo->excluir((int)$regra['id']);
            }
            $this->repo->marcarExposto(false);

            if (empty($existentes)) {
                return ['success' => true, 'message' => 'VPN não está mais exposta à internet.'];
            }

            // mesma regra de seguranca do resto do firewall: aplica com
            // janela de confirmacao + reversao automatica, nunca
            // autoconfirma sozinho -- mesmo sendo uma acao que o admin
            // pediu explicitamente, um bug na geracao da regra nao pode
            // virar definitivo sem uma checagem humana.
            $resultado = $iptablesService->aplicar();
            $resultado['message'] = 'Porta fechada, mas precisa confirmar em Infraestrutura > Firewall em até 90s (ou reverte sozinho). ' . ($resultado['message'] ?? '');

            return $resultado;
        }

        if (!empty($existentes)) {
            $this->repo->marcarExposto(true);

            return ['success' => true, 'message' => 'Já estava exposta à internet.'];
        }

        $base = [
            'protocolo' => null, 'porta_destino' => null, 'porta_origem' => null,
            'ip_origem' => null, 'ip_destino' => null,
            'interface_entrada' => null, 'interface_saida' => null,
            'nat_destino' => null, 'extra' => null,
            'registrar_log' => false, 'ordem' => 100,
            'origem_template' => self::ORIGEM_TEMPLATE, 'ativo' => true,
        ];

        $iptablesRepo->criar(array_merge($base, [
            'nome' => 'VPN WireGuard - liberar porta',
            'descricao' => 'Permite conexões UDP de entrada na porta do WireGuard.',
            'tabela' => 'filter', 'cadeia' => 'INPUT', 'acao' => 'ACCEPT',
            'protocolo' => 'udp', 'porta_destino' => $config['porta'],
        ]));

        $iptablesRepo->criar(array_merge($base, [
            'nome' => 'VPN WireGuard - encaminhar tráfego',
            'descricao' => 'Permite que os clientes da VPN encaminhem tráfego através do servidor.',
            'tabela' => 'filter', 'cadeia' => 'FORWARD', 'acao' => 'ACCEPT',
            'interface_entrada' => $config['interface_nome'],
        ]));

        $iptablesRepo->criar(array_merge($base, [
            'nome' => 'VPN WireGuard - NAT de saída',
            'descricao' => 'Mascara o tráfego dos clientes da VPN para saírem com o IP do servidor.',
            'tabela' => 'nat', 'cadeia' => 'POSTROUTING', 'acao' => 'MASQUERADE',
            'ip_origem' => $config['subnet_cidr'],
        ]));

        $resultado = $iptablesService->aplicar();
        if ($resultado['success']) {
            // marcado como exposto ja aqui pro admin ver o toggle ligado,
            // mas a regra em si so vira permanente se ele confirmar em
            // Infraestrutura > Firewall dentro da janela -- mesmo motivo
            // do bloco de "desexpor" acima.
            $this->repo->marcarExposto(true);
            $resultado['message'] = 'Porta liberada, mas precisa confirmar em Infraestrutura > Firewall em até 90s (ou reverte sozinho). ' . ($resultado['message'] ?? '');
        }

        return $resultado;
    }

    public function coletarTrafego(): void
    {
        $config = $this->repo->config();
        if (empty($config['instalado'])) {
            return;
        }

        $aoVivo = $this->statusAoVivo($config['interface_nome']);

        $porChave = [];
        foreach ($aoVivo['peers'] as $p) {
            $porChave[$p['chave_publica']] = $p;
        }

        foreach ($this->repo->listarPeers(true) as $peer) {
            $live = $porChave[$peer['chave_publica']] ?? null;
            if (!$live) continue;

            $handshake = $live['ultimo_handshake'] > 0 ? date('Y-m-d H:i:s', $live['ultimo_handshake']) : null;
            $this->repo->registrarTrafego((int)$peer['id'], $live['rx_bytes'], $live['tx_bytes'], $handshake);
        }
    }

    public function historicoTrafego(int $peerId, int $limite = 50): array
    {
        return $this->repo->historicoTrafego($peerId, $limite);
    }

    public function trafegoAgregadoHoje(): array
    {
        return $this->repo->trafegoAgregadoHoje();
    }

    private function proximoIpDisponivel(array $config): ?string
    {
        [$base, $prefixo] = explode('/', $config['subnet_cidr']);
        $prefixo = (int)$prefixo;
        $baseLong = ip2long($base);
        $totalHosts = (2 ** (32 - $prefixo)) - 2;

        $usados = array_flip(array_merge([$config['servidor_ip_interno']], $this->repo->ipsAtribuidos()));

        for ($i = 2; $i <= $totalHosts; $i++) {
            $candidato = long2ip($baseLong + $i);
            if (!isset($usados[$candidato])) {
                return $candidato;
            }
        }

        return null;
    }

    /**
     * Gera o par de chaves inteiramente dentro do shell (variaveis, nunca
     * argumento de linha de comando) -- a chave privada nao pode aparecer
     * em "ps aux" nem passar por um arquivo em disco.
     */
    private function gerarParDeChaves(): array
    {
        $resultado = $this->linux->executar(
            'PRIV=$(wg genkey); PUB=$(printf \'%s\' "$PRIV" | wg pubkey); printf \'%s|%s\' "$PRIV" "$PUB"'
        );

        [$privada, $publica] = array_pad(explode('|', trim($resultado['output']), 2), 2, '');

        return [$privada, $publica];
    }

    private function gerarQrCodeBase64(string $texto): ?string
    {
        $resultado = $this->linux->executarComEntrada('qrencode -t PNG -o -', $texto);

        if (!$resultado['success'] || $resultado['output'] === '') {
            return null;
        }

        return base64_encode($resultado['output']);
    }

    private function validarConfig(array $dados): ?string
    {
        if (!preg_match('/^[a-zA-Z0-9_-]{1,15}$/', $dados['interface_nome'] ?? '')) {
            return 'Nome de interface inválido.';
        }
        if (!filter_var($dados['porta'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]])) {
            return 'Porta inválida.';
        }
        if (!preg_match('#^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/\d{1,2}$#', $dados['subnet_cidr'] ?? '')) {
            return 'Subnet inválida (use o formato 10.8.0.0/24).';
        }
        if (!filter_var($dados['servidor_ip_interno'] ?? '', FILTER_VALIDATE_IP)) {
            return 'IP interno do servidor inválido.';
        }
        if (!empty($dados['mtu']) && !filter_var($dados['mtu'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 576, 'max_range' => 9000]])) {
            return 'MTU inválido.';
        }

        return null;
    }
}
