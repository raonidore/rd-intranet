<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

/** Acesso às tabelas wifi_* do Captive Portal. */
class WifiRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    private function um(string $sql, array $p = []): ?array
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($p);
        $r = $st->fetch(PDO::FETCH_ASSOC);

        return $r ?: null;
    }

    private function todos(string $sql, array $p = []): array
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($p);

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private function exec(string $sql, array $p = []): int
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($p);

        return $st->rowCount();
    }

    // ---------------------------------------------------------------- perfis

    public function perfis(bool $soAtivos = false): array
    {
        return $this->todos('SELECT * FROM wifi_perfis' . ($soAtivos ? ' WHERE ativo = 1' : '') . ' ORDER BY ordem, nome');
    }

    public function perfil(int $id): ?array
    {
        return $this->um('SELECT * FROM wifi_perfis WHERE id = ?', [$id]);
    }

    public function perfilPorSlug(string $slug): ?array
    {
        return $this->um('SELECT * FROM wifi_perfis WHERE slug = ?', [$slug]);
    }

    public function salvarPerfil(?int $id, array $d): int
    {
        $cols = ['slug', 'nome', 'descricao', 'cor', 'icone', 'ativo', 'tipo_acesso', 'autocadastro', 'exige_liberacao', 'campos', 'dominios_email',
            'validade_dias', 'sessao_minutos', 'banda_down_kbps', 'banda_up_kbps', 'cota_mb', 'max_aparelhos', 'lembrar_dias', 'horarios', 'filtro', 'ordem'];
        $vals = array_map(fn ($c) => $d[$c] ?? null, $cols);
        if ($id) {
            $this->exec('UPDATE wifi_perfis SET ' . implode(', ', array_map(fn ($c) => "$c = ?", $cols)) . ' WHERE id = ?', [...$vals, $id]);

            return $id;
        }
        $this->exec('INSERT INTO wifi_perfis (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')', $vals);

        return (int)$this->pdo->lastInsertId();
    }

    public function excluirPerfil(int $id): void
    {
        $this->exec('DELETE FROM wifi_perfis WHERE id = ?', [$id]);
    }

    public function contarPessoasDoPerfil(int $perfilId): int
    {
        return (int)$this->um('SELECT COUNT(*) n FROM wifi_pessoas WHERE perfil_id = ?', [$perfilId])['n'];
    }

    // ---------------------------------------------------------------- termos

    public function termoAtual(int $perfilId): ?array
    {
        return $this->um('SELECT * FROM wifi_termos WHERE perfil_id = ? ORDER BY versao DESC LIMIT 1', [$perfilId]);
    }

    public function novoTermo(int $perfilId, string $texto, ?int $usuarioId): array
    {
        $atual = $this->termoAtual($perfilId);
        $versao = ($atual['versao'] ?? 0) + 1;
        $this->exec('INSERT INTO wifi_termos (perfil_id, versao, texto, criado_por) VALUES (?, ?, ?, ?)', [$perfilId, $versao, $texto, $usuarioId]);

        return $this->termoAtual($perfilId);
    }

    public function aceitouTermo(int $pessoaId, int $termoId): bool
    {
        return (bool)$this->um('SELECT id FROM wifi_aceites WHERE pessoa_id = ? AND termo_id = ? LIMIT 1', [$pessoaId, $termoId]);
    }

    public function registrarAceite(int $pessoaId, array $termo, ?string $ip, ?string $mac): void
    {
        $this->exec('INSERT INTO wifi_aceites (pessoa_id, termo_id, versao, ip, mac) VALUES (?, ?, ?, ?, ?)', [$pessoaId, $termo['id'], $termo['versao'], $ip, $mac]);
    }

    public function aceitesDaPessoa(int $pessoaId): array
    {
        return $this->todos('SELECT a.*, p.nome perfil_nome FROM wifi_aceites a JOIN wifi_termos t ON t.id = a.termo_id JOIN wifi_perfis p ON p.id = t.perfil_id WHERE a.pessoa_id = ? ORDER BY a.aceito_em DESC', [$pessoaId]);
    }

    // ---------------------------------------------------------------- pessoas

    public function pessoa(int $id): ?array
    {
        return $this->um('SELECT p.*, f.nome perfil_nome, f.slug perfil_slug, f.cor perfil_cor FROM wifi_pessoas p JOIN wifi_perfis f ON f.id = p.perfil_id WHERE p.id = ?', [$id]);
    }

    public function pessoaPorEmail(string $email, ?int $perfilId = null): ?array
    {
        return $this->um('SELECT * FROM wifi_pessoas WHERE email = ? AND status <> \'anonimizado\'' . ($perfilId ? ' AND perfil_id = ' . (int)$perfilId : '') . ' ORDER BY id DESC LIMIT 1', [mb_strtolower(trim($email))]);
    }

    public function pessoaPorContato(int $perfilId, string $contato): ?array
    {
        $c = mb_strtolower(trim($contato));

        return $this->um('SELECT * FROM wifi_pessoas WHERE perfil_id = ? AND (email = ? OR telefone = ?) AND status <> \'anonimizado\' ORDER BY id DESC LIMIT 1', [$perfilId, $c, preg_replace('/\D/', '', $c)]);
    }

    public function listarPessoas(array $filtro = []): array
    {
        $where = ["p.status <> 'anonimizado'"];
        $par = [];
        if (!empty($filtro['status'])) {
            $where[] = 'p.status = ?';
            $par[] = $filtro['status'];
        }
        if (!empty($filtro['perfil_id'])) {
            $where[] = 'p.perfil_id = ?';
            $par[] = (int)$filtro['perfil_id'];
        }
        if (!empty($filtro['busca'])) {
            $where[] = '(p.nome LIKE ? OR p.email LIKE ? OR p.empresa LIKE ? OR p.telefone LIKE ?)';
            array_push($par, ...array_fill(0, 4, '%' . $filtro['busca'] . '%'));
        }

        return $this->todos('SELECT p.*, f.nome perfil_nome, f.cor perfil_cor, f.icone perfil_icone,
                (SELECT COUNT(*) FROM wifi_sessoes s WHERE s.pessoa_id = p.id AND s.fim IS NULL) sessoes_ativas
            FROM wifi_pessoas p JOIN wifi_perfis f ON f.id = p.perfil_id
            WHERE ' . implode(' AND ', $where) . ' ORDER BY FIELD(p.status, \'pendente\') DESC, p.criado_em DESC LIMIT 500', $par);
    }

    public function criarPessoa(array $d): int
    {
        $cols = ['perfil_id', 'nome', 'email', 'telefone', 'documento', 'empresa', 'cnpj', 'setor', 'anfitriao', 'dados_extra', 'senha_hash',
            'trocar_senha', 'status', 'valido_ate', 'origem', 'liberado_por', 'liberado_em'];
        // Só as colunas informadas -- as demais ficam com o padrão da tabela (status, trocar_senha...).
        $cols = array_values(array_filter($cols, fn ($c) => isset($d[$c])));
        $this->exec('INSERT INTO wifi_pessoas (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')',
            array_map(fn ($c) => $d[$c], $cols));

        return (int)$this->pdo->lastInsertId();
    }

    public function atualizarPessoa(int $id, array $campos): void
    {
        if (!$campos) {
            return;
        }
        $this->exec('UPDATE wifi_pessoas SET ' . implode(', ', array_map(fn ($c) => "$c = ?", array_keys($campos))) . ' WHERE id = ?', [...array_values($campos), $id]);
    }

    public function contarPorStatus(): array
    {
        $r = [];
        foreach ($this->todos("SELECT status, COUNT(*) n FROM wifi_pessoas GROUP BY status") as $l) {
            $r[$l['status']] = (int)$l['n'];
        }

        return $r;
    }

    // ---------------------------------------------------------------- aparelhos

    public function aparelhos(int $pessoaId): array
    {
        return $this->todos('SELECT * FROM wifi_aparelhos WHERE pessoa_id = ? ORDER BY ultimo_uso DESC', [$pessoaId]);
    }

    public function aparelhoPorMac(string $mac): ?array
    {
        return $this->um('SELECT a.*, p.status pessoa_status FROM wifi_aparelhos a JOIN wifi_pessoas p ON p.id = a.pessoa_id WHERE a.mac = ? ORDER BY a.ultimo_uso DESC LIMIT 1', [$mac]);
    }

    public function registrarAparelho(int $pessoaId, string $mac, ?string $lembradoAte): void
    {
        $this->exec('INSERT INTO wifi_aparelhos (pessoa_id, mac, lembrado_ate, ultimo_uso) VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE lembrado_ate = VALUES(lembrado_ate), ultimo_uso = NOW()', [$pessoaId, $mac, $lembradoAte]);
    }

    public function esquecerAparelhos(int $pessoaId): void
    {
        $this->exec('UPDATE wifi_aparelhos SET lembrado_ate = NULL WHERE pessoa_id = ?', [$pessoaId]);
    }

    public function bloquearAparelho(int $id, bool $bloqueado): void
    {
        $this->exec('UPDATE wifi_aparelhos SET bloqueado = ?, lembrado_ate = NULL WHERE id = ?', [$bloqueado ? 1 : 0, $id]);
    }

    public function aparelho(int $id): ?array
    {
        return $this->um('SELECT * FROM wifi_aparelhos WHERE id = ?', [$id]);
    }

    // ---------------------------------------------------------------- sessões

    public function abrirSessao(array $d): int
    {
        $this->exec('INSERT INTO wifi_sessoes (pessoa_id, perfil_id, mac, ip, ap_mac, ssid, inicio, fim_previsto) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)',
            [$d['pessoa_id'], $d['perfil_id'], $d['mac'], $d['ip'], $d['ap_mac'] ?? null, $d['ssid'] ?? null, $d['fim_previsto']]);

        return (int)$this->pdo->lastInsertId();
    }

    public function sessoesAtivas(?int $pessoaId = null): array
    {
        return $this->todos('SELECT s.*, p.nome pessoa_nome, p.email pessoa_email, p.empresa, f.nome perfil_nome, f.cor perfil_cor, f.slug perfil_slug
            FROM wifi_sessoes s LEFT JOIN wifi_pessoas p ON p.id = s.pessoa_id LEFT JOIN wifi_perfis f ON f.id = s.perfil_id
            WHERE s.fim IS NULL' . ($pessoaId ? ' AND s.pessoa_id = ' . (int)$pessoaId : '') . ' ORDER BY s.inicio DESC');
    }

    public function sessaoAtivaPorMac(string $mac): ?array
    {
        return $this->um('SELECT * FROM wifi_sessoes WHERE mac = ? AND fim IS NULL ORDER BY id DESC LIMIT 1', [$mac]);
    }

    public function sessao(int $id): ?array
    {
        return $this->um('SELECT * FROM wifi_sessoes WHERE id = ?', [$id]);
    }

    public function encerrarSessao(int $id, string $motivo): void
    {
        $this->exec('UPDATE wifi_sessoes SET fim = NOW(), motivo_fim = ? WHERE id = ? AND fim IS NULL', [$motivo, $id]);
    }

    public function atualizarConsumo(int $id, int $down, int $up, ?string $ip): void
    {
        $this->exec('UPDATE wifi_sessoes SET bytes_down = GREATEST(bytes_down, ?), bytes_up = GREATEST(bytes_up, ?), ip = COALESCE(?, ip) WHERE id = ?', [$down, $up, $ip, $id]);
    }

    public function historicoSessoes(array $filtro = [], int $limite = 500): array
    {
        $where = ['1=1'];
        $par = [];
        if (!empty($filtro['pessoa_id'])) {
            $where[] = 's.pessoa_id = ?';
            $par[] = (int)$filtro['pessoa_id'];
        }
        if (!empty($filtro['de'])) {
            $where[] = 's.inicio >= ?';
            $par[] = $filtro['de'] . ' 00:00:00';
        }
        if (!empty($filtro['ate'])) {
            $where[] = 's.inicio <= ?';
            $par[] = $filtro['ate'] . ' 23:59:59';
        }
        if (!empty($filtro['perfil_id'])) {
            $where[] = 's.perfil_id = ?';
            $par[] = (int)$filtro['perfil_id'];
        }

        return $this->todos('SELECT s.*, p.nome pessoa_nome, p.email pessoa_email, f.nome perfil_nome, f.cor perfil_cor
            FROM wifi_sessoes s LEFT JOIN wifi_pessoas p ON p.id = s.pessoa_id LEFT JOIN wifi_perfis f ON f.id = s.perfil_id
            WHERE ' . implode(' AND ', $where) . ' ORDER BY s.inicio DESC LIMIT ' . (int)$limite, $par);
    }

    public function resumoUso(string $de, string $ate): array
    {
        return $this->todos('SELECT f.id, f.nome, f.cor, COUNT(s.id) sessoes, COUNT(DISTINCT s.pessoa_id) pessoas, COUNT(DISTINCT s.mac) aparelhos,
                COALESCE(SUM(s.bytes_down), 0) bytes_down, COALESCE(SUM(s.bytes_up), 0) bytes_up
            FROM wifi_perfis f LEFT JOIN wifi_sessoes s ON s.perfil_id = f.id AND s.inicio BETWEEN ? AND ?
            GROUP BY f.id ORDER BY f.ordem', [$de . ' 00:00:00', $ate . ' 23:59:59']);
    }

    // ---------------------------------------------------------------- tokens / tentativas

    public function criarToken(int $pessoaId, string $hash, string $expira): void
    {
        $this->exec('INSERT INTO wifi_tokens (pessoa_id, token_hash, expira_em) VALUES (?, ?, ?)', [$pessoaId, $hash, $expira]);
    }

    public function tokenValido(string $hash): ?array
    {
        return $this->um('SELECT * FROM wifi_tokens WHERE token_hash = ? AND usado_em IS NULL AND expira_em > NOW()', [$hash]);
    }

    public function usarToken(int $id): void
    {
        $this->exec('UPDATE wifi_tokens SET usado_em = NOW() WHERE id = ?', [$id]);
    }

    public function registrarTentativa(string $chave, bool $sucesso): void
    {
        $this->exec('INSERT INTO wifi_tentativas (chave, sucesso) VALUES (?, ?)', [$chave, $sucesso ? 1 : 0]);
    }

    public function falhasRecentes(string $chave, int $minutos): int
    {
        return (int)$this->um('SELECT COUNT(*) n FROM wifi_tentativas WHERE chave = ? AND sucesso = 0 AND criado_em > NOW() - INTERVAL ? MINUTE', [$chave, $minutos])['n'];
    }

    // ---------------------------------------------------------------- manutenção / LGPD

    public function pessoasVencidas(): array
    {
        return $this->todos("SELECT * FROM wifi_pessoas WHERE status = 'ativo' AND valido_ate IS NOT NULL AND valido_ate < NOW()");
    }

    public function pessoasAVencer(int $dias): array
    {
        return $this->todos("SELECT * FROM wifi_pessoas WHERE status = 'ativo' AND valido_ate IS NOT NULL AND aviso_vencimento_em IS NULL
            AND valido_ate BETWEEN NOW() AND NOW() + INTERVAL ? DAY AND email IS NOT NULL", [$dias]);
    }

    public function sessoesVencidas(): array
    {
        return $this->todos('SELECT * FROM wifi_sessoes WHERE fim IS NULL AND fim_previsto IS NOT NULL AND fim_previsto < NOW()');
    }

    /** Anonimiza registros de conexão mais antigos que o prazo; devolve quantos. */
    public function anonimizarSessoesAntigas(int $dias): int
    {
        return $this->exec("UPDATE wifi_sessoes SET pessoa_id = NULL, mac = NULL, ip = NULL, anonimizado = 1
            WHERE anonimizado = 0 AND fim IS NOT NULL AND inicio < NOW() - INTERVAL ? DAY", [$dias]);
    }

    public function limparTentativas(): void
    {
        $this->exec('DELETE FROM wifi_tentativas WHERE criado_em < NOW() - INTERVAL 1 DAY');
        $this->exec('DELETE FROM wifi_tokens WHERE expira_em < NOW() - INTERVAL 7 DAY');
    }

    public function anonimizarPessoa(int $id): void
    {
        $this->exec("UPDATE wifi_pessoas SET nome = 'Anonimizado', email = NULL, telefone = NULL, documento = NULL, empresa = NULL, cnpj = NULL,
            setor = NULL, anfitriao = NULL, dados_extra = NULL, senha_hash = NULL, regras = NULL, status = 'anonimizado', motivo = 'Pedido do titular (LGPD)' WHERE id = ?", [$id]);
        $this->exec('DELETE FROM wifi_aparelhos WHERE pessoa_id = ?', [$id]);
        $this->exec('UPDATE wifi_sessoes SET pessoa_id = NULL, mac = NULL, ip = NULL, anonimizado = 1 WHERE pessoa_id = ?', [$id]);
    }

    /**
     * Pedido do titular: tira já tudo que não é necessário e bloqueia o acesso;
     * nome/e-mail ficam só enquanto houver registro de conexão dentro do prazo
     * legal (LGPD art. 16, I) -- depois a manutenção anonimiza de vez.
     */
    public function reduzirPessoa(int $id): void
    {
        $this->exec("UPDATE wifi_pessoas SET telefone = NULL, documento = NULL, empresa = NULL, cnpj = NULL, setor = NULL, anfitriao = NULL,
            dados_extra = NULL, senha_hash = NULL, regras = NULL, status = 'anonimizado', motivo = 'Pedido do titular (LGPD)' WHERE id = ?", [$id]);
        $this->exec('DELETE FROM wifi_aparelhos WHERE pessoa_id = ?', [$id]);
    }

    /** Cadastros encerrados (pedido do titular, recusados, vencidos) sem conexão dentro do prazo: anonimizar de vez. */
    public function pessoasParaAnonimizar(int $dias): array
    {
        return array_column($this->todos("SELECT p.id FROM wifi_pessoas p
            WHERE p.status IN ('anonimizado', 'recusado', 'expirado') AND p.nome <> 'Anonimizado'
              AND COALESCE(p.ultimo_acesso, p.criado_em) < NOW() - INTERVAL ? DAY
              AND NOT EXISTS (SELECT 1 FROM wifi_sessoes s WHERE s.pessoa_id = p.id AND s.inicio >= NOW() - INTERVAL ? DAY)", [$dias, $dias]), 'id');
    }

    /** Usuários do sistema com permissão de liberar acessos e e-mail cadastrado. */
    public function emailsLiberadores(): array
    {
        return array_column($this->todos("SELECT DISTINCT u.email FROM usuarios u
            LEFT JOIN usuario_modulos m ON m.usuario_id = u.id AND m.modulo = 'wifi_liberar'
            WHERE u.ativo = 1 AND u.email IS NOT NULL AND u.email <> '' AND (u.perfil = 'admin' OR m.modulo IS NOT NULL)"), 'email');
    }
}
