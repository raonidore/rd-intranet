<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class AtivoRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listar(array $filtros = []): array
    {
        $sql = "SELECT * FROM ativos WHERE 1=1";
        $params = [];

        if (!empty($filtros['tipo'])) {
            $sql .= " AND tipo = :tipo";
            $params['tipo'] = $filtros['tipo'];
        }

        if (!empty($filtros['status'])) {
            $sql .= " AND status = :status";
            $params['status'] = $filtros['status'];
        }

        if (!empty($filtros['busca'])) {
            $sql .= " AND (nome LIKE :busca OR codigo_patrimonio LIKE :busca OR numero_serie LIKE :busca)";
            $params['busca'] = '%' . $filtros['busca'] . '%';
        }

        $sql .= " ORDER BY criado_em DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM ativos WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function buscarPorIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT * FROM ativos WHERE id IN ($placeholders) ORDER BY tipo, nome");
        $stmt->execute($ids);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function criar(array $dados): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO ativos
            (tipo, codigo_patrimonio, nome, marca, modelo, numero_serie, setor, localizacao, responsavel, status, ip, snmp_habilitado, snmp_community, observacoes, detalhes)
            VALUES
            (:tipo, :codigo_patrimonio, :nome, :marca, :modelo, :numero_serie, :setor, :localizacao, :responsavel, :status, :ip, :snmp_habilitado, :snmp_community, :observacoes, :detalhes)
        ");

        $stmt->execute([
            'tipo' => $dados['tipo'],
            'codigo_patrimonio' => $dados['codigo_patrimonio'],
            'nome' => $dados['nome'],
            'marca' => $dados['marca'],
            'modelo' => $dados['modelo'],
            'numero_serie' => $dados['numero_serie'],
            'setor' => $dados['setor'],
            'localizacao' => $dados['localizacao'],
            'responsavel' => $dados['responsavel'],
            'status' => $dados['status'],
            'ip' => $dados['ip'],
            'snmp_habilitado' => $dados['snmp_habilitado'],
            'snmp_community' => $dados['snmp_community'],
            'observacoes' => $dados['observacoes'],
            'detalhes' => $dados['detalhes'],
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function atualizar(int $id, array $dados): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE ativos
               SET nome = :nome,
                   marca = :marca,
                   modelo = :modelo,
                   numero_serie = :numero_serie,
                   setor = :setor,
                   localizacao = :localizacao,
                   responsavel = :responsavel,
                   status = :status,
                   ip = :ip,
                   snmp_habilitado = :snmp_habilitado,
                   snmp_community = :snmp_community,
                   observacoes = :observacoes,
                   detalhes = :detalhes
             WHERE id = :id
        ");

        return $stmt->execute([
            'id' => $id,
            'nome' => $dados['nome'],
            'marca' => $dados['marca'],
            'modelo' => $dados['modelo'],
            'numero_serie' => $dados['numero_serie'],
            'setor' => $dados['setor'],
            'localizacao' => $dados['localizacao'],
            'responsavel' => $dados['responsavel'],
            'status' => $dados['status'],
            'ip' => $dados['ip'],
            'snmp_habilitado' => $dados['snmp_habilitado'],
            'snmp_community' => $dados['snmp_community'],
            'observacoes' => $dados['observacoes'],
            'detalhes' => $dados['detalhes'],
        ]);
    }

    public function atualizarDetalhesSnmp(int $id, string $detalhesJson): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE ativos
               SET detalhes = :detalhes,
                   origem = 'snmp',
                   ultimo_checkin = NOW()
             WHERE id = :id
        ");

        return $stmt->execute(['id' => $id, 'detalhes' => $detalhesJson]);
    }

    public function listarComSnmpHabilitado(): array
    {
        $stmt = $this->pdo->query("
            SELECT * FROM ativos
            WHERE snmp_habilitado = 1 AND ip IS NOT NULL AND ip <> ''
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function excluir(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM ativos WHERE id = ?");

        return $stmt->execute([$id]);
    }

    public function ultimoCodigoPorTipo(string $tipo): ?string
    {
        $stmt = $this->pdo->prepare("
            SELECT codigo_patrimonio
            FROM ativos
            WHERE tipo = ?
            ORDER BY id DESC
            LIMIT 1
        ");

        $stmt->execute([$tipo]);

        $codigo = $stmt->fetchColumn();

        return $codigo !== false ? $codigo : null;
    }

    public function contarPorTipo(): array
    {
        $stmt = $this->pdo->query("SELECT tipo, COUNT(*) AS total FROM ativos GROUP BY tipo");

        $resultado = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $linha) {
            $resultado[$linha['tipo']] = (int)$linha['total'];
        }

        return $resultado;
    }

    public function contarPorStatus(): array
    {
        $stmt = $this->pdo->query("SELECT status, COUNT(*) AS total FROM ativos GROUP BY status");

        $resultado = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $linha) {
            $resultado[$linha['status']] = (int)$linha['total'];
        }

        return $resultado;
    }

    public function contarTotal(): int
    {
        return (int)$this->pdo->query("SELECT COUNT(*) FROM ativos")->fetchColumn();
    }

    public function recentes(int $limite = 8): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM ativos ORDER BY criado_em DESC LIMIT ?");
        $stmt->bindValue(1, $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarProgramas(int $ativoId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM ativos_programas WHERE ativo_id = ? ORDER BY nome");
        $stmt->execute([$ativoId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarAlertas(int $ativoId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM ativos_alertas WHERE ativo_id = ? ORDER BY coletado_em DESC LIMIT 100");
        $stmt->execute([$ativoId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
