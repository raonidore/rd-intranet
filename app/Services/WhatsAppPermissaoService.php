<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use Throwable;

/**
 * Controle fino de visibilidade dentro de Atendimentos > Encerrados --
 * duas restrições independentes, cada uma desligada por padrão (nada
 * muda até alguém ativar em Configurações): quem pode abrir a aba
 * Encerrados, e quem, entre esses, vê também as mensagens da pesquisa
 * de satisfação dentro da conversa. Admin sempre vê tudo, igual ao
 * bypass já usado em PermissionService::temAcesso().
 */
class WhatsAppPermissaoService
{
    private const CHAVE_ENCERRADOS_RESTRITO = 'whatsapp_encerrados_restrito';
    private const CHAVE_NPS_RESTRITO = 'whatsapp_nps_restrito';

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function encerradosRestritoAtivo(): bool
    {
        return ConfigService::get(self::CHAVE_ENCERRADOS_RESTRITO, '') === '1';
    }

    public function npsRestritoAtivo(): bool
    {
        return ConfigService::get(self::CHAVE_NPS_RESTRITO, '') === '1';
    }

    /**
     * @return int[]
     */
    public function idsComAcessoEncerrados(): array
    {
        return array_map('intval', $this->pdo->query('SELECT usuario_id FROM whatsapp_permissao_encerrados')->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * @return int[]
     */
    public function idsComAcessoNps(): array
    {
        return array_map('intval', $this->pdo->query('SELECT usuario_id FROM whatsapp_permissao_nps')->fetchAll(PDO::FETCH_COLUMN));
    }

    public function usuarioPodeVerEncerrados(array $usuario): bool
    {
        if (($usuario['perfil'] ?? null) === 'admin') {
            return true;
        }

        if (!$this->encerradosRestritoAtivo()) {
            return true;
        }

        return in_array((int)($usuario['id'] ?? 0), $this->idsComAcessoEncerrados(), true);
    }

    public function usuarioPodeVerNps(array $usuario): bool
    {
        if (($usuario['perfil'] ?? null) === 'admin') {
            return true;
        }

        if (!$this->npsRestritoAtivo()) {
            return true;
        }

        return in_array((int)($usuario['id'] ?? 0), $this->idsComAcessoNps(), true);
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function salvarAcessoEncerrados(bool $ativo, array $usuarioIds): array
    {
        ConfigService::set(self::CHAVE_ENCERRADOS_RESTRITO, $ativo ? '1' : '0');

        if (!$this->substituirLista('whatsapp_permissao_encerrados', $usuarioIds)) {
            return ['success' => false, 'message' => 'Falha ao salvar os atendentes com acesso a Encerrados.'];
        }

        return ['success' => true, 'message' => 'Acesso a Encerrados salvo.'];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function salvarAcessoNps(bool $ativo, array $usuarioIds): array
    {
        ConfigService::set(self::CHAVE_NPS_RESTRITO, $ativo ? '1' : '0');

        if (!$this->substituirLista('whatsapp_permissao_nps', $usuarioIds)) {
            return ['success' => false, 'message' => 'Falha ao salvar os atendentes com acesso à pesquisa de satisfação.'];
        }

        return ['success' => true, 'message' => 'Acesso à pesquisa de satisfação salvo.'];
    }

    /**
     * $tabela nunca vem de fora -- só os dois valores fixos chamados
     * pelos métodos acima, então interpolar direto no SQL aqui é seguro.
     */
    private function substituirLista(string $tabela, array $usuarioIds): bool
    {
        $usuarioIds = array_values(array_unique(array_map('intval', $usuarioIds)));

        $this->pdo->beginTransaction();

        try {
            $this->pdo->exec("DELETE FROM {$tabela}");

            if ($usuarioIds) {
                $ins = $this->pdo->prepare("INSERT INTO {$tabela} (usuario_id) VALUES (?)");
                foreach ($usuarioIds as $usuarioId) {
                    $ins->execute([$usuarioId]);
                }
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return false;
        }

        return true;
    }
}
