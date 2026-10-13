<?php

namespace App\Services;

/**
 * Configurações gerais do Wi-Fi / Captive Portal (o que não é regra de
 * perfil): identidade do portal, LGPD, HTTPS, filtro e rede. Tudo em
 * `configuracoes`, com valor padrão aqui -- nenhuma regra fixa no código.
 */
class WifiConfigService
{
    private const PREFIXO = 'wifi_';

    private const PADROES = [
        'portal_ativo' => '0',
        'portal_titulo' => 'Wi-Fi',
        'portal_boas_vindas' => 'Entre com seu e-mail e senha ou faça seu cadastro para usar a internet.',
        'portal_cor' => '#0d7b83',
        'encarregado_nome' => '',
        'encarregado_email' => '',
        // LGPD: registros de conexão (dias) antes de anonimizar; sites visitados (dias) no filtro.
        'retencao_dias' => '365',
        'filtro_log_dias' => '7',
        // Filtro DNS (AdGuard Home no servidor).
        'filtro_ativo' => '0',
        'filtro_global' => '{"servicos":[],"categorias":["malware"],"bloquear":[],"liberar":[]}',
        'adguard_url' => 'http://127.0.0.1:3010',
        'adguard_usuario' => 'rdintranet',
        'adguard_senha_cifrada' => '',
        // HTTPS: nome do portal sob o domínio da RD + API do cPanel que hospeda o DNS.
        'https_dominio' => '',
        'https_ativo' => '0',
        // Proxy do cPanel na 443: a porta 2083 passa por um anti-bot que barra IPs de servidores.
        'cpanel_url' => 'https://cpanel.rd.inf.br',
        'cpanel_usuario' => 'rd',
        'cpanel_zona' => 'rd.inf.br',
        'cpanel_token_cifrado' => '',
        // Rede: qual controlador libera os aparelhos e o IP do servidor visto pelos aparelhos.
        'controlador' => 'unifi',
        'ip_portal' => '',
        // Proteção de login
        'tentativas_max' => '5',
        'tentativas_janela_min' => '15',
    ];

    public function get(string $chave): string
    {
        return (string)(ConfigService::get(self::PREFIXO . $chave, self::PADROES[$chave] ?? '') ?? (self::PADROES[$chave] ?? ''));
    }

    public function set(string $chave, string $valor): void
    {
        ConfigService::set(self::PREFIXO . $chave, $valor);
    }

    public function int(string $chave): int
    {
        return (int)$this->get($chave);
    }

    public function bool(string $chave): bool
    {
        return $this->get($chave) === '1';
    }

    public function json(string $chave): array
    {
        return json_decode($this->get($chave), true) ?: [];
    }

    public function segredo(string $chave): ?string
    {
        $cifrado = $this->get($chave);
        if ($cifrado === '') {
            return null;
        }
        try {
            return CryptoService::decriptar($cifrado);
        } catch (\RuntimeException $e) {
            return null;
        }
    }

    public function salvarSegredo(string $chave, string $valor): void
    {
        $this->set($chave, $valor === '' ? '' : CryptoService::encriptar($valor));
    }

    /** IP do servidor do RD.Intranet que os aparelhos do Wi-Fi alcançam (portal e DNS). */
    public function ipPortal(): string
    {
        $ip = $this->get('ip_portal');
        if ($ip !== '') {
            return $ip;
        }
        // Padrão: o IP da interface da rota padrão.
        if (preg_match('/src (\d+\.\d+\.\d+\.\d+)/', (string)@shell_exec('ip -4 route get 1.1.1.1 2>/dev/null'), $m)) {
            return $m[1];
        }

        return '';
    }

    /** Chave que assina o contexto (MAC/AP/URL) passado do HTTP pelo IP para o HTTPS pelo nome. */
    public function chavePortal(): string
    {
        $chave = $this->segredo('chave_portal_cifrada');
        if (!$chave) {
            $chave = bin2hex(random_bytes(32));
            $this->salvarSegredo('chave_portal_cifrada', $chave);
        }

        return $chave;
    }

    /** URL base do portal: HTTPS com o nome configurado, ou HTTP pelo IP. */
    public function urlPortal(): string
    {
        if ($this->bool('https_ativo') && $this->get('https_dominio') !== '') {
            return 'https://' . $this->get('https_dominio');
        }

        return 'http://' . $this->ipPortal();
    }
}
