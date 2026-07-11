<?php

namespace App\Services;

use App\Repositories\IptablesRegraRepository;
use PDO;

/**
 * Motor do firewall gerenciado. O banco (iptables_regras) e a fonte da
 * verdade; regenerarRuleset() reconstroi o ruleset inteiro (secoes *filter
 * e *nat) a cada mudanca e aplica via iptables-restore, sempre com backup +
 * reversao automatica agendada caso nao seja confirmada -- mesmo padrao ja
 * usado em NetworkConfigService pra configuracao de rede.
 *
 * Regras de protecao sempre presentes (nao ficam no banco, sao injetadas
 * aqui): conexoes ja estabelecidas, loopback, a(s) porta(s) SSH atualmente
 * configuradas no sshd e a(s) porta(s) do Apache (o proprio painel) -- pra
 * esta tela nunca conseguir, sozinha, cortar o proprio acesso de
 * administracao (nem via SSH nem via navegador).
 */
class IptablesService
{
    private const SEGUNDOS_ROLLBACK_PADRAO = 90;
    private const PROTOCOLOS_COM_PORTA = ['tcp', 'udp'];
    private const CAMPOS_MATCH = ['protocolo', 'porta_destino', 'porta_origem', 'ip_origem', 'ip_destino', 'interface_entrada', 'interface_saida'];
    private const ACOES_TERMINAIS = ['ACCEPT', 'DROP', 'REJECT', 'MASQUERADE', 'DNAT', 'SNAT'];

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

