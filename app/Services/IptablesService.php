<?php

namespace App\Services;

use App\Repositories\IptablesRegraRepository;

/**
 * Motor do firewall gerenciado. O banco (iptables_regras) e a fonte da
 * verdade; regenerarRuleset() reconstroi o ruleset inteiro (secoes *filter
 * e *nat) a cada mudanca e aplica via iptables-restore, sempre com backup +
 * reversao automatica agendada caso nao seja confirmada -- mesmo padrao ja
 * usado em NetworkConfigService pra configuracao de rede.
 *
 * Regras de protecao sempre presentes (nao ficam no banco, sao injetadas
 * aqui): conexoes ja estabelecidas, loopback, e a(s) porta(s) SSH
 * atualmente configuradas no sshd -- pra esta tela nunca conseguir, sozinha,
 * cortar o proprio acesso de administracao.
 */
class IptablesService
{
    private const SEGUNDOS_ROLLBACK_PADRAO = 90;
    private const PROTOCOLOS_COM_PORTA = ['tcp', 'udp'];

    private IptablesRegraRepository $repo;
    private LinuxService $linux;
    private IptablesExplicadorService $explicador;

    public function __construct()
    {
        $this->repo = new IptablesRegraRepository();
        $this->linux = new LinuxService();
        $this->explicador = new IptablesExplicadorService();
    }

    public function listar(): array
    {
        return $this->repo->listar();
    }

    public function buscar(int $id): ?array
    {
        return $this->repo->buscar($id);
    }

    public function explicar(array $regra): string
    {
        return $this->explicador->explicarRegra($regra);
    }

    public function interfacesValidas(): array
    {
        $saida = shell_exec('ip -o link show 2>/dev/null') ?? '';
        $nomes = [];

        foreach (explode("\n", trim($saida)) as $linha) {
            if (!preg_match('/^\d+:\s+(\S+?):/', $linha, $m)) continue;
            if ($m[1] === 'lo') continue;
            $nomes[] = $m[1];
        }

        return $nomes;
    }

    /**
     * Porta(s) do sshd, direto do arquivo (mundialmente legivel, sem
     * precisar de sudo). "#Port 22" comentado == usa o padrao 22.
     */
    public function portasSshAtuais(): array
    {
        $conteudo = @file_get_contents('/etc/ssh/sshd_config') ?: '';
        $portas = [];

        if (preg_match_all('/^\s*Port\s+(\d+)/mi', $conteudo, $m)) {
            $portas = array_map('intval', $m[1]);
        }

        return $portas ?: [22];
    }

    public function politicaAtual(string $cadeia): string
    {
        $valor = ConfigService::get("iptables_policy_{$cadeia}", 'ACCEPT');

        return in_array($valor, ['ACCEPT', 'DROP'], true) ? $valor : 'ACCEPT';
    }

    public function definirPoliticas(array $politicas): array
    {
        foreach (['INPUT', 'FORWARD', 'OUTPUT'] as $cadeia) {
            if (isset($politicas[$cadeia]) && in_array($politicas[$cadeia], ['ACCEPT', 'DROP'], true)) {
                ConfigService::set("iptables_policy_{$cadeia}", $politicas[$cadeia]);
            }
        }

        return $this->aplicar();
    }

    public function validar(array $d): ?string
    {
        if (trim($d['nome'] ?? '') === '') {
            return 'Informe um nome para a regra.';
        }
        if (!in_array($d['tabela'] ?? '', ['filter', 'nat'], true)) {
            return 'Tabela inválida.';
        }
        if (!in_array($d['cadeia'] ?? '', ['INPUT', 'OUTPUT', 'FORWARD', 'PREROUTING', 'POSTROUTING'], true)) {
            return 'Cadeia inválida.';
        }
        if (!in_array($d['acao'] ?? '', ['ACCEPT', 'DROP', 'REJECT', 'MASQUERADE', 'DNAT', 'SNAT', 'LOG', 'NONE'], true)) {
            return 'Ação inválida.';
        }
        if (!in_array($d['protocolo'] ?? '', ['tcp', 'udp', 'icmp', 'all'], true)) {
            return 'Protocolo inválido.';
        }
        if (!empty($d['porta_destino']) && !$this->validarPorta($d['porta_destino'])) {
            return 'Porta de destino inválida.';
        }
        if (!empty($d['porta_origem']) && !$this->validarPorta($d['porta_origem'])) {
            return 'Porta de origem inválida.';
        }
        if (!empty($d['porta_destino']) && !in_array($d['protocolo'], self::PROTOCOLOS_COM_PORTA, true)) {
            return 'Porta só se aplica a protocolo TCP ou UDP.';
        }
        foreach (['ip_origem', 'ip_destino'] as $campo) {
            if (!empty($d[$campo]) && !$this->validarCidrOuIp($d[$campo])) {
                return 'Endereço IP/CIDR inválido em ' . $campo . '.';
            }
        }
        foreach (['interface_entrada', 'interface_saida'] as $campo) {
            if (!empty($d[$campo]) && !in_array($d[$campo], $this->interfacesValidas(), true)) {
                return 'Interface inválida em ' . $campo . '.';
            }
        }
        if (!empty($d['extra']) && !preg_match('/^[a-zA-Z0-9\s\-\.\_\,\:\/]+$/', $d['extra'])) {
            return 'Campo avançado contém caracteres não permitidos.';
        }
        if (in_array($d['acao'], ['DNAT', 'SNAT'], true)) {
            if (empty($d['nat_destino']) || !preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}(:\d{1,5})?$/', $d['nat_destino'])) {
                return 'Destino do NAT inválido. Use o formato IP ou IP:porta.';
            }
        }

