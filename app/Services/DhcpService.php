<?php

namespace App\Services;

use App\Repositories\DhcpRepository;

/**
 * Servidor DHCP (Infraestrutura > Servidor DHCP) -- isc-dhcp-server,
 * instalado/configurado/aplicado neste próprio servidor. Mesmo espírito
 * de "serviço de rede gerenciado" já usado por WireGuard/Firewall:
 * config gerada em PHP, aplicada com validação de sintaxe + backup +
 * reversão automática agendada (ver scripts/system/dhcp_aplicar_web.sh).
 *
 * PERIGO REAL, documentado de propósito: só pode existir UM servidor
 * DHCP respondendo por segmento de rede. Ligar este serviço numa rede
 * que já tem outro DHCP (o próprio roteador do cliente, por exemplo)
 * faz os dois brigarem pra responder cada pedido -- resultado
 * imprevisível (parte dos dispositivos pega IP de um, parte do outro,
 * alguns não pegam nada). A tela avisa isso de forma destacada; o
 * código não tem como impedir fisicamente, só avisar com clareza.
 */
class DhcpService
{
    private DhcpRepository $repo;
    private LinuxService $linux;

    private const SEGUNDOS_ROLLBACK_PADRAO = 90;

    public function __construct()
    {
        $this->repo = new DhcpRepository();
        $this->linux = new LinuxService();
    }

    public function config(): array
    {
        return $this->repo->config();
    }

    public function instalado(): bool
    {
        return (bool)($this->repo->config()['instalado'] ?? false);
    }

    /** @return array{success: bool, message: string} */
    public function instalar(): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/dhcp_instalar_web.sh');
        $dados = json_decode(trim($resultado['output']), true);

