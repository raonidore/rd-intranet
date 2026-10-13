<?php

namespace App\Services;

/**
 * HTTPS do portal: nome sob o domínio da RD (ex: wifi-cliente.rd.inf.br)
 * apontando para o IP interno do servidor, com certificado Let's Encrypt
 * validado por DNS (TXT pela API do cPanel). Sem isso o navegador do
 * visitante mostra "não seguro" na tela de login.
 */
class WifiHttpsService
{
    public const SCRIPT_HTTPS = '/opt/rdtecnologia/scripts/wifi_https_web.sh';
    public const SCRIPT_APACHE = '/opt/rdtecnologia/scripts/wifi_portal_apache_web.sh';
    public const STATUS = '/var/lib/rd-intranet/wifi-https.json';

    private WifiConfigService $config;

    public function __construct()
    {
        $this->config = new WifiConfigService();
    }

    /** Publica (ou retira) o /guest no Apache. */
    public function publicarPortal(bool $ativar): array
    {
        return $this->rodar((new LinuxService())->executarScript(self::SCRIPT_APACHE, [$ativar ? 'ativar' : 'desativar']));
    }

    private function rodar(array $r): array
    {
        $json = json_decode(trim((string)$r['output']), true);

        return is_array($json) ? $json : ['success' => false, 'message' => 'Falha: ' . mb_substr(trim((string)$r['output']), -400)];
    }

    // ================================================================ cPanel (UAPI)

