<?php

namespace App\Services;

/**
 * Cloudflare Tunnel -- expõe esta intranet publicamente através da rede
 * da Cloudflare (hostname estável, sem abrir porta nenhuma de entrada).
 * API em https://api.cloudflare.com/client/v4. O connector token (usado
 * pra instalar o serviço do cloudflared) nunca fica salvo -- é uma
 * leitura repetível da própria API (GET .../token), buscado de novo toda
 * vez que precisa (re)instalar o serviço, em vez de guardar uma segunda
 * cópia do mesmo segredo.
 *
 * criar() usa uma pilha de rollback: cada chamada que cria um recurso
 * (túnel, registro DNS) empilha uma função de desfazer, executada em
 * ordem reversa se um passo seguinte falhar -- nunca deixa túnel/DNS
 * órfão na conta Cloudflare. remover() já é o inverso: percorre os
 * mesmos recursos best-effort (continua mesmo se algum já tiver sido
 * removido manualmente no painel), porque o objetivo ali é limpar o
 * estado local, não abortar por drift externo.
 */
class CloudflareTunnelService
{
    private const CHAVE_API_TOKEN_CIFRADO = 'cloudflare_tunnel_api_token_cifrado';
    private const CHAVE_ACCOUNT_ID = 'cloudflare_tunnel_account_id';
    private const CHAVE_ZONE_ID = 'cloudflare_tunnel_zone_id';
    private const CHAVE_HOSTNAME = 'cloudflare_tunnel_hostname';
    private const CHAVE_TUNNEL_ID = 'cloudflare_tunnel_id';
    private const CHAVE_DNS_RECORD_ID = 'cloudflare_tunnel_dns_record_id';
    private const CHAVE_CRIADO_EM = 'cloudflare_tunnel_criado_em';

    private const API_BASE = 'https://api.cloudflare.com/client/v4';
    private const SCRIPT = '/opt/rdtecnologia/scripts/cloudflared_web.sh';
    private const NOME_TUNEL = 'rd-intranet';

    private LinuxService $linux;

    public function __construct()
    {
        $this->linux = new LinuxService();
    }

    public function configurado(): bool
    {
        return $this->apiTokenAtual() !== null
            && $this->accountIdAtual() !== ''
            && $this->zoneIdAtual() !== '';
    }

    public function accountIdAtual(): string
    {
        return trim((string)(ConfigService::get(self::CHAVE_ACCOUNT_ID, '') ?: ''));
    }

    public function zoneIdAtual(): string
    {
        return trim((string)(ConfigService::get(self::CHAVE_ZONE_ID, '') ?: ''));
    }

    public function hostnameAtual(): string
    {
        return trim((string)(ConfigService::get(self::CHAVE_HOSTNAME, '') ?: ''));
    }

    public function tunelCriado(): bool
    {
        return (ConfigService::get(self::CHAVE_TUNNEL_ID, '') ?: '') !== '';
    }

    public function salvarCredenciais(string $apiToken, string $accountId, string $zoneId, string $hostname): bool
    {
        $apiToken = trim($apiToken);
        $accountId = trim($accountId);
        $zoneId = trim($zoneId);
        $hostname = trim($hostname);

        if ($accountId === '' || $zoneId === '' || $hostname === '' || ($apiToken === '' && !$this->configurado())) {
            NotificationService::error('Preencha API Token, Account ID, Zone ID e o hostname público.');
            return false;
        }

        if (!preg_match('/^[a-zA-Z0-9.-]+$/', $hostname)) {
            NotificationService::error('Hostname inválido.');
            return false;
        }

        if ($apiToken !== '') {
            ConfigService::set(self::CHAVE_API_TOKEN_CIFRADO, CryptoService::encriptar($apiToken));
        }
        ConfigService::set(self::CHAVE_ACCOUNT_ID, $accountId);
        ConfigService::set(self::CHAVE_ZONE_ID, $zoneId);
        ConfigService::set(self::CHAVE_HOSTNAME, $hostname);

        AuditService::registrar('Infraestrutura', 'Túneis', 'Credenciais do Cloudflare Tunnel atualizadas.');
        NotificationService::success('Credenciais salvas.');

        return true;
    }

