<?php

namespace App\Services;

use App\Repositories\IptablesRegraRepository;
use App\Repositories\VpnIkev2Repository;
use App\Repositories\VpnIkev2SaidaRepository;

/**
 * Servidor IKEv2 (EAP-MSCHAPv2, usuario/senha -- mais facil de
 * configurar nos apps nativos de iOS/Android/Windows do que
 * certificado por cliente) + orquestra a regeneracao do ipsec.conf/
 * ipsec.secrets COMBINADOS com as conexoes de saida (VpnIkev2SaidaService),
 * porque o strongSwan roda tudo no mesmo daemon/arquivo -- ao contrario
 * do WireGuard/OpenVPN (interfaces/unidades systemd separadas por
 * conexao), aqui QUALQUER mudanca (servidor ou saida) precisa
 * regenerar o arquivo inteiro.
 */
class VpnIkev2Service
{
    private const ORIGEM_TEMPLATE = 'vpn_ikev2';
    public const CA_DIR = '/etc/rd-intranet/ikev2/cacerts';

    private LinuxService $linux;
    private VpnIkev2Repository $repo;

    public function __construct()
    {
        $this->linux = new LinuxService();
        $this->repo = new VpnIkev2Repository();
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

        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/vpn_ikev2_instalar_web.sh');
        $dados = json_decode(trim($resultado['output']), true);
        $dados = is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];

        if (!empty($dados['success'])) {
            $this->repo->marcarInstalado();
        }

