<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class VpnIkev2SaidaRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listar(): array
    {
        $stmt = $this->pdo->query('
            SELECT id, nome, servidor_remoto, tipo_auth, usuario_eap, subnet_remota, ativo_no_boot, criado_em
              FROM vpn_ikev2_conexoes_saida
             ORDER BY nome
        ');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM vpn_ikev2_conexoes_saida WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function buscarPorNome(string $nome): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM vpn_ikev2_conexoes_saida WHERE nome = ? LIMIT 1');
        $stmt->execute([$nome]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function listarAtivas(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM vpn_ikev2_conexoes_saida ORDER BY nome');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function criar(array $dados): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO vpn_ikev2_conexoes_saida
                (nome, servidor_remoto, tipo_auth, segredo, usuario_eap, subnet_remota, ca_remota)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $dados['nome'],
            $dados['servidor_remoto'],
            $dados['tipo_auth'],
            $dados['segredo'],
            $dados['usuario_eap'],
            $dados['subnet_remota'],
            $dados['ca_remota'],
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function marcarAtivoNoBoot(int $id, bool $ativo): void
    {
        $stmt = $this->pdo->prepare('UPDATE vpn_ikev2_conexoes_saida SET ativo_no_boot = ? WHERE id = ?');
        $stmt->execute([$ativo ? 1 : 0, $id]);
    }

    public function excluir(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM vpn_ikev2_conexoes_saida WHERE id = ?');
        $stmt->execute([$id]);
    }
}
