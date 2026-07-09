<?php

namespace App\Services;

/**
 * Gestao de HTTPS (certificado digital) do painel. Suporta autoassinado
 * (sempre disponivel, cobre uso so-LAN), Let's Encrypt (precisa de dominio
 * publico) e importacao de certificado proprio. Toda troca de certificado
 * valida a configuracao do Apache (apache2ctl configtest) ANTES de
 * recarregar -- diferente do firewall/rede, aqui nao precisa de janela de
 * confirmacao com reversao por tempo, porque o vhost :80 existente nunca e
 * tocado: se o novo vhost :443 falhar a validacao, o HTTP continua
 * funcionando normalmente, sem risco de perder acesso.
 */
class CertificadoService
{
    private LinuxService $linux;

    public function __construct()
    {
        $this->linux = new LinuxService();
    }

    public function status(): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/certificado_status_web.sh');
        $secoes = $this->dividirSecoes($resultado['output']);

        $certTexto = trim($secoes['CERT-ATUAL'] ?? 'NENHUM');
        $cert = null;

        if ($certTexto !== 'NENHUM' && $certTexto !== '') {
            $cert = $this->parseCertTexto($certTexto);
        }

        return [
            'mod_ssl' => trim($secoes['MOD-SSL'] ?? '0') === '1',
            'site_ssl' => trim($secoes['SITE-SSL'] ?? '0') === '1',
            'certbot_instalado' => trim($secoes['CERTBOT'] ?? '0') === '1',
            'tipo' => trim($secoes['TIPO'] ?? 'nenhum'),
            'dominio' => trim($secoes['DOMINIO'] ?? ''),
            'certificado' => $cert,
        ];
    }

    public function interfacesEIps(): array
    {
        $saida = shell_exec("ip -o -4 addr show 2>/dev/null | awk '{print $4}' | cut -d/ -f1") ?? '';

        return array_values(array_filter(array_map('trim', explode("\n", $saida)), fn($ip) => $ip !== '' && $ip !== '127.0.0.1'));
    }

    public function gerarAutoassinado(string $cn, string $ipExtra): array
    {
        $cn = trim($cn);
        if ($cn === '' || !preg_match('/^[a-zA-Z0-9.-]+$/', $cn)) {
            return ['success' => false, 'message' => 'Nome (CN) inválido.'];
        }
        if ($ipExtra !== '' && !filter_var($ipExtra, FILTER_VALIDATE_IP)) {
            return ['success' => false, 'message' => 'IP adicional inválido.'];
        }

        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/certificado_gerar_autoassinado_web.sh',
            array_filter([$cn, $ipExtra])
        );

        return $this->processarObtencao($resultado);
    }

    public function importar(array $arquivoCrt, array $arquivoKey, ?array $arquivoChain = null): array
    {
        if (($arquivoCrt['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
            || ($arquivoKey['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Envie o certificado (.crt/.pem) e a chave privada (.key).'];
        }

        $args = [$arquivoCrt['tmp_name'], $arquivoKey['tmp_name']];

        if ($arquivoChain && ($arquivoChain['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $args[] = $arquivoChain['tmp_name'];
        }

        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/certificado_importar_web.sh', $args);

        return $this->processarObtencao($resultado);
    }

    public function configurarLetsEncrypt(string $dominio, string $email): array
    {
        $dominio = trim($dominio);
        $email = trim($email);

        if ($dominio === '' || !preg_match('/^[a-zA-Z0-9.-]+$/', $dominio)) {
            return ['success' => false, 'message' => 'Domínio inválido.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'E-mail inválido.'];
        }

        // certbot pode demorar mais que o max_execution_time padrao do PHP
        // (validacao do desafio ACME envolve ida e volta pela internet)
        set_time_limit(180);

        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/certificado_letsencrypt_web.sh',
            [$dominio, $email]
        );

        return $this->processarObtencao($resultado);
    }

    /**
     * Depois de obter/instalar um certificado novo, tenta ativar o HTTPS
     * (mod_ssl + vhost + reload). Se a ativacao falhar, restaura o
     * certificado anterior pra nao deixar o sistema num estado inconsistente.
     */
    private function processarObtencao(array $resultadoScript): array
    {
        $dados = json_decode(trim($resultadoScript['output']), true);

        if (!is_array($dados) || empty($dados['success'])) {
            return ['success' => false, 'message' => is_array($dados) ? ($dados['message'] ?? 'Falha desconhecida.') : $resultadoScript['output']];
        }

        $ativacao = $this->linux->executarScript('/opt/rdtecnologia/scripts/certificado_ativar_web.sh');
        $dadosAtivacao = json_decode(trim($ativacao['output']), true);
        $ativado = is_array($dadosAtivacao) && !empty($dadosAtivacao['success']);

        if ($ativado) {
            return ['success' => true, 'message' => $dados['message'] . ' ' . $dadosAtivacao['message']];
        }

        // ativacao falhou -- restaura o certificado anterior pra nao deixar
        // atual.crt/atual.key num estado que nunca foi validado
        $this->linux->executarScript('/opt/rdtecnologia/scripts/certificado_restaurar_backup_web.sh', [
            $dados['backup_crt'] ?? '',
            $dados['backup_key'] ?? '',
        ]);

        return [
            'success' => false,
            'message' => 'Certificado obtido, mas a ativação do HTTPS falhou (certificado anterior restaurado): '
                . (is_array($dadosAtivacao) ? ($dadosAtivacao['message'] ?? '') : $ativacao['output']),
        ];
    }

    private function parseCertTexto(string $texto): array
    {
        $get = function (string $chave) use ($texto): ?string {
            if (preg_match('/^' . preg_quote($chave, '/') . '\s*=\s*(.+)$/mi', $texto, $m)) {
                return trim($m[1]);
            }
            return null;
        };

        $notAfter = $get('notAfter');
        $expiraEm = $notAfter ? strtotime($notAfter) : null;
        $diasRestantes = $expiraEm ? (int)floor(($expiraEm - time()) / 86400) : null;

        return [
            'subject' => $get('subject'),
            'issuer' => $get('issuer'),
            'nao_antes' => $get('notBefore'),
            'nao_depois' => $notAfter,
            'dias_restantes' => $diasRestantes,
            'expirado' => $diasRestantes !== null && $diasRestantes < 0,
            'expirando' => $diasRestantes !== null && $diasRestantes >= 0 && $diasRestantes <= 15,
            'fingerprint' => $get('sha256 Fingerprint'),
        ];
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
}