    /**
     * Porta(s) que o Apache (o proprio painel da RD Intranet) escuta,
     * direto do ports.conf (mundialmente legivel). Mesma logica de
     * portasSshAtuais() -- injetada na baseline pra nenhuma alteracao de
     * firewall (nem o Modo Panico) conseguir trancar o admin fora da
     * propria tela que ele usaria pra corrigir o problema.
     */
    public function portasPainelAtuais(): array
    {
        $conteudo = @file_get_contents('/etc/apache2/ports.conf') ?: '';
        $portas = [];

        if (preg_match_all('/^\s*Listen\s+(\d+)/mi', $conteudo, $m)) {
            $portas = array_unique(array_map('intval', $m[1]));
        }

        return $portas ?: [80, 443];
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
     * Aviso best-effort (nao bloqueia, so avisa) de que uma regra parece
     * arriscada -- casos mais comuns de "o admin se tranca fora sozinho".
     * A janela de confirmacao/reversao automatica ja protege contra o pior
     * cenario, isto e so pra avisar ANTES de aplicar.
     */
    public function avaliarRisco(array $d): ?string
    {
        if (($d['cadeia'] ?? '') !== 'INPUT' || !in_array($d['acao'] ?? '', ['DROP', 'REJECT'], true)) {
            return null;
        }

        $avisos = [];
        $portaDestino = trim((string)($d['porta_destino'] ?? ''));

        if ($portaDestino !== '' && in_array((int)$portaDestino, $this->portasSshAtuais(), true) && empty($d['ip_origem'])) {
            $avisos[] = 'Esta regra bloqueia a porta SSH sem restringir a origem — pode derrubar seu próprio acesso remoto (a baseline libera o SSH atual, mas cuidado se ele mudar).';
        }

        if ($portaDestino === '' && empty($d['ip_origem']) && empty($d['interface_entrada'])) {
            $avisos[] = 'Esta regra bloqueia toda a entrada, sem restringir porta, origem ou interface — risco de bloquear acesso ao servidor inteiro (fora do SSH/painel, que ficam sempre liberados).';
        }

        $meuIp = $_SERVER['REMOTE_ADDR'] ?? null;
        if ($meuIp && !empty($d['ip_origem']) && $this->cidrContemIp($d['ip_origem'], $meuIp)) {
            $avisos[] = "Esta regra bloqueia o IP de onde você está acessando agora ({$meuIp}).";
        }

        return $avisos ? implode(' ', $avisos) : null;
    }

    /**
     * Heuristica best-effort (mesmo espirito de IptablesExplicadorService --
     * nao cobre 100% dos casos, cobre os comuns): uma regra B nunca e
     * alcancada se uma regra A, na mesma (tabela, cadeia) e antes dela, tem
     * acao terminal (ACCEPT/DROP/REJECT/MASQUERADE/DNAT/SNAT -- LOG e NONE
     * nao decidem, o pacote segue) e cobre todo campo que B usa (A nao
     * restringe aquele campo, ou restringe com o mesmo valor de B).
     *
     * @return array<int, array{regra_sombreada: array, regra_bloqueadora: array, mensagem: string}>
     */
    public function detectarSombreadas(): array
    {
        $avisos = [];
        $porGrupo = [];

        foreach ($this->repo->listarAtivas() as $r) {
            $porGrupo[$r['tabela']][$r['cadeia']][] = $r;
        }

        foreach ($porGrupo as $porCadeia) {
            foreach ($porCadeia as $cadeia => $regras) {
                foreach ($regras as $i => $b) {
                    foreach (array_slice($regras, 0, $i) as $a) {
                        if (!in_array($a['acao'], self::ACOES_TERMINAIS, true)) {
                            continue;
                        }
                        if ($this->regraCobre($a, $b)) {
                            $avisos[] = [
                                'regra_sombreada' => $b,
                                'regra_bloqueadora' => $a,
                                'mensagem' => "A regra \"{$b['nome']}\" nunca é alcançada porque \"{$a['nome']}\" (antes dela, na cadeia {$cadeia}) já intercepta o mesmo tráfego.",
                            ];
                            break;
                        }
                    }
                }
            }
        }

        return $avisos;
    }

    private function regraCobre(array $a, array $b): bool
    {
        foreach (self::CAMPOS_MATCH as $campo) {
            $valorA = (string)($a[$campo] ?? '');
            if ($campo === 'protocolo' && $valorA === 'all') {
                $valorA = '';
            }
            if ($valorA === '') {
                continue;
            }
            if ($valorA !== (string)($b[$campo] ?? '')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Importa um lote de regras (mesmo formato exportado por
     * IptablesController::exportar()). Aditivo -- nunca apaga as regras
     * existentes. Valida tudo antes de inserir qualquer coisa (tudo ou
     * nada) e aplica uma unica vez no final, nao uma vez por regra.
     *
     * @return array{success: bool, message: string}
     */
    public function importarRegras(array $regras): array
    {
        if (empty($regras)) {
            return ['success' => false, 'message' => 'Nenhuma regra encontrada no arquivo.'];
        }

        foreach ($regras as $i => $d) {
            if (!is_array($d)) {
                return ['success' => false, 'message' => "Item #{$i} do arquivo não é uma regra válida."];
            }

            $erro = $this->validar($d);
            if ($erro !== null) {
                $nome = $d['nome'] ?? '?';
                return ['success' => false, 'message' => "Regra #{$i} (\"{$nome}\"): {$erro}"];
            }
        }

        foreach ($regras as $d) {
            $this->repo->criar($d);
        }

        $resultado = $this->aplicar();
        $resultado['message'] = count($regras) . ' regra(s) importada(s). ' . $resultado['message'];

        return $resultado;
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

    public function panicoAtivo(): bool
    {
        return ConfigService::get('iptables_panico_ativo', '') === '1';
    }

    /**
     * Desativa temporariamente todas as regras cadastradas e forca as
     * cadeias INPUT/FORWARD para DROP -- guarda o estado anterior (quais
     * IDs estavam ativos + politicas) pra desativarPanico() restaurar
     * depois. Passa pelo mesmo aplicar() de sempre, entao a janela de
     * confirmacao/reversao automatica de 90s protege esta acao tambem: se
     * o admin nao confirmar, o firewall anterior volta sozinho. A baseline
     * (SSH + porta do painel web) garante que o proprio admin nao fica
     * trancado fora enquanto decide.
     */
    public function ativarPanico(): array
    {
        if ($this->panicoAtivo()) {
            return ['success' => false, 'message' => 'O modo pânico já está ativo.'];
        }

        $idsAtivos = array_column($this->repo->listarAtivas(), 'id');

        ConfigService::set('iptables_panico_ids_ativos', json_encode($idsAtivos));
        ConfigService::set('iptables_panico_politica_input', $this->politicaAtual('INPUT'));
        ConfigService::set('iptables_panico_politica_forward', $this->politicaAtual('FORWARD'));

        foreach ($idsAtivos as $id) {
            $this->repo->definirAtivo((int)$id, false);
        }

        ConfigService::set('iptables_policy_INPUT', 'DROP');
        ConfigService::set('iptables_policy_FORWARD', 'DROP');

        $resultado = $this->aplicar();

        if ($resultado['success']) {
            ConfigService::set('iptables_panico_ativo', '1');
            $resultado['message'] = 'Modo pânico ativado: só SSH, este painel e conexões já estabelecidas continuam liberados. ' . $resultado['message'];
        }

        return $resultado;
    }

    public function desativarPanico(): array
    {
        if (!$this->panicoAtivo()) {
            return ['success' => false, 'message' => 'O modo pânico não está ativo.'];
        }

        $idsAtivos = json_decode(ConfigService::get('iptables_panico_ids_ativos', '[]'), true) ?: [];

        foreach ($idsAtivos as $id) {
            $this->repo->definirAtivo((int)$id, true);
        }

        ConfigService::set('iptables_policy_INPUT', ConfigService::get('iptables_panico_politica_input', 'ACCEPT'));
        ConfigService::set('iptables_policy_FORWARD', ConfigService::get('iptables_panico_politica_forward', 'ACCEPT'));

        $resultado = $this->aplicar();

        if ($resultado['success']) {
            ConfigService::set('iptables_panico_ativo', '');
        }

        return $resultado;
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
    /**
     * Posicao (1-indexado) que cada regra ativa do banco ocupa dentro da
     * sua (tabela, cadeia) no ruleset de verdade -- espelha exatamente a
     * mesma logica de contagem de linhas que gerarConteudo()/linhasRegra()
     * usam pra escrever o ruleset (baseline antes do INPUT + 2 linhas por
     * regra quando registrar_log esta ligado). Usado pra casar cada regra
     * do banco com o "num" que `iptables -L -v --line-numbers` devolve em
     * contadores().
     *
     * @return array<int, array{id: int, tabela: string, cadeia: string, posicao: int}>
     */
    public function regrasComPosicao(): array
    {
        $porTabela = ['filter' => [], 'nat' => []];
        foreach ($this->repo->listarAtivas() as $r) {
            $porTabela[$r['tabela']][$r['cadeia']][] = $r;
        }

        $baselineInput = 2 + count($this->portasSshAtuais()) + count($this->portasPainelAtuais());
        $offsets = [
            'filter' => ['INPUT' => $baselineInput, 'FORWARD' => 0, 'OUTPUT' => 0],
            'nat' => ['PREROUTING' => 0, 'INPUT' => 0, 'OUTPUT' => 0, 'POSTROUTING' => 0],
        ];

        $resultado = [];
        foreach ($porTabela as $tabela => $porCadeia) {
            foreach ($porCadeia as $cadeia => $lista) {
                $pos = $offsets[$tabela][$cadeia] ?? 0;
                foreach ($lista as $r) {
                    $pos += !empty($r['registrar_log']) ? 2 : 1;
                    $resultado[] = ['id' => (int)$r['id'], 'tabela' => $tabela, 'cadeia' => $cadeia, 'posicao' => $pos];
                }
            }
        }

        return $resultado;
    }

    /**
     * Top regras por pacotes ganhos num periodo, comparando o snapshot
     * mais antigo e o mais recente dentro da janela em
     * iptables_regras_historico. Contador zerado/reiniciado (ex: reboot)
     * geraria delta negativo -- tratado como 0 em vez de deixar o ranking
     * estranho.
     *
     * @return array<int, array{regra_id: int, nome: string, acao: string, pkts_periodo: int}>
     */
    public function topRegrasPorHits(int $horas = 24, int $limite = 10): array
    {
        $pdo = \App\Core\Database::connection();

        $stmt = $pdo->prepare("
            SELECT
                h.regra_id,
                MIN(h.pkts) AS pkts_min,
                MAX(h.pkts) AS pkts_max
            FROM iptables_regras_historico h
            WHERE h.coletado_em >= (NOW() - INTERVAL ? HOUR)
            GROUP BY h.regra_id
        ");
        $stmt->execute([$horas]);
        $deltas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($deltas)) {
            return [];
        }

        $regras = [];
        foreach ($this->repo->listar() as $r) {
            $regras[(int)$r['id']] = $r;
        }

        $resultado = [];
        foreach ($deltas as $d) {
            $regra = $regras[(int)$d['regra_id']] ?? null;
            if ($regra === null) continue;

            $delta = max(0, (int)$d['pkts_max'] - (int)$d['pkts_min']);
            if ($delta === 0) continue;

            $resultado[] = [
                'regra_id' => (int)$d['regra_id'],
                'nome' => $regra['nome'],
                'acao' => $regra['acao'],
                'pkts_periodo' => $delta,
            ];
        }

        usort($resultado, fn($a, $b) => $b['pkts_periodo'] <=> $a['pkts_periodo']);

        return array_slice($resultado, 0, $limite);
    }

    /**
     * Ranking dos IPs de origem que mais apareceram nos eventos de log do
     * firewall (regras com "registrar_log" ligado), coletados por
     * coletar_logs_iptables.php.
     *
     * @return array<int, array{ip_origem: string, eventos: int}>
     */
    public function rankingIpsBloqueados(int $horas = 24, int $limite = 10): array
    {
        $limite = max(1, min($limite, 50));

        $pdo = \App\Core\Database::connection();
        $stmt = $pdo->prepare("
            SELECT ip_origem, COUNT(*) AS eventos
            FROM iptables_log_eventos
            WHERE ocorrido_em >= (NOW() - INTERVAL ? HOUR)
              AND ip_origem IS NOT NULL
            GROUP BY ip_origem
            ORDER BY eventos DESC
            LIMIT {$limite}
        ");
        $stmt->execute([$horas]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

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
        foreach ($this->portasPainelAtuais() as $porta) {
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

    /**
     * So IPv4 (mesmo escopo de validarCidrOuIp). Usado por avaliarRisco()
     * pra saber se um ip_origem de regra bateria no IP de quem esta logado.
     */
    private function cidrContemIp(string $cidr, string $ip): bool
    {
        $ipLong = ip2long($ip);
        if ($ipLong === false) {
            return false;
        }

        if (!str_contains($cidr, '/')) {
            return ip2long($cidr) === $ipLong;
        }

        [$rede, $prefixo] = explode('/', $cidr, 2);
        $redeLong = ip2long($rede);
        if ($redeLong === false || !ctype_digit($prefixo)) {
            return false;
        }

        $mascara = (int)$prefixo === 0 ? 0 : (~0 << (32 - (int)$prefixo));

        return ($ipLong & $mascara) === ($redeLong & $mascara);
    }
}
