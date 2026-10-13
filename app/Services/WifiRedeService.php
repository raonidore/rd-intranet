<?php

namespace App\Services;

use App\Repositories\AtivoRepository;
use App\Services\Rede\DriverSwitch;
use App\Services\Rede\RedeDrivers;

/**
 * Assistente de rede do portal: monta, num passo só, a rede de visitantes
 * (VLAN + DHCP com o DNS do filtro), o Wi-Fi aberto com portal e o portal
 * externo apontando para o RD.Intranet; e leva a VLAN tagueada pelos
 * switches que não são do mesmo fabricante (ex: TP-Link entre o gateway
 * UniFi e o AP). Tudo pelos drivers de Rede\ -- marca nova = driver novo.
 */
class WifiRedeService
{
    private WifiConfigService $config;

    public function __construct()
    {
        $this->config = new WifiConfigService();
    }

    /** Situação atual: controlador, redes, Wi-Fis, portal e switches que precisam da VLAN. */
    public function diagnostico(): array
    {
        $unifi = new UnifiService();
        $d = [
            'controlador' => $unifi->configurado(),
            'ip' => $this->config->ipPortal(),
            'redes' => [],
            'wifis' => [],
            'portal' => null,
            'switches' => [],
        ];
        if (!$d['controlador']) {
            return $d;
        }
        foreach ($unifi->listarRedesConfiguradas() as $r) {
            if (($r['purpose'] ?? '') === 'wan' || ($r['purpose'] ?? '') === 'remote-user-vpn' || ($r['purpose'] ?? '') === 'vpn-client') {
                continue;
            }
            $d['redes'][] = [
                'id' => $r['_id'], 'nome' => $r['name'] ?? '', 'vlan' => !empty($r['vlan_enabled']) ? (int)($r['vlan'] ?? 0) : 1,
                'subnet' => $r['ip_subnet'] ?? '', 'dns' => $r['dhcpd_dns_1'] ?? '', 'isolada' => !empty($r['network_isolation_enabled']),
            ];
        }
        foreach ($unifi->listarWifis() as $w) {
            $d['wifis'][] = ['id' => $w['_id'], 'nome' => $w['name'] ?? '', 'portal' => !empty($w['is_guest']), 'rede_id' => $w['networkconf_id'] ?? '',
                'aberta' => ($w['security'] ?? '') === 'open', 'ativa' => !empty($w['enabled'])];
        }
        $p = $unifi->configuracaoPortal();
        if ($p) {
            $d['portal'] = ['ligado' => !empty($p['portal_enabled']), 'modo' => $p['auth'] ?? '', 'ip' => $p['custom_ip'] ?? '',
                'aponta_para_nos' => ($p['auth'] ?? '') === 'custom' && ($p['custom_ip'] ?? '') === $d['ip']];
        }
        foreach ((new AtivoRepository())->listar() as $a) {
            if (($a['tipo_slug'] ?? '') !== 'switch') {
                continue;
            }
            $detalhes = json_decode($a['detalhes'] ?? '', true) ?: [];
            if (AtivoService::fonteVlanSwitch($a, $detalhes) !== 'omada') {
                continue; // UniFi leva todas as VLANs do site sozinho
            }
            $driver = RedeDrivers::paraSwitch($a);
            $portas = [];
            foreach ($detalhes['omada_portas'] ?? [] as $porta) {
                $disp = $porta['dispositivos'] ?? [];
                $portas[] = [
                    'porta' => $porta['porta'], 'link' => !empty($porta['link']),
                    'vlans' => array_map(fn ($v) => (int)$v['id'], $porta['vlans'] ?? []),
                    'resumo' => implode(', ', array_slice(array_filter(array_map(fn ($x) => $x['hostname'] ?? ($x['ip'] ?? ''), $disp)), 0, 3)),
                    'sugerida' => (bool)array_filter($disp, fn ($x) => !empty($x['lldp'])),
                ];
            }
            $d['switches'][] = [
                'id' => (int)$a['id'], 'nome' => $a['nome'], 'ip' => $a['ip'], 'driver' => $driver?->nome(),
                'pode' => $driver && in_array(DriverSwitch::ADICIONAR_TAG, $driver->capacidades(), true), 'portas' => $portas,
            ];
        }

        return $d;
    }