    /** Estado ao vivo do serviço local (nunca cacheado) + o que já foi persistido sobre o túnel. */
    public function status(): array
    {
        return [
            'instalado' => $this->linux->executar('command -v cloudflared')['success'],
            'ativo' => trim($this->linux->executar('systemctl is-active cloudflared')['output']) === 'active',
            'tunel_criado' => $this->tunelCriado(),
            'hostname' => $this->hostnameAtual(),
        ];
    }

    public function criar(): array
    {
        if (!$this->configurado()) {
            return ['success' => false, 'message' => 'Configure API Token, Account ID, Zone ID e o hostname antes de criar o túnel.'];
        }

        if ($this->tunelCriado()) {
            return ['success' => false, 'message' => 'Já existe um túnel criado -- remova antes de criar outro.'];
        }

        $token = $this->apiTokenAtual();
        $accountId = $this->accountIdAtual();
        $zoneId = $this->zoneIdAtual();
        $hostname = $this->hostnameAtual();

        // Instala o pacote fora da pilha de rollback -- idempotente, não
        // faz sentido desinstalar só porque um passo posterior falhou
        // (mesmo raciocínio do certbot ficar instalado mesmo se uma
        // tentativa de emissão Let's Encrypt falhar).
        $instalacao = $this->linux->executarScript(self::SCRIPT, ['instalar']);
        $dadosInstalacao = json_decode($instalacao['output'], true);
        if (!($dadosInstalacao['success'] ?? false)) {
            return ['success' => false, 'message' => $dadosInstalacao['message'] ?? 'Falha ao instalar o cloudflared.'];
        }

        $desfazer = [];

        try {
            $resposta = $this->chamarApi('POST', "/accounts/{$accountId}/cfd_tunnel", [
                'name' => self::NOME_TUNEL,
                'config_src' => 'cloudflare',
            ], $token);
            if (!$resposta['sucesso']) {
                return ['success' => false, 'message' => $resposta['mensagem']];
            }
            $tunnelId = $resposta['dados']['result']['id'] ?? null;
            if (!$tunnelId) {
                return ['success' => false, 'message' => 'A Cloudflare não retornou o ID do túnel criado.'];
            }
            $desfazer[] = fn() => $this->chamarApi('DELETE', "/accounts/{$accountId}/cfd_tunnel/{$tunnelId}", null, $token);

            $resposta = $this->chamarApi('PUT', "/accounts/{$accountId}/cfd_tunnel/{$tunnelId}/configurations", [
                'config' => [
                    'ingress' => [
                        ['hostname' => $hostname, 'service' => 'http://localhost:80'],
                        ['service' => 'http_status:404'],
                    ],
                ],
            ], $token);
            if (!$resposta['sucesso']) {
                $this->executarDesfazer($desfazer);
                return ['success' => false, 'message' => $resposta['mensagem']];
            }

            $resposta = $this->chamarApi('GET', "/accounts/{$accountId}/cfd_tunnel/{$tunnelId}/token", null, $token);
            $connectorToken = $resposta['dados']['result'] ?? null;
            if (!$resposta['sucesso'] || !$connectorToken) {
                $this->executarDesfazer($desfazer);
                return ['success' => false, 'message' => $resposta['mensagem'] ?: 'Não foi possível obter o token de conexão do túnel.'];
            }

            $resposta = $this->chamarApi('POST', "/zones/{$zoneId}/dns_records", [
                'type' => 'CNAME',
                'name' => $hostname,
                'content' => "{$tunnelId}.cfargotunnel.com",
                'proxied' => true,
            ], $token);
            if (!$resposta['sucesso']) {
                $this->executarDesfazer($desfazer);
                return ['success' => false, 'message' => $resposta['mensagem']];
            }
            $dnsRecordId = $resposta['dados']['result']['id'] ?? null;
            if ($dnsRecordId) {
                $desfazer[] = fn() => $this->chamarApi('DELETE', "/zones/{$zoneId}/dns_records/{$dnsRecordId}", null, $token);
            }

            $comando = 'sudo ' . escapeshellarg(self::SCRIPT) . ' servico_instalar';
            $resultado = $this->linux->executarComEntrada($comando, $connectorToken);
            $dadosServico = json_decode($resultado['output'], true);
            if (!($dadosServico['success'] ?? false)) {
                $this->executarDesfazer($desfazer);
                return ['success' => false, 'message' => $dadosServico['message'] ?? 'Falha ao instalar o serviço do cloudflared.'];
            }

            ConfigService::set(self::CHAVE_TUNNEL_ID, $tunnelId);
            ConfigService::set(self::CHAVE_DNS_RECORD_ID, (string)$dnsRecordId);
            ConfigService::set(self::CHAVE_CRIADO_EM, date('Y-m-d H:i:s'));
            AuditService::registrar('Infraestrutura', 'Túneis', "Túnel Cloudflare criado, expondo https://{$hostname}.");

            return ['success' => true, 'message' => "Túnel criado -- https://{$hostname} já deve responder em alguns segundos."];
        } catch (\Throwable $e) {
            $this->executarDesfazer($desfazer);
            return ['success' => false, 'message' => 'Erro inesperado ao criar o túnel: ' . $e->getMessage()];
        }
    }

