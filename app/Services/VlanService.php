<?php

namespace App\Services;

use App\Repositories\VlanRepository;

/**
 * Infraestrutura > VLANs -- cria sub-interfaces 802.1Q (netplan) neste
 * próprio servidor, cada uma virando o "gateway" de uma rede/VLAN.
 *
 * Nasceu de um caso real de emergência: o gateway UniFi do cliente (que
 * fazia roteamento entre VLANs + DHCP) parou de funcionar, e este
 * servidor precisou assumir as duas funções até o equipamento ser
 * trocado. Este módulo cuida só da "perna" de cada VLAN (a interface e o
 * IP nela); o roteamento entre VLANs (ip_forward) e o compartilhamento
 * de internet (NAT/masquerade) já existem prontos em Infraestrutura >
 * Firewall > Templates -- não duplicado aqui de propósito. O Servidor
 * DHCP (outro módulo) escuta em cima das interfaces criadas aqui.
 *
 * Mesmo padrão de "serviço de rede gerenciado" do resto do projeto:
 * config gerada em PHP, aplicada com validação + backup + reversão
 * automática agendada (ver scripts/system/vlan_aplicar_web.sh) -- mesmo
 * risco do editor de Interfaces (Infraestrutura > Network): uma VLAN mal
 * configurada pode derrubar o próprio acesso ao servidor.
 */
class VlanService
{
    private VlanRepository $repo;
    private LinuxService $linux;

    private const SEGUNDOS_ROLLBACK_PADRAO = 90;
    private const PASSO_SUGESTAO_ID = 10;

    public function __construct()
    {
        $this->repo = new VlanRepository();
        $this->linux = new LinuxService();
    }

    /** Interfaces físicas/trunk válidas como "pai" de uma VLAN -- exclui sub-interfaces de VLAN já existentes (evita empilhar tag em cima de tag). */
    public function interfacesFisicasDisponiveis(): array
    {
        return array_values(array_filter(
            (new NetworkConfigService())->interfacesValidas(),
            fn (string $nome) => !str_contains($nome, '.')
        ));
    }

    /** @return array<int, array<string, mixed>> cada VLAN + rede/broadcast calculados + se já está aplicada de verdade na interface ao vivo. */
    public function listar(): array
    {
        $aoVivo = $this->enderecosAoVivo();

        return array_map(function (array $vlan) use ($aoVivo) {
            $vlan['rede'] = $this->enderecoRede($vlan['ip_endereco'], (int)$vlan['prefixo']);
            $vlan['cidr'] = $vlan['ip_endereco'] . '/' . $vlan['prefixo'];
            $nomeInterface = $vlan['interface_pai'] . '.' . $vlan['vlan_id'];
            $vlan['interface'] = $nomeInterface;
            $vlan['aplicada'] = in_array($vlan['cidr'], $aoVivo[$nomeInterface] ?? [], true);

            return $vlan;
        }, $this->repo->listar());
    }

    /** Primeiro ID de VLAN livre pra essa interface, sugerindo de 10 em 10 (convenção comum: 10, 20, 30...). */
    public function proximoIdSugerido(string $interfacePai): int
    {
        $usados = $this->repo->idsUsados($interfacePai);

        for ($id = self::PASSO_SUGESTAO_ID; $id <= 4090; $id += self::PASSO_SUGESTAO_ID) {
            if (!in_array($id, $usados, true)) {
                return $id;
            }
        }

        for ($id = 2; $id <= 4094; $id++) {
            if (!in_array($id, $usados, true)) {
                return $id;
            }
        }

        return 2;
    }