    /**
     * Monta a rede do portal. $dados: vlan, nome, subnet, dhcp_inicio, dhcp_fim,
     * ssid, rede_existente (id, opcional), portas[ativo_id][] = porta.
     * Cada passo é registrado; para no primeiro erro dizendo o que já foi feito.
     */
    public function aplicar(array $dados): array
    {
        $unifi = new UnifiService();
        if (!$unifi->configurado()) {
            return ['success' => false, 'message' => 'Integração UniFi não configurada.', 'passos' => []];
        }
        $ip = $this->config->ipPortal();
        $passos = [];
        $falha = function (string $msg) use (&$passos) {
            AuditService::registrar('Wi-Fi', 'Assistente de rede', 'Falhou: ' . $msg . ' Já feito: ' . (implode(' | ', $passos) ?: 'nada') . '.');

            return ['success' => false, 'message' => $msg, 'passos' => $passos];
        };
        $vlan = (int)($dados['vlan'] ?? 0);
        $redeId = trim((string)($dados['rede_existente'] ?? ''));
        $filtro = (new WifiFiltroService())->ativo();

        // 1. Rede (VLAN) de visitantes
        if ($redeId === '') {
            $nome = trim((string)($dados['nome'] ?? ''));
            $subnet = trim((string)($dados['subnet'] ?? ''));
            $inicio = trim((string)($dados['dhcp_inicio'] ?? ''));
            $fim = trim((string)($dados['dhcp_fim'] ?? ''));
            if ($vlan < 2 || $vlan > 4094 || $nome === '' || !AtivoService::redeValida($subnet)
                || !AtivoService::ipNaRede($inicio, $subnet) || !AtivoService::ipNaRede($fim, $subnet) || ip2long($inicio) > ip2long($fim)) {
                return ['success' => false, 'message' => 'Confira VLAN (2-4094), nome, sub-rede (ex.: 10.50.0.1/24) e a faixa de DHCP dentro dela.', 'passos' => []];
            }
            $campos = [
                'name' => $nome, 'purpose' => 'corporate', 'vlan_enabled' => true, 'vlan' => $vlan, 'networkgroup' => 'LAN', 'enabled' => true,
                'ip_subnet' => $subnet, 'dhcpd_enabled' => true, 'dhcpd_start' => $inicio, 'dhcpd_stop' => $fim,
                // Sem "isolar rede": a política de convidado do UniFi já bloqueia as redes privadas
                // (restricted_subnet) e libera só o servidor (allowed_subnet); isolar cortaria o portal e o DNS.
                'dhcpd_leasetime' => 3600, 'network_isolation_enabled' => false, 'internet_access_enabled' => true,
            ];
            if ($filtro) {
                $campos += ['dhcpd_dns_enabled' => true, 'dhcpd_dns_1' => $ip];
            }
            $r = $unifi->criarRede($campos);
            if (!$r['success']) {
                return $falha($r['message']);
            }
            $redeId = $r['id'];
            $passos[] = "Rede \"{$nome}\" (VLAN {$vlan}, {$subnet}, DHCP {$inicio}-{$fim}" . ($filtro ? ", DNS {$ip}" : '') . ') criada no UniFi';
        } elseif ($filtro) {
            $r = $unifi->editarRede($redeId, ['dhcpd_dns_enabled' => true, 'dhcpd_dns_1' => $ip]);
            $passos[] = $r['success'] ? "DNS da rede existente apontado para o filtro ({$ip})" : 'Aviso: não foi possível trocar o DNS da rede existente: ' . $r['message'];
        }

        // 2. Wi-Fi com portal
        $ssid = trim((string)($dados['ssid'] ?? ''));
        if ($ssid !== '') {
            if (mb_strlen($ssid) > 32) {
                return $falha('Nome do Wi-Fi com mais de 32 caracteres.');
            }
            $r = $unifi->criarWifiPortal($ssid, $redeId);
            if (!$r['success']) {
                return $falha($r['message']);
            }
            $passos[] = "Wi-Fi \"{$ssid}\" (aberto, isolado, com portal) criado";
        }

        // 3. Portal externo apontando para este servidor
        $r = $unifi->configurarPortalExterno($ip, max(60, (int)($dados['expira_min'] ?? 480)));
        if (!$r['success']) {
            return $falha($r['message']);
        }
        $passos[] = "Portal externo do UniFi apontando para {$ip}";

        // 4. VLAN tagueada nos switches de outras marcas
        if ($vlan >= 2) {
            $ativos = new AtivoRepository();
            foreach ((array)($dados['portas'] ?? []) as $ativoId => $portas) {
                $ativo = $ativos->buscarPorId((int)$ativoId);
                $driver = $ativo ? RedeDrivers::paraSwitch($ativo) : null;
                if (!$driver || !in_array(DriverSwitch::ADICIONAR_TAG, $driver->capacidades(), true)) {
                    return $falha('Switch ' . ($ativo['nome'] ?? $ativoId) . ' sem acesso de configuração (verifique a credencial SSH).');
                }
                $r = $driver->criarVlan($ativo, $vlan, mb_substr(preg_replace('/[^A-Za-z0-9_-]/', '', (string)($dados['nome'] ?? 'WiFi')) ?: 'WiFi', 0, 16));
                if (!$r['success'] && !str_contains(mb_strtolower($r['message']), 'exist')) {
                    return $falha("{$ativo['nome']}: " . $r['message']);
                }
                foreach ((array)$portas as $porta) {
                    $r = $driver->adicionarVlanTagueada($ativo, (string)$porta, $vlan);
                    if (!$r['success']) {
                        return $falha("{$ativo['nome']} porta {$porta}: " . $r['message']);
                    }
                    $passos[] = "{$ativo['nome']}: VLAN {$vlan} tagueada na porta {$porta}";
                }
            }
        }

        $this->config->set('controlador', 'unifi');
        AuditService::registrar('Wi-Fi', 'Assistente de rede', implode(' | ', $passos) . '.');

        return ['success' => true, 'message' => 'Rede do portal pronta.', 'passos' => $passos];
    }
}