        return null;
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function criar(array $dados): array
    {
        $erro = $this->validar($dados);
        if ($erro !== null) {
            return ['success' => false, 'message' => $erro];
        }

        $this->repo->criar($dados);

        return $this->aplicar();
    }

    public function atualizar(int $id, array $dados): array
    {
        $erro = $this->validar($dados);
        if ($erro !== null) {
            return ['success' => false, 'message' => $erro];
        }

        $this->repo->atualizar($id, $dados);

        return $this->aplicar();
    }

    public function excluir(int $id): array
    {
        $this->repo->excluir($id);

        return $this->aplicar();
    }

    public function alternarAtivo(int $id, bool $ativo): array
    {
        $this->repo->definirAtivo($id, $ativo);

        return $this->aplicar();
    }

    public function mover(int $id, int $delta): array
    {
        $regra = $this->repo->buscar($id);
        if (!$regra) {
            return ['success' => false, 'message' => 'Regra não encontrada.'];
        }

        $this->repo->reordenar($id, (int)$regra['ordem'] + $delta);

        return $this->aplicar();
    }

    /**
     * Aplica um template pre-pronto: valida os parametros conforme o
     * catalogo, cria a(s) regra(s) correspondentes e executa acoes extras
     * (troca de porta do sshd, habilitar ip_forward) antes de aplicar o
     * ruleset -- tudo dentro da mesma janela de confirmacao/rollback.
     */
    public function aplicarTemplate(string $chave, array $parametros): array
    {
        $templates = new IptablesTemplateService();
        $catalogo = $templates->catalogo();

        if (!isset($catalogo[$chave])) {
            return ['success' => false, 'message' => 'Template desconhecido.'];
        }

        foreach ($catalogo[$chave]['campos'] as $campo) {
            $valor = trim((string)($parametros[$campo['nome']] ?? ''));

            if (!empty($campo['obrigatorio']) && $valor === '') {
                return ['success' => false, 'message' => "Campo obrigatório não informado: {$campo['label']}."];
            }
            if ($valor === '' && !empty($campo['padrao'])) {
                $parametros[$campo['nome']] = $campo['padrao'];
                $valor = $campo['padrao'];
            }
            if ($valor === '') continue;

            $erro = match ($campo['tipo']) {
                'interface' => in_array($valor, $this->interfacesValidas(), true) ? null : "Interface inválida: {$campo['label']}.",
                'porta' => $this->validarPorta($valor) ? null : "Porta inválida: {$campo['label']}.",
                'ip' => $this->validarIp($valor) ? null : "IP inválido: {$campo['label']}.",
                'cidr' => $this->validarCidrOuIp($valor) ? null : "IP/rede inválido: {$campo['label']}.",
                'protocolo' => in_array($valor, ['tcp', 'udp'], true) ? null : "Protocolo inválido: {$campo['label']}.",
                'numero' => ctype_digit($valor) ? null : "Valor numérico inválido: {$campo['label']}.",
                default => null,
            };
            if ($erro !== null) {
                return ['success' => false, 'message' => $erro];
            }
        }

        $construido = $templates->construirRegras($chave, $parametros);

        if (empty($construido['regras'])) {
            return ['success' => false, 'message' => 'Não foi possível montar as regras deste template.'];
        }

        foreach ($construido['regras'] as $regra) {
            $regra += ['descricao' => null, 'porta_destino' => null, 'porta_origem' => null,
                'ip_origem' => null, 'ip_destino' => null, 'interface_entrada' => null,
                'interface_saida' => null, 'nat_destino' => null, 'extra' => null, 'registrar_log' => false];
            $this->repo->criar($regra);
        }

        foreach ($construido['acoes_extras'] as $acao) {
            if ($acao['tipo'] === 'sshd_porta') {
                $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/iptables_ssh_porta_web.sh', [(string)$acao['porta']]);
                $dados = json_decode(trim($resultado['output']), true);
                if (!is_array($dados) || empty($dados['success'])) {
                    return ['success' => false, 'message' => 'Falha ao trocar a porta do sshd: ' . ($dados['message'] ?? $resultado['output'])];
                }
            }
            if ($acao['tipo'] === 'ip_forward') {
                $this->linux->executarScript('/opt/rdtecnologia/scripts/iptables_ip_forward_web.sh');
            }
        }

        return $this->aplicar();
    }

