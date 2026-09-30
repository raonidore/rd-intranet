<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Módulo Chamados do agente Windows. O agente já se autentica com a
 * chave de instalação (X-RD-Agente-Chave) e a máquina é identificada pelo
 * machine_guid; isso basta pra ABRIR chamado sem login (o solicitante
 * informa nome e contato, como no formulário do portal).
 *
 * Pra ver e responder os próprios chamados, a pessoa entra com usuário e
 * senha do RD Intranet dentro do agente: vira uma sessão de agente (token
 * aleatório entregue uma vez, só o SHA-256 fica no banco, expira em
 * 30 dias, revogável no logout). A chave de instalação está em toda
 * máquina, então o login tem limite de tentativas por máquina e por
 * login -- senão viraria um jeito de testar senha sem limite.
 */
class AgenteChamadoService
{
    /** Mesmo acesso de "Meus Chamados" no portal. */
    public const MODULOS = ['chamados_atendimentos', 'chamados_abrir'];

    private const VALIDADE_SESSAO_DIAS = 30;
    private const LIMITE_FALHAS = 5;
    private const JANELA_FALHAS_MIN = 15;

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    // ------------------------------------------------------------ sessão

    /** @return array{success: bool, message: string, token?: string, usuario?: array} */
    public function login(string $login, string $senha, int $ativoId, string $usuarioWindows): array
    {
        $login = trim($login);
        if ($login === '' || $senha === '') {
            return ['success' => false, 'message' => 'Informe usuário e senha.'];
        }

        if ($this->bloqueado($login, $ativoId)) {
            return ['success' => false, 'message' => 'Muitas tentativas. Aguarde ' . self::JANELA_FALHAS_MIN . ' minutos e tente de novo.'];
        }

        $stmt = $this->pdo->prepare('SELECT * FROM usuarios WHERE login = ? AND ativo = 1 LIMIT 1');
        $stmt->execute([$login]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario || !password_verify($senha, $usuario['senha_hash'])) {
            $this->pdo->prepare('INSERT INTO agente_login_falhas (ativo_id, login) VALUES (?, ?)')->execute([$ativoId, mb_substr($login, 0, 60)]);
            AuditService::registrar('Chamados', 'Agente - login', "Falha de login de \"{$login}\" pelo agente (ativo #{$ativoId}).");
            return ['success' => false, 'message' => 'Usuário ou senha inválidos.'];
        }

        if (!$this->podeUsar($usuario)) {
            return ['success' => false, 'message' => 'Seu usuário não tem acesso a chamados. Fale com o administrador.'];
        }

        $token = bin2hex(random_bytes(32));
        $this->pdo->prepare(
            'INSERT INTO agente_sessoes (usuario_id, ativo_id, token_hash, usuario_windows, expira_em) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? DAY))'
        )->execute([(int)$usuario['id'], $ativoId, hash('sha256', $token), mb_substr($usuarioWindows, 0, 150) ?: null, self::VALIDADE_SESSAO_DIAS]);

        AuditService::registrar('Chamados', 'Agente - login', "Usuário {$usuario['login']} entrou pelo agente (ativo #{$ativoId}).");

        return ['success' => true, 'message' => 'Conectado.', 'token' => $token, 'usuario' => $this->dadosUsuario($usuario)];
    }

    /** Usuário dono de um token válido (não expirado, não revogado, usuário ainda ativo e com acesso) -- ou null. */
    public function usuarioDoToken(string $token): ?array
    {
        if (strlen($token) !== 64 || !ctype_xdigit($token)) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT s.id AS sessao_id, u.*
             FROM agente_sessoes s
             JOIN usuarios u ON u.id = s.usuario_id
             WHERE s.token_hash = ? AND s.revogado = 0 AND s.expira_em > NOW() AND u.ativo = 1'
        );
        $stmt->execute([hash('sha256', $token)]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario || !$this->podeUsar($usuario)) {
            return null;
        }

        $this->pdo->prepare('UPDATE agente_sessoes SET ultimo_uso_em = NOW() WHERE id = ?')->execute([(int)$usuario['sessao_id']]);

