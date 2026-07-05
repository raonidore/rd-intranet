<?php

namespace App\Services;

use App\Core\Database;
use App\Repositories\DeployCenterRepository;
use PDO;

class SambaGrupoService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    /**
     * Nomes de grupo já em uso (por compartilhamentos ou por usuários),
     * para sugerir nos formulários de criação/edição.
     */
    public function listarNomes(): array
    {
        $stmt = $this->pdo->query("
            SELECT grupo AS nome FROM samba_compartilhamentos
            UNION
            SELECT departamento AS nome FROM samba_usuarios
            ORDER BY nome
        ");

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Grupos com detalhe de quem usa cada um (compartilhamentos e usuários),
     * para a tela de consulta de Grupos Samba.
     */
    public function listarComDetalhes(): array
    {
        $grupos = [];

        foreach ($this->listarNomes() as $nome) {
            $grupos[$nome] = [
                'nome' => $nome,
                'compartilhamentos' => [],
                'usuarios' => [],
            ];
        }

        $stmtShares = $this->pdo->query("SELECT id, nome, grupo FROM samba_compartilhamentos ORDER BY nome");
        foreach ($stmtShares->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $grupos[$row['grupo']]['compartilhamentos'][] = [
                'id' => $row['id'],
                'nome' => $row['nome'],
            ];
        }

        $stmtUsers = $this->pdo->query("
            SELECT id, nome, login, departamento
            FROM samba_usuarios
            WHERE status = 'ativo'
            ORDER BY nome
        ");
        foreach ($stmtUsers->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $grupos[$row['departamento']]['usuarios'][] = [
                'id' => $row['id'],
                'nome' => $row['nome'],
                'login' => $row['login'],
            ];
        }

        return array_values($grupos);
    }

    /**
     * Renomeia um grupo em todo lugar: Linux (groupmod), compartilhamentos
     * e usuários no banco, e reaplica o deploy do Samba na hora -- o
     * smb.conf referencia o grupo pelo nome como texto puro, então deixar
     * isso pendente (como o resto do app faz) deixaria o compartilhamento
     * afetado sem ninguém conseguindo resolver o grupo até o próximo deploy.
     */
    public function renomear(string $antigo, string $novo): bool
    {
        $antigo = trim($antigo);
        $novo = trim(strtolower($novo));

        if (!preg_match('/^[a-z][a-z0-9_-]*$/', $novo)) {
            NotificationService::error('Nome de grupo inválido. Use letras minúsculas, números, "_" e "-", começando com letra.');
            return false;
        }

        if ($novo === $antigo) {
            NotificationService::error('O novo nome é igual ao atual.');
            return false;
        }

        if (in_array($novo, $this->listarNomes(), true)) {
            NotificationService::error('Já existe um grupo com esse nome.');
            return false;
        }

        $linux = new LinuxService();
        $resultado = $linux->executarScript(
            '/opt/rdtecnologia/scripts/renomear_grupo_web.sh',
            [$antigo, $novo]
        );

        if (!$resultado['success']) {
            NotificationService::error('Erro ao renomear o grupo no sistema.', $resultado['output']);
            return false;
        }

        $this->pdo->beginTransaction();

        try {
            $this->pdo->prepare("UPDATE samba_compartilhamentos SET grupo = ? WHERE grupo = ?")
                ->execute([$novo, $antigo]);

            $this->pdo->prepare("UPDATE samba_usuarios SET departamento = ? WHERE departamento = ?")
                ->execute([$novo, $antigo]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            NotificationService::error('Grupo renomeado no sistema, mas falhou ao atualizar o banco. Verifique manualmente.', $e->getMessage());
            return false;
        }

        AuditService::registrar('Samba', 'Renomear grupo', "Grupo '{$antigo}' renomeado para '{$novo}'.");

        $deploy = (new SambaConfigDeployService())->deploy();

        if (!$deploy['success']) {
            NotificationService::error(
                "Grupo renomeado para '{$novo}', mas falhou ao reaplicar o smb.conf. Aplique manualmente no Deploy Center o quanto antes.",
                $deploy['output']
            );
            return false;
        }

        $backup = null;
        if (preg_match('/Backup criado em:\s*(.+)/', $deploy['output'], $m)) {
            $backup = trim($m[1]);
        }

        (new DeployCenterRepository())->marcarAplicado('samba', $backup);

        NotificationService::success(
            "Grupo renomeado de '{$antigo}' para '{$novo}' e smb.conf já reaplicado.",
            $deploy['output']
        );

        return true;
    }
}