    /**
     * Reconstroi o ruleset inteiro a partir do banco (regras ativas +
     * baseline de protecao) e aplica via iptables-restore, com backup e
     * reversao automatica agendada.
     */
    public function aplicar(int $segundosRollback = self::SEGUNDOS_ROLLBACK_PADRAO): array
    {
        $conteudo = $this->gerarConteudo();

        $tmp = tempnam(sys_get_temp_dir(), 'rd_ipt_');
        file_put_contents($tmp, $conteudo);

        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/iptables_aplicar_web.sh',
            [$tmp, (string)$segundosRollback]
        );

        @unlink($tmp);

        $dados = json_decode(trim($resultado['output']), true);
        $sucesso = is_array($dados) ? (bool)($dados['success'] ?? false) : false;
        $mensagem = is_array($dados) ? ($dados['message'] ?? '') : $resultado['output'];

        if ($sucesso) {
            ConfigService::set('iptables_ultimo_apply_em', date('Y-m-d H:i:s'));
            ConfigService::set('iptables_ultimo_apply_erro', '');
        } else {
            ConfigService::set('iptables_ultimo_apply_erro', $mensagem ?: 'Erro desconhecido ao aplicar o firewall.');
        }

        return ['success' => $sucesso, 'message' => $mensagem];
    }

    public function confirmar(): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/iptables_confirmar_web.sh');
        $dados = json_decode(trim($resultado['output']), true);

        return is_array($dados) ? $dados : ['success' => false, 'message' => 'Resposta inesperada: ' . $resultado['output']];
    }

    public function reverterAgora(): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/iptables_rollback_web.sh');
        $dados = json_decode(trim($resultado['output']), true);

        return is_array($dados) ? $dados : ['success' => false, 'message' => 'Resposta inesperada: ' . $resultado['output']];
    }

    public function statusRollback(): array
    {
        $resultado = $this->linux->executar('systemctl is-active rd-iptables-rollback.timer 2>/dev/null');

        if (trim($resultado['output']) !== 'active') {
            return ['pendente' => false, 'segundos_restantes' => 0];
        }

        $deadline = @file_get_contents('/etc/rd-intranet/.iptables-deadline');
        $restante = $deadline !== false ? max(0, (int)trim($deadline) - time()) : 0;

        return ['pendente' => $restante > 0, 'segundos_restantes' => $restante];
    }

    public function ultimoErroApply(): ?string
    {
        $erro = ConfigService::get('iptables_ultimo_apply_erro', '');

        return $erro !== '' ? $erro : null;
    }

    public function ultimoApplyEm(): ?string
    {
        return ConfigService::get('iptables_ultimo_apply_em');
    }

    /**
     * Estado ao vivo do firewall (o que realmente esta rodando no kernel
     * agora), pra tela de "regras atuais" -- inclui coisas fora do controle
     * desta ferramenta (ex: regras do ufw ou feitas na mao).
     */
    public function estadoAoVivo(): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/iptables_status_web.sh');
        $saida = $resultado['output'];

        $secoes = $this->dividirSecoes($saida);

        $regrasAoVivo = [];
        $tabelaAtual = 'filter';
        $indicePorCadeia = [];
        foreach (explode("\n", $secoes['IPTABLES-SAVE'] ?? '') as $linha) {
            $linha = rtrim($linha);
            if ($linha === '' || str_starts_with($linha, '#')) continue;
            if (str_starts_with($linha, '*')) {
                $tabelaAtual = ltrim($linha, '*');
                continue;
            }
            if ($linha === 'COMMIT' || str_starts_with($linha, ':')) continue;

            preg_match('/^-A\s+(\S+)/', $linha, $m);
            $cadeia = $m[1] ?? '?';
            $indicePorCadeia[$tabelaAtual][$cadeia] = ($indicePorCadeia[$tabelaAtual][$cadeia] ?? 0) + 1;

            $regrasAoVivo[] = [
                'tabela' => $tabelaAtual,
                'cadeia' => $cadeia,
                'indice' => $indicePorCadeia[$tabelaAtual][$cadeia],
                'linha' => $linha,
                'explicacao' => $this->explicador->explicarLinha($linha),
            ];
        }

        $ufwStatus = trim($secoes['UFW-STATUS'] ?? '');
        $ufwAtivo = str_contains(strtolower($ufwStatus), 'status: active');

        return [
            'regras' => $regrasAoVivo,
            'ufw_ativo' => $ufwAtivo,
            'ufw_bruto' => $ufwStatus,
            'ip_forward' => trim($secoes['IP-FORWARD'] ?? '0') === '1',
            'ssh_portas' => $this->portasSshAtuais(),
        ];
    }

    /**
     * IPs (e portas/protocolo) que bateram numa regra especifica com
     * "registrar_log" ligado, lendo o log do kernel pelo prefixo unico
     * "RD-FW-<id>:" que a propria regra grava (ver linhasRegra()).
     */
    public function logsRegra(int $id, int $limite = 50): array
    {
        $prefixo = "RD-FW-{$id}:";

        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/iptables_logs_web.sh', [$prefixo]);

        $itens = [];
        foreach (explode("\n", $resultado['output']) as $linha) {
            $linha = trim($linha);
            if ($linha === '' || !str_contains($linha, $prefixo)) continue;

            preg_match('/^(\S+\s+\d+\s+[\d:]+)/', $linha, $mQuando);
            preg_match('/\bSRC=(\S+)/', $linha, $mSrc);
            preg_match('/\bDST=(\S+)/', $linha, $mDst);
            preg_match('/\bPROTO=(\S+)/', $linha, $mProto);
            preg_match('/\bSPT=(\S+)/', $linha, $mSpt);
            preg_match('/\bDPT=(\S+)/', $linha, $mDpt);

            $itens[] = [
                'quando' => $mQuando[1] ?? '',
                'origem' => $mSrc[1] ?? '?',
                'destino' => $mDst[1] ?? '?',
                'protocolo' => $mProto[1] ?? '?',
                'porta_origem' => $mSpt[1] ?? null,
                'porta_destino' => $mDpt[1] ?? null,
            ];
        }

        return array_slice(array_reverse($itens), 0, $limite);
    }

    /**
     * Contadores de pacotes/bytes por regra (iptables -L -v), pra tela de
     * Firewall Ao Vivo mostrar em tempo real quando uma regra esta de fato
     * bloqueando/aceitando trafego -- iptables-save nao traz contadores.
     */
    public function contadores(): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/iptables_contadores_web.sh');
        $secoes = $this->dividirSecoes($resultado['output']);

        return [
            'filter' => $this->parseContadores($secoes['CONTADORES-FILTER'] ?? ''),
            'nat' => $this->parseContadores($secoes['CONTADORES-NAT'] ?? ''),
        ];
    }

    private function parseContadores(string $saida): array
    {
        $cadeias = [];
        $atual = null;

        foreach (explode("\n", $saida) as $linha) {
            $linha = rtrim($linha);

            if (preg_match('/^Chain\s+(\S+)\s+\(policy\s+(\S+)\s+(\d+)\s+packets,\s+(\d+)\s+bytes\)/', $linha, $m)) {
                $atual = $m[1];
                $cadeias[$atual] = [
                    'policy' => $m[2],
                    'policy_pkts' => (int)$m[3],
                    'policy_bytes' => (int)$m[4],
                    'regras' => [],
                ];
                continue;
            }

            if ($atual === null || $linha === '' || str_starts_with(trim($linha), 'num ')) {
                continue;
            }

            if (preg_match('/^(\d+)\s+(\d+)\s+(\d+)\s+(\S*)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s*(.*)$/', $linha, $m)) {
                $cadeias[$atual]['regras'][] = [
                    'num' => (int)$m[1],
                    'pkts' => (int)$m[2],
                    'bytes' => (int)$m[3],
                    'target' => $m[4],
                    'prot' => $m[5],
                    'in' => $m[7],
                    'out' => $m[8],
                    'source' => $m[9],
                    'destination' => $m[10],
                    'extra' => trim($m[11]),
                ];
            }
        }

        return $cadeias;
    }

    private function dividirSecoes(string $saida): array
    {
        $secoes = [];
        $atual = null;

        foreach (explode("\n", $saida) as $linha) {
            if (preg_match('/^===\s*(.+?)\s*===$/', trim($linha), $m)) {
                $atual = $m[1];
                $secoes[$atual] = '';
                continue;
            }
            if ($atual !== null) {
                $secoes[$atual] .= $linha . "\n";
            }
        }

        return $secoes;
    }

    private function gerarConteudo(): string
    {
        $regras = $this->repo->listarAtivas();
        $porTabela = ['filter' => [], 'nat' => []];

        foreach ($regras as $r) {
            $porTabela[$r['tabela']][] = $r;
        }

        $linhas = [];
        $linhas[] = '*filter';
        $linhas[] = ':INPUT ' . $this->politicaAtual('INPUT') . ' [0:0]';
        $linhas[] = ':FORWARD ' . $this->politicaAtual('FORWARD') . ' [0:0]';
        $linhas[] = ':OUTPUT ' . $this->politicaAtual('OUTPUT') . ' [0:0]';

        // baseline de protecao -- sempre primeiro, nunca vem do banco
        $linhas[] = '-A INPUT -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT';
        $linhas[] = '-A INPUT -i lo -j ACCEPT';
        foreach ($this->portasSshAtuais() as $porta) {
            $linhas[] = "-A INPUT -p tcp --dport {$porta} -j ACCEPT";
        }

        foreach ($porTabela['filter'] as $r) {
            array_push($linhas, ...$this->linhasRegra($r));
        }
        $linhas[] = 'COMMIT';

        $linhas[] = '*nat';
        $linhas[] = ':PREROUTING ACCEPT [0:0]';
        $linhas[] = ':INPUT ACCEPT [0:0]';
        $linhas[] = ':OUTPUT ACCEPT [0:0]';
        $linhas[] = ':POSTROUTING ACCEPT [0:0]';
        foreach ($porTabela['nat'] as $r) {
            array_push($linhas, ...$this->linhasRegra($r));
        }
        $linhas[] = 'COMMIT';

        return implode("\n", $linhas) . "\n";
    }

    /**
     * @return string[] uma linha (a regra) ou duas (LOG + a regra, quando
     * registrar_log esta ligado -- LOG nao decide aceitar/bloquear, so
     * registra e deixa o pacote seguir pra proxima linha, que e a regra real
     * com o mesmo match).
     */
    private function linhasRegra(array $r): array
    {
        $match = "-A {$r['cadeia']}";

        if ($r['protocolo'] !== 'all') {
            $match .= " -p {$r['protocolo']}";
        }
        if (!empty($r['interface_entrada'])) {
            $match .= " -i {$r['interface_entrada']}";
        }
        if (!empty($r['interface_saida'])) {
            $match .= " -o {$r['interface_saida']}";
        }
        if (!empty($r['ip_origem'])) {
            $match .= " -s {$r['ip_origem']}";
        }
        if (!empty($r['ip_destino'])) {
            $match .= " -d {$r['ip_destino']}";
        }
        if (!empty($r['porta_destino'])) {
            $match .= " --dport {$r['porta_destino']}";
        }
        if (!empty($r['porta_origem'])) {
            $match .= " --sport {$r['porta_origem']}";
        }
        if (!empty($r['extra'])) {
            $match .= " {$r['extra']}";
        }

        $linha = $match;
        if ($r['acao'] !== 'NONE') {
            $linha .= " -j {$r['acao']}";
            if ($r['acao'] === 'DNAT' && !empty($r['nat_destino'])) {
                $linha .= " --to-destination {$r['nat_destino']}";
            }
            if ($r['acao'] === 'SNAT' && !empty($r['nat_destino'])) {
                $linha .= " --to-source {$r['nat_destino']}";
            }
        }

        if (empty($r['registrar_log'])) {
            return [$linha];
        }

        $prefixo = "RD-FW-{$r['id']}:";
        $linhaLog = "{$match} -j LOG --log-prefix \"{$prefixo} \" --log-level 4";

        return [$linhaLog, $linha];
    }

    private function validarPorta(string $v): bool
    {
        if (preg_match('/^\d+$/', $v)) {
            return (int)$v >= 1 && (int)$v <= 65535;
        }
        return (bool)preg_match('/^\d{1,5}:\d{1,5}$/', $v);
    }

    private function validarIp(string $v): bool
    {
        return (bool)filter_var($v, FILTER_VALIDATE_IP);
    }

    private function validarCidrOuIp(string $v): bool
    {
        if (str_contains($v, '/')) {
            [$ip, $prefixo] = explode('/', $v, 2);
            return $this->validarIp($ip) && ctype_digit($prefixo) && (int)$prefixo <= 32;
        }
        return $this->validarIp($v);
    }
}
