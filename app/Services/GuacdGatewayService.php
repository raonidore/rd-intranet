<?php

namespace App\Services;

/**
 * Infraestrutura compartilhada de "sessão remota pelo navegador" -- guacd
 * (traduz o protocolo RDP/SSH/VNC) + ponte guacamole-lite (WebSocket, só
 * em 127.0.0.1) + proxy reverso do Apache (mesma porta HTTPS que o site já
 * usa, endpoint /rdp-ws -- nome herdado de quando só existia RDP, mas o
 * proxy e a ponte são inteiramente agnósticos de protocolo: o "type" fica
 * dentro do token cifrado, não na URL). Extraído de RdpService (que foi o
 * primeiro a usar) pra ser reaproveitado por qualquer módulo que precise
 * do mesmo gateway -- hoje RDP (Ativos) e SSH.
 *
 * Decisão de infraestrutura (documentada originalmente em RdpService):
 * proxy pela mesma porta do site em vez de porta própria, porque abrir
 * mais uma porta no roteador/NAT de cada servidor de cliente é inviável
 * administrando vários servidores atrás de NAT com mapeamento mínimo de
 * portas -- confirmado ao vivo.
 */
class GuacdGatewayService
{
    private const SCRIPT_GUACD = '/opt/rdtecnologia/scripts/guacd_instalar_web.sh';
    private const SCRIPT_BRIDGE = '/opt/rdtecnologia/scripts/guacamole_bridge_instalar_web.sh';
    private const SCRIPT_PROXY = '/opt/rdtecnologia/scripts/rdp_proxy_ativar_web.sh';
    private const VHOST_SSL = '/etc/apache2/sites-available/rd.intranet-ssl.conf';
    private const MARCA_PROXY = '# RD Intranet - proxy da ponte RDP pelo navegador';
    private const CHAVE_SEGREDO = '/etc/rd-intranet/db_secret.key';

    private LinuxService $linux;

    public function __construct()
    {
        $this->linux = new LinuxService();
    }

    /**
     * Estado ao vivo (nunca cacheado) do gateway. O vhost SSL é 644
     * root:root -- world-readable, confirmado, diferente do certificado em
     * si -- então dá pra checar o proxy direto por aqui, sem script root.
     */
    public function status(): array
    {
        return [
            'guacd_instalado' => $this->linux->executar('command -v guacd')['success'],
            'guacd_ativo' => trim($this->linux->executar('systemctl is-active guacd')['output']) === 'active',
            'bridge_ativo' => trim($this->linux->executar('systemctl is-active rd-guac-bridge')['output']) === 'active',
            'proxy_configurado' => is_readable(self::VHOST_SSL) && str_contains(file_get_contents(self::VHOST_SSL), self::MARCA_PROXY),
        ];
    }

    public function pronto(): bool
    {
        $status = $this->status();

        return $status['guacd_ativo'] && $status['bridge_ativo'] && $status['proxy_configurado'];
    }

    public function instalar(): array
    {
        $guacd = $this->resultadoScript(
            $this->linux->executarScript(self::SCRIPT_GUACD),
            'Falha ao instalar o guacd.'
        );
        if (!$guacd['success']) {
            return $guacd;
        }

        $bridge = $this->resultadoScript(
            $this->linux->executarScript(self::SCRIPT_BRIDGE),
            'Falha ao instalar a ponte de acesso remoto.'
        );
        if (!$bridge['success']) {
            return $bridge;
        }

        $proxy = $this->resultadoScript(
            $this->linux->executarScript(self::SCRIPT_PROXY),
            'Falha ao configurar o proxy no Apache.'
        );
        if (!$proxy['success']) {
            return $proxy;
        }

        return ['success' => true, 'message' => 'Suporte a acesso remoto pelo navegador pronto.'];
    }

    /**
     * Cifra o payload de conexão (JSON com type/settings) no formato que o
     * guacamole-lite espera: base64(JSON{iv,value}), AES-256-CBC com a
     * MESMA chave de 32 bytes já usada pelo CryptoService (reaproveitada
     * em vez de provisionar mais um segredo; o bridge lê o mesmo arquivo).
     */
    public function gerarToken(array $payloadConexao): ?string
    {
        $chave = $this->chaveCompartilhada();
        if ($chave === null) {
            return null;
        }

        $payload = json_encode(['connection' => $payloadConexao]);

        $iv = random_bytes(16);
        $cifrado = openssl_encrypt($payload, 'aes-256-cbc', $chave, OPENSSL_RAW_DATA, $iv);
        if ($cifrado === false) {
            return null;
        }

        $envelope = json_encode([
            'iv' => base64_encode($iv),
            'value' => base64_encode($cifrado),
        ]);

        return base64_encode($envelope);
    }

    private function chaveCompartilhada(): ?string
    {
        if (!is_readable(self::CHAVE_SEGREDO)) {
            return null;
        }

        $chave = base64_decode(trim(file_get_contents(self::CHAVE_SEGREDO)));

        return $chave !== false ? $chave : null;
    }

    private function resultadoScript(array $execResultado, string $mensagemPadrao): array
    {
        $dados = json_decode($execResultado['output'], true);

        if (is_array($dados) && isset($dados['success'])) {
            return [
                'success' => (bool)$dados['success'],
                'message' => (string)($dados['message'] ?? $mensagemPadrao),
                'detalhes' => $execResultado['output'],
            ];
        }

        return ['success' => false, 'message' => $mensagemPadrao, 'detalhes' => $execResultado['output']];
    }
}