    public function remover(): array
    {
        $token = $this->apiTokenAtual();
        $accountId = $this->accountIdAtual();
        $zoneId = $this->zoneIdAtual();
        $tunnelId = ConfigService::get(self::CHAVE_TUNNEL_ID, '') ?: '';
        $dnsRecordId = ConfigService::get(self::CHAVE_DNS_RECORD_ID, '') ?: '';

        $this->linux->executarScript(self::SCRIPT, ['servico_remover']);

        if ($token !== null && $dnsRecordId !== '') {
            $this->chamarApi('DELETE', "/zones/{$zoneId}/dns_records/{$dnsRecordId}", null, $token);
        }
        if ($token !== null && $tunnelId !== '') {
            $this->chamarApi('DELETE', "/accounts/{$accountId}/cfd_tunnel/{$tunnelId}", null, $token);
        }

        ConfigService::set(self::CHAVE_TUNNEL_ID, '');
        ConfigService::set(self::CHAVE_DNS_RECORD_ID, '');
        ConfigService::set(self::CHAVE_CRIADO_EM, '');

        AuditService::registrar('Infraestrutura', 'Túneis', 'Túnel Cloudflare removido.');

        return ['success' => true, 'message' => 'Túnel removido.'];
    }

    private function executarDesfazer(array $acoes): void
    {
        foreach (array_reverse($acoes) as $acao) {
            try {
                $acao();
            } catch (\Throwable $e) {
                // melhor esforço -- uma falha ao desfazer não pode mascarar o erro original
            }
        }
    }

    private function apiTokenAtual(): ?string
    {
        $cifrado = ConfigService::get(self::CHAVE_API_TOKEN_CIFRADO, '');
        if (!$cifrado) {
            return null;
        }

        try {
            return CryptoService::decriptar($cifrado);
        } catch (\RuntimeException $e) {
            return null;
        }
    }

    /** @return array{sucesso:bool, dados:mixed, mensagem:string} */
    private function chamarApi(string $metodo, string $caminho, ?array $corpo, string $token): array
    {
        $ch = curl_init(self::API_BASE . $caminho);

        $cabecalhos = ['Authorization: Bearer ' . $token];
        if ($corpo !== null) {
            $cabecalhos[] = 'Content-Type: application/json';
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $metodo,
            CURLOPT_HTTPHEADER => $cabecalhos,
            CURLOPT_TIMEOUT => 20,
        ]);

        if (in_array($metodo, ['POST', 'PUT', 'PATCH'], true)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $corpo !== null ? json_encode($corpo) : '');
        }

        $resposta = curl_exec($ch);
        $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erroCurl = curl_error($ch);
        curl_close($ch);

        if ($resposta === false) {
            return ['sucesso' => false, 'dados' => null, 'mensagem' => "Erro de comunicação com a Cloudflare: {$erroCurl}"];
        }

        $dados = $resposta !== '' ? json_decode($resposta, true) : null;

        if ($codigo >= 200 && $codigo < 300 && ($dados['success'] ?? true)) {
            return ['sucesso' => true, 'dados' => $dados, 'mensagem' => ''];
        }

        $mensagemErro = $dados['errors'][0]['message'] ?? "Erro HTTP {$codigo} ao falar com a Cloudflare.";

        return ['sucesso' => false, 'dados' => $dados, 'mensagem' => $mensagemErro];
    }
}
