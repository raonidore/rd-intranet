<?php

namespace App\Services;

use App\Repositories\WifiRepository;

/**
 * Filtro de sites do Wi-Fi, independente da marca do equipamento: o AdGuard
 * Home roda no servidor (só a API em 127.0.0.1) e é o DNS da rede do portal.
 * Cada pessoa conectada vira um "cliente" do AdGuard (identificado pelo IP
 * da sessão) com as regras do perfil + exceções dela + o filtro global.
 *
 * Filtro (JSON, no perfil/pessoa/global):
 *   categorias: malware | adulto | apostas | busca_segura
 *   servicos:   ids de serviço do AdGuard (youtube, tiktok, ...)
 *   bloquear / liberar: domínios
 */
class WifiFiltroService
{
    public const SCRIPT_INSTALAR = '/opt/rdtecnologia/scripts/wifi_filtro_instalar_web.sh';

    public const CATEGORIAS = [
        'malware' => 'Golpes, vírus e phishing',
        'adulto' => 'Conteúdo adulto',
        'apostas' => 'Apostas e cassinos online',
        'busca_segura' => 'Busca segura (Google, Bing, YouTube)',
    ];

    /** Apostas: o AdGuard não tem categoria pronta; lista base + o que a TI acrescentar. */
    private const DOMINIOS_APOSTAS = ['bet365.com', 'betano.com', 'betano.bet.br', 'sportingbet.com', 'sportingbet.bet.br', 'betfair.com', 'pixbet.com', 'pixbet.bet.br',
        'estrelabet.com', 'estrelabet.bet.br', 'blaze.com', 'blaze.bet.br', 'stake.com', 'stake.bet.br', 'galera.bet', 'galera.bet.br', 'kto.com', 'kto.bet.br', 'novibet.com',
        'novibet.bet.br', 'superbet.com', 'superbet.bet.br', 'betnacional.com', 'betnacional.bet.br', 'f12.bet', 'f12.bet.br', 'esportesdasorte.com', 'esportesdasorte.bet.br',
        'vaidebet.com', 'vaidebet.bet.br', 'br4.bet', 'br4.bet.br', 'mrjack.bet', 'mrjack.bet.br', 'betfast.io', 'betfast.bet.br', 'parimatch.com', '1xbet.com', 'pinnacle.com',
        'betway.com', 'rivalo.com', 'brazino777.com', 'pokerstars.com', 'lottoland.com', 'reidopitaco.com.br', 'reidopitaco.bet.br', 'bet7k.com', 'bet7k.bet.br', 'betpix365.com'];

    private const INICIO_BLOCO = '! --- RD.Intranet Wi-Fi (gerado automaticamente, não editar) ---';
    private const FIM_BLOCO = '! --- fim RD.Intranet Wi-Fi ---';

    private WifiConfigService $config;
    private WifiRepository $repo;

    public function __construct()
    {
        $this->config = new WifiConfigService();
        $this->repo = new WifiRepository();
    }

    public function ativo(): bool
    {
        return $this->config->bool('filtro_ativo') && $this->config->segredo('adguard_senha_cifrada') !== null;
    }

    // ================================================================ instalação

