<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class PoliticaRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    /** @return array<string, array> regra_id => linha de estado, só as regras que já têm alguma linha salva pra esse ativo. */
    public function estadoPorAtivo(int $ativoId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ativos_politicas_estado WHERE ativo_id = ?');
        $stmt->execute([$ativoId]);

        $porRegra = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $linha) {
            $porRegra[$linha['regra_id']] = $linha;
        }

        return $porRegra;
    }

    public function upsertEstado(int $ativoId, string $regraId, int $desejado, string $status, ?string $mensagem, ?int $solicitacaoId): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO ativos_politicas_estado (ativo_id, regra_id, desejado, status, mensagem, solicitacao_id)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                desejado = VALUES(desejado),
                status = VALUES(status),
                mensagem = VALUES(mensagem),
                solicitacao_id = VALUES(solicitacao_id)
        ');

        $stmt->execute([$ativoId, $regraId, $desejado, $status, $mensagem, $solicitacaoId]);
    }

    /** @return array<int, array> linhas de ativos_politicas_estado ainda 'pendente' presas a essa solicitação. */
    public function porSolicitacao(int $solicitacaoId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ativos_politicas_estado WHERE solicitacao_id = ?');
        $stmt->execute([$solicitacaoId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function atualizarStatus(int $id, string $status, ?string $mensagem): void
    {
        $stmt = $this->pdo->prepare('UPDATE ativos_politicas_estado SET status = ?, mensagem = ? WHERE id = ?');
        $stmt->execute([$status, $mensagem, $id]);
    }

    /*
     |---------------------------------------------------------
     | Fase 2: recursos de rede (impressora/unidade) por setor.
     |---------------------------------------------------------
     */

    /** @return array<int, array> todos os recursos, com o nome do setor já junto (pra listar numa tabela só). */
    public function listarRecursosSetor(): array
    {
        $stmt = $this->pdo->query('
            SELECT r.*, c.nome AS setor_nome
            FROM ativos_setor_recursos r
            JOIN ativos_catalogos c ON c.id = r.setor_id
            ORDER BY c.nome, r.tipo, r.nome_exibicao
        ');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int, array> só os recursos de UM setor (usado na hora de montar o script de mapeamento). */
    public function recursosPorSetor(int $setorId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ativos_setor_recursos WHERE setor_id = ? ORDER BY tipo, nome_exibicao');
        $stmt->execute([$setorId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function criarRecursoSetor(int $setorId, string $tipo, string $nomeExibicao, ?string $letraUnidade, string $caminhoUnc): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO ativos_setor_recursos (setor_id, tipo, nome_exibicao, letra_unidade, caminho_unc)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([$setorId, $tipo, $nomeExibicao, $letraUnidade, $caminhoUnc]);
    }

    public function excluirRecursoSetor(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM ativos_setor_recursos WHERE id = ?');
        $stmt->execute([$id]);
    }

    /*
     |---------------------------------------------------------
     | Fase 3: catálogo de pacotes de software instaláveis.
     |---------------------------------------------------------
     */

    public function listarPacotesSoftware(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM ativos_pacotes_software ORDER BY nome');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarPacoteSoftware(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ativos_pacotes_software WHERE id = ?');
        $stmt->execute([$id]);

        $linha = $stmt->fetch(PDO::FETCH_ASSOC);

        return $linha ?: null;
    }

    public function criarPacoteSoftware(string $nome, string $nomeOriginal, string $caminho, ?string $argumentos, ?string $criadoPor): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO ativos_pacotes_software (nome, arquivo_nome_original, arquivo_caminho, argumentos_silenciosos, criado_por)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([$nome, $nomeOriginal, $caminho, $argumentos, $criadoPor]);

        return (int)$this->pdo->lastInsertId();
    }

    public function excluirPacoteSoftware(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM ativos_pacotes_software WHERE id = ?');
        $stmt->execute([$id]);
    }
}