    /** @return array{success: bool, message: string} */
    public function salvar(array $post): array
    {
        $id = (int)($post['id'] ?? 0);
        $nome = trim($post['nome'] ?? '');
        $interfacePai = trim($post['interface_pai'] ?? '');
        $vlanId = (int)($post['vlan_id'] ?? 0);
        $ip = trim($post['ip_endereco'] ?? '');
        $prefixo = (int)($post['prefixo'] ?? 24);
        $descricao = trim($post['descricao'] ?? '') ?: null;

        if ($nome === '') {
            return ['success' => false, 'message' => 'Informe um nome pra identificar esta VLAN.'];
        }
        if (!in_array($interfacePai, $this->interfacesFisicasDisponiveis(), true)) {
            return ['success' => false, 'message' => 'Selecione uma interface física válida.'];
        }
        if ($vlanId < 1 || $vlanId > 4094) {
            return ['success' => false, 'message' => 'ID de VLAN inválido -- use um valor entre 1 e 4094.'];
        }
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return ['success' => false, 'message' => "Endereço IP inválido: \"{$ip}\"."];
        }
        if ($prefixo < 1 || $prefixo > 32) {
            return ['success' => false, 'message' => 'Prefixo (máscara) inválido -- use um valor entre 1 e 32.'];
        }
        if ($prefixo < 31) {
            $rede = $this->enderecoRede($ip, $prefixo);
            $broadcast = $this->enderecoBroadcast($ip, $prefixo);
            if ($ip === $rede || $ip === $broadcast) {
                return ['success' => false, 'message' => "O IP {$ip} é o endereço de rede ou de broadcast de {$rede}/{$prefixo} -- escolha um IP de host válido (ex: {$rede} com o último octeto trocado)."];
            }
        }

        $conflitoId = $this->repo->buscarPorInterfaceVlanId($interfacePai, $vlanId, $id ?: null);
        if ($conflitoId) {
            return ['success' => false, 'message' => "Já existe a VLAN \"{$conflitoId['nome']}\" com esse mesmo ID ({$vlanId}) na interface {$interfacePai}."];
        }

        $conflitoIp = $this->repo->buscarPorIp($ip, $id ?: null);
        if ($conflitoIp) {
            return ['success' => false, 'message' => "O IP {$ip} já está em uso pela VLAN \"{$conflitoIp['nome']}\"."];
        }

        $dados = [
            'nome' => $nome,
            'interface_pai' => $interfacePai,
            'vlan_id' => $vlanId,
            'ip_endereco' => $ip,
            'prefixo' => $prefixo,
            'descricao' => $descricao,
            'ativo' => 1,
        ];

        if ($id > 0) {
            $this->repo->atualizar($id, $dados);
            AuditService::registrar('Infraestrutura', 'VLANs', "VLAN \"{$nome}\" atualizada ({$interfacePai}.{$vlanId}).");
        } else {
            $this->repo->criar($dados);
            AuditService::registrar('Infraestrutura', 'VLANs', "VLAN \"{$nome}\" criada ({$interfacePai}.{$vlanId}, {$ip}/{$prefixo}).");
        }

