<?php

namespace App\Services;

/**
 * Tailscale (VPN mesh) -- conecta este servidor a uma rede privada
 * (tailnet) pra acesso direto de qualquer dispositivo autorizado, sem
 * depender de VPN de terceiros. API em https://api.tailscale.com só pra
 * gerar um authkey de curta duração (1h, não reutilizável); o "tailscale
 * up" em si roda local via scripts/system/tailscale_web.sh, authkey
 * entregue por STDIN (nunca argv/disco do lado PHP).
 */
class TailscaleService
{
    private const CHAVE_API_TOKEN_CIFRADO = 'tailscale_api_token_cifrado';
    private const CHAVE_TAILNET = 'tailscale_tailnet';
    private const CHAVE_HOSTNAME = 'tailscale_hostname_desejado';
    private const CHAVE_CONECTADO_DESDE = 'tailscale_conectado_desde';

    private const API_BASE = 'https://api.tailscale.com/api/v2';
    private const SCRIPT = '/opt/rdtecnologia/scripts/tailscale_web.sh';

    private LinuxService $linux;

    public function __construct()
    {
        $this->linux = new LinuxService();
    }

    public function configurado(): bool
    {
        return $this->apiTokenAtual() !== null;
    }

    public function tailnetAtual(): string
    {
        return trim((string)(ConfigService::get(self::CHAVE_TAILNET, '') ?: '')) ?: '-';
    }

    public function hostnameDesejadoAtual(): string
    {
        return trim((string)(ConfigService::get(self::CHAVE_HOSTNAME, '') ?: ''));
    }

    public function salvarCredenciais(string $apiToken, string $tailnet, string $hostname): bool
    {
        $apiToken = trim($apiToken);
        $tailnet = trim($tailnet);
        $hostname = trim($hostname);

        if ($apiToken === '' && !$this->configurado()) {
            NotificationService::error('Informe o API Token do Tailscale.');
            return false;
        }

        if ($hostname !== '' && !preg_match('/^[a-zA-Z0-9.-]{1,63}$/', $hostname)) {
            NotificationService::error('Hostname inválido.');
            return false;
        }

        if ($apiToken !== '') {
            ConfigService::set(self::CHAVE_API_TOKEN_CIFRADO, CryptoService::encriptar($apiToken));
        }
        ConfigService::set(self::CHAVE_TAILNET, $tailnet !== '' ? $tailnet : '-');
        ConfigService::set(self::CHAVE_HOSTNAME, $hostname);

        AuditService::registrar('Infraestrutura', 'Túneis', 'Credenciais do Tailscale atualizadas.');
        NotificationService::success('Credenciais salvas.');

        return true;
    }

    /** Estado ao vivo (nunca cacheado) -- lê o binário/daemon local, não a API. */
    public function status(): array
    {
        $resultado = $this->linux->executarScript(self::SCRIPT, ['status']);
        $dados = json_decode($resultado['output'], true);

        // Saída que não parseia como JSON (script ainda não sincronizado,
        // sudo falhou, etc.) nunca pode ser lida como "instalado" por
        // eliminação -- só conta como instalado quando o script realmente
        // confirmar um BackendState conhecido.
        if (!is_array($dados) || !isset($dados['BackendState'])) {
            return [
                'instalado' => false,
                'conectado' => false,
                'backend_state' => 'Unknown',
                'ip' => null,
                'hostname_tailnet' => null,
                'conectado_desde' => null,
            ];
        }

        $backendState = $dados['BackendState'];
        $self = $dados['Self'] ?? null;

        return [
            'instalado' => $backendState !== 'NotInstalled',
            'conectado' => $backendState === 'Running',
            'backend_state' => $backendState,
            'ip' => $self['TailscaleIPs'][0] ?? null,
            'hostname_tailnet' => $self['DNSName'] ?? null,
            'conectado_desde' => ConfigService::get(self::CHAVE_CONECTADO_DESDE, '') ?: null,
        ];
    }

