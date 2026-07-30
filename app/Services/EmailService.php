<?php

namespace App\Services;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * SMTP unico por instalacao (Sistema > E-mail), usado pelas notificacoes
 * do modulo Backup em Nuvem (relatorio diario / alerta de falha). Config
 * key-value via ConfigService, senha cifrada com CryptoService -- mesmo
 * esquema ja usado pelo token do Cloudflare Tunnel
 * (CloudflareTunnelService).
 */
class EmailService
{
    private const CHAVE_HOST = 'smtp_host';
    private const CHAVE_PORTA = 'smtp_porta';
    private const CHAVE_USUARIO = 'smtp_usuario';
    private const CHAVE_SENHA_CIFRADA = 'smtp_senha_cifrada';
    private const CHAVE_CRIPTOGRAFIA = 'smtp_criptografia';
    private const CHAVE_REMETENTE_NOME = 'smtp_remetente_nome';
    private const CHAVE_REMETENTE_EMAIL = 'smtp_remetente_email';

    private const CRIPTOGRAFIAS_VALIDAS = ['tls', 'ssl', 'nenhuma'];

    public function configurado(): bool
    {
        return $this->host() !== ''
            && $this->usuario() !== ''
            && $this->senhaAtual() !== null
            && $this->remetenteEmail() !== '';
    }

    public function host(): string
    {
        return trim((string)(ConfigService::get(self::CHAVE_HOST, '') ?: ''));
    }

    public function porta(): int
    {
        return (int)(ConfigService::get(self::CHAVE_PORTA, '587') ?: '587');
    }

    public function usuario(): string
    {
        return trim((string)(ConfigService::get(self::CHAVE_USUARIO, '') ?: ''));
    }

    public function criptografia(): string
    {
        $valor = ConfigService::get(self::CHAVE_CRIPTOGRAFIA, 'tls') ?: 'tls';
        return in_array($valor, self::CRIPTOGRAFIAS_VALIDAS, true) ? $valor : 'tls';
    }

    public function remetenteNome(): string
    {
        return trim((string)(ConfigService::get(self::CHAVE_REMETENTE_NOME, '') ?: '')) ?: 'RD Intranet';
    }

    public function remetenteEmail(): string
    {
        return trim((string)(ConfigService::get(self::CHAVE_REMETENTE_EMAIL, '') ?: ''));
    }

    private function senhaAtual(): ?string
    {
        $cifrada = ConfigService::get(self::CHAVE_SENHA_CIFRADA, '');
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
     * @return array{success: bool, message: string}
     */
    public function salvarConfiguracao(array $dados): array
    {
        $host = trim($dados['host'] ?? '');
        $porta = (int)($dados['porta'] ?? 587);
        $usuario = trim($dados['usuario'] ?? '');
        $senha = trim($dados['senha'] ?? '');
        $criptografia = in_array($dados['criptografia'] ?? '', self::CRIPTOGRAFIAS_VALIDAS, true) ? $dados['criptografia'] : 'tls';
        $remetenteNome = trim($dados['remetente_nome'] ?? '');
        $remetenteEmail = trim($dados['remetente_email'] ?? '');

        if ($host === '' || $usuario === '' || $remetenteEmail === '') {
            return ['success' => false, 'message' => 'Preencha servidor SMTP, usuário e e-mail do remetente.'];
        }

        if (!filter_var($remetenteEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'E-mail do remetente inválido.'];
        }

        ConfigService::set(self::CHAVE_HOST, $host);
        ConfigService::set(self::CHAVE_PORTA, (string)$porta);
        ConfigService::set(self::CHAVE_USUARIO, $usuario);
        ConfigService::set(self::CHAVE_CRIPTOGRAFIA, $criptografia);
        ConfigService::set(self::CHAVE_REMETENTE_NOME, $remetenteNome);
        ConfigService::set(self::CHAVE_REMETENTE_EMAIL, $remetenteEmail);

        if ($senha !== '') {
            ConfigService::set(self::CHAVE_SENHA_CIFRADA, CryptoService::encriptar($senha));
        }

        if ($senha === '' && $this->senhaAtual() === null) {
            return ['success' => false, 'message' => 'Configuração salva, mas falta informar a senha SMTP pelo menos uma vez.'];
        }

        return ['success' => true, 'message' => 'Configuração de e-mail salva com sucesso.'];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function enviarTeste(string $para): array
    {
        return $this->enviar(
            $para,
            'RD Intranet -- e-mail de teste',
            '<p>Se você recebeu esta mensagem, a configuração de SMTP em <strong>Sistema &gt; E-mail</strong> está funcionando corretamente.</p>'
        );
    }

    /**
     * @param string|string[] $para um e-mail, uma lista separada por
     *   vírgula, ou um array já separado -- normaliza e valida cada um.
     * @return array{success: bool, message: string}
     */
    public function enviar(string|array $para, string $assunto, string $corpoHtml): array
    {
        if (!$this->configurado()) {
            return ['success' => false, 'message' => 'SMTP não configurado (Sistema > E-mail).'];
        }

        $destinatarios = self::normalizarLista($para);
        if (empty($destinatarios)) {
            return ['success' => false, 'message' => 'Nenhum e-mail de destino válido.'];
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $this->host();
            $mail->Port = $this->porta();
            $mail->SMTPAuth = true;
            $mail->Username = $this->usuario();
            $mail->Password = (string)$this->senhaAtual();
            $mail->CharSet = 'UTF-8';

            $criptografia = $this->criptografia();
            if ($criptografia === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($criptografia === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPAutoTLS = false;
            }

            $mail->setFrom($this->remetenteEmail(), $this->remetenteNome());
            foreach ($destinatarios as $destinatario) {
                $mail->addAddress($destinatario);
            }
            $mail->isHTML(true);
            $mail->Subject = $assunto;
            $mail->Body = $corpoHtml;

            $mail->send();

            return ['success' => true, 'message' => 'E-mail enviado com sucesso.'];
        } catch (PHPMailerException $e) {
            return ['success' => false, 'message' => 'Falha ao enviar e-mail: ' . $mail->ErrorInfo];
        }
    }

    /**
     * Aceita "a@x.com, b@x.com" ou um array e devolve só os endereços
     * válidos, sem duplicatas -- usado tanto aqui quanto na validação do
     * formulário de destino (BackupService::salvarDestino()).
     *
     * @param string|string[] $lista
     * @return string[]
     */
    public static function normalizarLista(string|array $lista): array
    {
        $bruta = is_array($lista) ? $lista : explode(',', $lista);

        $validos = [];
        foreach ($bruta as $item) {
            $email = trim((string)$item);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $validos[strtolower($email)] = $email;
            }
        }

        return array_values($validos);
    }
}