        if (is_array($dados) && !empty($dados['success'])) {
            $this->repo->marcarInstalado();
        }

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }

    public function interfacesDisponiveis(): array
    {
        return (new NetworkConfigService())->interfacesValidas();
    }

    /** @return array{success: bool, message: string} */
    public function salvarConfig(array $post): array
    {
        $interfacesEscolhidas = array_map('trim', (array)($post['interfaces'] ?? []));
        $interfacesEscolhidas = array_values(array_filter($interfacesEscolhidas, fn ($v) => $v !== ''));

        if (empty($interfacesEscolhidas)) {
            return ['success' => false, 'message' => 'Selecione ao menos uma interface (a física e/ou cada VLAN que deve responder DHCP).'];
        }
        foreach ($interfacesEscolhidas as $iface) {
            if (!in_array($iface, $this->interfacesDisponiveis(), true)) {
                return ['success' => false, 'message' => "Interface inválida: \"{$iface}\"."];
            }
        }
        $interface = implode(' ', $interfacesEscolhidas);

        $leasePadrao = (int)($post['lease_padrao_segundos'] ?? 43200);
        $leaseMaximo = (int)($post['lease_maximo_segundos'] ?? 86400);
        if ($leasePadrao < 60 || $leaseMaximo < $leasePadrao) {
            return ['success' => false, 'message' => 'Tempo de concessão inválido (máximo precisa ser maior ou igual ao padrão).'];
        }

        $this->repo->salvarConfig([
            'interface' => $interface,
            'dominio' => trim($post['dominio'] ?? '') ?: null,
            'dns_primario' => trim($post['dns_primario'] ?? '') ?: null,
            'dns_secundario' => trim($post['dns_secundario'] ?? '') ?: null,
            'lease_padrao_segundos' => $leasePadrao,
            'lease_maximo_segundos' => $leaseMaximo,
        ]);

        AuditService::registrar('Infraestrutura', 'Servidor DHCP', "Configuração geral salva (interfaces: {$interface}).");

        return ['success' => true, 'message' => 'Configuração salva -- clique em "Aplicar" pra ativar de verdade.'];
    }

    // ── Subnets ──────────────────────────────────────────────────────────

    public function listarSubnets(): array
    {
        return $this->repo->listarSubnets();
    }

    /** @return array{success: bool, message: string} */
    public function salvarSubnet(array $post): array
    {
        $id = (int)($post['id'] ?? 0);
        $nome = trim($post['nome'] ?? '');
        $rede = trim($post['rede'] ?? '');
        $mascara = trim($post['mascara'] ?? '');
        $inicio = trim($post['faixa_inicio'] ?? '');
        $fim = trim($post['faixa_fim'] ?? '');
        $gateway = trim($post['gateway'] ?? '') ?: null;

        if ($nome === '') {
            return ['success' => false, 'message' => 'Informe um nome pra identificar esta rede.'];
        }
        foreach (['rede' => $rede, 'máscara' => $mascara, 'início da faixa' => $inicio, 'fim da faixa' => $fim] as $rotulo => $valor) {
            if (!filter_var($valor, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return ['success' => false, 'message' => "Endereço de {$rotulo} inválido: \"{$valor}\"."];
            }
        }
        if ($gateway !== null && !filter_var($gateway, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return ['success' => false, 'message' => "Gateway inválido: \"{$gateway}\"."];
        }

        if (ip2long($inicio) > ip2long($fim)) {
            return ['success' => false, 'message' => 'O início da faixa precisa vir antes do fim.'];
        }

        foreach (['início da faixa' => $inicio, 'fim da faixa' => $fim] as $rotulo => $valor) {
            if (!$this->ipPertenceARede($valor, $rede, $mascara)) {
                return ['success' => false, 'message' => "O {$rotulo} ({$valor}) não pertence à rede {$rede}/{$mascara}."];
            }
        }

        $dados = compact('nome', 'rede', 'mascara', 'gateway') + ['faixa_inicio' => $inicio, 'faixa_fim' => $fim];

        if ($id > 0) {
            $this->repo->atualizarSubnet($id, $dados);
            AuditService::registrar('Infraestrutura', 'Servidor DHCP', "Rede \"{$nome}\" atualizada.");
        } else {
            $this->repo->criarSubnet($dados);
            AuditService::registrar('Infraestrutura', 'Servidor DHCP', "Rede \"{$nome}\" criada ({$rede}/{$mascara}).");
        }

        return ['success' => true, 'message' => 'Rede salva -- clique em "Aplicar" pra ativar de verdade.'];
    }

    public function excluirSubnet(int $id): array
    {
        $subnet = $this->repo->buscarSubnet($id);
        if (!$subnet) {
            return ['success' => false, 'message' => 'Rede não encontrada.'];
        }

        $this->repo->excluirSubnet($id);
        AuditService::registrar('Infraestrutura', 'Servidor DHCP', "Rede \"{$subnet['nome']}\" removida (e reservas associadas).");

        return ['success' => true, 'message' => 'Rede removida -- clique em "Aplicar" pra ativar de verdade.'];
    }

    // ── Reservas ─────────────────────────────────────────────────────────

    public function listarReservas(): array
    {
        return $this->repo->listarReservas();
    }

    /** @return array{success: bool, message: string} */
    public function criarReserva(array $post): array
    {
        $subnetId = (int)($post['subnet_id'] ?? 0);
        $mac = strtolower(trim($post['mac_address'] ?? ''));
        $ip = trim($post['ip'] ?? '');
        $descricao = trim($post['descricao'] ?? '');

        $subnet = $this->repo->buscarSubnet($subnetId);
        if (!$subnet) {
            return ['success' => false, 'message' => 'Selecione uma rede válida.'];
        }
        if ($descricao === '') {
            return ['success' => false, 'message' => 'Informe uma descrição (ex: nome do equipamento).'];
        }
        if (!preg_match('/^([0-9a-f]{2}:){5}[0-9a-f]{2}$/', $mac)) {
            return ['success' => false, 'message' => 'Endereço MAC inválido -- use o formato aa:bb:cc:dd:ee:ff.'];
        }
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return ['success' => false, 'message' => 'IP inválido.'];
        }
        if (!$this->ipPertenceARede($ip, $subnet['rede'], $subnet['mascara'])) {
            return ['success' => false, 'message' => "O IP {$ip} não pertence à rede {$subnet['nome']} ({$subnet['rede']}/{$subnet['mascara']})."];
        }

        $conflito = $this->repo->buscarReservaPorMacOuIp($mac, $ip);
        if ($conflito) {
            return ['success' => false, 'message' => "Já existe uma reserva com esse MAC ou IP (\"{$conflito['descricao']}\")."];
        }

        $this->repo->criarReserva(['subnet_id' => $subnetId, 'mac_address' => $mac, 'ip' => $ip, 'descricao' => $descricao]);
        AuditService::registrar('Infraestrutura', 'Servidor DHCP', "Reserva criada: {$descricao} ({$mac} -> {$ip}).");

        return ['success' => true, 'message' => 'Reserva criada -- clique em "Aplicar" pra ativar de verdade.'];
    }

    public function excluirReserva(int $id): array
    {
        $this->repo->excluirReserva($id);
        AuditService::registrar('Infraestrutura', 'Servidor DHCP', "Reserva #{$id} removida.");

        return ['success' => true, 'message' => 'Reserva removida -- clique em "Aplicar" pra ativar de verdade.'];
    }

    private function ipPertenceARede(string $ip, string $rede, string $mascara): bool
    {
        return (ip2long($ip) & ip2long($mascara)) === (ip2long($rede) & ip2long($mascara));
    }

    // ── Geração de config / aplicação segura ────────────────────────────

    private function gerarConteudoDhcpd(): string
    {
        $config = $this->repo->config();
        $subnets = $this->repo->listarSubnets();
        $reservas = $this->repo->listarReservas();

        $linhas = [
            '# Gerado pela RD Intranet (Infraestrutura > Servidor DHCP). Não edite manualmente.',
            'authoritative;',
            'default-lease-time ' . (int)$config['lease_padrao_segundos'] . ';',
            'max-lease-time ' . (int)$config['lease_maximo_segundos'] . ';',
        ];

        if (!empty($config['dominio'])) {
            $linhas[] = 'option domain-name "' . $config['dominio'] . '";';
        }

        $dnsServidores = array_filter([$config['dns_primario'] ?? null, $config['dns_secundario'] ?? null]);
        if (!empty($dnsServidores)) {
            $linhas[] = 'option domain-name-servers ' . implode(', ', $dnsServidores) . ';';
        }

        foreach ($subnets as $subnet) {
            $linhas[] = '';
            $linhas[] = "# {$subnet['nome']}";
            $linhas[] = "subnet {$subnet['rede']} netmask {$subnet['mascara']} {";
            $linhas[] = "    range {$subnet['faixa_inicio']} {$subnet['faixa_fim']};";
            if (!empty($subnet['gateway'])) {
                $linhas[] = "    option routers {$subnet['gateway']};";
            }
            $linhas[] = '}';
        }

        if (!empty($reservas)) {
            $linhas[] = '';
            $linhas[] = '# Reservas (IP fixo por MAC)';
            foreach ($reservas as $reserva) {
                $identificador = 'reserva-' . str_replace(':', '', $reserva['mac_address']);
                $linhas[] = "host {$identificador} {";
                $linhas[] = "    # {$reserva['descricao']}";
                $linhas[] = "    hardware ethernet {$reserva['mac_address']};";
                $linhas[] = "    fixed-address {$reserva['ip']};";
                $linhas[] = '}';
            }
        }

        return implode("\n", $linhas) . "\n";
    }

    /**
     * Escreve a config gerada e aplica de verdade (reinicia o serviço),
     * com validação de sintaxe antes e reversão automática agendada
     * depois -- só fica permanente se confirmar() for chamado a tempo.
     * @return array{success: bool, message: string}
     */
    public function aplicar(int $segundosRollback = self::SEGUNDOS_ROLLBACK_PADRAO): array
    {
        $config = $this->repo->config();
        $interface = $config['interface'] ?? '';

        if ($interface === '' || $interface === null) {
            return ['success' => false, 'message' => 'Selecione e salve uma interface antes de aplicar.'];
        }
        if (empty($this->repo->listarSubnets())) {
            return ['success' => false, 'message' => 'Cadastre pelo menos uma rede (subnet) antes de aplicar.'];
        }

        $conteudo = $this->gerarConteudoDhcpd();
        $tmp = tempnam(sys_get_temp_dir(), 'rd_dhcp_');
        file_put_contents($tmp, $conteudo);

        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/dhcp_aplicar_web.sh',
            [$tmp, $interface, (string)$segundosRollback]
        );

        @unlink($tmp);

        $dados = json_decode(trim($resultado['output']), true);
        $sucesso = is_array($dados) ? (bool)($dados['success'] ?? false) : false;
        $mensagem = is_array($dados) ? ($dados['message'] ?? '') : $resultado['output'];

        if ($sucesso) {
            $this->repo->marcarServicoAtivo(true);
            AuditService::registrar('Infraestrutura', 'Servidor DHCP', "Configuração aplicada na interface {$interface}.");
        }

        return ['success' => $sucesso, 'message' => $mensagem];
    }

    public function confirmar(): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/dhcp_confirmar_web.sh');
        $dados = json_decode(trim($resultado['output']), true);

        if (is_array($dados) && !empty($dados['success'])) {
            AuditService::registrar('Infraestrutura', 'Servidor DHCP', 'Alteração confirmada.');
        }

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }

    public function reverterAgora(): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/dhcp_rollback_web.sh');
        $dados = json_decode(trim($resultado['output']), true);

        if (is_array($dados) && !empty($dados['success'])) {
            AuditService::registrar('Infraestrutura', 'Servidor DHCP', 'Configuração revertida manualmente.');
        }

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }

    /** Mesmo padrão de IptablesService::statusRollback() -- consultado por polling na tela. */
    public function statusRollback(): array
    {
        $resultado = $this->linux->executar('systemctl is-active rd-dhcp-rollback.timer 2>/dev/null');

        if (trim($resultado['output']) !== 'active') {
            return ['pendente' => false, 'segundos_restantes' => 0];
        }

        $deadline = @file_get_contents('/etc/rd-intranet/.dhcp-deadline');
        $restantes = $deadline !== false ? max(0, (int)trim($deadline) - time()) : 0;

        return ['pendente' => true, 'segundos_restantes' => $restantes];
    }

    /** @return array{servico_ativo: bool, leases: array} */
    public function statusAoVivo(): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/dhcp_status_web.sh');

        $servicoAtivo = false;
        $leases = [];

        foreach (explode("\n", $resultado['output'] ?? '') as $linha) {
            $partes = explode('|', trim($linha));

            if ($partes[0] === 'SERVICO') {
                $servicoAtivo = ($partes[1] ?? '') === 'ativo';
            } elseif ($partes[0] === 'LEASE') {
                $leases[] = [
                    'ip' => $partes[1] ?? '',
                    'mac' => $partes[2] ?? '',
                    'hostname' => $partes[3] ?? '',
                    'expira_em' => $partes[4] ?? '',
                ];
            }
        }

        return ['servico_ativo' => $servicoAtivo, 'leases' => $leases];
    }

    public function ligar(): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/dhcp_ligar_web.sh');
        $dados = json_decode(trim($resultado['output']), true);

        if (is_array($dados) && !empty($dados['success'])) {
            $this->repo->marcarServicoAtivo(true);
            AuditService::registrar('Infraestrutura', 'Servidor DHCP', 'Serviço ligado.');
        }

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }

    public function desligar(): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/dhcp_desligar_web.sh');
        $dados = json_decode(trim($resultado['output']), true);

        if (is_array($dados) && !empty($dados['success'])) {
            $this->repo->marcarServicoAtivo(false);
            AuditService::registrar('Infraestrutura', 'Servidor DHCP', 'Serviço desligado.');
        }

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }
}