        return ['success' => true, 'message' => 'VLAN salva -- clique em "Aplicar" pra criar a interface de verdade.'];
    }

    public function excluir(int $id): array
    {
        $vlan = $this->repo->buscar($id);
        if (!$vlan) {
            return ['success' => false, 'message' => 'VLAN não encontrada.'];
        }

        $this->repo->excluir($id);
        AuditService::registrar('Infraestrutura', 'VLANs', "VLAN \"{$vlan['nome']}\" removida ({$vlan['interface_pai']}.{$vlan['vlan_id']}).");

        return ['success' => true, 'message' => 'VLAN removida -- clique em "Aplicar" pra remover a interface de verdade.'];
    }

    // ── Geração de config / aplicação segura ────────────────────────────

    private function gerarConteudoNetplan(): ?string
    {
        $vlans = $this->repo->listarAtivas();
        if (empty($vlans)) {
            return null;
        }

        $linhas = [
            '# Gerado pela RD Intranet (Infraestrutura > VLANs). Não edite manualmente.',
            'network:',
            '  version: 2',
            '  vlans:',
        ];

        foreach ($vlans as $vlan) {
            $nomeInterface = $vlan['interface_pai'] . '.' . $vlan['vlan_id'];
            $linhas[] = "    {$nomeInterface}:";
            $linhas[] = "      id: {$vlan['vlan_id']}";
            $linhas[] = "      link: {$vlan['interface_pai']}";
            $linhas[] = "      addresses: [{$vlan['ip_endereco']}/{$vlan['prefixo']}]";
        }

        return implode("\n", $linhas) . "\n";
    }

    /**
     * Escreve a config gerada (ou remove, se não sobrar nenhuma VLAN ativa)
     * e aplica de verdade via netplan, com validação de sintaxe antes e
     * reversão automática agendada depois -- só fica permanente se
     * confirmar() for chamado a tempo. Mesmo risco de "se cortar" que o
     * editor de Interfaces: uma VLAN errada na interface que você está
     * usando pra acessar o servidor pode derrubar seu próprio acesso.
     * @return array{success: bool, message: string}
     */
    public function aplicar(int $segundosRollback = self::SEGUNDOS_ROLLBACK_PADRAO): array
    {
        $interfacesValidas = (new NetworkConfigService())->interfacesValidas();
        foreach ($this->repo->listarAtivas() as $vlan) {
            if (!in_array($vlan['interface_pai'], $interfacesValidas, true)) {
                return ['success' => false, 'message' => "A interface \"{$vlan['interface_pai']}\" usada pela VLAN \"{$vlan['nome']}\" não existe mais nesta máquina -- corrija ou remova essa VLAN antes de aplicar."];
            }
        }

        $conteudo = $this->gerarConteudoNetplan();
        $tmp = '-';
        if ($conteudo !== null) {
            $tmp = tempnam(sys_get_temp_dir(), 'rd_vlan_');
            file_put_contents($tmp, $conteudo);
        }

        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/vlan_aplicar_web.sh',
            [$tmp, (string)$segundosRollback]
        );

        if ($tmp !== '-') {
            @unlink($tmp);
        }

        $dados = json_decode(trim($resultado['output']), true);
        $sucesso = is_array($dados) ? (bool)($dados['success'] ?? false) : false;
        $mensagem = is_array($dados) ? ($dados['message'] ?? '') : $resultado['output'];

        if ($sucesso) {
            AuditService::registrar('Infraestrutura', 'VLANs', 'Configuração de VLANs aplicada.');
        }

        return ['success' => $sucesso, 'message' => $mensagem];
    }

    public function confirmar(): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/vlan_confirmar_web.sh');
        $dados = json_decode(trim($resultado['output']), true);

        if (is_array($dados) && !empty($dados['success'])) {
            AuditService::registrar('Infraestrutura', 'VLANs', 'Alteração de VLANs confirmada.');
        }

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }

    public function reverterAgora(): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/vlan_rollback_web.sh');
        $dados = json_decode(trim($resultado['output']), true);

        if (is_array($dados) && !empty($dados['success'])) {
            AuditService::registrar('Infraestrutura', 'VLANs', 'Configuração de VLANs revertida manualmente.');
        }

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }

    /** Mesmo padrão de NetworkConfigService::statusRollback() -- consultado por polling na tela. */
    public function statusRollback(): array
    {
        $resultado = $this->linux->executar('systemctl is-active rd-vlan-rollback.timer 2>/dev/null');

        if (trim($resultado['output']) !== 'active') {
            return ['pendente' => false, 'segundos_restantes' => 0];
        }

        $deadline = @file_get_contents('/etc/rd-intranet/.vlan-deadline');
        $restante = $deadline !== false ? max(0, (int)trim($deadline) - time()) : 0;

        return ['pendente' => $restante > 0, 'segundos_restantes' => $restante];
    }

    /** Mapa interface => [cidr, cidr...] lido AO VIVO (ip -o -4 addr show), pra saber quais VLANs cadastradas já estão de fato ativas na máquina. */
    private function enderecosAoVivo(): array
    {
        $saida = $this->linux->executar('ip -o -4 addr show 2>/dev/null')['output'] ?? '';
        $mapa = [];

        foreach (explode("\n", trim($saida)) as $linha) {
            if (!preg_match('/^\d+:\s+(\S+)\s+inet\s+(\S+)/', trim($linha), $m)) {
                continue;
            }
            $iface = explode('@', $m[1])[0];
            $mapa[$iface][] = $m[2];
        }

        return $mapa;
    }

    private function enderecoRede(string $ip, int $prefixo): string
    {
        $mascaraLong = $prefixo === 0 ? 0 : (-1 << (32 - $prefixo));
        return long2ip(ip2long($ip) & $mascaraLong);
    }

    private function enderecoBroadcast(string $ip, int $prefixo): string
    {
        $mascaraLong = $prefixo === 0 ? 0 : (-1 << (32 - $prefixo));
        return long2ip((ip2long($ip) & $mascaraLong) | (~$mascaraLong));
    }
}
