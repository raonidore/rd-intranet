<?php

namespace App\Services;

/**
 * Integração com a API do UniFi Network Controller (Ubiquiti) -- usada pra
 * coletar dados de pontos de acesso (modelo, firmware, status, rádios,
 * clientes conectados) sem depender de SNMP, que se provou não-funcional
 * nessa linha de APs (config chega no equipamento via API, mas o daemon
 * SNMP nunca sobe na porta 161, confirmado em 2 modelos diferentes).
 *
 * Autenticação: API Key local do Controller (header X-API-KEY), gerada em
 * Configurações > Integração/Integrations no próprio Controller -- não
 * usa a sessão SSO+MFA (essa exige humano digitando código a cada login,
 * inviável pra automação). Confirmado ao vivo antes de escrever esta
 * classe: X-API-KEY funciona contra os endpoints REST em /proxy/network/
 * (tanto os novos /integration/v1/* quanto os legados /api/s/{site}/*).
 *
 * O Controller é local (mesma rede da instalação, certificado
 * autoassinado) -- por isso CURLOPT_SSL_VERIFYPEER/VERIFYHOST ficam
 * desligados aqui, mesmo risco que o próprio app oficial da Ubiquiti
 * assume nessa mesma rede.
 */
class UnifiService
{
    private const CHAVE_URL = 'unifi_url';
    private const CHAVE_API_KEY_CIFRADA = 'unifi_api_key_cifrada';
    private const CHAVE_SITE_ID = 'unifi_site_id';
    private const CHAVE_SITE_REF = 'unifi_site_ref';

    private const LIMITE_PAGINA = 200;

    public function configurado(): bool
    {
        return $this->urlAtual() !== ''
            && (ConfigService::get(self::CHAVE_API_KEY_CIFRADA, '') ?: '') !== '';
    }

    public function urlAtual(): string
    {
        return trim((string)(ConfigService::get(self::CHAVE_URL, '') ?: ''));
    }

    public function siteIdAtual(): ?string
    {
        $id = trim((string)(ConfigService::get(self::CHAVE_SITE_ID, '') ?: ''));

        return $id !== '' ? $id : null;
    }

    /** Identificador curto do site (ex: "default") -- usado nos endpoints legados /api/s/{ref}/*, diferente do UUID usado em /integration/v1/sites/{id}/*. */
    public function siteRefAtual(): ?string
    {
        $ref = trim((string)(ConfigService::get(self::CHAVE_SITE_REF, '') ?: ''));

        return $ref !== '' ? $ref : null;
    }

    public function salvarConfiguracao(string $url, string $apiKey): bool
    {
        $url = rtrim(trim($url), '/');
        $apiKey = trim($apiKey);

        if ($url === '') {
            NotificationService::error('Informe a URL do UniFi Controller.');
            return false;
        }

        // API key em branco mantém a atual -- só exige uma chave nova quando ainda não havia nenhuma.
        if ($apiKey === '' && !$this->configurado()) {
            NotificationService::error('Informe a API Key.');
            return false;
        }

        ConfigService::set(self::CHAVE_URL, $url);

        if ($apiKey !== '') {
            ConfigService::set(self::CHAVE_API_KEY_CIFRADA, CryptoService::encriptar($apiKey));
            // Chave nova -- o site resolvido pela chave anterior pode não valer mais (ou nem existir), então limpa e força um novo "Testar conexão".
            ConfigService::set(self::CHAVE_SITE_ID, '');
        }

        AuditService::registrar('UniFi Controller', 'Configuração', "URL do Controller atualizada ({$url}).");
        NotificationService::success('Configuração salva -- use "Testar conexão" para validar e identificar o site.');

        return true;
    }

    public function removerConfiguracao(): void
    {
        ConfigService::set(self::CHAVE_URL, '');
        ConfigService::set(self::CHAVE_API_KEY_CIFRADA, '');
        ConfigService::set(self::CHAVE_SITE_ID, '');

        AuditService::registrar('UniFi Controller', 'Configuração', 'Configuração removida.');
        NotificationService::success('Configuração removida.');
    }

    private function apiKeyAtual(): ?string
    {
        $cifrada = ConfigService::get(self::CHAVE_API_KEY_CIFRADA, '');

        if (!$cifrada) {
            return null;
        }

        try {
            return CryptoService::decriptar($cifrada);
        } catch (\RuntimeException $e) {
            return null;
        }
    }

    /**
     * Valida a API key contra o Controller e identifica o site (a grande
     * maioria das instalações tem só um, "Default" -- por isso pega o
     * primeiro da lista em vez de pedir o ID pro usuário digitar).
     *
     * @return array{success:bool, message:string}
     */
    public function testarConexao(): array
    {
        $resultado = $this->chamarApi('GET', '/proxy/network/integration/v1/sites');

        if (!$resultado['sucesso']) {
            return ['success' => false, 'message' => 'Falha ao conectar: ' . $resultado['mensagem']];
        }

        $sites = $resultado['dados']['data'] ?? [];

        if (empty($sites)) {
            return ['success' => false, 'message' => 'Conectou no Controller, mas nenhum site foi encontrado.'];
        }

        $site = $sites[0];
        ConfigService::set(self::CHAVE_SITE_ID, (string)$site['id']);
        // "internalReference" (ex: "default") é o identificador curto que os endpoints legados /api/s/{ref}/* esperam -- diferente do UUID usado em /integration/v1/sites/{id}/*.
        ConfigService::set(self::CHAVE_SITE_REF, (string)($site['internalReference'] ?? 'default'));

        AuditService::registrar('UniFi Controller', 'Testar conexão', "Conexão validada -- site \"{$site['name']}\" ({$site['id']}).");

        return ['success' => true, 'message' => "Conectado com sucesso -- site \"{$site['name']}\" identificado e salvo."];
    }

