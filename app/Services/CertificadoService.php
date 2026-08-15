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

    public function alertaEmailAtual(): string
    {
        return trim((string)(ConfigService::get('certificado_alerta_email', '') ?: ''));
    }

    public function salvarAlertaEmail(string $email): array
    {
        $email = trim($email);

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'E-mail inválido.'];
        }

        ConfigService::set('certificado_alerta_email', $email);

        return [
            'success' => true,
            'message' => $email === ''
                ? 'Alerta por e-mail desativado.'
                : "Alerta por e-mail configurado para {$email}.",
        ];
    }

    /**
     * Chamado pelo cron nativo "Verificar vencimento do certificado"
     * (ver AtualizacaoService::garantirCronCertificado()), uma vez por dia.
     * So manda e-mail quando ha alguem configurado pra receber E o
     * certificado esta a 15 dias ou menos de vencer (ou ja vencido) --
     * mesmo limiar do aviso visual desta tela. Reenvia todo dia enquanto
     * a situacao nao mudar (o alerta e' o sinal de que a renovacao
     * automatica do Let's Encrypt, que roda ~30 dias antes, nao
     * aconteceu -- silenciar isso seria esconder um problema real).
     */
    public function verificarVencimento(): array
    {
        $emailAlerta = $this->alertaEmailAtual();
        if ($emailAlerta === '') {
            return ['success' => true, 'message' => 'Nenhum e-mail de alerta configurado, nada a fazer.'];
        }

        $status = $this->status();
        $cert = $status['certificado'];

        if ($cert === null) {
            return ['success' => true, 'message' => 'Nenhum certificado instalado, nada a verificar.'];
        }

        if (!$cert['expirado'] && !$cert['expirando']) {
            return ['success' => true, 'message' => "Certificado ok, faltam {$cert['dias_restantes']} dia(s)."];
        }

        $email = new EmailService();
        if (!$email->configurado()) {
            return ['success' => false, 'message' => 'Certificado perto de vencer, mas o SMTP (Sistema > E-mail) não está configurado -- alerta não pôde ser enviado.'];
        }

        $dominio = $status['dominio'] ?: ($cert['subject'] ?? 'este servidor');
        $dias = $cert['dias_restantes'];

        if ($cert['expirado']) {
            $titulo = 'Certificado expirado';
            $cor = '#dc3545';
            $corpo = "O certificado HTTPS de <strong>" . htmlspecialchars($dominio) . "</strong> está <strong>expirado há " . abs($dias) . " dia(s)</strong> (venceu em " . htmlspecialchars((string)$cert['nao_depois']) . ").";
        } else {
            $titulo = 'Certificado expirando em breve';
            $cor = '#f59f00';
            $corpo = "O certificado HTTPS de <strong>" . htmlspecialchars($dominio) . "</strong> vence em <strong>{$dias} dia(s)</strong> (" . htmlspecialchars((string)$cert['nao_depois']) . ").";
        }

        if ($status['tipo'] === 'letsencrypt') {
            $corpo .= '<br><br>Certificados Let\'s Encrypt renovam sozinhos, normalmente uns 30 dias antes de vencer -- '
                . 'como chegou até aqui, vale conferir se a porta 80 continua acessível de fora e se o '
                . '<code>certbot renew --dry-run</code> roda sem erro no servidor.';
        }

        $envio = $email->enviar($emailAlerta, "[RD Intranet] {$titulo}: {$dominio}", $this->montarEmailAlerta($titulo, $cor, $corpo));

        return [
            'success' => $envio['success'],
            'message' => $envio['success']
                ? "Alerta enviado para {$emailAlerta} ({$titulo}, {$dias} dia(s))."
                : "Falha ao enviar alerta por e-mail: {$envio['message']}",
        ];
    }

    private function montarEmailAlerta(string $titulo, string $cor, string $conteudoHtml): string
    {
        return '<div style="background:#f1f3f5;padding:32px 16px;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">'
            . '<div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.08);">'
            . '<div style="background:' . $cor . ';padding:28px 32px;">'
            . '<div style="color:rgba(255,255,255,.85);font-size:12px;font-weight:600;letter-spacing:.8px;">CERTIFICADO DIGITAL</div>'
            . '<div style="color:#ffffff;font-size:22px;font-weight:700;margin-top:6px;">' . htmlspecialchars($titulo) . '</div>'
            . '</div>'
            . '<div style="padding:32px;font-size:14px;color:#212529;line-height:1.6;">' . $conteudoHtml . '</div>'
            . '<div style="padding:16px 32px;background:#f8f9fa;border-top:1px solid #eee;font-size:11.5px;color:#adb5bd;">'
            . 'Enviado automaticamente pela verificação diária de certificado da RD Intranet. Não responda este e-mail.'
            . '</div>'
            . '</div>'
            . '</div>';
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