        return $dados;
    }

    /**
     * O CN do certificado do servidor precisa bater com o endereço que
     * os clientes vão usar pra conectar -- por isso só inicializa
     * depois de ter um endpoint (configurado ou detectado).
     */
    public function inicializarPki(): array
    {
        set_time_limit(120);

        // CA/certificado "server" genérico são compartilhados com o
        // OpenVPN -- se o OpenVPN já inicializou antes, isso é um
        // no-op (o script só cria o que ainda não existe).
        $baseOk = (new VpnPkiService())->inicializar();
        if (empty($baseOk['success'])) {
            return $baseOk;
        }

        $config = $this->repo->config();
        $cn = trim($config['endpoint_publico'] ?? '');
        if ($cn === '') {
            $cn = (new PublicIpService())->obter() ?? '';
        }
        if ($cn === '') {
            return ['success' => false, 'message' => 'Não foi possível detectar o endereço público. Configure "Endereço público" antes de inicializar.'];
        }

        $emitido = (new VpnPkiService())->emitirServidorComCn($cn);
        if (empty($emitido['success'])) {
            return $emitido;
        }

        $this->repo->marcarPkiInicializada();

        return ['success' => true, 'message' => "PKI inicializada com sucesso (certificado emitido para \"{$cn}\")."];
    }

    public function status(): array
    {
        $config = $this->repo->config();
        $clientes = $this->repo->listarClientes();

        $aoVivo = $this->statusAoVivo();

        $porUsuario = [];
        foreach ($aoVivo['clientes'] as $c) {
            $porUsuario[$c['usuario']] = $c;
        }

        $clientesComStatus = [];
        foreach ($clientes as $cliente) {
            $live = $porUsuario[$cliente['nome']] ?? null;
            $cliente['online'] = $live !== null;
            $cliente['endereco_real'] = $live['ip_remoto'] ?? null;
            $cliente['rx_bytes'] = $live['rx_bytes'] ?? 0;
            $cliente['tx_bytes'] = $live['tx_bytes'] ?? 0;
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
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/vpn_ikev2_status_web.sh');

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
                    'usuario' => $partes[1] ?? '',
                    'ip_remoto' => $partes[2] ?? null,
                    'rx_bytes' => (int)($partes[3] ?? 0),
                    'tx_bytes' => (int)($partes[4] ?? 0),
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
            return ['success' => false, 'message' => 'Instale o strongSwan antes de salvar a configuração.'];
        }
        if (!$this->pkiInicializada()) {
            return ['success' => false, 'message' => 'Inicialize a PKI antes de salvar a configuração.'];
        }

        $this->repo->salvarConfig($dados);

        return $this->regenerarEAplicar();
    }

    /**
     * Ponto único de regeneração do ipsec.conf/ipsec.secrets --
     * chamado tanto daqui (mudanças de servidor/cliente) quanto de
     * VpnIkev2SaidaService (mudanças de conexão de saída), porque os
     * dois lados compartilham o mesmo arquivo.
     */
    public function regenerarEAplicar(): array
    {
        $config = $this->repo->config();
        $clientes = $this->repo->listarClientes(true);
        $saidas = (new VpnIkev2SaidaRepository())->listarAtivas();

        $conf = $this->gerarIpsecConf($config, $saidas);
        $secrets = $this->gerarIpsecSecrets($config, $clientes, $saidas);

        $caCerts = [];
        foreach ($saidas as $s) {
            if ($s['tipo_auth'] === 'eap' && !empty($s['ca_remota'])) {
                $caCerts[basename($this->caFilePath($s['nome']))] = $s['ca_remota'];
            }
        }

        return (new IpsecConfigDeployService())->deploy($conf, $secrets, $caCerts);
    }

    private function gerarIpsecConf(array $config, array $saidas): string
    {
        $linhas = [
            'config setup',
            '    uniqueids=no',
            '',
            'conn %default',
            '    keyexchange=ikev2',
            '    dpdaction=clear',
            '    dpddelay=300s',
            '    rekey=no',
        ];

        if (!empty($config['pki_inicializada'])) {
            $cn = trim($config['endpoint_publico'] ?? '') ?: '%any';
            $linhas[] = '';
            $linhas[] = 'conn ikev2-server';
            $linhas[] = '    auto=add';
            $linhas[] = '    left=%any';
            $linhas[] = "    leftid=@{$cn}";
            $linhas[] = "    leftcert={$cn}.crt";
            $linhas[] = '    leftsendcert=always';
            $linhas[] = '    leftsubnet=0.0.0.0/0';
            $linhas[] = '    right=%any';
            $linhas[] = '    rightid=%any';
            $linhas[] = '    rightauth=eap-mschapv2';
            $linhas[] = '    rightsourceip=' . $config['subnet_cidr'];
            if (!empty($config['dns_push'])) {
                $linhas[] = '    rightdns=' . $config['dns_push'];
            }
            $linhas[] = '    eap_identity=%identity';
        }

        foreach ($saidas as $s) {
            $linhas[] = '';
            $linhas[] = "conn saida-{$s['nome']}";
            $linhas[] = '    auto=' . ((int)$s['ativo_no_boot'] === 1 ? 'start' : 'add');
            $linhas[] = '    left=%defaultroute';
            $linhas[] = '    right=' . $s['servidor_remoto'];
            $linhas[] = '    rightsubnet=' . $s['subnet_remota'];

            if ($s['tipo_auth'] === 'eap') {
                $linhas[] = '    leftauth=eap-mschapv2';
                $linhas[] = '    eap_identity=' . $s['usuario_eap'];
                $linhas[] = '    rightauth=pubkey';
                if (!empty($s['ca_remota'])) {
                    $linhas[] = "    rightca=\"{$this->caFilePath($s['nome'])}\"";
                }
            } else {
                $linhas[] = '    leftauth=psk';
                $linhas[] = '    rightauth=psk';
            }
        }

        return implode("\n", $linhas) . "\n";
    }

    private function gerarIpsecSecrets(array $config, array $clientes, array $saidas): string
    {
        $linhas = [];

        if (!empty($config['pki_inicializada'])) {
            $cn = trim($config['endpoint_publico'] ?? '') ?: 'server';
            $linhas[] = ": RSA {$cn}.key";
        }

        foreach ($clientes as $cliente) {
            try {
                $senha = CryptoService::decriptar($cliente['senha']);
            } catch (\Throwable $e) {
                continue;
            }
            $senhaEscapada = str_replace('"', '\\"', $senha);
            $linhas[] = "{$cliente['nome']} : EAP \"{$senhaEscapada}\"";
        }

        foreach ($saidas as $s) {
            try {
                $segredo = CryptoService::decriptar($s['segredo']);
            } catch (\Throwable $e) {
                continue;
            }
            $segredoEscapado = str_replace('"', '\\"', $segredo);

            if ($s['tipo_auth'] === 'eap') {
                $linhas[] = "{$s['usuario_eap']} : EAP \"{$segredoEscapado}\"";
            } else {
                $linhas[] = "%any {$s['servidor_remoto']} : PSK \"{$segredoEscapado}\"";
            }
        }

        return implode("\n", $linhas) . "\n";
    }

    public function caFilePath(string $nomeConexao): string
    {
        return self::CA_DIR . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $nomeConexao) . '.pem';
    }

    /**
     * @return array{success: bool, message: string, cliente_id?: int, usuario?: string, senha?: string}
     */
    public function criarCliente(string $nome, string $senha): array
    {
        $nome = trim($nome);
        if (!preg_match('/^[a-zA-Z0-9_.-]{1,64}$/', $nome)) {
            return ['success' => false, 'message' => 'Nome inválido.'];
        }
        if (!$this->pkiInicializada()) {
            return ['success' => false, 'message' => 'Inicialize a PKI antes de criar clientes.'];
        }
        if ($this->repo->buscarClientePorNome($nome)) {
            return ['success' => false, 'message' => 'Já existe um cliente com este nome.'];
        }

        $senha = trim($senha) !== '' ? $senha : $this->gerarSenhaAleatoria();

        $clienteId = $this->repo->criarCliente($nome, CryptoService::encriptar($senha));

        $resultadoDeploy = $this->regenerarEAplicar();
        if (!$resultadoDeploy['success']) {
            $this->repo->revogarCliente($clienteId);

            return $resultadoDeploy;
        }

        return [
            'success' => true,
            'message' => 'Cliente criado com sucesso.',
            'cliente_id' => $clienteId,
            'usuario' => $nome,
            'senha' => $senha,
        ];
    }

    private function gerarSenhaAleatoria(): string
    {
        return bin2hex(random_bytes(9));
    }

    public function marcarConfigEntregue(int $id): void
    {
        $this->repo->marcarConfigEntregue($id);
    }

    public function revogarCliente(int $id): array
    {
        $this->repo->revogarCliente($id);

        return $this->regenerarEAplicar();
    }

    public function caCertificado(): ?string
    {
        $resultado = (new VpnPkiService())->baixarCa();

        return !empty($resultado['success']) ? $resultado['ca'] : null;
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function exporConexaoInternet(bool $expor): array
    {
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
            $resultado['message'] = 'Portas fechadas, mas precisa confirmar em Infraestrutura > Firewall em até 90s (ou reverte sozinho). ' . ($resultado['message'] ?? '');

            return $resultado;
        }

        if (!empty($existentes)) {
            $this->repo->marcarExposto(true);

            return ['success' => true, 'message' => 'Já estava exposta à internet.'];
        }

        $config = $this->repo->config();
        $base = [
            'protocolo' => 'udp', 'porta_origem' => null,
            'ip_origem' => null, 'ip_destino' => null,
            'interface_entrada' => null, 'interface_saida' => null,
            'nat_destino' => null, 'extra' => null,
            'registrar_log' => false, 'ordem' => 100,
            'origem_template' => self::ORIGEM_TEMPLATE, 'ativo' => true,
        ];

        foreach ([500, 4500] as $porta) {
            $iptablesRepo->criar(array_merge($base, [
                'nome' => "VPN IKEv2 - liberar porta {$porta}",
                'descricao' => 'Portas padrão do protocolo IKEv2/IPsec (negociação + NAT-T).',
                'tabela' => 'filter', 'cadeia' => 'INPUT', 'acao' => 'ACCEPT',
                'porta_destino' => $porta,
            ]));
        }

        $iptablesRepo->criar(array_merge($base, [
            'nome' => 'VPN IKEv2 - encaminhar tráfego',
            'descricao' => 'Permite que os clientes IKEv2 encaminhem tráfego através do servidor.',
            'tabela' => 'filter', 'cadeia' => 'FORWARD', 'acao' => 'ACCEPT',
            'protocolo' => null,
            'ip_origem' => $config['subnet_cidr'],
        ]));

        $iptablesRepo->criar(array_merge($base, [
            'nome' => 'VPN IKEv2 - NAT de saída',
            'descricao' => 'Mascara o tráfego dos clientes IKEv2 para saírem com o IP do servidor.',
            'tabela' => 'nat', 'cadeia' => 'POSTROUTING', 'acao' => 'MASQUERADE',
            'protocolo' => null,
            'ip_origem' => $config['subnet_cidr'],
        ]));

        $resultado = $iptablesService->aplicar();
        if ($resultado['success']) {
            $this->repo->marcarExposto(true);
            $resultado['message'] = 'Portas liberadas, mas precisa confirmar em Infraestrutura > Firewall em até 90s (ou reverte sozinho). ' . ($resultado['message'] ?? '');
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

        $porUsuario = [];
        foreach ($aoVivo['clientes'] as $c) {
            $porUsuario[$c['usuario']] = $c;
        }

        foreach ($this->repo->listarClientes(true) as $cliente) {
            $live = $porUsuario[$cliente['nome']] ?? null;
            if (!$live) continue;

            $this->repo->registrarTrafego((int)$cliente['id'], $live['rx_bytes'], $live['tx_bytes']);
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

    private function validarConfig(array $dados): ?string
    {
        if (!preg_match('#^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/\d{1,2}$#', $dados['subnet_cidr'] ?? '')) {
            return 'Subnet inválida (use o formato 10.10.0.0/24).';
        }

        return null;
    }
}