    /** Dispositivos (APs, switches, gateway) do site configurado -- id, nome, modelo, mac, ip, firmware, estado. */
    public function listarDispositivos(): array
    {
        $siteId = $this->siteIdAtual();

        if ($siteId === null) {
            NotificationService::error('Site do UniFi Controller ainda não identificado -- use "Testar conexão" em Integrações > UniFi.');
            return [];
        }

        return $this->paginar("/proxy/network/integration/v1/sites/{$siteId}/devices");
    }

    /** Detalhe de um dispositivo específico -- traz o array de rádios (canal/largura/padrão) e adoptedAt, que não vêm na listagem. */
    public function buscarDetalheDispositivo(string $deviceId): ?array
    {
        $siteId = $this->siteIdAtual();

        if ($siteId === null) {
            return null;
        }

        $resultado = $this->chamarApi('GET', "/proxy/network/integration/v1/sites/{$siteId}/devices/" . rawurlencode($deviceId));

        return $resultado['sucesso'] ? $resultado['dados'] : null;
    }

    /**
     * Todos os dispositivos via API LEGADA (/api/s/{ref}/stat/device) --
     * usada só como fallback de casamento por IP: dispositivos gateway
     * reportam em /integration/v1 o IP do WAN (o que faz sentido pro
     * Controller, mas não é o IP de gerenciamento na LAN que a gente
     * cadastra em Ativos). A API legada expõe `port_table[].ip` por
     * porta, incluindo o IP da LAN -- ver coletarUnifi() em AtivoService.
     */
    public function listarDispositivosLegado(): array
    {
        $ref = $this->siteRefAtual();

        if ($ref === null) {
            return [];
        }

        $resultado = $this->chamarApi('GET', "/proxy/network/api/s/{$ref}/stat/device");

        return $resultado['sucesso'] ? ($resultado['dados']['data'] ?? []) : [];
    }

    /**
     * Clientes conectados via API LEGADA (/api/s/{ref}/stat/sta) -- ao
     * contrário de /integration/v1/clients, traz `essid` (rede Wi-Fi),
     * `signal` (dBm) e `ap_mac` (MAC do AP, casamento direto sem precisar
     * do id interno do dispositivo).
     */
    public function listarClientesLegado(): array
    {
        $ref = $this->siteRefAtual();

        if ($ref === null) {
            NotificationService::error('Site do UniFi Controller ainda não identificado -- use "Testar conexão" em Integrações > UniFi.');
            return [];
        }

        $resultado = $this->chamarApi('GET', "/proxy/network/api/s/{$ref}/stat/sta");

        if (!$resultado['sucesso']) {
            NotificationService::error('Erro ao listar clientes do UniFi Controller.', $resultado['mensagem']);
            return [];
        }

        return $resultado['dados']['data'] ?? [];
    }

    /** Registro completo (API legada) de UM dispositivo específico por MAC -- traz system-stats, temperatura, uptime, wan1/wan2, uptime_stats (disponibilidade/latência por alvo, ICMP e DNS) e speedtest-status, nada disso existe em /integration/v1. */
    public function buscarDispositivoLegado(string $mac): ?array
    {
        $ref = $this->siteRefAtual();

        if ($ref === null) {
            return null;
        }

        $resultado = $this->chamarApi('GET', "/proxy/network/api/s/{$ref}/stat/device/" . rawurlencode($mac));

        return $resultado['sucesso'] ? ($resultado['dados']['data'][0] ?? null) : null;
    }

    /**
     * Todas as redes configuradas no site (WAN e LAN) -- só os campos
     * seguros pra guardar em `detalhes` e mostrar na ficha do ativo.
     * Deliberadamente NUNCA inclui `x_wan_password`/`wan_password` aqui:
     * a senha do PPPoE é sensível de verdade (credencial do provedor) e só
     * é buscada sob demanda (ver AtivoService::coletarUnifi()), nunca
     * ficando espalhada por outros métodos que não precisam dela.
     */
    public function listarRedesConfiguradas(): array
    {
        $ref = $this->siteRefAtual();

        if ($ref === null) {
            return [];
        }

        $resultado = $this->chamarApi('GET', "/proxy/network/api/s/{$ref}/rest/networkconf");

        if (!$resultado['sucesso']) {
            return [];
        }

        return $resultado['dados']['data'] ?? [];
    }

    /**
     * Troca qual WAN é a primária de failover -- mesmo efeito de reordenar
     * manualmente em Configurações > Internet no UniFi Network. Reordena
     * $grupoWan pra prioridade 1 e empurra as demais uma posição, preservando
     * a ordem relativa entre elas (funciona igual com 2 ou mais WANs). Só
     * faz sentido pra WANs em modo failover -- em load-balance as duas são
     * usadas ao mesmo tempo por peso, não existe "primária" pra trocar.
     *
     * @return array{success:bool, message:string}
     */
    public function definirWanPrimaria(string $grupoWan): array
    {
        $ref = $this->siteRefAtual();

        if ($ref === null) {
            return ['success' => false, 'message' => 'Site do UniFi Controller ainda não identificado -- use "Testar conexão" em Integrações > UniFi.'];
        }

        $redes = array_values(array_filter(
            $this->listarRedesConfiguradas(),
            fn($r) => ($r['purpose'] ?? '') === 'wan'
        ));

        if (empty($redes)) {
            return ['success' => false, 'message' => 'Nenhuma rede WAN encontrada no Controller.'];
        }

        $alvo = null;
        foreach ($redes as $rede) {
            if (($rede['wan_networkgroup'] ?? '') === $grupoWan) {
                $alvo = $rede;
                break;
            }
        }

        if ($alvo === null) {
            return ['success' => false, 'message' => 'Essa WAN não foi encontrada no Controller -- recarregue a página e tente de novo.'];
        }

        if ((int)($alvo['wan_failover_priority'] ?? 0) === 1) {
            return ['success' => true, 'message' => 'Essa WAN já é a primária.'];
        }

        usort($redes, fn($a, $b) => ($a['wan_failover_priority'] ?? 99) <=> ($b['wan_failover_priority'] ?? 99));
        $outras = array_values(array_filter($redes, fn($r) => $r['_id'] !== $alvo['_id']));
        $reordenadas = array_merge([$alvo], $outras);

        foreach ($reordenadas as $i => $rede) {
            $novaPrioridade = $i + 1;

            if ((int)($rede['wan_failover_priority'] ?? 0) === $novaPrioridade) {
                continue;
            }

            $rede['wan_failover_priority'] = $novaPrioridade;
            $resultado = $this->chamarApi('PUT', "/proxy/network/api/s/{$ref}/rest/networkconf/{$rede['_id']}", $rede);

            if (!$resultado['sucesso']) {
                return ['success' => false, 'message' => 'Falha ao atualizar "' . ($rede['name'] ?? $rede['wan_networkgroup']) . '": ' . $resultado['mensagem']];
            }
        }

        return ['success' => true, 'message' => 'WAN primária alterada -- pode levar alguns segundos pra rede migrar de fato.'];
    }

