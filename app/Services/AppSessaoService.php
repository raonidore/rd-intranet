<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Login do app Android (/api/app/*). Token opaco de 64 hex no cabeçalho
 * X-RD-Token -- só o hash fica em app_sessoes, revogável por aparelho.
 * A cada requisição, usuarioDoToken() monta $_SESSION['usuario'] igual ao
 * login web (perfil + módulos diretos e de grupo), então PermissionService,
 * AuditService e ChamadoService funcionam sem saber que é o app.
 */
class AppSessaoService
{
    /** Quem pode entrar no app: precisa de algum módulo de chamados de atendente. */
    public const MODULOS = ['chamados_atendimentos', 'chamados_fila', 'chamados_abrir'];

    private const VALIDADE_SESSAO_DIAS = 60;
    private const LIMITE_FALHAS = 5;
    private const JANELA_FALHAS_MIN = 15;

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    /** @return array{success: bool, message: string, token?: string} */
    public function login(string $login, string $senha, string $dispositivo, string $versaoApp): array
    {
        $login = trim($login);
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        if ($login === '' || $senha === '') {
            return ['success' => false, 'message' => 'Informe usuário e senha.'];
        }

        if ($this->bloqueado($login, $ip)) {
            return ['success' => false, 'message' => 'Muitas tentativas. Aguarde ' . self::JANELA_FALHAS_MIN . ' minutos e tente de novo.'];
        }

        $stmt = $this->pdo->prepare('SELECT * FROM usuarios WHERE login = ? AND ativo = 1 LIMIT 1');
        $stmt->execute([$login]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario || !password_verify($senha, $usuario['senha_hash'])) {
            $this->pdo->prepare('INSERT INTO app_login_falhas (login, ip) VALUES (?, ?)')->execute([mb_substr($login, 0, 60), $ip]);
            AuditService::registrar('Autenticação', 'Falha de Login (app)', "Tentativa de login pelo app Android com o usuário: {$login}");
            return ['success' => false, 'message' => 'Usuário ou senha inválidos.'];
        }

        $this->aplicarSessao($usuario);
        if (!$this->podeUsar()) {
            $_SESSION['usuario'] = null;
            return ['success' => false, 'message' => 'Seu usuário não tem acesso aos chamados. Fale com o administrador.'];
        }

        $token = bin2hex(random_bytes(32));
        $this->pdo->prepare(
            'INSERT INTO app_sessoes (usuario_id, token_hash, dispositivo, versao_app, ip, expira_em) VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? DAY))'
        )->execute([(int)$usuario['id'], hash('sha256', $token), mb_substr($dispositivo, 0, 150) ?: null, mb_substr($versaoApp, 0, 20) ?: null, $ip, self::VALIDADE_SESSAO_DIAS]);

        AuditService::registrar('Autenticação', 'Login (app)', "Usuário {$usuario['login']} entrou pelo app Android" . ($dispositivo !== '' ? " ({$dispositivo})" : '') . '.');

        return ['success' => true, 'message' => 'Conectado.', 'token' => $token];
    }

    /** Valida o token e monta a sessão em memória; null se inválido/expirado/sem acesso. */
    public function usuarioDoToken(string $token): ?array
    {
        if (strlen($token) !== 64 || !ctype_xdigit($token)) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT s.id AS sessao_id, u.*
             FROM app_sessoes s
             JOIN usuarios u ON u.id = s.usuario_id
             WHERE s.token_hash = ? AND s.revogado = 0 AND s.expira_em > NOW() AND u.ativo = 1'
        );
        $stmt->execute([hash('sha256', $token)]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$usuario) {
            return null;
        }

        $this->aplicarSessao($usuario);
        if (!$this->podeUsar()) {
            return null;
        }

        // Renova a validade a cada uso: quem usa todo dia não é deslogado.
        $this->pdo->prepare('UPDATE app_sessoes SET ultimo_uso_em = NOW(), expira_em = DATE_ADD(NOW(), INTERVAL ? DAY) WHERE id = ?')
            ->execute([self::VALIDADE_SESSAO_DIAS, (int)$usuario['sessao_id']]);
        (new UsuarioOnlineService())->registrarAcesso((int)$usuario['id']);

        return $usuario;
    }

    public function logout(string $token): void
    {
        $this->pdo->prepare('UPDATE app_sessoes SET revogado = 1 WHERE token_hash = ?')->execute([hash('sha256', $token)]);
    }

    /** O que o app pode mostrar/fazer -- espelha os módulos do menu web. */
    public function permissoes(): array
    {
        return [
            'atendimentos' => PermissionService::temAcesso('chamados_atendimentos'),
            'fila' => PermissionService::temAcesso('chamados_fila'),
            'equipe' => PermissionService::temAcesso('chamados_equipe'),
            'transferir' => PermissionService::temAcesso('chamados_transferir'),
            'abrir' => PermissionService::temAcesso('chamados_atendimentos') || PermissionService::temAcesso('chamados_abrir'),
        ];
    }

    /** Mesmo formato do login web (AuthController::login). */
    private function aplicarSessao(array $usuario): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT modulo FROM usuario_modulos WHERE usuario_id = ?
             UNION
             SELECT gm.modulo FROM grupo_modulos gm JOIN grupo_usuarios gu ON gu.grupo_id = gm.grupo_id WHERE gu.usuario_id = ?'
        );
        $stmt->execute([(int)$usuario['id'], (int)$usuario['id']]);

        $_SESSION['usuario'] = [
            'id' => $usuario['id'],
            'nome' => $usuario['nome'],
            'login' => $usuario['login'],
            'perfil' => $usuario['perfil'],
            'email' => $usuario['email'] ?? '',
            'modulos' => $stmt->fetchAll(PDO::FETCH_COLUMN),
        ];
    }

    private function podeUsar(): bool
    {
        foreach (self::MODULOS as $modulo) {
            if (PermissionService::temAcesso($modulo)) {
                return true;
            }
        }

        return false;
    }

    private function bloqueado(string $login, ?string $ip): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT SUM(login = ?) AS por_login, SUM(ip = ?) AS por_ip
             FROM app_login_falhas WHERE criado_em > DATE_SUB(NOW(), INTERVAL ? MINUTE)'
        );
        $stmt->execute([$login, $ip, self::JANELA_FALHAS_MIN]);
        $linha = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // Por IP o teto é maior: a equipe inteira pode sair pelo mesmo IP (Wi-Fi da empresa).
        return (int)($linha['por_login'] ?? 0) >= self::LIMITE_FALHAS || (int)($linha['por_ip'] ?? 0) >= self::LIMITE_FALHAS * 4;
    }
}
