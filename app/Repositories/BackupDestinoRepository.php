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
                dropbox_token_cifrado, dropbox_client_id, dropbox_client_secret_cifrada, dropbox_prefixo,
                storj_access_key_id, storj_secret_access_key_cifrada, storj_bucket, storj_prefixo,
                scaleway_access_key_id, scaleway_secret_access_key_cifrada, scaleway_bucket, scaleway_regiao, scaleway_prefixo,
                hetzner_access_key_id, hetzner_secret_access_key_cifrada, hetzner_bucket, hetzner_regiao, hetzner_prefixo,
                akamai_access_key_id, akamai_secret_access_key_cifrada, akamai_bucket, akamai_regiao, akamai_prefixo,
                relatorio_diario_ativo, alerta_falha_ativo, email_notificacao
            ) VALUES (
                :provider, :nome, :ativo, :retencao_dias,
                :b2_key_id, :b2_application_key_cifrada, :b2_bucket, :b2_prefixo,
                :s3_access_key_id, :s3_secret_access_key_cifrada, :s3_bucket, :s3_regiao, :s3_endpoint, :s3_prefixo,
                :drive_token_cifrado, :drive_client_id, :drive_client_secret_cifrada, :drive_pasta_id,
                :dropbox_token_cifrado, :dropbox_client_id, :dropbox_client_secret_cifrada, :dropbox_prefixo,
                :storj_access_key_id, :storj_secret_access_key_cifrada, :storj_bucket, :storj_prefixo,
                :scaleway_access_key_id, :scaleway_secret_access_key_cifrada, :scaleway_bucket, :scaleway_regiao, :scaleway_prefixo,
                :hetzner_access_key_id, :hetzner_secret_access_key_cifrada, :hetzner_bucket, :hetzner_regiao, :hetzner_prefixo,
                :akamai_access_key_id, :akamai_secret_access_key_cifrada, :akamai_bucket, :akamai_regiao, :akamai_prefixo,
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
                dropbox_token_cifrado = :dropbox_token_cifrado, dropbox_client_id = :dropbox_client_id,
                dropbox_client_secret_cifrada = :dropbox_client_secret_cifrada, dropbox_prefixo = :dropbox_prefixo,
                storj_access_key_id = :storj_access_key_id,
                storj_secret_access_key_cifrada = :storj_secret_access_key_cifrada,
                storj_bucket = :storj_bucket, storj_prefixo = :storj_prefixo,
                scaleway_access_key_id = :scaleway_access_key_id,
                scaleway_secret_access_key_cifrada = :scaleway_secret_access_key_cifrada,
                scaleway_bucket = :scaleway_bucket, scaleway_regiao = :scaleway_regiao,
                scaleway_prefixo = :scaleway_prefixo,
                hetzner_access_key_id = :hetzner_access_key_id,
                hetzner_secret_access_key_cifrada = :hetzner_secret_access_key_cifrada,
                hetzner_bucket = :hetzner_bucket, hetzner_regiao = :hetzner_regiao,
                hetzner_prefixo = :hetzner_prefixo,
                akamai_access_key_id = :akamai_access_key_id,
                akamai_secret_access_key_cifrada = :akamai_secret_access_key_cifrada,
                akamai_bucket = :akamai_bucket, akamai_regiao = :akamai_regiao,
                akamai_prefixo = :akamai_prefixo,
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

    /** Mesma logica de atualizarDriveToken(), pro Dropbox (tambem OAuth). */
    public function atualizarDropboxToken(int $id, string $tokenCifrado): void
    {
        $stmt = $this->pdo->prepare("UPDATE backup_destinos SET dropbox_token_cifrado = ? WHERE id = ?");
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
            'dropbox_token_cifrado' => $dados['dropbox_token_cifrado'] ?? null,
            'dropbox_client_id' => $dados['dropbox_client_id'] ?? null,
            'dropbox_client_secret_cifrada' => $dados['dropbox_client_secret_cifrada'] ?? null,
            'dropbox_prefixo' => $dados['dropbox_prefixo'] ?? null,
            'storj_access_key_id' => $dados['storj_access_key_id'] ?? null,
            'storj_secret_access_key_cifrada' => $dados['storj_secret_access_key_cifrada'] ?? null,
            'storj_bucket' => $dados['storj_bucket'] ?? null,
            'storj_prefixo' => $dados['storj_prefixo'] ?? null,
            'scaleway_access_key_id' => $dados['scaleway_access_key_id'] ?? null,
            'scaleway_secret_access_key_cifrada' => $dados['scaleway_secret_access_key_cifrada'] ?? null,
            'scaleway_bucket' => $dados['scaleway_bucket'] ?? null,
            'scaleway_regiao' => $dados['scaleway_regiao'] ?? null,
            'scaleway_prefixo' => $dados['scaleway_prefixo'] ?? null,
            'hetzner_access_key_id' => $dados['hetzner_access_key_id'] ?? null,
            'hetzner_secret_access_key_cifrada' => $dados['hetzner_secret_access_key_cifrada'] ?? null,
            'hetzner_bucket' => $dados['hetzner_bucket'] ?? null,
            'hetzner_regiao' => $dados['hetzner_regiao'] ?? null,
            'hetzner_prefixo' => $dados['hetzner_prefixo'] ?? null,
            'akamai_access_key_id' => $dados['akamai_access_key_id'] ?? null,
            'akamai_secret_access_key_cifrada' => $dados['akamai_secret_access_key_cifrada'] ?? null,
            'akamai_bucket' => $dados['akamai_bucket'] ?? null,
            'akamai_regiao' => $dados['akamai_regiao'] ?? null,
            'akamai_prefixo' => $dados['akamai_prefixo'] ?? null,
            'relatorio_diario_ativo' => !empty($dados['relatorio_diario_ativo']) ? 1 : 0,
            'alerta_falha_ativo' => !empty($dados['alerta_falha_ativo']) ? 1 : 0,
            'email_notificacao' => $dados['email_notificacao'] ?? null,
        ];
    }
}