    /**
     * Troca o modo global das WANs entre failover e balanceamento de carga --
     * mesmo controle de "WAN Mode" em Internet > WAN Mode no app UniFi.
     * Em failover, só a WAN de maior prioridade (menor wan_failover_priority)
     * fica "weighted" (a que carrega o tráfego); as demais viram
     * "failover-only" (só entram se ela cair) -- é assim que o próprio
     * Controller já marca as WANs numa config de failover real, confirmado
     * lendo os registros ao vivo. Em balanceamento, todas ficam "weighted"
     * (usadas ao mesmo tempo, divididas pelo peso configurado em cada uma).
     *
     * @param string $modo 'failover' ou 'balanceamento'
     * @return array{success:bool, message:string}
     */
    public function definirModoWan(string $modo): array
    {
        if (!in_array($modo, ['failover', 'balanceamento'], true)) {
            return ['success' => false, 'message' => 'Modo inválido.'];
        }

        $ref = $this->siteRefAtual();

        if ($ref === null) {
            return ['success' => false, 'message' => 'Site do UniFi Controller ainda não identificado -- use "Testar conexão" em Integrações > UniFi.'];
        }

        $redes = array_values(array_filter(
            $this->listarRedesConfiguradas(),
            fn($r) => ($r['purpose'] ?? '') === 'wan'
        ));

        if (empty($redes)) {
            return ['success' => false, 'message' => 'Nenhuma rede WAN encontrada no Controller.'];
        }

        usort($redes, fn($a, $b) => ($a['wan_failover_priority'] ?? 99) <=> ($b['wan_failover_priority'] ?? 99));

        foreach ($redes as $i => $rede) {
            $novoTipo = $modo === 'balanceamento' ? 'weighted' : ($i === 0 ? 'weighted' : 'failover-only');

            if (($rede['wan_load_balance_type'] ?? '') === $novoTipo) {
                continue;
            }

            $rede['wan_load_balance_type'] = $novoTipo;
            $resultado = $this->chamarApi('PUT', "/proxy/network/api/s/{$ref}/rest/networkconf/{$rede['_id']}", $rede);

            if (!$resultado['sucesso']) {
                return ['success' => false, 'message' => 'Falha ao atualizar "' . ($rede['name'] ?? $rede['wan_networkgroup']) . '": ' . $resultado['mensagem']];
            }
        }

        $mensagem = $modo === 'balanceamento'
            ? 'Modo alterado para balanceamento de carga -- as WANs passam a ser usadas ao mesmo tempo.'
            : 'Modo alterado para failover -- só a WAN primária carrega tráfego até ela cair.';

        return ['success' => true, 'message' => $mensagem];
    }

    /** Dispara um speedtest sob demanda no gateway (mesmo comando usado pelo próprio app oficial) -- ver AtivoService::avaliarInternetUnifi() pra acompanhar o resultado. */
    public function dispararSpeedtest(): array
    {
        $ref = $this->siteRefAtual();

        if ($ref === null) {
            return ['success' => false, 'message' => 'Site do UniFi Controller ainda não identificado.'];
        }

        $resultado = $this->chamarApi('POST', "/proxy/network/api/s/{$ref}/cmd/devmgr", ['cmd' => 'speedtest']);

        if (!$resultado['sucesso']) {
            return ['success' => false, 'message' => $resultado['mensagem']];
        }

        return ['success' => true, 'message' => 'Speedtest disparado.'];
    }

    /**
     * Todos os clientes já vistos pelo Controller (não só os conectados
     * agora) com `blocked = true` -- fonte pro botão "Desbloquear": um
     * cliente bloqueado desconecta na hora e some de listarClientesLegado()
     * (só mostra quem está conectado), então precisa dessa lista à parte
     * pra conseguir desbloquear alguém que já caiu da rede.
     */
    public function listarClientesBloqueados(): array
    {
        $ref = $this->siteRefAtual();

        if ($ref === null) {
            return [];
        }

        $resultado = $this->chamarApi('GET', "/proxy/network/api/s/{$ref}/list/user");

        if (!$resultado['sucesso']) {
            NotificationService::error('Erro ao listar clientes bloqueados do UniFi Controller.', $resultado['mensagem']);
            return [];
        }

        return array_values(array_filter($resultado['dados']['data'] ?? [], fn($u) => !empty($u['blocked'])));
    }

    /**
     * Nomes dos "zone-based firewall" (id -> nome), ex: "Internal",
     * "External", "Gateway", "Vpn", "Hotspot", "Dmz" -- confirmado ao vivo
     * contra o Controller real (UCG) do smb-pmpe. As políticas em si
     * (listarPoliticasFirewall()) só trazem o ID da zona, não o nome.
     *
     * @return array<string,string> id da zona => nome
     */
    private function nomesZonasFirewall(string $ref): array
    {
        $resultado = $this->chamarApi('GET', "/proxy/network/v2/api/site/{$ref}/firewall/zone-matrix");

        if (!$resultado['sucesso']) {
            return [];
        }

        $nomes = [];
        foreach ($resultado['dados'] ?? [] as $zona) {
            if (isset($zona['_id'], $zona['name'])) {
                $nomes[$zona['_id']] = $zona['name'];
            }
        }

        return $nomes;
    }