        return $usuario;
    }

    public function logout(string $token): void
    {
        $this->pdo->prepare('UPDATE agente_sessoes SET revogado = 1 WHERE token_hash = ?')->execute([hash('sha256', $token)]);
    }

    public function dadosUsuario(array $usuario): array
    {
        return ['id' => (int)$usuario['id'], 'nome' => $usuario['nome'], 'login' => $usuario['login'], 'email' => $usuario['email'] ?? ''];
    }

    private function podeUsar(array $usuario): bool
    {
        if (($usuario['perfil'] ?? '') === 'admin') {
            return true;
        }

        $marcadores = implode(',', array_fill(0, count(self::MODULOS), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM usuario_modulos WHERE usuario_id = ? AND modulo IN ({$marcadores})
             UNION
             SELECT 1 FROM grupo_modulos gm JOIN grupo_usuarios gu ON gu.grupo_id = gm.grupo_id
             WHERE gu.usuario_id = ? AND gm.modulo IN ({$marcadores})
             LIMIT 1"
        );
        $stmt->execute(array_merge([(int)$usuario['id']], self::MODULOS, [(int)$usuario['id']], self::MODULOS));

        return (bool)$stmt->fetchColumn();
    }

    private function bloqueado(string $login, int $ativoId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                SUM(login = ?) AS por_login,
                SUM(ativo_id = ?) AS por_maquina
             FROM agente_login_falhas
             WHERE criado_em > DATE_SUB(NOW(), INTERVAL ? MINUTE)'
        );
        $stmt->execute([$login, $ativoId, self::JANELA_FALHAS_MIN]);
        $linha = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // Por máquina o teto é maior: várias pessoas podem errar a senha no mesmo PC.
        return (int)($linha['por_login'] ?? 0) >= self::LIMITE_FALHAS || (int)($linha['por_maquina'] ?? 0) >= self::LIMITE_FALHAS * 3;
    }

    // ------------------------------------------------------------ formulário

    /** Tudo que a tela de abertura precisa, numa chamada só -- mesmos campos de Chamados > Atendimentos > Novo. */
    public function formulario(int $ativoId): array
    {
        $ativo = (new AtivoService())->buscar($ativoId);

        return [
            'categorias' => array_map(fn (array $c) => [
                'id' => (int)$c['id'],
                'nome' => $c['nome'],
                'exige_subcategoria' => !empty($c['exige_subcategoria']),
            ], (new ChamadoCategoriaService())->listarAtivas()),
            'subcategorias' => (object)(new ChamadoSubcategoriaService())->listarAtivasAgrupadas(),
            'setores' => array_map(fn (array $s) => ['id' => (int)$s['id'], 'nome' => $s['nome']], (new ChamadoSetorService())->listarAtivos()),
            'unidades' => array_map(fn (array $u) => ['id' => (int)$u['id'], 'nome' => $u['nome']], (new UnidadeService())->listarAtivas()),
            'prioridades' => array_map(fn ($chave, $rotulo) => ['id' => $chave, 'nome' => $rotulo], array_keys(ChamadoService::PRIORIDADES), ChamadoService::PRIORIDADES),
            'este_ativo' => $ativo ? [
                'id' => (int)$ativo['id'],
                'codigo' => $ativo['codigo_patrimonio'],
                'nome' => $ativo['nome'],
                'unidade_id' => (int)$ativo['unidade_id'],
            ] : null,
        ];
    }

    /**
     * Busca de "outro equipamento" (monitor, impressora...). A chave de
     * instalação está em toda máquina, então devolve só o mínimo pra
     * reconhecer o equipamento, no máximo 10, e exige 2+ caracteres.
     */
    public function buscarAtivos(string $termo): array
    {
        $termo = trim($termo);
        if (mb_strlen($termo) < 2) {
            return [];
        }

        $ativos = array_slice((new AtivoService())->listar(['busca' => mb_substr($termo, 0, 60)]), 0, 10);

        return array_map(fn (array $a) => [
            'id' => (int)$a['id'],
            'codigo' => $a['codigo_patrimonio'],
            'nome' => $a['nome'],
            'tipo' => $a['tipo_nome'] ?? '',
        ], $ativos);
    }

    // ------------------------------------------------------------ chamados

    /** @return array{success: bool, message: string, id?: int, numero_controle?: string} */
    public function abrir(array $dados, int $ativoMaquinaId, ?array $usuario): array
    {
        // "Sobre o quê": esta máquina (padrão), outro equipamento escolhido na busca, ou nenhum (software, acesso...).
        $alvo = (string)($dados['alvo'] ?? 'este');
        $ativoId = match ($alvo) {
            'outro' => (int)($dados['ativo_id'] ?? 0) ?: null,
            'nenhum' => null,
            default => $ativoMaquinaId,
        };
        if ($alvo === 'outro' && ($ativoId === null || !(new AtivoService())->buscar($ativoId))) {
            return ['success' => false, 'message' => 'Escolha o equipamento na busca.'];
        }

        $post = [
            'titulo' => mb_substr(trim((string)($dados['titulo'] ?? '')), 0, 200),
            'descricao' => trim((string)($dados['descricao'] ?? '')),
            'categoria_id' => (int)($dados['categoria_id'] ?? 0),
            'subcategoria_id' => (int)($dados['subcategoria_id'] ?? 0) ?: null,
            'setor_id' => (int)($dados['setor_id'] ?? 0) ?: null,
            'unidade_id' => (int)($dados['unidade_id'] ?? 0),
            'prioridade' => (string)($dados['prioridade'] ?? 'media'),
            'ativo_id' => $ativoId,
            'solicitante_nome' => trim((string)($dados['solicitante_nome'] ?? '')),
            'solicitante_email' => trim((string)($dados['solicitante_email'] ?? '')),
            'solicitante_telefone' => trim((string)($dados['solicitante_telefone'] ?? '')),
        ];

        // Logado: o solicitante é o próprio usuário, se ele não preencheu outro nome/e-mail.
        if ($usuario) {
            $post['solicitante_nome'] = $post['solicitante_nome'] ?: $usuario['nome'];
            $post['solicitante_email'] = $post['solicitante_email'] ?: (string)($usuario['email'] ?? '');
        }

        if ($post['solicitante_email'] !== '' && !filter_var($post['solicitante_email'], FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'E-mail do solicitante inválido.'];
        }

        $usuarioWindows = mb_substr(trim((string)($dados['usuario_windows'] ?? '')), 0, 150);
        if ($usuarioWindows !== '' && $post['descricao'] !== '') {
            $post['descricao'] .= "\n\n-- Aberto pelo agente, usuário do Windows: {$usuarioWindows}";
        }

        return (new ChamadoService())->abrir($post, 'agente', $usuario ? (int)$usuario['id'] : null);
    }

    public function meus(int $usuarioId): array
    {
        return array_map(fn (array $c) => $this->resumo($c), array_slice((new ChamadoService())->listarAbertosPeloUsuario($usuarioId), 0, 100));
    }

    /** Só o dono (quem abriu) vê -- nunca notas internas. */
    public function ver(int $chamadoId, int $usuarioId): ?array
    {
        $service = new ChamadoService();
        $chamado = $service->buscar($chamadoId);
        if (!$chamado || (int)($chamado['usuario_abertura_id'] ?? 0) !== $usuarioId) {
            return null;
        }

        return $this->resumo($chamado) + [
            'descricao' => $chamado['descricao'],
            'setor' => $chamado['setor_nome'] ?? '',
            'ativo' => $chamado['ativo_codigo'] ? $chamado['ativo_codigo'] . ' - ' . $chamado['ativo_nome'] : '',
            'pode_responder' => !in_array($chamado['status'], ['resolvido', 'fechado'], true),
            'mensagens' => array_map(fn (array $m) => [
                'quando' => $m['criado_em'],
                'autor' => $m['usuario_id'] === null ? 'Você' : ((int)$m['usuario_id'] === $usuarioId ? 'Você' : ($m['usuario_nome'] ?? 'Suporte')),
                'do_suporte' => $m['usuario_id'] !== null && (int)$m['usuario_id'] !== $usuarioId,
                'texto' => $m['conteudo'],
            ], $service->comentarios($chamadoId, false)),
        ];
    }

    /** @return array{success: bool, message: string} */
    public function responder(int $chamadoId, int $usuarioId, string $texto): array
    {
        if ($this->ver($chamadoId, $usuarioId) === null) {
            return ['success' => false, 'message' => 'Chamado não encontrado.'];
        }

        // Resposta do solicitante: devolve o chamado pra equipe (aguardando resposta = 1).
        return (new ChamadoService())->responderComoSolicitante($chamadoId, mb_substr($texto, 0, 5000));
    }

    private function resumo(array $c): array
    {
        return [
            'id' => (int)$c['id'],
            'numero' => $c['numero_controle'] ?? (string)$c['id'],
            'titulo' => $c['titulo'],
            'categoria' => $c['categoria_nome'],
            'status' => ChamadoService::STATUS[$c['status']] ?? $c['status'],
            'status_chave' => $c['status'],
            'prioridade' => ChamadoService::PRIORIDADES[$c['prioridade']] ?? $c['prioridade'],
            'atendente' => $c['usuario_nome'] ?? '',
            'aberto_em' => $c['aberto_em'],
            'atualizado_em' => $c['ultima_mensagem_em'] ?? $c['aberto_em'],
        ];
    }
}