    public function conectar(): array
    {
        $token = $this->apiTokenAtual();
        if ($token === null) {
            return ['success' => false, 'message' => 'Configure o API Token do Tailscale antes de conectar.'];
        }

        if (!$this->status()['instalado']) {
            $instalacao = $this->linux->executarScript(self::SCRIPT, ['instalar']);
            $dadosInstalacao = json_decode($instalacao['output'], true);
            if (!($dadosInstalacao['success'] ?? false)) {
                return ['success' => false, 'message' => $dadosInstalacao['message'] ?? 'Falha ao instalar o Tailscale.'];
            }
        }

        $authkey = $this->gerarAuthKey($token);
        if (!$authkey['sucesso']) {
            return ['success' => false, 'message' => $authkey['mensagem']];
        }

        $hostname = $this->hostnameDesejadoAtual();
        $comando = 'sudo ' . escapeshellarg(self::SCRIPT) . ' conectar' . ($hostname !== '' ? ' ' . escapeshellarg($hostname) : '');
        $resultado = $this->linux->executarComEntrada($comando, $authkey['dados']);
        $dados = json_decode($resultado['output'], true);

        if (!($dados['success'] ?? false)) {
            return ['success' => false, 'message' => $dados['message'] ?? 'Falha ao conectar ao Tailscale.'];
        }

        ConfigService::set(self::CHAVE_CONECTADO_DESDE, date('Y-m-d H:i:s'));
        AuditService::registrar('Infraestrutura', 'Túneis', 'Servidor conectado ao tailnet via Tailscale.');

        return ['success' => true, 'message' => 'Conectado ao tailnet.'];
    }

    public function desconectar(bool $logout = false): array
    {
        $args = ['desconectar'];
        if ($logout) {
            $args[] = '--logout';
        }

        $resultado = $this->linux->executarScript(self::SCRIPT, $args);
        $dados = json_decode($resultado['output'], true);

        if (!($dados['success'] ?? false)) {
            return ['success' => false, 'message' => $dados['message'] ?? 'Falha ao desconectar.'];
        }

        ConfigService::set(self::CHAVE_CONECTADO_DESDE, '');
        AuditService::registrar('Infraestrutura', 'Túneis', 'Servidor desconectado do tailnet' . ($logout ? ' (logout completo)' : '') . '.');

        return ['success' => true, 'message' => 'Desconectado.'];
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

    /**
     * Authkey reusável=false, expira em 1h -- de uso único pra essa
     * conexão, não fica guardado em lugar nenhum depois de usado.
     *
     * @return array{sucesso:bool, dados:?string, mensagem:string}
     */
    private function gerarAuthKey(string $token): array
    {
        $tailnet = rawurlencode($this->tailnetAtual());

        $ch = curl_init(self::API_BASE . "/tailnet/{$tailnet}/keys");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode([
                'capabilities' => [
                    'devices' => [
                        'create' => [
                            'reusable' => false,
                            'ephemeral' => false,
                            'preauthorized' => true,
                        ],
                    ],
                ],
                'expirySeconds' => 3600,
            ]),
            CURLOPT_TIMEOUT => 20,
        ]);

        $resposta = curl_exec($ch);
        $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erroCurl = curl_error($ch);
        curl_close($ch);

        if ($resposta === false) {
            return ['sucesso' => false, 'dados' => null, 'mensagem' => "Erro de comunicação com o Tailscale: {$erroCurl}"];
        }

        $dados = json_decode($resposta, true);

        if ($codigo >= 200 && $codigo < 300 && !empty($dados['key'])) {
            return ['sucesso' => true, 'dados' => $dados['key'], 'mensagem' => ''];
        }

        $mensagemErro = $dados['message'] ?? "Erro HTTP {$codigo} ao gerar authkey (confira o API Token/Tailnet).";

        return ['sucesso' => false, 'dados' => null, 'mensagem' => $mensagemErro];
    }
}