    /**
     * Nomes de regra que são só a "malha" automática entre zonas (uma
     * combinação fixa de Allow/Block/Return/Invalid por par de zonas, criada
     * sozinha pelo Controller) -- confirmado ao vivo: das 132 regras reais
     * desse Controller, só ~20 são coisa que um humano configurou de
     * verdade (bloqueio de app específico, regra de porta/serviço
     * personalizada); o resto é essa malha repetida pra cada par de zonas.
     * Escondida por padrão na tela (dá pra mostrar tudo com um toggle) --
     * senão a lista de "regras" vira ruído puro de operação interna do
     * Controller, sem nada acionável.
     */
    private const NOMES_REGRA_FIREWALL_AUTOMATICA = [
        'Allow All Traffic', 'Block All Traffic', 'Allow Return Traffic', 'Block Invalid Traffic',
        'Allow mDNS', 'Allow DHCP', 'Allow DHCPv6', 'Allow DNS', 'Allow Public DNS',
        'Allow ICMP', 'Allow ICMPv6', 'Allow RADIUS Authentication', 'Allow RADIUS Accounting',
        'Allow Hotspot Portal', 'Allow Hotspot Portal Authentication', 'Allow Hotspot Portal Redirects',
        'Post-Authorization Restrictions', 'Block Unauthorized Traffic',
        'Allow Neighbor Solicitations', 'Allow Neighbor Advertisements', 'Allow Link-Local DHCPv6',
        'Allow Router Advertisements', 'Allow OpenVPN Server',
    ];

    /**
     * Regras de firewall (zone-based, UniFi OS 8+) -- só leitura. Confirmado
     * ao vivo: `/rest/firewallrule` (API clássica) responde OK mas sempre
     * vazio nesse Controller -- as regras de verdade vivem só na API v2
     * (`/v2/api/site/{ref}/firewall-policies`), que não existia nas versões
     * mais antigas do Controller/UniFi OS.
     *
     * @return array{success:bool, message?:string, regras?:array}
     */
    public function listarPoliticasFirewall(): array
    {
        $ref = $this->siteRefAtual();

        if ($ref === null) {
            return ['success' => false, 'message' => 'Site do UniFi Controller ainda não identificado -- use "Testar conexão" em Integrações > UniFi.'];
        }

        $resultado = $this->chamarApi('GET', "/proxy/network/v2/api/site/{$ref}/firewall-policies");

        if (!$resultado['sucesso']) {
            return ['success' => false, 'message' => $resultado['mensagem']];
        }

        $zonas = $this->nomesZonasFirewall($ref);

        $regras = [];
        foreach ($resultado['dados'] ?? [] as $p) {
            $nome = $p['name'] ?? '';
            $regras[] = [
                'id' => $p['_id'] ?? '',
                'nome' => $nome,
                'acao' => $p['action'] ?? '',
                'habilitada' => (bool)($p['enabled'] ?? false),
                'automatica' => in_array($nome, self::NOMES_REGRA_FIREWALL_AUTOMATICA, true),
                'protocolo' => $p['protocol'] ?? '',
                'zona_origem' => $zonas[$p['source']['zone_id'] ?? ''] ?? null,
                'zona_destino' => $zonas[$p['destination']['zone_id'] ?? ''] ?? null,
                'porta_destino' => $p['destination']['port'] ?? null,
                'agendamento' => $p['schedule']['mode'] ?? 'ALWAYS',
                'hits' => isset($p['hits']) ? (int)$p['hits'] : null,
                'ultimo_hit' => isset($p['last_hit']) ? (int)round($p['last_hit'] / 1000) : null,
                'personalizada' => empty($p['predefined']),
            ];
        }

        return ['success' => true, 'regras' => $regras];
    }

    /** @return array{success:bool, message?:string, zonas?:array<int,array{id:string,nome:string}>} */
    public function listarZonasFirewallParaFormulario(): array
    {
        $ref = $this->siteRefAtual();

        if ($ref === null) {
            return ['success' => false, 'message' => 'Site do UniFi Controller ainda não identificado -- use "Testar conexão" em Integrações > UniFi.'];
        }

        $nomes = $this->nomesZonasFirewall($ref);

        if (empty($nomes)) {
            return ['success' => false, 'message' => 'Não foi possível consultar as zonas do firewall no Controller.'];
        }

        $zonas = [];
        foreach ($nomes as $id => $nome) {
            $zonas[] = ['id' => $id, 'nome' => $nome];
        }

        return ['success' => true, 'zonas' => $zonas];
    }

    /**
     * Monta o "lado" (origem ou destino) de uma regra -- IP específico só
     * entra quando informado (confirmado ao vivo: a forma "qualquer IP" da
     * API nem manda a chave `matching_target_type`, só existe quando o alvo
     * é "IP"/"SPECIFIC" de verdade).
     */
    private function montarLadoRegraFirewall(string $zonaId, string $ip): array
    {
        $lado = [
            'zone_id' => $zonaId,
            'matching_target' => $ip !== '' ? 'IP' : 'ANY',
            'port_matching_type' => 'ANY',
            'match_opposite_ports' => false,
        ];

        if ($ip !== '') {
            $lado['matching_target_type'] = 'SPECIFIC';
            $lado['ips'] = [$ip];
            $lado['match_opposite_ips'] = false;
        }

        return $lado;
    }