    /** Instala/reconfigura o AdGuard Home (script root). DNS no IP da LAN, API só local. */
    public function instalar(): array
    {
        $ip = $this->config->ipPortal();
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return ['success' => false, 'message' => 'Defina o IP do servidor em Wi-Fi > Configurações.'];
        }
        $senha = $this->config->segredo('adguard_senha_cifrada') ?: bin2hex(random_bytes(16));
        $r = (new LinuxService())->executarComEntrada('sudo ' . escapeshellarg(self::SCRIPT_INSTALAR) . ' ' . escapeshellarg($ip) . ' '
            . escapeshellarg($this->config->get('adguard_usuario')) . ' 2>&1', $senha);
        $json = json_decode(trim((string)$r['output']), true);
        if (!is_array($json) || empty($json['success'])) {
            return ['success' => false, 'message' => $json['message'] ?? ('Falha na instalação: ' . mb_substr(trim((string)$r['output']), -400))];
        }
        $this->config->salvarSegredo('adguard_senha_cifrada', $senha);
        try {
            // Vários resolvedores: o padrão do AdGuard (só Quad9 por DoH) às vezes demora na 1ª consulta.
            $this->api('POST', 'dns_config', [
                'upstream_dns' => ['https://dns.cloudflare.com/dns-query', 'https://dns10.quad9.net/dns-query', '1.1.1.1', '8.8.8.8'],
                'bootstrap_dns' => ['1.1.1.1', '8.8.8.8', '9.9.9.10'],
                'upstream_mode' => 'load_balance',
            ]);
        } catch (\Throwable $e) {
            // segue com o padrão do AdGuard
        }
        AuditService::registrar('Wi-Fi', 'Instalar filtro de sites', "AdGuard Home instalado (DNS em {$ip}:53).");

