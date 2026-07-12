<?php

namespace App\Services;

use App\Repositories\IptablesRegraRepository;
use App\Repositories\VpnOpenvpnRepository;

class VpnOpenvpnService
{
    private const ORIGEM_TEMPLATE = 'vpn_openvpn';
    private const PKI_DIR = '/etc/openvpn/server/easy-rsa/pki';

    private LinuxService $linux;
    private VpnOpenvpnRepository $repo;

    public function __construct()
    {
        $this->linux = new LinuxService();
        $this->repo = new VpnOpenvpnRepository();
    }

    public function config(): array
    {
        return $this->repo->config();
    }

    public function instalado(): bool
    {
        return (bool)($this->repo->config()['instalado'] ?? false);
    }

    public function pkiInicializada(): bool
    {
        return (bool)($this->repo->config()['pki_inicializada'] ?? false);
    }

    public function instalar(): array
    {
        set_time_limit(120);

        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/vpn_openvpn_instalar_web.sh');
        $dados = json_decode(trim($resultado['output']), true);
        $dados = is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];

        if (!empty($dados['success'])) {
            $this->repo->marcarInstalado();
        }

        return $dados;
    }

    public function inicializarPki(): array
    {
        $resultado = (new VpnPkiService())->inicializar();

        if (!empty($resultado['success'])) {
            $this->repo->marcarPkiInicializada();
        }

        return $resultado;
    }

    public function status(): array
    {
        $config = $this->repo->config();
        $clientes = $this->repo->listarClientes();

        $aoVivo = $this->statusAoVivo();

        $porNome = [];
        foreach ($aoVivo['clientes'] as $c) {
            $porNome[$c['nome']] = $c;
        }

        $clientesComStatus = [];
        foreach ($clientes as $cliente) {
            $live = $porNome[$cliente['nome']] ?? null;
            $cliente['online'] = $live !== null;
            $cliente['endereco_real'] = $live['endereco_real'] ?? null;
            $cliente['rx_bytes'] = $live['rx_bytes'] ?? 0;
            $cliente['tx_bytes'] = $live['tx_bytes'] ?? 0;
            $cliente['conectado_desde'] = ($live['conectado_desde'] ?? 0) > 0 ? date('Y-m-d H:i:s', $live['conectado_desde']) : null;
            $clientesComStatus[] = $cliente;
        }

        return [
            'config' => $config,
            'servidor_ativo' => $aoVivo['servidor_ativo'],
            'clientes' => $clientesComStatus,
        ];
    }

    private function statusAoVivo(): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/vpn_openvpn_status_web.sh');

        $ativo = false;
        $clientes = [];

        foreach (explode("\n", trim($resultado['output'])) as $linha) {
            $linha = trim($linha);
            if ($linha === '' || !str_contains($linha, '|')) continue;

            $partes = explode('|', $linha);

            if ($partes[0] === 'SERVIDOR_ATIVO') {
                $ativo = ($partes[1] ?? '0') === '1';
                continue;
            }
            if ($partes[0] === 'CLIENTE') {
                $clientes[] = [
                    'nome' => $partes[1] ?? '',
                    'endereco_real' => $partes[2] ?? null,
                    'rx_bytes' => (int)($partes[3] ?? 0),
                    'tx_bytes' => (int)($partes[4] ?? 0),
                    'conectado_desde' => (int)($partes[5] ?? 0),
                ];
            }
        }

        return ['servidor_ativo' => $ativo, 'clientes' => $clientes];
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
            return ['success' => false, 'message' => 'Instale o OpenVPN antes de salvar a configuração.'];
        }
        if (!$this->pkiInicializada()) {
            return ['success' => false, 'message' => 'Inicialize a PKI antes de salvar a configuração.'];
        }

        $this->repo->salvarConfig($dados);

        $config = $this->repo->config();

        return (new OpenvpnConfigDeployService())->deploy($this->gerarConteudoServidor($config));
    }

    private function gerarConteudoServidor(array $config): string
    {
        [$rede, $mascara] = $this->cidrParaRedeMascara($config['subnet_cidr']);

        $linhas = [
            "port {$config['porta']}",
            "proto {$config['protocolo']}",
            'dev tun',
            'ca ' . self::PKI_DIR . '/ca.crt',
            'cert ' . self::PKI_DIR . '/issued/server.crt',
            'key ' . self::PKI_DIR . '/private/server.key',
            'dh none',
            'tls-crypt /etc/openvpn/server/tls-crypt.key',
            'topology subnet',
            "server {$rede} {$mascara}",
            'ifconfig-pool-persist /etc/openvpn/server/ipp.txt',
        ];

        if (!empty($config['redirect_gateway'])) {
            $linhas[] = 'push "redirect-gateway def1 bypass-dhcp"';
        }
        if (!empty($config['dns_push'])) {
            $linhas[] = "push \"dhcp-option DNS {$config['dns_push']}\"";
        }

        $linhas = array_merge($linhas, [
            'keepalive 10 120',
            'cipher AES-256-GCM',
            'auth SHA256',
            'crl-verify ' . self::PKI_DIR . '/crl.pem',
            'persist-key',
            'persist-tun',
            'status /etc/openvpn/server/openvpn-status.log',
            'status-version 3',
            'verb 3',
        ]);

        if ($config['protocolo'] === 'udp') {
            $linhas[] = 'explicit-exit-notify 1';
        }

        return implode("\n", $linhas) . "\n";
    }

    /**
     * @return array{success: bool, message: string, cliente_id?: int, config_texto?: string}
     */
    public function criarCliente(string $nome): array
    {
        $nome = trim($nome);
        if (!preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $nome)) {
            return ['success' => false, 'message' => 'Nome inválido (use letras, números, "-" ou "_").'];
        }
        if (!$this->pkiInicializada()) {
            return ['success' => false, 'message' => 'Inicialize a PKI antes de criar clientes.'];
        }
        if ($this->repo->buscarClientePorNome($nome)) {
            return ['success' => false, 'message' => 'Já existe um cliente com este nome.'];
        }

        $emitido = (new VpnPkiService())->emitirCliente($nome);
        if (empty($emitido['success'])) {
            return ['success' => false, 'message' => $emitido['message'] ?? 'Falha ao emitir certificado.'];
        }

        $clienteId = $this->repo->criarCliente($nome);

        return [
            'success' => true,
            'message' => 'Cliente criado com sucesso.',
            'cliente_id' => $clienteId,
            'config_texto' => $this->gerarConteudoCliente($emitido),
        ];
    }

    /**
     * @return array{success: bool, message: string, config_texto?: string}
     */
    public function baixarClienteNovamente(int $id): array
    {
        $cliente = $this->repo->buscarCliente($id);
        if (!$cliente || (int)$cliente['ativo'] !== 1) {
            return ['success' => false, 'message' => 'Cliente não encontrado ou revogado.'];
        }

        $dados = (new VpnPkiService())->baixarCliente($cliente['nome']);
        if (empty($dados['success'])) {
            return ['success' => false, 'message' => $dados['message'] ?? 'Falha ao recuperar certificado.'];
        }

        return ['success' => true, 'message' => 'OK', 'config_texto' => $this->gerarConteudoCliente($dados)];
    }

    private function gerarConteudoCliente(array $certData): string
    {
        $config = $this->repo->config();

        $endpointHost = trim($config['endpoint_publico'] ?? '');
        if ($endpointHost === '') {
            $endpointHost = (new PublicIpService())->obter() ?? '<configure o endereco publico do servidor>';
        }

        $linhas = [
            'client',
            'dev tun',
            "proto {$config['protocolo']}",
            "remote {$endpointHost} {$config['porta']}",
            'resolv-retry infinite',
            'nobind',
            'persist-key',
            'persist-tun',
            'remote-cert-tls server',
            'cipher AES-256-GCM',
            'auth SHA256',
            'verb 3',
            '<ca>',
            trim($certData['ca']),
            '</ca>',
            '<cert>',
            trim($certData['cert']),
            '</cert>',
            '<key>',
            trim($certData['key']),
            '</key>',
            '<tls-crypt>',
            trim($certData['tls_crypt']),
            '</tls-crypt>',
        ];

        return implode("\n", $linhas) . "\n";
    }

    public function marcarConfigEntregue(int $id): void
    {
        $this->repo->marcarConfigEntregue($id);
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function revogarCliente(int $id): array
    {
        $cliente = $this->repo->buscarCliente($id);
        if (!$cliente) {
            return ['success' => false, 'message' => 'Cliente não encontrado.'];
        }

        $resultado = (new VpnPkiService())->revogarCliente($cliente['nome']);
        if (!empty($resultado['success'])) {
            $this->repo->revogarCliente($id);
        }

        return $resultado;
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
            'nome' => 'VPN OpenVPN - liberar porta',
            'descricao' => 'Permite conexões de entrada na porta do OpenVPN.',
            'tabela' => 'filter', 'cadeia' => 'INPUT', 'acao' => 'ACCEPT',
            'protocolo' => $config['protocolo'], 'porta_destino' => $config['porta'],
        ]));

        $iptablesRepo->criar(array_merge($base, [
            'nome' => 'VPN OpenVPN - encaminhar tráfego',
            'descricao' => 'Permite que os clientes da VPN encaminhem tráfego através do servidor.',
            'tabela' => 'filter', 'cadeia' => 'FORWARD', 'acao' => 'ACCEPT',
            'interface_entrada' => 'tun+',
        ]));

        $iptablesRepo->criar(array_merge($base, [
            'nome' => 'VPN OpenVPN - NAT de saída',
            'descricao' => 'Mascara o tráfego dos clientes da VPN para saírem com o IP do servidor.',
            'tabela' => 'nat', 'cadeia' => 'POSTROUTING', 'acao' => 'MASQUERADE',
            'ip_origem' => $config['subnet_cidr'],
        ]));

        $resultado = $iptablesService->aplicar();
        if ($resultado['success']) {
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

        $aoVivo = $this->statusAoVivo();

        $porNome = [];
        foreach ($aoVivo['clientes'] as $c) {
            $porNome[$c['nome']] = $c;
        }

        foreach ($this->repo->listarClientes(true) as $cliente) {
            $live = $porNome[$cliente['nome']] ?? null;
            if (!$live) continue;

            $conectadoDesde = $live['conectado_desde'] > 0 ? date('Y-m-d H:i:s', $live['conectado_desde']) : null;
            $this->repo->registrarTrafego((int)$cliente['id'], $live['rx_bytes'], $live['tx_bytes'], $conectadoDesde);
        }
    }

    public function historicoTrafego(int $clienteId, int $limite = 50): array
    {
        return $this->repo->historicoTrafego($clienteId, $limite);
    }

    public function trafegoAgregadoHoje(): array
    {
        return $this->repo->trafegoAgregadoHoje();
    }

    private function cidrParaRedeMascara(string $cidr): array
    {
        [$rede, $prefixo] = explode('/', $cidr);
        $mascaraLong = -1 << (32 - (int)$prefixo);
        $mascara = long2ip($mascaraLong & 0xFFFFFFFF);

        return [$rede, $mascara];
    }

    private function validarConfig(array $dados): ?string
    {
        if (!filter_var($dados['porta'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]])) {
            return 'Porta inválida.';
        }
        if (!in_array($dados['protocolo'] ?? '', ['udp', 'tcp'], true)) {
            return 'Protocolo inválido.';
        }
        if (!preg_match('#^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/\d{1,2}$#', $dados['subnet_cidr'] ?? '')) {
            return 'Subnet inválida (use o formato 10.9.0.0/24).';
        }

        return null;
    }
}
