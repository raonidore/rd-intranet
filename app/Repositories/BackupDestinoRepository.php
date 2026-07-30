<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class BackupDestinoRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listar(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM backup_destinos ORDER BY id DESC");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM backup_destinos WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function criar(array $dados): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO backup_destinos (
                provider, nome, ativo, retencao_dias,
                b2_key_id, b2_application_key_cifrada, b2_bucket, b2_prefixo,
                s3_access_key_id, s3_secret_access_key_cifrada, s3_bucket, s3_regiao, s3_endpoint, s3_prefixo,
                drive_token_cifrado, drive_client_id, drive_client_secret_cifrada, drive_pasta_id,
                relatorio_diario_ativo, alerta_falha_ativo, email_notificacao
            ) VALUES (
                :provider, :nome, :ativo, :retencao_dias,
                :b2_key_id, :b2_application_key_cifrada, :b2_bucket, :b2_prefixo,
                :s3_access_key_id, :s3_secret_access_key_cifrada, :s3_bucket, :s3_regiao, :s3_endpoint, :s3_prefixo,
                :drive_token_cifrado, :drive_client_id, :drive_client_secret_cifrada, :drive_pasta_id,
                :relatorio_diario_ativo, :alerta_falha_ativo, :email_notificacao
            )
        ");
        $stmt->execute($this->parametros($dados));

        return (int)$this->pdo->lastInsertId();
    }

    public function atualizar(int $id, array $dados): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE backup_destinos SET
                provider = :provider, nome = :nome, ativo = :ativo,
                retencao_dias = :retencao_dias,
                b2_key_id = :b2_key_id, b2_application_key_cifrada = :b2_application_key_cifrada,
                b2_bucket = :b2_bucket, b2_prefixo = :b2_prefixo,
                s3_access_key_id = :s3_access_key_id, s3_secret_access_key_cifrada = :s3_secret_access_key_cifrada,
                s3_bucket = :s3_bucket, s3_regiao = :s3_regiao, s3_endpoint = :s3_endpoint, s3_prefixo = :s3_prefixo,
                drive_token_cifrado = :drive_token_cifrado, drive_client_id = :drive_client_id,
                drive_client_secret_cifrada = :drive_client_secret_cifrada, drive_pasta_id = :drive_pasta_id,
                relatorio_diario_ativo = :relatorio_diario_ativo, alerta_falha_ativo = :alerta_falha_ativo,
                email_notificacao = :email_notificacao
            WHERE id = :id
        ");
        $stmt->execute(array_merge($this->parametros($dados), ['id' => $id]));
    }

    public function excluir(int $id): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM backup_destinos WHERE id = ?");
        $stmt->execute([$id]);
    }

    public function definirAtivo(int $id, bool $ativo): void
    {
        $stmt = $this->pdo->prepare("UPDATE backup_destinos SET ativo = ? WHERE id = ?");
        $stmt->execute([$ativo ? 1 : 0, $id]);
    }

    public function desativarTodos(): void
    {
        $this->pdo->exec("UPDATE backup_destinos SET ativo = 0");
    }

    /** Atualiza so o token do Google Drive (rclone renova o access_token a cada execucao). */
    public function atualizarDriveToken(int $id, string $tokenCifrado): void
    {
        $stmt = $this->pdo->prepare("UPDATE backup_destinos SET drive_token_cifrado = ? WHERE id = ?");
        $stmt->execute([$tokenCifrado, $id]);
    }

    private function parametros(array $dados): array
    {
        return [
            'provider' => $dados['provider'],
            'nome' => $dados['nome'],
            'ativo' => !empty($dados['ativo']) ? 1 : 0,
            'retencao_dias' => (int)$dados['retencao_dias'],
            'b2_key_id' => $dados['b2_key_id'] ?? null,
            'b2_application_key_cifrada' => $dados['b2_application_key_cifrada'] ?? null,
            'b2_bucket' => $dados['b2_bucket'] ?? null,
            'b2_prefixo' => $dados['b2_prefixo'] ?? null,
            's3_access_key_id' => $dados['s3_access_key_id'] ?? null,
            's3_secret_access_key_cifrada' => $dados['s3_secret_access_key_cifrada'] ?? null,
            's3_bucket' => $dados['s3_bucket'] ?? null,
            's3_regiao' => $dados['s3_regiao'] ?? null,
            's3_endpoint' => $dados['s3_endpoint'] ?? null,
            's3_prefixo' => $dados['s3_prefixo'] ?? null,
            'drive_token_cifrado' => $dados['drive_token_cifrado'] ?? null,
            'drive_client_id' => $dados['drive_client_id'] ?? null,
            'drive_client_secret_cifrada' => $dados['drive_client_secret_cifrada'] ?? null,
            'drive_pasta_id' => $dados['drive_pasta_id'] ?? null,
            'relatorio_diario_ativo' => !empty($dados['relatorio_diario_ativo']) ? 1 : 0,
            'alerta_falha_ativo' => !empty($dados['alerta_falha_ativo']) ? 1 : 0,
            'email_notificacao' => $dados['email_notificacao'] ?? null,
        ];
    }
}