        return ['success' => true, 'message' => $json['message']];
    }

    // ================================================================ API do AdGuard

    /** @return array{0: int, 1: mixed} [status http, corpo decodificado] */
    private function api(string $metodo, string $caminho, ?array $corpo = null): array
    {
        $ch = curl_init(rtrim($this->config->get('adguard_url'), '/') . '/control/' . ltrim($caminho, '/'));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $metodo,
            CURLOPT_USERPWD => $this->config->get('adguard_usuario') . ':' . $this->config->segredo('adguard_senha_cifrada'),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 15,
        ]);
        if ($corpo !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($corpo, JSON_UNESCAPED_SLASHES));
        }
        $resp = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erro = curl_error($ch);
        curl_close($ch);
        if ($resp === false) {
            throw new \RuntimeException('AdGuard Home não respondeu: ' . $erro);
        }
        if ($status >= 400) {
            throw new \RuntimeException("AdGuard Home recusou {$caminho} (HTTP {$status}): " . mb_substr(trim((string)$resp), 0, 200));
        }

        return [$status, json_decode((string)$resp, true) ?? $resp];
    }

    public function status(): array
    {
        if ($this->config->segredo('adguard_senha_cifrada') === null) {
            return ['instalado' => false];
        }
        try {
            [, $s] = $this->api('GET', 'status');

            return ['instalado' => true, 'rodando' => !empty($s['running']), 'versao' => $s['version'] ?? '', 'dns' => $s['dns_addresses'] ?? []];
        } catch (\Throwable $e) {
            return ['instalado' => true, 'rodando' => false, 'erro' => $e->getMessage()];
        }
    }

    /** Serviços que o AdGuard sabe bloquear (id => nome), para a tela de regras. */
    public function servicosDisponiveis(): array
    {
        try {
            [, $r] = $this->api('GET', 'blocked_services/all');
            $lista = [];
            foreach ($r['blocked_services'] ?? [] as $s) {
                $lista[$s['id']] = $s['name'];
            }
            asort($lista, SORT_NATURAL | SORT_FLAG_CASE);

            return $lista;
        } catch (\Throwable $e) {
            return ['youtube' => 'YouTube', 'tiktok' => 'TikTok', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'twitter' => 'X (Twitter)',
                'whatsapp' => 'WhatsApp', 'netflix' => 'Netflix', 'twitch' => 'Twitch', 'discord' => 'Discord', 'telegram' => 'Telegram', 'roblox' => 'Roblox', 'steam' => 'Steam'];
        }
    }

    // ================================================================ sincronização

    /** Junta filtros (global + perfil + pessoa): categorias e listas somam; "liberar" vence "bloquear". */
    public static function combinar(array ...$filtros): array
    {
        $r = ['categorias' => [], 'servicos' => [], 'bloquear' => [], 'liberar' => []];
        foreach ($filtros as $f) {
            foreach ($r as $k => $_) {
                $r[$k] = array_values(array_unique(array_merge($r[$k], array_filter(array_map('trim', (array)($f[$k] ?? []))))));
            }
        }

        return $r;
    }

    public static function normalizarDominios(string $texto): array
    {
        $lista = [];
        foreach (preg_split('/[\s,;]+/', mb_strtolower($texto)) as $d) {
            $d = preg_replace('#^(https?://)?(\*\.|@)?#', '', trim($d));
            $d = explode('/', $d)[0];
            if (preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $d)) {
                $lista[] = $d;
            }
        }

        return array_values(array_unique($lista));
    }

    /** Ajusta clientes, regras, retenção do log e o nome do portal no AdGuard. */
    public function sincronizar(): array
    {
        if (!$this->ativo()) {
            return ['success' => true, 'message' => 'Filtro desativado.'];
        }
        $global = $this->config->json('filtro_global');
        $perfis = [];
        foreach ($this->repo->perfis() as $p) {
            $perfis[(int)$p['id']] = $p;
        }

        // Clientes desejados: um por pessoa com sessão ativa (IPs das sessões).
        $desejados = [];
        $wifiSvc = new WifiPortalService();
        foreach ($this->repo->sessoesAtivas() as $s) {
            if (!$s['ip'] || !$s['pessoa_id']) {
                continue;
            }
            $nome = 'wifi-p' . (int)$s['pessoa_id'];
            if (!isset($desejados[$nome])) {
                $pessoa = $this->repo->pessoa((int)$s['pessoa_id']);
                $perfil = $perfis[(int)$s['perfil_id']] ?? null;
                if (!$pessoa || !$perfil) {
                    continue;
                }
                $desejados[$nome] = ['ids' => [], 'filtro' => self::combinar($global, $wifiSvc->regras($perfil, $pessoa)['filtro'])];
            }
            $desejados[$nome]['ids'][] = $s['ip'];
        }

        [, $atuais] = $this->api('GET', 'clients');
        $existentes = [];
        foreach ($atuais['clients'] ?? [] as $c) {
            if (str_starts_with($c['name'] ?? '', 'wifi-p')) {
                $existentes[$c['name']] = $c;
            }
        }
        // IP que mudou de dono precisa sair do cliente antigo antes de entrar no novo.
        foreach ($existentes as $nome => $c) {
            if (!isset($desejados[$nome])) {
                $this->api('POST', 'clients/delete', ['name' => $nome]);
            }
        }
        foreach ($desejados as $nome => $d) {
            $f = $d['filtro'];
            $cliente = [
                'name' => $nome,
                'ids' => array_values(array_unique($d['ids'])),
                'use_global_settings' => false,
                'filtering_enabled' => true,
                'parental_enabled' => in_array('adulto', $f['categorias'], true),
                'safebrowsing_enabled' => in_array('malware', $f['categorias'], true),
                'safe_search' => ['enabled' => in_array('busca_segura', $f['categorias'], true), 'bing' => true, 'duckduckgo' => true, 'google' => true,
                    'pixabay' => true, 'yandex' => true, 'youtube' => true, 'ecosia' => true],
                'use_global_blocked_services' => false,
                'blocked_services' => $f['servicos'],
                'tags' => [],
                'upstreams' => [],
                'ignore_querylog' => false,
                'ignore_statistics' => false,
            ];
            if (isset($existentes[$nome])) {
                $this->api('POST', 'clients/update', ['name' => $nome, 'data' => $cliente]);
            } else {
                $this->api('POST', 'clients/add', $cliente);
            }
        }

        $this->sincronizarRegras($desejados);
        $this->sincronizarLog();
        $this->sincronizarNomePortal();

        return ['success' => true, 'message' => count($desejados) . ' pessoa(s) conectada(s) com filtro aplicado.'];
    }

    /** Regras de domínio por cliente ($client=...), num bloco próprio que não mexe no que a TI escreveu à mão. */
    private function sincronizarRegras(array $desejados): void
    {
        $porDominio = ['bloquear' => [], 'liberar' => []];
        foreach ($desejados as $nome => $d) {
            $bloquear = $d['filtro']['bloquear'];
            if (in_array('apostas', $d['filtro']['categorias'], true)) {
                $bloquear = array_merge($bloquear, self::DOMINIOS_APOSTAS);
            }
            foreach (array_unique($bloquear) as $dom) {
                $porDominio['bloquear'][$dom][] = $nome;
            }
            foreach ($d['filtro']['liberar'] as $dom) {
                $porDominio['liberar'][$dom][] = $nome;
            }
        }
        $bloco = [self::INICIO_BLOCO];
        foreach ($porDominio['bloquear'] as $dom => $clientes) {
            $bloco[] = '||' . $dom . '^$client=' . implode('|', $clientes);
        }
        foreach ($porDominio['liberar'] as $dom => $clientes) {
            $bloco[] = '@@||' . $dom . '^$important,client=' . implode('|', $clientes);
        }
        $bloco[] = self::FIM_BLOCO;

        [, $st] = $this->api('GET', 'filtering/status');
        $regras = $st['user_rules'] ?? [];
        $ini = array_search(self::INICIO_BLOCO, $regras, true);
        $fim = array_search(self::FIM_BLOCO, $regras, true);
        if ($ini !== false && $fim !== false && $fim > $ini) {
            array_splice($regras, $ini, $fim - $ini + 1);
        }
        $regras = array_merge(array_values(array_filter($regras, fn ($l) => $l !== '')), $bloco);
        $this->api('POST', 'filtering/set_rules', ['rules' => $regras]);
    }

    /** Sites visitados: guarda só o configurado (padrão 7 dias, para diagnóstico). */
    private function sincronizarLog(): void
    {
        $dias = max(1, min(90, $this->config->int('filtro_log_dias')));
        $this->api('PUT', 'querylog/config/update', ['enabled' => true, 'anonymize_client_ip' => false, 'interval' => $dias * 86400000, 'ignored' => []]);
        $this->api('PUT', 'stats/config/update', ['enabled' => true, 'interval' => $dias * 86400000, 'ignored' => []]);
    }

    /** O nome HTTPS do portal resolve para o IP do servidor dentro da rede. */
    private function sincronizarNomePortal(): void
    {
        $dominio = $this->config->get('https_dominio');
        $ip = $this->config->ipPortal();
        if ($dominio === '' || $ip === '') {
            return;
        }
        [, $lista] = $this->api('GET', 'rewrite/list');
        foreach ((array)$lista as $r) {
            if (($r['domain'] ?? '') === $dominio) {
                if (($r['answer'] ?? '') === $ip) {
                    return;
                }
                $this->api('POST', 'rewrite/delete', ['domain' => $r['domain'], 'answer' => $r['answer']]);
            }
        }
        $this->api('POST', 'rewrite/add', ['domain' => $dominio, 'answer' => $ip]);
    }

    // ================================================================ consulta (diagnóstico)

    /** Últimos sites consultados (log do AdGuard), opcionalmente de um IP. */
    public function sitesVisitados(?string $ip = null, int $limite = 200, ?string $busca = null): array
    {
        $q = ['limit' => $limite];
        if ($ip) {
            $q['search'] = $ip;
        } elseif ($busca) {
            $q['search'] = $busca;
        }
        [, $r] = $this->api('GET', 'querylog?' . http_build_query($q));
        $lista = [];
        foreach ($r['data'] ?? [] as $l) {
            $lista[] = [
                'quando' => $l['time'] ?? '',
                'ip' => $l['client'] ?? '',
                'cliente' => $l['client_info']['name'] ?? '',
                'dominio' => $l['question']['name'] ?? '',
                'bloqueado' => str_starts_with((string)($l['reason'] ?? ''), 'Filtered'),
                'motivo' => $l['reason'] ?? '',
            ];
        }

        return $lista;
    }
}
