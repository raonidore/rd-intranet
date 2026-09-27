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
        return $this->pdo->query(
            "SELECT p.*, a.codigo_patrimonio, a.nome AS ativo_nome, a.ip, a.mesh_device_id, a.ultimo_heartbeat,
                    TIMESTAMPDIFF(MINUTE, p.criado_em, NOW()) AS minutos_esperando
             FROM suporte_remoto_pedidos p
             JOIN ativos a ON a.id = p.ativo_id
             WHERE p.status = 'aberto'
             ORDER BY p.criado_em ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
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
