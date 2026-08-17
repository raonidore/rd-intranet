<?php

namespace App\Services;

use App\Repositories\PasswordResetRepository;
use App\Repositories\UserRepository;

/**
 * Fluxo de "esqueci minha senha" do /login. So funciona se o SMTP estiver
 * configurado (Sistema > E-mail, ver EmailService::configurado()) e o
 * usuario tiver um e-mail cadastrado (Administracao > Usuarios).
 *
 * Por design, solicitar() nunca revela se o login/e-mail existe ou nao --
 * sempre "funciona" do ponto de vista de quem esta na tela, so envia o
 * e-mail de verdade quando encontra um usuario ativo correspondente. Evita
 * que a tela de recuperacao vire uma forma de descobrir quais logins
 * existem no sistema.
 */
class PasswordResetService
{
    private const VALIDADE_HORAS = 1;

    private UserRepository $usuarios;
    private PasswordResetRepository $tokens;
    private EmailService $email;

    public function __construct()
    {
        $this->usuarios = new UserRepository();
        $this->tokens = new PasswordResetRepository();
        $this->email = new EmailService();
    }

    public function solicitar(string $loginOuEmail, string $urlBase): void
    {
        $loginOuEmail = trim($loginOuEmail);
        if ($loginOuEmail === '' || !$this->email->configurado()) {
            return;
        }

        $usuario = $this->usuarios->buscarPorLoginOuEmail($loginOuEmail);
        if (!$usuario || empty($usuario['email'])) {
            return;
        }

        $this->tokens->invalidarPendentes((int)$usuario['id']);

        $token = bin2hex(random_bytes(32));
        $expiraEm = date('Y-m-d H:i:s', time() + self::VALIDADE_HORAS * 3600);

        $this->tokens->criar((int)$usuario['id'], hash('sha256', $token), $expiraEm);

        $link = rtrim($urlBase, '/') . url('/login/redefinir?token=' . $token);

        $this->email->enviar(
            $usuario['email'],
            'RD Intranet -- Redefinição de senha',
            '<p>Olá, ' . htmlspecialchars($usuario['nome']) . '.</p>'
            . '<p>Recebemos um pedido pra redefinir a senha da sua conta (usuário <strong>' . htmlspecialchars($usuario['login']) . '</strong>).</p>'
            . '<p><a href="' . htmlspecialchars($link) . '">Clique aqui pra criar uma nova senha</a></p>'
            . '<p>Esse link expira em ' . self::VALIDADE_HORAS . ' hora(s). Se você não pediu essa redefinição, ignore este e-mail.</p>'
        );
    }

    public function validarToken(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        return $this->tokens->buscarValido(hash('sha256', $token));
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function redefinir(string $token, string $senha, string $confirmacao): array
    {
        $registro = $this->validarToken($token);
        if (!$registro) {
            return ['success' => false, 'message' => 'Link inválido ou expirado. Solicite a redefinição novamente.'];
        }

        $erros = PasswordPolicyService::validar($senha, [
            'nome' => $registro['nome'],
            'login' => $registro['login'],
            'email' => $registro['email'],
        ]);
        if ($erros) {
            return ['success' => false, 'message' => $erros[0]];
        }

        if ($senha !== $confirmacao) {
            return ['success' => false, 'message' => 'As senhas não conferem.'];
        }

        $this->usuarios->atualizarSenha((int)$registro['usuario_id'], password_hash($senha, PASSWORD_DEFAULT));
        $this->tokens->marcarUsado((int)$registro['id']);

        return ['success' => true, 'message' => 'Senha redefinida com sucesso. Faça login com a nova senha.'];
    }
}
