<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class WhatsAppContatoService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    /**
     * Acha o contato pelo número (dígitos só) ou cria um novo -- ponto de
     * entrada usado pelo webhook toda vez que chega mensagem de um
     * número ainda não visto antes.
     */
    public function buscarOuCriarPorNumero(string $numero, ?string $nome = null): array
    {
        $numero = $this->normalizarNumero($numero);
        $nome = $nome !== null ? trim($nome) : null;
        if ($nome === '') {
            $nome = null;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM whatsapp_contatos WHERE numero = ?');
        $stmt->execute([$numero]);
        $contato = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($contato) {
            if ($nome !== null && $nome !== $contato['nome']) {
                $upd = $this->pdo->prepare('UPDATE whatsapp_contatos SET nome = ? WHERE id = ?');
                $upd->execute([$nome, $contato['id']]);
                $contato['nome'] = $nome;
            }

            return $contato;
        }

        $ins = $this->pdo->prepare('INSERT INTO whatsapp_contatos (numero, nome) VALUES (?, ?)');
        $ins->execute([$numero, $nome]);

        return ['id' => (int)$this->pdo->lastInsertId(), 'numero' => $numero, 'nome' => $nome];
    }

    /**
     * Normaliza um número digitado à mão (ex: "(83) 99104-3598") pro
     * mesmo formato usado internamente (DDI 55 + DDD + número, só
     * dígitos) -- aceita tanto com quanto sem o "55" na frente (mais
     * natural pra quem tá digitando um número local). Retorna null se
     * não bater com o formato esperado (nem 10/11 dígitos locais, nem
     * 12/13 já com DDI).
     */
    public function normalizarNumeroBr(string $entrada): ?string
    {
        $digitos = $this->normalizarNumero($entrada);

        if (preg_match('/^\d{10,11}$/', $digitos)) {
            $digitos = '55' . $digitos;
        }

        return preg_match('/^55\d{10,11}$/', $digitos) ? $digitos : null;
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM whatsapp_contatos WHERE id = ?');
        $stmt->execute([$id]);

        $contato = $stmt->fetch(PDO::FETCH_ASSOC);

        return $contato ?: null;
    }

    /**
     * Lista paginada pra tela de Contatos -- "atendente responsável" e
     * "status atual" não são campos guardados (não existe dono fixo de
     * um contato no sistema, só de um atendimento), são derivados aqui
     * do atendimento mais recente desse contato via subquery
     * correlacionada, mesmo estilo simples do resto do projeto.
     *
     * @return array{itens: array, total: int}
     */
    public function listar(string $busca = '', int $pagina = 1, int $porPagina = 20): array
    {
        $busca = trim($busca);
        $curinga = '%' . $busca . '%';
        $where = $busca === '' ? '' : 'WHERE (c.nome LIKE ? OR c.numero LIKE ?)';

        $stmt = $this->pdo->prepare(
            "SELECT c.*,
                (SELECT u.nome FROM whatsapp_atendimentos a2 JOIN usuarios u ON u.id = a2.usuario_id
                 WHERE a2.contato_id = c.id AND a2.usuario_id IS NOT NULL ORDER BY a2.id DESC LIMIT 1) AS atendente_responsavel,
                (SELECT a3.status FROM whatsapp_atendimentos a3 WHERE a3.contato_id = c.id ORDER BY a3.id DESC LIMIT 1) AS status_atual,
                (SELECT COUNT(*) FROM whatsapp_atendimentos a4 WHERE a4.contato_id = c.id) AS total_atendimentos,
                (SELECT COUNT(*) FROM whatsapp_mensagens m JOIN whatsapp_atendimentos a5 ON a5.id = m.atendimento_id WHERE a5.contato_id = c.id) AS total_mensagens
             FROM whatsapp_contatos c
             {$where}
             ORDER BY c.atualizado_em DESC, c.id DESC
             LIMIT ? OFFSET ?"
        );

        $params = $busca === '' ? [] : [$curinga, $curinga];
        $params[] = $porPagina;
        $params[] = ($pagina - 1) * $porPagina;

        foreach ($params as $indice => $valor) {
            $stmt->bindValue($indice + 1, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmtTotal = $this->pdo->prepare("SELECT COUNT(*) FROM whatsapp_contatos c {$where}");
        $stmtTotal->execute($busca === '' ? [] : [$curinga, $curinga]);
        $total = (int)$stmtTotal->fetchColumn();

        return ['itens' => $itens, 'total' => $total];
    }

    /** Todos os atendimentos desse contato (atual e passados), mais recente primeiro. */
    public function historicoAtendimentos(int $contatoId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT a.*, s.nome AS setor_nome, u.nome AS usuario_nome
             FROM whatsapp_atendimentos a
             LEFT JOIN whatsapp_setores s ON s.id = a.setor_id
             LEFT JOIN usuarios u ON u.id = a.usuario_id
             WHERE a.contato_id = ?
             ORDER BY a.id DESC"
        );
        $stmt->execute([$contatoId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{atendimentos: int, mensagens: int} */
    public function contarHistorico(int $contatoId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                (SELECT COUNT(*) FROM whatsapp_atendimentos WHERE contato_id = ?) AS atendimentos,
                (SELECT COUNT(*) FROM whatsapp_mensagens m JOIN whatsapp_atendimentos a ON a.id = m.atendimento_id WHERE a.contato_id = ?) AS mensagens"
        );
        $stmt->execute([$contatoId, $contatoId]);

        $linha = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['atendimentos' => 0, 'mensagens' => 0];

        return ['atendimentos' => (int)$linha['atendimentos'], 'mensagens' => (int)$linha['mensagens']];
    }

    /**
     * Apaga o contato -- em cascata (FK do banco) apaga junto TODOS os
     * atendimentos e mensagens dele (`fk_whatsapp_atendimentos_contato
     * ... ON DELETE CASCADE`). Decisão de produto confirmada: avisar
     * quantos registros isso leva junto (`contarHistorico()`, chamado
     * pelo controller antes de exibir a confirmação) em vez de bloquear
     * ou fazer soft-delete.
     *
     * @return array{success: bool, message: string}
     */
    public function excluir(int $id): array
    {
        $this->pdo->prepare('DELETE FROM whatsapp_contatos WHERE id = ?')->execute([$id]);

        return ['success' => true, 'message' => 'Contato removido.'];
    }

    private function normalizarNumero(string $numero): string
    {
        return preg_replace('/\D+/', '', $numero) ?? '';
    }
}