    /**
     * Cria uma regra de firewall nova -- confirmado ao vivo (criada
     * desativada, verificada, excluída de novo, sem deixar rastro). Cobre só
     * o caso comum (zona a zona, com IP/porta de destino opcionais);
     * schedule sempre "Always", sem estado de conexão/ICMP customizado --
     * pra isso, o Controller direto.
     *
     * @param array{nome:string, acao:string, protocolo:string, zona_origem_id:string, zona_destino_id:string,
     *   ip_origem?:string, ip_destino?:string, porta_destino?:string, habilitada:bool} $dados
     * @return array{success:bool, message:string}
     */
    public function criarPoliticaFirewall(array $dados): array
    {
        $ref = $this->siteRefAtual();

        if ($ref === null) {
            return ['success' => false, 'message' => 'Site do UniFi Controller ainda não identificado -- use "Testar conexão" em Integrações > UniFi.'];
        }

        $nome = trim($dados['nome'] ?? '');
        $acao = $dados['acao'] ?? '';
        $zonaOrigemId = trim($dados['zona_origem_id'] ?? '');
        $zonaDestinoId = trim($dados['zona_destino_id'] ?? '');

        if ($nome === '') {
            return ['success' => false, 'message' => 'Informe um nome pra regra.'];
        }
        if (!in_array($acao, ['ALLOW', 'BLOCK'], true)) {
            return ['success' => false, 'message' => 'Ação inválida.'];
        }
        if ($zonaOrigemId === '' || $zonaDestinoId === '') {
            return ['success' => false, 'message' => 'Selecione a zona de origem e a de destino.'];
        }

        $ipOrigem = trim($dados['ip_origem'] ?? '');
        $ipDestino = trim($dados['ip_destino'] ?? '');
        foreach (['IP de origem' => $ipOrigem, 'IP de destino' => $ipDestino] as $rotulo => $ip) {
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) === false) {
                return ['success' => false, 'message' => "\"{$ip}\" não é um IP válido ({$rotulo})."];
            }
        }

        $portaDestino = trim($dados['porta_destino'] ?? '');
        if ($portaDestino !== '' && (!ctype_digit($portaDestino) || (int)$portaDestino < 1 || (int)$portaDestino > 65535)) {
            return ['success' => false, 'message' => 'Porta de destino inválida (use um número de 1 a 65535, ou deixe em branco pra qualquer porta).'];
        }

        $protocolo = in_array($dados['protocolo'] ?? '', ['all', 'tcp', 'udp', 'icmp'], true) ? $dados['protocolo'] : 'all';

        $origem = $this->montarLadoRegraFirewall($zonaOrigemId, $ipOrigem);
        $destino = $this->montarLadoRegraFirewall($zonaDestinoId, $ipDestino);
        if ($portaDestino !== '') {
            $destino['port'] = $portaDestino;
            $destino['port_matching_type'] = 'SPECIFIC';
        }

        $payload = [
            'name' => $nome,
            'action' => $acao,
            'enabled' => (bool)($dados['habilitada'] ?? true),
            'protocol' => $protocolo,
            'connection_state_type' => 'ALL',
            'connection_states' => [],
            // Confirmado ao vivo: o Controller recusa
            // ("Firewall policy create respond traffic not allowed") esse
            // campo true numa regra BLOCK -- só faz sentido pra ALLOW (cria
            // sozinho a regra de retorno do tráfego permitido).
            'create_allow_respond' => $acao === 'ALLOW',
            'ip_version' => 'BOTH',
            'icmp_typename' => 'ANY',
            'icmp_v6_typename' => 'ANY',
            'logging' => false,
            'match_ip_sec' => false,
            'match_opposite_protocol' => false,
            'predefined' => false,
            'schedule' => ['mode' => 'ALWAYS'],
            'source' => $origem,
            'destination' => $destino,
        ];

        $resultado = $this->chamarApi('POST', "/proxy/network/v2/api/site/{$ref}/firewall-policies", $payload);

        if (!$resultado['sucesso']) {
            return ['success' => false, 'message' => $resultado['mensagem']];
        }

        return ['success' => true, 'message' => "Regra \"{$nome}\" criada."];
    }

    public function excluirPoliticaFirewall(string $policyId, string $nome): array
    {
        $ref = $this->siteRefAtual();

        if ($ref === null) {
            return ['success' => false, 'message' => 'Site do UniFi Controller ainda não identificado -- use "Testar conexão" em Integrações > UniFi.'];
        }

        $resultado = $this->chamarApi('DELETE', "/proxy/network/v2/api/site/{$ref}/firewall-policies/{$policyId}");

        if (!$resultado['sucesso']) {
            return ['success' => false, 'message' => $resultado['mensagem']];
        }

        return ['success' => true, 'message' => "Regra \"{$nome}\" excluída."];
    }

    /**
     * Liga/desliga uma regra de firewall existente -- confirmado ao vivo que
     * o Controller espera o objeto INTEIRO da regra no PUT (não um patch
     * parcial só com "enabled"), por isso busca a lista de novo pra pegar o
     * objeto completo antes de mudar só o campo `enabled` e mandar de volta.
     * Não cria, não remove, não muda ação/zona/porta -- só ativa/desativa.
     */
    public function alterarPoliticaFirewall(string $policyId, bool $habilitada): array
    {
        $ref = $this->siteRefAtual();

        if ($ref === null) {
            return ['success' => false, 'message' => 'Site do UniFi Controller ainda não identificado -- use "Testar conexão" em Integrações > UniFi.'];
        }

        $lista = $this->chamarApi('GET', "/proxy/network/v2/api/site/{$ref}/firewall-policies");

        if (!$lista['sucesso']) {
            return ['success' => false, 'message' => $lista['mensagem']];
        }

        $politica = null;
        foreach ($lista['dados'] ?? [] as $p) {
            if (($p['_id'] ?? '') === $policyId) {
                $politica = $p;
                break;
            }
        }

        if ($politica === null) {
            return ['success' => false, 'message' => 'Regra não encontrada -- pode ter sido removida ou alterada direto no Controller. Atualize a lista.'];
        }

        $politica['enabled'] = $habilitada;

        $resultado = $this->chamarApi('PUT', "/proxy/network/v2/api/site/{$ref}/firewall-policies/{$policyId}", $politica);

        if (!$resultado['sucesso']) {
            return ['success' => false, 'message' => $resultado['mensagem']];
        }

        return ['success' => true, 'message' => $habilitada ? "Regra \"{$politica['name']}\" ativada." : "Regra \"{$politica['name']}\" desativada."];
    }

    /**
     * Todos os clientes conectados agora no site (com fio + Wi-Fi, todos os
     * dispositivos, não só os de um AP) -- confirmado ao vivo via
     * `/v2/api/site/{ref}/clients/active`. Complementa
     * listarClientesLegado() (que só traz Wi-Fi de UM AP específico, usado
     * na aba "Wi-Fi" de cada ponto de acesso): esse aqui é pro Gateway
     * mostrar a rede inteira num lugar só.
     *
     * @return array{success:bool, message?:string, clientes?:array}
     */
    public function listarClientesRede(): array
    {
        $ref = $this->siteRefAtual();

        if ($ref === null) {
            return ['success' => false, 'message' => 'Site do UniFi Controller ainda não identificado -- use "Testar conexão" em Integrações > UniFi.'];
        }

        $resultado = $this->chamarApi('GET', "/proxy/network/v2/api/site/{$ref}/clients/active");

        if (!$resultado['sucesso']) {
            return ['success' => false, 'message' => $resultado['mensagem']];
        }

        $clientes = [];
        foreach ($resultado['dados'] ?? [] as $c) {
            $rx = (int)($c['rx_bytes'] ?? 0);
            $tx = (int)($c['tx_bytes'] ?? 0);
            $clientes[] = [
                'nome' => $c['display_name'] ?? ($c['name'] ?? ($c['mac'] ?? '')),
                'mac' => $c['mac'] ?? '',
                'ip' => $c['ip'] ?? '',
                'com_fio' => (bool)($c['is_wired'] ?? false),
                'rede' => $c['network_name'] ?? '',
                'rx_bytes' => $rx,
                'tx_bytes' => $tx,
                'total_bytes' => $rx + $tx,
                'status' => $c['status'] ?? '',
                'bloqueado' => (bool)($c['blocked'] ?? false),
                'uptime_segundos' => isset($c['uptime']) ? (int)$c['uptime'] : null,
            ];
        }

        // Maior consumo primeiro -- é o que mais interessa numa lista de tráfego.
        usort($clientes, fn ($a, $b) => $b['total_bytes'] <=> $a['total_bytes']);

        return ['success' => true, 'clientes' => $clientes];
    }

    /**
     * Saúde da rede por subsistema (wan/wlan/lan/vpn) -- confirmado ao vivo
     * via `stat/health` (API clássica). Cada subsistema tem campos
     * diferentes (ex: só "wan" tem `wan_ip`/`isp_name`), por isso os campos
     * abaixo saem `null` quando não existem pro subsistema em questão.
     *
     * @return array{success:bool, message?:string, subsistemas?:array}
     */
    public function buscarSaudeRede(): array
    {
        $ref = $this->siteRefAtual();

        if ($ref === null) {
            return ['success' => false, 'message' => 'Site do UniFi Controller ainda não identificado -- use "Testar conexão" em Integrações > UniFi.'];
        }

        $resultado = $this->chamarApi('GET', "/proxy/network/api/s/{$ref}/stat/health");

        if (!$resultado['sucesso']) {
            return ['success' => false, 'message' => $resultado['mensagem']];
        }

        $subsistemas = [];
        foreach ($resultado['dados']['data'] ?? [] as $s) {
            $subsistemas[] = [
                'nome' => $s['subsystem'] ?? '',
                'status' => $s['status'] ?? '',
                'num_ap' => $s['num_ap'] ?? null,
                'num_adopted' => $s['num_adopted'] ?? null,
                'num_disconnected' => $s['num_disconnected'] ?? null,
                'num_pending' => $s['num_pending'] ?? null,
                'num_user' => $s['num_user'] ?? null,
                'num_guest' => $s['num_guest'] ?? null,
                'wan_ip' => $s['wan_ip'] ?? null,
                'isp_name' => $s['isp_name'] ?? null,
                'gw_cpu_pct' => isset($s['gw_system-stats']['cpu']) ? (float)$s['gw_system-stats']['cpu'] : null,
                'gw_mem_pct' => isset($s['gw_system-stats']['mem']) ? (float)$s['gw_system-stats']['mem'] : null,
                'gw_uptime_segundos' => isset($s['gw_system-stats']['uptime']) ? (int)$s['gw_system-stats']['uptime'] : null,
            ];
        }

        return ['success' => true, 'subsistemas' => $subsistemas];
    }

    /**
     * Redes Wi-Fi vizinhas/interferentes detectadas pelos rádios dos
     * próprios APs (`stat/rogueap`) -- não é uma varredura ativa, é o que os
     * rádios já veem passivamente. `is_rogue` distingue rede realmente
     * suspeita (ex: mesmo SSID clonado) de só uma rede qualquer do vizinho.
     *
     * Confirmado ao vivo: esse endpoint devolve o HISTÓRICO de avistamentos
     * (525 linhas num Controller com 326 redes vizinhas únicas num prédio
     * bem denso de Wi-Fi -- cada AP que enxerga a mesma rede gera outra
     * linha, e entradas antigas não somem sozinhas). Sem filtro isso vira
     * uma tabela imensa e inútil, então: só o que foi visto na última hora
     * (retrato de "agora"), e um só registro por BSSID (fica o de sinal mais
     * forte entre as repetições).
     *
     * @return array{success:bool, message?:string, redes?:array}
     */
    public function listarRedesVizinhas(): array
    {
        $ref = $this->siteRefAtual();

        if ($ref === null) {
            return ['success' => false, 'message' => 'Site do UniFi Controller ainda não identificado -- use "Testar conexão" em Integrações > UniFi.'];
        }

        $resultado = $this->chamarApi('GET', "/proxy/network/api/s/{$ref}/stat/rogueap");

        if (!$resultado['sucesso']) {
            return ['success' => false, 'message' => $resultado['mensagem']];
        }

        $agora = time();
        $porBssid = [];
        foreach ($resultado['dados']['data'] ?? [] as $r) {
            $vistoEm = isset($r['last_seen']) ? (int)$r['last_seen'] : 0;
            if (($agora - $vistoEm) > 3600) {
                continue;
            }

            $bssid = $r['bssid'] ?? '';
            $sinal = $r['signal'] ?? -999;
            if (isset($porBssid[$bssid]) && $porBssid[$bssid]['sinal_dbm'] >= $sinal) {
                continue;
            }

            $porBssid[$bssid] = [
                'ssid' => $r['essid'] ?: '(oculta)',
                'bssid' => $bssid,
                'canal' => $r['channel'] ?? null,
                // Canal <= 14 é sempre 2.4GHz, senão é 5GHz -- mais confiável
                // que tentar decifrar o enum "band" cru ("ng", "na" etc.).
                'banda' => isset($r['channel']) && (int)$r['channel'] <= 14 ? '2.4 GHz' : '5 GHz',
                'sinal_dbm' => $sinal,
                'seguranca' => $r['security'] ?? '',
                'suspeita' => (bool)($r['is_rogue'] ?? false),
                'detectada_por_ap' => $r['ap_mac'] ?? '',
                'visto_por_ultimo' => $vistoEm,
            ];
        }

        $redes = array_values($porBssid);

        // Suspeitas primeiro, depois sinal mais forte (rssi maior) primeiro.
        usort($redes, fn ($a, $b) => ($b['suspeita'] <=> $a['suspeita']) ?: ($b['sinal_dbm'] <=> $a['sinal_dbm']));

        return ['success' => true, 'redes' => $redes];
    }

    /**
     * Regras de redirecionamento de porta (NAT) -- lista SEPARADA das
     * políticas de firewall (`firewall-policies`), confirmado ao vivo via
     * `list/portforward` (API clássica). Só leitura.
     *
     * @return array{success:bool, message?:string, regras?:array}
     */
    public function listarPortForward(): array
    {
        $ref = $this->siteRefAtual();

        if ($ref === null) {
            return ['success' => false, 'message' => 'Site do UniFi Controller ainda não identificado -- use "Testar conexão" em Integrações > UniFi.'];
        }

        $resultado = $this->chamarApi('GET', "/proxy/network/api/s/{$ref}/list/portforward");

        if (!$resultado['sucesso']) {
            return ['success' => false, 'message' => $resultado['mensagem']];
        }

        $regras = [];
        foreach ($resultado['dados']['data'] ?? [] as $p) {
            $regras[] = [
                'nome' => $p['name'] ?? '',
                'habilitada' => (bool)($p['enabled'] ?? false),
                'protocolo' => strtoupper((string)($p['proto'] ?? '')),
                'porta_externa' => $p['dst_port'] ?? '',
                'destino_ip' => $p['fwd'] ?? '',
                'destino_porta' => $p['fwd_port'] ?? '',
            ];
        }

        return ['success' => true, 'regras' => $regras];
    }

    /**
     * Configuração das redes Wi-Fi cadastradas (SSID, segurança, banda,
     * oculta) -- confirmado ao vivo via `rest/wlanconf` (API clássica). Só
     * leitura; não expõe a senha (`x_passphrase` existe no retorno cru, mas
     * não é repassada por aqui de propósito).
     *
     * @return array{success:bool, message?:string, redes?:array}
     */
    public function listarRedesWifiConfiguradas(): array
    {
        $ref = $this->siteRefAtual();

        if ($ref === null) {
            return ['success' => false, 'message' => 'Site do UniFi Controller ainda não identificado -- use "Testar conexão" em Integrações > UniFi.'];
        }

        $resultado = $this->chamarApi('GET', "/proxy/network/api/s/{$ref}/rest/wlanconf");

        if (!$resultado['sucesso']) {
            return ['success' => false, 'message' => $resultado['mensagem']];
        }

        $redes = [];
        foreach ($resultado['dados']['data'] ?? [] as $w) {
            $redes[] = [
                'nome' => $w['name'] ?? '',
                'habilitada' => (bool)($w['enabled'] ?? true),
                'banda' => $w['wlan_band'] ?? '',
                'seguranca' => strtoupper((string)($w['wpa_mode'] ?? ($w['security'] ?? ''))),
                'oculta' => (bool)($w['hide_ssid'] ?? false),
                'convidado' => (bool)($w['is_guest'] ?? false),
            ];
        }

        return ['success' => true, 'redes' => $redes];
    }

    /**
     * Rotas estáticas configuradas no Gateway -- confirmado ao vivo via
     * `rest/routing` (API clássica). Só leitura.
     *
     * @return array{success:bool, message?:string, rotas?:array}
     */
    public function listarRotasEstaticas(): array
    {
        $ref = $this->siteRefAtual();

        if ($ref === null) {
            return ['success' => false, 'message' => 'Site do UniFi Controller ainda não identificado -- use "Testar conexão" em Integrações > UniFi.'];
        }

        $resultado = $this->chamarApi('GET', "/proxy/network/api/s/{$ref}/rest/routing");

        if (!$resultado['sucesso']) {
            return ['success' => false, 'message' => $resultado['mensagem']];
        }

        $rotas = [];
        foreach ($resultado['dados']['data'] ?? [] as $r) {
            $rotas[] = [
                'nome' => $r['name'] ?? '',
                'habilitada' => (bool)($r['enabled'] ?? false),
                'rede_destino' => $r['static-route_network'] ?? '',
                'proximo_salto' => $r['static-route_nexthop'] ?? '',
                'tipo' => $r['static-route_type'] ?? '',
            ];
        }

        return ['success' => true, 'rotas' => $rotas];
    }

    /**
     * Informações do próprio Controller (versão, hostname, uptime, se tem
     * atualização disponível) -- confirmado ao vivo via `stat/sysinfo` (API
     * clássica). Só leitura.
     *
     * @return array{success:bool, message?:string, versao?:string, hostname?:string, uptime_segundos?:int,
     *   atualizacao_disponivel?:bool, ips?:array}
     */
    public function buscarInfoControlador(): array
    {
        $ref = $this->siteRefAtual();

        if ($ref === null) {
            return ['success' => false, 'message' => 'Site do UniFi Controller ainda não identificado -- use "Testar conexão" em Integrações > UniFi.'];
        }

        $resultado = $this->chamarApi('GET', "/proxy/network/api/s/{$ref}/stat/sysinfo");

        if (!$resultado['sucesso']) {
            return ['success' => false, 'message' => $resultado['mensagem']];
        }

        $info = $resultado['dados']['data'][0] ?? [];

        return [
            'success' => true,
            'versao' => $info['version'] ?? '',
            'hostname' => $info['hostname'] ?? ($info['name'] ?? ''),
            'uptime_segundos' => isset($info['uptime']) ? (int)$info['uptime'] : null,
            'atualizacao_disponivel' => (bool)($info['update_available'] ?? false),
            'ips' => $info['ip_addrs'] ?? [],
        ];
    }

    /** Desconecta o cliente agora -- reconecta sozinho em seguida (não é um bloqueio, só força uma nova associação). */
    public function desconectarCliente(string $mac): array
    {
        return $this->executarComandoStamgr('kick-sta', $mac, 'Cliente desconectado -- pode reconectar normalmente em seguida.');
    }

    /** Bloqueia o cliente -- fica impedido de conectar em qualquer AP deste site até ser desbloqueado (não existe bloqueio por tempo determinado nativo no Controller). */
    public function bloquearCliente(string $mac): array
    {
        return $this->executarComandoStamgr('block-sta', $mac, 'Cliente bloqueado -- vai continuar impedido de conectar até ser desbloqueado.');
    }

    public function desbloquearCliente(string $mac): array
    {
        return $this->executarComandoStamgr('unblock-sta', $mac, 'Cliente desbloqueado.');
    }

    /** @return array{success:bool, message:string} */
    private function executarComandoStamgr(string $cmd, string $mac, string $mensagemSucesso): array
    {
        $ref = $this->siteRefAtual();

        if ($ref === null) {
            return ['success' => false, 'message' => 'Site do UniFi Controller ainda não identificado -- use "Testar conexão" em Integrações > UniFi.'];
        }

        $resultado = $this->chamarApi('POST', "/proxy/network/api/s/{$ref}/cmd/stamgr", ['cmd' => $cmd, 'mac' => strtolower(trim($mac))]);

        if (!$resultado['sucesso']) {
            return ['success' => false, 'message' => $resultado['mensagem']];
        }

        return ['success' => true, 'message' => $mensagemSucesso];
    }

    /** offset/limit até esgotar totalCount -- na prática quase sempre cabe numa página só, mas evita truncar silenciosamente em instalações maiores. */
    private function paginar(string $caminhoBase): array
    {
        $todos = [];
        $offset = 0;

        do {
            $separador = str_contains($caminhoBase, '?') ? '&' : '?';
            $resultado = $this->chamarApi('GET', "{$caminhoBase}{$separador}offset={$offset}&limit=" . self::LIMITE_PAGINA);

            if (!$resultado['sucesso']) {
                NotificationService::error('Erro ao consultar o UniFi Controller.', $resultado['mensagem']);
                return $todos;
            }

            $pagina = $resultado['dados']['data'] ?? [];
            foreach ($pagina as $item) {
                $todos[] = $item;
            }

            $total = (int)($resultado['dados']['totalCount'] ?? count($todos));
            $offset += self::LIMITE_PAGINA;
        } while ($offset < $total);

        return $todos;
    }

    /**
     * Chokepoint único de curl -- mesmo idioma do EntraService::chamarGraph()/KbService::chamarCentral(),
     * trocando só o header de autenticação (X-API-KEY em vez de Bearer token).
     *
     * @return array{sucesso:bool, dados:mixed, mensagem:string}
     */
    private function chamarApi(string $metodo, string $caminho, ?array $corpo = null): array
    {
        if (!$this->configurado()) {
            return ['sucesso' => false, 'dados' => null, 'mensagem' => 'Integração com o UniFi Controller ainda não configurada -- veja Integrações.'];
        }

        $apiKey = $this->apiKeyAtual();

        if ($apiKey === null) {
            return ['sucesso' => false, 'dados' => null, 'mensagem' => 'Não foi possível ler a API Key configurada.'];
        }

        $url = $this->urlAtual() . $caminho;

        $cabecalhos = ['X-API-KEY: ' . $apiKey, 'Accept: application/json'];
        if ($corpo !== null) {
            $cabecalhos[] = 'Content-Type: application/json';
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $metodo,
            CURLOPT_HTTPHEADER => $cabecalhos,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        if ($corpo !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($corpo));
        }

        $resposta = curl_exec($ch);
        $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erroCurl = curl_error($ch);
        curl_close($ch);

        if ($resposta === false) {
            return ['sucesso' => false, 'dados' => null, 'mensagem' => "Erro de comunicação com o Controller: {$erroCurl}"];
        }

        $dados = $resposta !== '' ? json_decode($resposta, true) : null;

        if ($codigo >= 200 && $codigo < 300) {
            return ['sucesso' => true, 'dados' => $dados, 'mensagem' => ''];
        }

        $mensagemErro = $dados['message'] ?? ($dados['error']['message'] ?? "Erro HTTP {$codigo} ao falar com o Controller.");

        return ['sucesso' => false, 'dados' => null, 'mensagem' => is_string($mensagemErro) ? $mensagemErro : "Erro HTTP {$codigo}."];
    }
}
