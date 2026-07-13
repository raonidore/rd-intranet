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

    private const SELECT_COM_CATALOGOS = "
        SELECT a.*, cs.nome AS setor_nome, cl.nome AS localizacao_nome
        FROM ativos a
        LEFT JOIN ativos_catalogos cs ON cs.id = a.setor_id
        LEFT JOIN ativos_catalogos cl ON cl.id = a.localizacao_id
    ";

    public function listar(array $filtros = []): array
    {
        $sql = self::SELECT_COM_CATALOGOS . " WHERE 1=1";
        $params = [];

        if (!empty($filtros['tipo'])) {
            $sql .= " AND a.tipo = :tipo";
            $params['tipo'] = $filtros['tipo'];
        }

        if (!empty($filtros['status'])) {
            $sql .= " AND a.status = :status";
            $params['status'] = $filtros['status'];
        }

        if (!empty($filtros['busca'])) {
            $sql .= " AND (a.nome LIKE :busca OR a.codigo_patrimonio LIKE :busca OR a.numero_serie LIKE :busca)";
            $params['busca'] = '%' . $filtros['busca'] . '%';
        }

        $sql .= " ORDER BY a.criado_em DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->pdo->prepare(self::SELECT_COM_CATALOGOS . " WHERE a.id = ? LIMIT 1");
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
        $stmt = $this->pdo->prepare(self::SELECT_COM_CATALOGOS . " WHERE a.id IN ($placeholders) ORDER BY a.tipo, a.nome");
        $stmt->execute($ids);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function criar(array $dados): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO ativos
            (tipo, codigo_patrimonio, nome, marca, modelo, numero_serie, setor_id, localizacao_id, responsavel, status, ip, snmp_habilitado, snmp_community, observacoes, detalhes)
            VALUES
            (:tipo, :codigo_patrimonio, :nome, :marca, :modelo, :numero_serie, :setor_id, :localizacao_id, :responsavel, :status, :ip, :snmp_habilitado, :snmp_community, :observacoes, :detalhes)
        ");

        $stmt->execute([
            'tipo' => $dados['tipo'],
            'codigo_patrimonio' => $dados['codigo_patrimonio'],
            'nome' => $dados['nome'],
            'marca' => $dados['marca'],
            'modelo' => $dados['modelo'],
            'numero_serie' => $dados['numero_serie'],
            'setor_id' => $dados['setor_id'],
            'localizacao_id' => $dados['localizacao_id'],
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
                   setor_id = :setor_id,
                   localizacao_id = :localizacao_id,
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
            'setor_id' => $dados['setor_id'],
            'localizacao_id' => $dados['localizacao_id'],
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

    public function vincularMesh(int $id, ?string $meshDeviceId): bool
    {
        $stmt = $this->pdo->prepare("UPDATE ativos SET mesh_device_id = :mesh_device_id WHERE id = :id");

        return $stmt->execute(['id' => $id, 'mesh_device_id' => $meshDeviceId]);
    }

    public function limparMeshDeOutros(string $meshDeviceId, int $exceto): void
    {
        $stmt = $this->pdo->prepare("UPDATE ativos SET mesh_device_id = NULL WHERE mesh_device_id = :mesh_device_id AND id <> :id");
        $stmt->execute(['mesh_device_id' => $meshDeviceId, 'id' => $exceto]);
    }

    public function limparMesh(string $meshDeviceId): void
    {
        $stmt = $this->pdo->prepare("UPDATE ativos SET mesh_device_id = NULL WHERE mesh_device_id = :mesh_device_id");
        $stmt->execute(['mesh_device_id' => $meshDeviceId]);
    }

    public function listarComSnmpHabilitado(): array
    {
        $stmt = $this->pdo->query("
            SELECT * FROM ativos
            WHERE snmp_habilitado = 1 AND ip IS NOT NULL AND ip <> ''
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarPorMachineGuid(string $machineGuid): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM ativos WHERE machine_guid = ? LIMIT 1");
        $stmt->execute([$machineGuid]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function criarViaAgente(array $dados): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO ativos
            (tipo, codigo_patrimonio, nome, marca, modelo, numero_serie, ip, status, machine_guid, origem, ultimo_checkin, detalhes)
            VALUES
            (:tipo, :codigo_patrimonio, :nome, :marca, :modelo, :numero_serie, :ip, 'ativo', :machine_guid, 'agente', NOW(), :detalhes)
        ");

        $stmt->execute([
            'tipo' => $dados['tipo'],
            'codigo_patrimonio' => $dados['codigo_patrimonio'],
            'nome' => $dados['nome'],
            'marca' => $dados['marca'],
            'modelo' => $dados['modelo'],
            'numero_serie' => $dados['numero_serie'],
            'ip' => $dados['ip'],
            'machine_guid' => $dados['machine_guid'],
            'detalhes' => $dados['detalhes'],
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function atualizarViaAgente(int $id, array $camposBase, string $detalhesJson): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE ativos
               SET nome = :nome,
                   marca = COALESCE(:marca, marca),
                   modelo = COALESCE(:modelo, modelo),
                   numero_serie = COALESCE(:numero_serie, numero_serie),
                   ip = COALESCE(:ip, ip),
                   detalhes = :detalhes,
                   origem = 'agente',
                   ultimo_checkin = NOW()
             WHERE id = :id
        ");

        return $stmt->execute([
            'id' => $id,
            'nome' => $camposBase['nome'],
            'marca' => $camposBase['marca'],
            'modelo' => $camposBase['modelo'],
            'numero_serie' => $camposBase['numero_serie'],
            'ip' => $camposBase['ip'],
            'detalhes' => $detalhesJson,
        ]);
    }

    public function substituirProgramas(int $ativoId, array $programas): void
    {
        $this->pdo->prepare("DELETE FROM ativos_programas WHERE ativo_id = ?")->execute([$ativoId]);

        if (empty($programas)) {
            return;
        }

        $stmt = $this->pdo->prepare("INSERT INTO ativos_programas (ativo_id, nome, versao, data_instalacao, uninstall_string) VALUES (?, ?, ?, ?, ?)");

        foreach ($programas as $p) {
            $nome = trim((string)($p['nome'] ?? ''));
            if ($nome === '') {
                continue;
            }
            $versao = trim((string)($p['versao'] ?? ''));
            $dataInstalacao = trim((string)($p['data_instalacao'] ?? ''));
            $uninstallString = trim((string)($p['uninstall_string'] ?? ''));
            $stmt->execute([
                $ativoId,
                $nome,
                $versao !== '' ? $versao : null,
                $dataInstalacao !== '' ? $dataInstalacao : null,
                $uninstallString !== '' ? $uninstallString : null,
            ]);
        }
    }

    public function buscarPrograma(int $ativoId, int $programaId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM ativos_programas WHERE id = ? AND ativo_id = ? LIMIT 1");
        $stmt->execute([$programaId, $ativoId]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function substituirAtualizacoesWindows(int $ativoId, array $atualizacoes): void
    {
        $this->pdo->prepare("DELETE FROM ativos_atualizacoes_windows WHERE ativo_id = ?")->execute([$ativoId]);

        if (empty($atualizacoes)) {
            return;
        }

        $stmt = $this->pdo->prepare("INSERT INTO ativos_atualizacoes_windows (ativo_id, kb, descricao, instalado_em) VALUES (?, ?, ?, ?)");

        foreach ($atualizacoes as $a) {
            $kb = trim((string)($a['kb'] ?? ''));
            if ($kb === '') {
                continue;
            }

            $descricao = trim((string)($a['descricao'] ?? ''));
            $instaladoEm = trim((string)($a['instalado_em'] ?? ''));

            $stmt->execute([
                $ativoId,
                $kb,
                $descricao !== '' ? $descricao : null,
                $instaladoEm !== '' ? $instaladoEm : null,
            ]);
        }
    }

    public function listarAtualizacoesWindows(int $ativoId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM ativos_atualizacoes_windows WHERE ativo_id = ? ORDER BY instalado_em DESC, kb DESC");
        $stmt->execute([$ativoId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarAtualizacaoWindows(int $ativoId, int $atualizacaoId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM ativos_atualizacoes_windows WHERE id = ? AND ativo_id = ? LIMIT 1");
        $stmt->execute([$atualizacaoId, $ativoId]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function substituirRedes(int $ativoId, array $redes): void
    {
        $this->pdo->prepare("DELETE FROM ativos_redes WHERE ativo_id = ?")->execute([$ativoId]);

        if (empty($redes)) {
            return;
        }

        $stmt = $this->pdo->prepare("INSERT INTO ativos_redes (ativo_id, nome_adaptador, mac, ip) VALUES (?, ?, ?, ?)");

        foreach ($redes as $r) {
            $mac = trim((string)($r['mac'] ?? ''));
            $ip = trim((string)($r['ip'] ?? ''));
            if ($mac === '' && $ip === '') {
                continue;
            }

            $nomeAdaptador = trim((string)($r['nome_adaptador'] ?? ''));
            $stmt->execute([
                $ativoId,
                $nomeAdaptador !== '' ? $nomeAdaptador : null,
                $mac !== '' ? $mac : null,
                $ip !== '' ? $ip : null,
            ]);
        }
    }

    public function listarRedes(int $ativoId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM ativos_redes WHERE ativo_id = ? ORDER BY nome_adaptador");
        $stmt->execute([$ativoId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function substituirVolumes(int $ativoId, array $volumes): void
    {
        $this->pdo->prepare("DELETE FROM ativos_volumes WHERE ativo_id = ?")->execute([$ativoId]);

        if (empty($volumes)) {
            return;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO ativos_volumes (ativo_id, unidade, total_gb, usado_gb, modelo_disco, fabricante_disco, serial_disco)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($volumes as $v) {
            $unidade = trim((string)($v['unidade'] ?? ''));
            if ($unidade === '') {
                continue;
            }

            $modeloDisco = trim((string)($v['modelo_disco'] ?? ''));
            $fabricanteDisco = trim((string)($v['fabricante_disco'] ?? ''));
            $serialDisco = trim((string)($v['serial_disco'] ?? ''));

            $stmt->execute([
                $ativoId,
                $unidade,
                isset($v['total_gb']) ? (float)$v['total_gb'] : null,
                isset($v['usado_gb']) ? (float)$v['usado_gb'] : null,
                $modeloDisco !== '' ? $modeloDisco : null,
                $fabricanteDisco !== '' ? $fabricanteDisco : null,
                $serialDisco !== '' ? $serialDisco : null,
            ]);
        }
    }

    public function substituirMemoria(int $ativoId, array $modulos): void
    {
        $this->pdo->prepare("DELETE FROM ativos_memoria WHERE ativo_id = ?")->execute([$ativoId]);

        if (empty($modulos)) {
            return;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO ativos_memoria (ativo_id, fabricante, modelo, capacidade_gb, frequencia_mhz, numero_serie)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        foreach ($modulos as $m) {
            $fabricante = trim((string)($m['fabricante'] ?? ''));
            $modelo = trim((string)($m['modelo'] ?? ''));
            $serial = trim((string)($m['numero_serie'] ?? ''));

            if ($fabricante === '' && $modelo === '' && empty($m['capacidade_gb'])) {
                continue;
            }

            $stmt->execute([
                $ativoId,
                $fabricante !== '' ? $fabricante : null,
                $modelo !== '' ? $modelo : null,
                isset($m['capacidade_gb']) ? (float)$m['capacidade_gb'] : null,
                isset($m['frequencia_mhz']) ? (int)$m['frequencia_mhz'] : null,
                $serial !== '' ? $serial : null,
            ]);
        }
    }

    public function listarMemoria(int $ativoId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM ativos_memoria WHERE ativo_id = ? ORDER BY id");
        $stmt->execute([$ativoId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarVolumes(int $ativoId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM ativos_volumes WHERE ativo_id = ? ORDER BY unidade");
        $stmt->execute([$ativoId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function substituirPortas(int $ativoId, array $portas): void
    {
        $this->pdo->prepare("DELETE FROM ativos_portas WHERE ativo_id = ?")->execute([$ativoId]);

        if (empty($portas)) {
            return;
        }

        $stmt = $this->pdo->prepare("INSERT INTO ativos_portas (ativo_id, tipo, descricao) VALUES (?, ?, ?)");

        foreach ($portas as $p) {
            $descricao = trim((string)($p['descricao'] ?? ''));
            if ($descricao === '') {
                continue;
            }

            $tipo = in_array($p['tipo'] ?? '', ['usb', 'serial'], true) ? $p['tipo'] : 'usb';
            $stmt->execute([$ativoId, $tipo, $descricao]);
        }
    }

    public function listarPortas(int $ativoId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM ativos_portas WHERE ativo_id = ? ORDER BY tipo, descricao");
        $stmt->execute([$ativoId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function substituirPortasRede(int $ativoId, array $portas): void
    {
        $this->pdo->prepare("DELETE FROM ativos_portas_rede WHERE ativo_id = ?")->execute([$ativoId]);

        if (empty($portas)) {
            return;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO ativos_portas_rede (ativo_id, protocolo, porta_local, endereco_local, processo, pid)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        foreach ($portas as $p) {
            $protocolo = strtolower(trim((string)($p['protocolo'] ?? '')));
            $porta = (int)($p['porta_local'] ?? 0);

            if (!in_array($protocolo, ['tcp', 'udp'], true) || $porta <= 0 || $porta > 65535) {
                continue;
            }

            $stmt->execute([
                $ativoId,
                $protocolo,
                $porta,
                trim((string)($p['endereco_local'] ?? '')) ?: null,
                trim((string)($p['processo'] ?? '')) ?: null,
                isset($p['pid']) ? (int)$p['pid'] : null,
            ]);
        }
    }

    public function listarPortasRede(int $ativoId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM ativos_portas_rede WHERE ativo_id = ? ORDER BY protocolo, porta_local");
        $stmt->execute([$ativoId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function inserirAlertas(int $ativoId, array $alertas): void
    {
        if (empty($alertas)) {
            return;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO ativos_alertas (ativo_id, nivel, origem_evento, mensagem, ocorrido_em)
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($alertas as $a) {
            $mensagem = trim((string)($a['mensagem'] ?? ''));
            if ($mensagem === '') {
                continue;
            }

            $nivel = in_array($a['nivel'] ?? '', ['erro', 'aviso', 'informacao'], true) ? $a['nivel'] : 'informacao';
            $origemEvento = trim((string)($a['origem_evento'] ?? ''));
            $ocorridoEm = !empty($a['ocorrido_em']) ? $a['ocorrido_em'] : null;

            $stmt->execute([$ativoId, $nivel, $origemEvento !== '' ? $origemEvento : null, $mensagem, $ocorridoEm]);
        }
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
        $stmt = $this->pdo->prepare(self::SELECT_COM_CATALOGOS . " ORDER BY a.criado_em DESC LIMIT ?");
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

    public function criarComando(int $ativoId, string $comando, ?string $solicitadoPor, ?string $alvo = null, ?string $alvoLabel = null): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO ativos_comandos (ativo_id, comando, alvo, alvo_label, solicitado_por)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$ativoId, $comando, $alvo, $alvoLabel, $solicitadoPor]);

        return (int)$this->pdo->lastInsertId();
    }

    public function comandosPendentes(int $ativoId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM ativos_comandos
            WHERE ativo_id = ? AND status = 'pendente'
            ORDER BY id
        ");
        $stmt->execute([$ativoId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function marcarComandosEntregues(array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("
            UPDATE ativos_comandos
               SET status = 'entregue', entregue_em = NOW()
             WHERE id IN ($placeholders)
        ");
        $stmt->execute($ids);
    }

    public function historicoComandos(int $ativoId, int $limite = 10): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM ativos_comandos
            WHERE ativo_id = ?
            ORDER BY id DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $ativoId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
