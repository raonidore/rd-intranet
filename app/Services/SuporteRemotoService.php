<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Chamados > Suporte Remoto: pedidos de ajuda feitos pelo botão "Pedir
 * ajuda" do agente, atalho de conexão pra todas as máquinas ligadas ao
 * MeshCentral e os dispositivos do MeshCentral que não são ativos
 * cadastrados (ex.: cliente que entrou pelo MeshCentral Assistant).
 */
class SuporteRemotoService
{
    /** Uma máquina com pedido aberto não abre outro -- atualiza a mensagem. */
    private const LIMITE_MENSAGEM = 500;

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    /** @return array{success: bool, message: string, id?: int} */
    public function registrarPedido(int $ativoId, string $usuario, string $mensagem): array
    {
        $usuario = mb_substr(trim($usuario), 0, 150);
        $mensagem = mb_substr(trim($mensagem), 0, self::LIMITE_MENSAGEM);

        $stmt = $this->pdo->prepare("SELECT id FROM suporte_remoto_pedidos WHERE ativo_id = ? AND status = 'aberto' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$ativoId]);
        $aberto = $stmt->fetchColumn();

        if ($aberto !== false) {
            $this->pdo->prepare('UPDATE suporte_remoto_pedidos SET usuario = ?, mensagem = ?, criado_em = NOW() WHERE id = ?')
                ->execute([$usuario ?: null, $mensagem ?: null, (int)$aberto]);
            return ['success' => true, 'message' => 'Pedido atualizado -- o suporte já foi avisado.', 'id' => (int)$aberto];
        }

        $this->pdo->prepare('INSERT INTO suporte_remoto_pedidos (ativo_id, usuario, mensagem) VALUES (?, ?, ?)')
            ->execute([$ativoId, $usuario ?: null, $mensagem ?: null]);
        $id = (int)$this->pdo->lastInsertId();

        $ativo = (new AtivoService())->buscar($ativoId);
        AuditService::registrar('Chamados', 'Suporte remoto', sprintf(
            'Pedido de ajuda de %s (%s)%s.',
            $ativo['codigo_patrimonio'] ?? "#{$ativoId}",
            $ativo['nome'] ?? '?',
            $usuario !== '' ? " -- usuário {$usuario}" : ''
        ));

        return ['success' => true, 'message' => 'Pedido enviado ao suporte.', 'id' => $id];
    }

    public function listarAbertos(): array
    {
        $this->vincularInstalacoesMeshAguardando();

        return $this->pdo->query(
            "SELECT p.*, a.codigo_patrimonio, a.nome AS ativo_nome, a.ip, a.mesh_device_id, a.ultimo_heartbeat,
                    TIMESTAMPDIFF(MINUTE, p.criado_em, NOW()) AS minutos_esperando
             FROM suporte_remoto_pedidos p
             JOIN ativos a ON a.id = p.ativo_id
             WHERE p.status = 'aberto'
             ORDER BY p.criado_em ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function meshAgentDisponivel(): bool
    {
        $acesso = new AcessoRemotoService();
        return $acesso->rodando()
            && $acesso->credenciaisConfiguradas()
            && ($acesso->meshAgenteDisponivel('x64') || $acesso->meshAgenteDisponivel('arm64'));
    }

    public function solicitarInstalacaoMesh(int $pedidoId, string $solicitadoPor): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.id, p.ativo_id, p.status, a.mesh_device_id
             FROM suporte_remoto_pedidos p JOIN ativos a ON a.id = p.ativo_id
             WHERE p.id = ? LIMIT 1"
        );
        $stmt->execute([$pedidoId]);
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$pedido || $pedido['status'] !== 'aberto') {
            return ['success' => false, 'message' => 'Pedido não encontrado ou já atendido.'];
        }
        if (!empty($pedido['mesh_device_id'])) {
            return ['success' => false, 'message' => 'Esta máquina já está vinculada ao MeshCentral.'];
        }
        if (!$this->meshAgentDisponivel()) {
            return ['success' => false, 'message' => 'MeshCentral precisa estar ativo, com credenciais e instalador x64 ou ARM64 enviados em Ativos > Acesso Remoto.'];
        }

        $pendente = $this->pdo->prepare(
            "SELECT id FROM ativos_solicitacoes WHERE ativo_id = ? AND tipo = 'mesh_install' AND status = 'pendente' ORDER BY id DESC LIMIT 1"
        );
        $pendente->execute([(int)$pedido['ativo_id']]);
        $idPendente = $pendente->fetchColumn();
        if ($idPendente !== false) {
            return ['success' => true, 'solicitacao_id' => (int)$idPendente, 'ativo_id' => (int)$pedido['ativo_id'], 'message' => 'A instalação já está em andamento.'];
        }

        $solicitacao = (new AtivoService())->solicitarListagem((int)$pedido['ativo_id'], 'mesh_install', null, $solicitadoPor);
        if (empty($solicitacao['success'])) return $solicitacao;

        return [
            'success' => true,
            'solicitacao_id' => (int)$solicitacao['id'],
            'ativo_id' => (int)$pedido['ativo_id'],
            'message' => 'Pedido de instalação enviado ao agente.',
        ];
    }

