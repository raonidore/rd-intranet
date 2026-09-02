<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use PDOException;
use RuntimeException;

/**
 * CRUD das conexões WhatsApp via QR Code -- um cliente pode ter vários
 * departamentos, cada um com seu próprio número (processo bridge Node
 * separado, ver whatsapp-bridge/index.js e
 * scripts/system/whatsapp_bridge_instalar_web.sh). A conexão "Principal"
 * (a que já rodava antes desta tabela existir) nasce pela migration
 * 2026_09_02_whatsapp_multiplas_conexoes.sql, com os caminhos fixos que
 * já estavam em uso -- nunca reinstalada com caminhos diferentes.
 *
 * `padrao` marca qual conexão é usada quando `WhatsAppMensagemService::enviar()`
 * não recebe um `conexaoId` explícito (mensagem avulsa, sem atendimento
 * em andamento) -- sempre exatamente uma linha com `padrao = 1`.
 */
class WhatsAppConexaoService
{
    private const PORTA_INICIAL = 3300;

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listar(): array
    {
        $stmt = $this->pdo->query(
            "SELECT c.*, COUNT(cs.setor_id) AS total_setores
             FROM whatsapp_conexoes c
             LEFT JOIN whatsapp_conexao_setores cs ON cs.conexao_id = c.id
             WHERE c.ativo = 1
             GROUP BY c.id
             ORDER BY c.padrao DESC, c.id ASC"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM whatsapp_conexoes WHERE id = ?');
        $stmt->execute([$id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function conexaoPadrao(): ?array
    {
        $stmt = $this->pdo->query('SELECT * FROM whatsapp_conexoes WHERE padrao = 1 AND ativo = 1 LIMIT 1');
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    /**
     * Autentica E identifica a conexão de origem de um webhook ao mesmo
     * tempo -- quem bater a chave é quem mandou a mensagem, nunca
     * dessincroniza autenticação de identificação. Poucas linhas na
     * prática (um número por departamento), por isso o loop com
     * descriptografia individual é aceitável.
     *
     * @throws PDOException se `whatsapp_conexoes` ainda não existir (janela de deploy antes da migration rodar) -- o chamador decide o fallback.
     */
    public function buscarPorApiKey(string $chaveRecebida): ?array
    {
        if ($chaveRecebida === '') {
            return null;
        }

        $stmt = $this->pdo->query('SELECT * FROM whatsapp_conexoes WHERE ativo = 1 AND api_key_cifrada IS NOT NULL');

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $conexao) {
            try {
                $chave = CryptoService::decriptar($conexao['api_key_cifrada']);
            } catch (RuntimeException $e) {
                continue; // chave corrompida nessa linha -- não impede achar outra
            }

            if (hash_equals($chave, $chaveRecebida)) {
                return $conexao;
            }
        }

        return null;
    }

    /** @return array{success: bool, message: string, id?: int} */
    public function criar(string $nome): array
    {
        $nome = trim($nome);
        if ($nome === '') {
            return ['success' => false, 'message' => 'Informe um nome pra essa conexão.'];
        }

        $porta = $this->proximaPortaDisponivel();
        $chave = bin2hex(random_bytes(24));

        $stmt = $this->pdo->prepare(
            'INSERT INTO whatsapp_conexoes (nome, porta, api_key_cifrada, diretorio_instalacao, usuario_sistema, unit_systemd, instalado, ativo, padrao)
             VALUES (?, ?, ?, ?, ?, ?, 0, 1, 0)'
        );
        // diretorio/usuario/unit ficam provisórios (com {id} literal) até o INSERT
        // devolver o id de verdade -- corrigidos logo abaixo, sem depender de
        // nenhuma outra tabela pra gerar o identificador.
        $stmt->execute([$nome, $porta, CryptoService::encriptar($chave), '', '', '']);

        $id = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare(
            'UPDATE whatsapp_conexoes SET diretorio_instalacao = ?, usuario_sistema = ?, unit_systemd = ? WHERE id = ?'
        )->execute([
            "/opt/rdtecnologia/whatsapp-bridge-{$id}",
            "whatsapp-bridge-{$id}",
            "whatsapp-bridge-{$id}.service",
            $id,
        ]);

        return ['success' => true, 'message' => 'Conexão criada -- clique em "Instalar" pra colocar no ar.', 'id' => $id];
    }

    /** @return array{success: bool, message: string} */
    public function renomear(int $id, string $nome): array
    {
        $nome = trim($nome);
        if ($nome === '') {
            return ['success' => false, 'message' => 'Informe um nome pra essa conexão.'];
        }

        $this->pdo->prepare('UPDATE whatsapp_conexoes SET nome = ? WHERE id = ?')->execute([$nome, $id]);

        return ['success' => true, 'message' => 'Conexão renomeada.'];
    }

    public function marcarInstalado(int $id): void
    {
        $this->pdo->prepare('UPDATE whatsapp_conexoes SET instalado = 1 WHERE id = ?')->execute([$id]);
    }

    /** @return int[] */
    public function idsSetoresDaConexao(int $conexaoId): array
    {
        $stmt = $this->pdo->prepare('SELECT setor_id FROM whatsapp_conexao_setores WHERE conexao_id = ?');
        $stmt->execute([$conexaoId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return array{success: bool, message: string} */
    public function salvarSetoresDaConexao(int $conexaoId, array $setorIds): array
    {
        $setorIds = array_values(array_unique(array_map('intval', $setorIds)));

        $this->pdo->beginTransaction();

        try {
            $this->pdo->prepare('DELETE FROM whatsapp_conexao_setores WHERE conexao_id = ?')->execute([$conexaoId]);

            if ($setorIds) {
                $ins = $this->pdo->prepare('INSERT INTO whatsapp_conexao_setores (conexao_id, setor_id) VALUES (?, ?)');
                foreach ($setorIds as $setorId) {
                    $ins->execute([$conexaoId, $setorId]);
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => 'Falha ao salvar os setores dessa conexão.'];
        }

        return ['success' => true, 'message' => 'Setores dessa conexão atualizados.'];
    }

    private function proximaPortaDisponivel(): int
    {
        $maior = (int)$this->pdo->query('SELECT MAX(porta) FROM whatsapp_conexoes')->fetchColumn();

        return max($maior + 1, self::PORTA_INICIAL);
    }

    /**
     * Roda pós-deploy (comando `rd whatsapp:diagnosticar-conexoes`), sem
     * precisar de acesso SSH ao servidor -- confirma que a migração da
     * conexão legada ficou correta antes de mexer em qualquer coisa.
     *
     * @return array<int, array{nome: string, ok: bool, detalhes: string[]}>
     */
    public function diagnosticar(): array
    {
        $conexoes = $this->pdo->query('SELECT * FROM whatsapp_conexoes ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $resultado = [];

        $totalPadrao = 0;
        foreach ($conexoes as $conexao) {
            if ((int)$conexao['padrao'] === 1 && (int)$conexao['ativo'] === 1) {
                $totalPadrao++;
            }
        }

        foreach ($conexoes as $conexao) {
            $detalhes = [];
            $ok = true;

            if (empty($conexao['api_key_cifrada'])) {
                $detalhes[] = 'Sem API key gravada.';
                $ok = false;
            } else {
                try {
                    CryptoService::decriptar($conexao['api_key_cifrada']);
                    $detalhes[] = 'API key decripta OK.';
                } catch (RuntimeException $e) {
                    $detalhes[] = 'API key não decripta: ' . $e->getMessage();
                    $ok = false;
                }
            }

            if ((int)$conexao['instalado'] === 1) {
                $status = (new WhatsAppBridgeService($conexao))->status();
                if (!empty($status['success'])) {
                    $detalhes[] = 'Bridge respondeu: status=' . ($status['status'] ?? '?') . ', numero=' . ($status['numero'] ?? '-');
                } else {
                    $detalhes[] = 'Bridge não respondeu (' . ($status['message'] ?? 'sem detalhe') . ').';
                    $ok = false;
                }
            } else {
                $detalhes[] = 'Ainda não instalada -- não testado.';
            }

            $resultado[] = ['nome' => $conexao['nome'], 'ok' => $ok, 'detalhes' => $detalhes];
        }

        if ($totalPadrao !== 1) {
            $resultado[] = [
                'nome' => '(geral)',
                'ok' => false,
                'detalhes' => ["Deveria existir exatamente 1 conexão ativa marcada como padrão -- encontrado: {$totalPadrao}."],
            ];
        }

        return $resultado;
    }
}