    private function uapi(string $funcao, array $q = []): array
    {
        $token = $this->config->segredo('cpanel_token_cifrado');
        if (!$token) {
            throw new \RuntimeException('Token da API do cPanel não configurado.');
        }
        $ch = curl_init(rtrim($this->config->get('cpanel_url'), '/') . '/execute/DNS/' . $funcao . '?' . http_build_query($q));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: cpanel ' . $this->config->get('cpanel_usuario') . ':' . $token],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60,
        ]);
        $resp = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erro = curl_error($ch);
        curl_close($ch);
        $json = json_decode((string)$resp, true);
        if ($resp === false || !is_array($json)) {
            throw new \RuntimeException("cPanel não respondeu (HTTP {$status}) {$erro}");
        }
        if (empty($json['status'])) {
            throw new \RuntimeException('cPanel recusou: ' . implode(' ', (array)($json['errors'] ?? ['erro desconhecido'])));
        }

        return (array)($json['data'] ?? []);
    }

    /** Registros da zona com nome/dados decodificados + serial atual. */
    private function zona(): array
    {
        $serial = null;
        $registros = [];
        foreach ($this->uapi('parse_zone', ['zone' => $this->config->get('cpanel_zona')]) as $r) {
            if (($r['type'] ?? '') !== 'record') {
                continue;
            }
            $dados = array_map('base64_decode', $r['data_b64'] ?? []);
            if ($r['record_type'] === 'SOA') {
                $serial = $dados[2] ?? null;
            }
            $registros[] = ['linha' => (int)$r['line_index'], 'tipo' => $r['record_type'], 'nome' => $this->nomeAbsoluto(base64_decode($r['dname_b64'] ?? '')), 'dados' => $dados];
        }

        return [$serial, $registros];
    }

    /** O cPanel devolve uns nomes absolutos ("x.rd.inf.br.") e outros relativos à zona ("x"). */
    private function nomeAbsoluto(string $nome): string
    {
        $zona = $this->config->get('cpanel_zona');
        if ($nome === '' || $nome === '@') {
            return $zona;
        }

        return str_ends_with($nome, '.') ? rtrim($nome, '.') : $nome . '.' . $zona;
    }

    public function testarCpanel(): array
    {
        try {
            [$serial, $registros] = $this->zona();

            return ['success' => true, 'message' => 'API do cPanel OK: zona ' . $this->config->get('cpanel_zona') . ' com ' . count($registros) . " registros (serial {$serial})."];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /** Cria/atualiza o registro A do nome do portal para o IP interno do servidor. */
    public function garantirRegistroA(string $dominio, string $ip): array
    {
        $zona = $this->config->get('cpanel_zona');
        if (!str_ends_with($dominio, '.' . $zona)) {
            return ['success' => false, 'message' => "O nome precisa terminar em .{$zona}."];
        }
        try {
            [$serial, $registros] = $this->zona();
            $fqdn = $dominio;
            $atual = null;
            foreach ($registros as $r) {
                if ($r['tipo'] === 'A' && $r['nome'] === $fqdn) {
                    $atual = $r;
                }
                if ($r['tipo'] === 'CNAME' && $r['nome'] === $fqdn) {
                    return ['success' => false, 'message' => "{$dominio} já existe como CNAME na zona -- escolha outro nome."];
                }
            }
            if ($atual && ($atual['dados'][0] ?? '') === $ip) {
                return ['success' => true, 'message' => "{$dominio} já aponta para {$ip}."];
            }
            $relativo = substr($dominio, 0, -strlen($zona) - 1);
            $registro = ['dname' => $relativo, 'ttl' => 300, 'record_type' => 'A', 'data' => [$ip]];
            if ($atual) {
                $this->uapi('mass_edit_zone', ['zone' => $zona, 'serial' => $serial, 'edit' => json_encode($registro + ['line_index' => $atual['linha']])]);
            } else {
                $this->uapi('mass_edit_zone', ['zone' => $zona, 'serial' => $serial, 'add' => json_encode($registro)]);
            }
            AuditService::registrar('Wi-Fi', 'DNS do portal', "{$dominio} -> {$ip} na zona {$zona}.");

            return ['success' => true, 'message' => "{$dominio} agora aponta para {$ip}."];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /** Registro A + certificado (DNS-01) + vhost 443. Pode levar alguns minutos. */
    public function emitir(string $email): array
    {
        $dominio = $this->config->get('https_dominio');
        $ip = $this->config->ipPortal();
        $token = $this->config->segredo('cpanel_token_cifrado');
        if ($dominio === '' || $ip === '' || !$token) {
            return ['success' => false, 'message' => 'Preencha o nome do portal, o IP do servidor e o token do cPanel.'];
        }
        $a = $this->garantirRegistroA($dominio, $ip);
        if (!$a['success']) {
            return $a;
        }
        $atual = $this->andamento();
        if ($atual && ($atual['success'] ?? false) === null && strtotime($atual['quando'] ?? '') > time() - 900) {
            return ['success' => false, 'message' => 'Já existe uma emissão em andamento. Aguarde.'];
        }
        // Em segundo plano: a espera pelo DNS leva minutos e o certbot mexe no
        // Apache -- não pode ficar pendurado numa requisição web.
        (new LinuxService())->executarScriptEmSegundoPlanoComEntrada(self::SCRIPT_HTTPS, [
            'emitir', $dominio, $email, $this->config->get('cpanel_url'), $this->config->get('cpanel_usuario'), $this->config->get('cpanel_zona'),
        ], $token . "\n");
        AuditService::registrar('Wi-Fi', 'HTTPS do portal', "Emissão do certificado para {$dominio} iniciada.");

        return ['success' => true, 'message' => "{$a['message']} Emitindo o certificado em segundo plano (de 1 a 4 minutos) -- acompanhe nesta tela."];
    }

    /** Último resultado da emissão em segundo plano (success null = em andamento). */
    public function andamento(): ?array
    {
        $json = @file_get_contents(self::STATUS);

        return $json ? json_decode($json, true) : null;
    }

    /** Chamado pela tela: quando a emissão termina bem, liga o HTTPS (uma vez por emissão). */
    public function concluirSeEmitido(): ?array
    {
        $a = $this->andamento();
        if (!$a || ($a['success'] ?? null) !== true || $this->config->get('https_concluido') === ($a['quando'] ?? '')) {
            return $a;
        }
        $this->config->set('https_concluido', (string)$a['quando']);
        $this->config->set('https_ativo', '1');
        AuditService::registrar('Wi-Fi', 'HTTPS do portal', 'Certificado emitido para ' . $this->config->get('https_dominio') . '; o portal passa a abrir por HTTPS.');
        try {
            (new WifiFiltroService())->sincronizar();
        } catch (\Throwable $e) {
            // filtro desligado/indisponível não impede o HTTPS
        }

        return $a;
    }

    public function status(): array
    {
        $dominio = $this->config->get('https_dominio');
        if ($dominio === '') {
            return ['success' => false, 'message' => 'Nome do portal não definido.'];
        }

        return $this->rodar((new LinuxService())->executarScript(self::SCRIPT_HTTPS, ['status', $dominio]));
    }
}