    public function statusInstalacaoMesh(int $pedidoId, int $solicitacaoId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.ativo_id, p.status AS pedido_status, s.tipo, s.respondido_em
             FROM suporte_remoto_pedidos p
             JOIN ativos_solicitacoes s ON s.ativo_id = p.ativo_id AND s.id = ?
             WHERE p.id = ? LIMIT 1"
        );
        $stmt->execute([$solicitacaoId, $pedidoId]);
        $dados = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$dados || $dados['tipo'] !== 'mesh_install') {
            return ['success' => false, 'message' => 'Solicitação de instalação não encontrada.'];
        }

        $resultado = (new AtivoService())->resultadoSolicitacao($solicitacaoId, (int)$dados['ativo_id']);
        if (empty($resultado['success']) || $resultado['status'] !== 'concluido') return $resultado;

        $detalhes = $resultado['resultado'] ?? [];
        if (empty($detalhes['instalado']) && empty($detalhes['ja_instalado'])) return $resultado;

        $vinculo = $this->vincularMeshSeUnico((int)$dados['ativo_id']);
        if (!empty($vinculo['vinculado'])) {
            $detalhes['mesh_device_id'] = $vinculo['mesh_device_id'];
            $detalhes['vinculado'] = true;
            $detalhes['mensagem'] = 'MeshAgent instalado e máquina vinculada ao MeshCentral.';
            return ['success' => true, 'status' => 'concluido', 'resultado' => $detalhes];
        }

        $segundosDesdeResposta = !empty($dados['respondido_em']) ? time() - strtotime($dados['respondido_em']) : 0;
        if ($segundosDesdeResposta < 75 && $dados['pedido_status'] === 'aberto') {
            return [
                'success' => true,
                'status' => 'pendente',
                'etapa' => 'aguardando_meshcentral',
                'message' => 'MeshAgent instalado; aguardando o dispositivo aparecer no MeshCentral.',
            ];
        }

        $detalhes['vinculado'] = false;
        $detalhes['mensagem'] = $vinculo['message'] ?? 'MeshAgent instalado, mas não foi possível vincular automaticamente.';
        return ['success' => true, 'status' => 'concluido', 'resultado' => $detalhes];
    }

    private function vincularInstalacoesMeshAguardando(): void
    {
        try {
            $stmt = $this->pdo->query(
                "SELECT DISTINCT s.ativo_id, s.resultado
                 FROM ativos_solicitacoes s
                 JOIN ativos a ON a.id = s.ativo_id
                 JOIN suporte_remoto_pedidos p ON p.ativo_id = a.id AND p.status = 'aberto'
                 WHERE s.tipo = 'mesh_install' AND s.status = 'concluido'
                   AND a.mesh_device_id IS NULL"
            );
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $solicitacao) {
                $resultado = json_decode($solicitacao['resultado'] ?? '', true) ?: [];
                if (!empty($resultado['instalado']) || !empty($resultado['ja_instalado'])) {
                    $this->vincularMeshSeUnico((int)$solicitacao['ativo_id']);
                }
            }
        } catch (\Throwable) {
            // A listagem dos pedidos continua disponível mesmo se MeshCentral estiver temporariamente offline.
        }
    }

    private function vincularMeshSeUnico(int $ativoId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, nome, detalhes, mesh_device_id FROM ativos WHERE id = ? LIMIT 1');
        $stmt->execute([$ativoId]);
        $ativo = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ativo) return ['vinculado' => false, 'message' => 'Ativo não encontrado.'];
        if (!empty($ativo['mesh_device_id'])) return ['vinculado' => true, 'mesh_device_id' => $ativo['mesh_device_id']];

        $detalhes = json_decode($ativo['detalhes'] ?? '', true) ?: [];
        $nomesAtivo = array_filter([
            $this->normalizarNomeMesh((string)($detalhes['nome_computador'] ?? '')),
            $this->normalizarNomeMesh((string)$ativo['nome']),
        ]);
        if (!$nomesAtivo) return ['vinculado' => false, 'message' => 'O ativo não tem hostname para comparar com o MeshCentral.'];

        $acesso = new AcessoRemotoService();
        if (!$acesso->rodando() || !$acesso->credenciaisConfiguradas())
            return ['vinculado' => false, 'message' => 'MeshCentral está indisponível para concluir o vínculo.'];

        $candidatos = [];
        foreach ($acesso->listarDispositivos() as $dispositivo) {
            $id = (string)($dispositivo['_id'] ?? '');
            if ($id === '') continue;
            $nomesDispositivo = array_filter([
                $this->normalizarNomeMesh((string)($dispositivo['name'] ?? '')),
                $this->normalizarNomeMesh((string)($dispositivo['rname'] ?? '')),
                $this->normalizarNomeMesh((string)($dispositivo['hostname'] ?? '')),
            ]);
            if (!array_intersect($nomesAtivo, $nomesDispositivo)) continue;

            $ocupado = $this->pdo->prepare('SELECT id FROM ativos WHERE mesh_device_id = ? AND id <> ? LIMIT 1');
            $ocupado->execute([$id, $ativoId]);
            if ($ocupado->fetchColumn() === false) $candidatos[$id] = true;
        }

        if (count($candidatos) !== 1) {
            return [
                'vinculado' => false,
                'message' => count($candidatos) > 1
                    ? 'Mais de um dispositivo MeshCentral tem esse hostname; faça o vínculo manual para evitar associar o ativo errado.'
                    : 'MeshAgent instalado; dispositivo ainda não apareceu com um hostname único no MeshCentral.',
            ];
        }

        $meshDeviceId = (string)array_key_first($candidatos);
        $ok = (new AtivoService())->vincularDispositivoMesh($ativoId, $meshDeviceId);
        return $ok
            ? ['vinculado' => true, 'mesh_device_id' => $meshDeviceId]
            : ['vinculado' => false, 'message' => 'Não foi possível salvar o vínculo automático.'];
    }

    private function normalizarNomeMesh(string $nome): string
    {
        return mb_strtolower(rtrim(trim($nome), '.'), 'UTF-8');
    }

    /** Barato pra rodar em toda página (badge do menu). Tabela ausente = 0. */
    public function contarAbertos(): int
    {
        try {
            return (int)$this->pdo->query("SELECT COUNT(*) FROM suporte_remoto_pedidos WHERE status = 'aberto'")->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    public function marcarAtendido(int $id, string $usuario): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE suporte_remoto_pedidos SET status = 'atendido', atendido_em = NOW(), atendido_por = ? WHERE id = ? AND status = 'aberto'"
        );
        $stmt->execute([mb_substr($usuario, 0, 100), $id]);

        return $stmt->rowCount() > 0;
    }

    /** Ativos ligados ao MeshCentral -- o atalho de conexão rápida. */
    public function ativosComAcessoRemoto(): array
    {
        return $this->pdo->query(
            "SELECT a.id, a.codigo_patrimonio, a.nome, a.ip, a.mesh_device_id, a.ultimo_heartbeat,
                    (a.ultimo_heartbeat IS NOT NULL AND a.ultimo_heartbeat > NOW() - INTERVAL 2 MINUTE) AS online,
                    JSON_UNQUOTE(JSON_EXTRACT(a.detalhes, '$.usuario_logado')) AS usuario_logado
             FROM ativos a
             WHERE a.mesh_device_id IS NOT NULL AND a.mesh_device_id <> ''
             ORDER BY online DESC, a.codigo_patrimonio"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Dispositivos do MeshCentral sem ativo cadastrado. Vazio quando o
     * MeshCentral não está instalado/rodando ou sem credencial configurada.
     */
    public function dispositivosSemCadastro(): array
    {
        try {
            $acesso = new AcessoRemotoService();
            if (!$acesso->rodando() || !$acesso->credenciaisConfiguradas()) {
                return [];
            }

            $vinculados = array_flip($this->pdo->query(
                "SELECT mesh_device_id FROM ativos WHERE mesh_device_id IS NOT NULL AND mesh_device_id <> ''"
            )->fetchAll(PDO::FETCH_COLUMN));

            $dispositivos = [];
            foreach ($acesso->listarDispositivos() as $d) {
                $id = (string)($d['_id'] ?? '');
                if ($id === '' || isset($vinculados[$id])) {
                    continue;
                }
                $dispositivos[] = [
                    'id' => $id,
                    'nome' => (string)($d['name'] ?? $d['rname'] ?? '?'),
                    'grupo' => (string)($d['groupname'] ?? ''),
                    'ip' => (string)($d['ip'] ?? $d['host'] ?? ''),
                    'usuarios' => implode(', ', array_map('strval', (array)($d['users'] ?? []))),
                    'online' => (int)($d['conn'] ?? 0) > 0,
                    'sistema' => (string)($d['osdesc'] ?? ''),
                ];
            }

            usort($dispositivos, fn ($a, $b) => [$b['online'], $a['nome']] <=> [$a['online'], $b['nome']]);

            return $dispositivos;
        } catch (\Throwable) {
            return [];
        }
    }

    /** Só dispositivos que existem no MeshCentral e não são ativos (os ativos usam o fluxo da ficha). */
    public function dispositivoSemCadastroExiste(string $meshDeviceId): bool
    {
        foreach ($this->dispositivosSemCadastro() as $d) {
            if ($d['id'] === $meshDeviceId) {
                return true;
            }
        }

        return false;
    }
}
