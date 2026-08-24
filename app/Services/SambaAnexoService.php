<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Ponte entre "anexar um arquivo" (Contratos, e depois Documentos/
 * Chamados externos) e o módulo Samba > Arquivos que já existe --
 * deixa escolher um arquivo já existente em vez de fazer upload
 * duplicado. Reaproveita os MESMOS scripts (_web.sh) que
 * SambaArquivosController já usa pra ler/listar -- nunca acessa
 * /srv/samba direto, mesmo padrão de segurança (sudo + realpath
 * validado dentro do script).
 *
 * Visibilidade É EM DUAS CAMADAS, de propósito:
 *  - Escolhendo o arquivo: só os compartilhamentos que o usuário tem
 *    liberado NO PORTAL (samba_compartilhamento_portal_usuarios,
 *    gerenciado em /samba/compartilhamentos/usuarios?id= -- diferente
 *    da lista de contas Windows/rede que já mora ali). Admin sempre
 *    vê tudo.
 *  - Depois de anexado: a permissão do Samba deixa de valer -- quem
 *    pode ver o registro dono (Contrato etc.) vê o anexo, ponto. Por
 *    isso este serviço NUNCA é chamado de novo pra revalidar acesso
 *    depois que o caminho já foi salvo num anexo -- é troca de "dono"
 *    da permissão, de propósito.
 */
class SambaAnexoService
{
    private const BASE_SCRIPT_LISTAR = '/opt/rdtecnologia/scripts/lista_arquivos_samba_web.sh';

    private PDO $pdo;
    private LinuxService $linux;

    public function __construct()
    {
        $this->pdo = Database::connection();
        $this->linux = new LinuxService();
    }

    /** @return array<int, array{id:int, nome:string, caminho:string}> */
    public function compartilhamentosVisiveis(int $usuarioId, bool $ehAdmin): array
    {
        if ($ehAdmin) {
            return $this->pdo->query(
                "SELECT id, nome, caminho FROM samba_compartilhamentos WHERE status = 'ativo' ORDER BY nome"
            )->fetchAll(PDO::FETCH_ASSOC);
        }

        $stmt = $this->pdo->prepare(
            "SELECT sc.id, sc.nome, sc.caminho
             FROM samba_compartilhamentos sc
             JOIN samba_compartilhamento_portal_usuarios spu ON spu.compartilhamento_id = sc.id
             WHERE spu.usuario_id = ? AND sc.status = 'ativo'
             ORDER BY sc.nome"
        );
        $stmt->execute([$usuarioId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function podeAcessarCompartilhamento(int $compartilhamentoId, int $usuarioId, bool $ehAdmin): bool
    {
        if ($ehAdmin) {
            return true;
        }

        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM samba_compartilhamento_portal_usuarios WHERE compartilhamento_id = ? AND usuario_id = ?'
        );
        $stmt->execute([$compartilhamentoId, $usuarioId]);

        return (bool)$stmt->fetchColumn();
    }

    public function buscarCompartilhamento(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id, nome, caminho FROM samba_compartilhamentos WHERE id = ? AND status = 'ativo'");
        $stmt->execute([$id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    /** Mesma validação de SambaArquivosController::validarRel() -- rejeita ".." como segmento e byte nulo. */
    public function validarSubcaminho(string $sub): ?string
    {
        $sub = trim($sub, '/');

        if (str_contains($sub, "\0")) {
            return null;
        }

        if ($sub !== '' && in_array('..', explode('/', $sub), true)) {
            return null;
        }

        return $sub;
    }

    /** Lista pastas e arquivos dentro do compartilhamento (+ subcaminho opcional) -- reaproveita lista_arquivos_samba_web.sh. */
    public function listarItens(array $compartilhamento, string $subcaminho): array
    {
        $rel = $compartilhamento['caminho'] . ($subcaminho !== '' ? '/' . $subcaminho : '');
        $resultado = $this->linux->executarScript(self::BASE_SCRIPT_LISTAR, [$rel]);
        $lista = json_decode($resultado['output'], true);

        return is_array($lista) && !isset($lista['error']) ? $lista : [];
    }

    /** Caminho completo (a partir da raiz dos compartilhamentos) pra guardar no anexo -- é isso que vai em anexo_caminho. */
    public function caminhoParaAnexo(array $compartilhamento, string $subcaminho, string $nomeArquivo): string
    {
        $partes = array_filter([$compartilhamento['caminho'], $subcaminho, $nomeArquivo]);

        return implode('/', $partes);
    }

    /** Serve os bytes de um anexo de origem Samba -- streaming direto, mesmo padrão de SambaArquivosController::download(). */
    public function servirArquivo(string $caminhoCompleto): void
    {
        passthru('sudo /opt/rdtecnologia/scripts/ler_arquivo_samba_web.sh ' . escapeshellarg($caminhoCompleto) . ' 2>/dev/null');
    }

    /** @return int[] ids de usuarios (portal) com acesso liberado a este compartilhamento no seletor de anexo. */
    public function usuariosPortalAutorizados(int $compartilhamentoId): array
    {
        $stmt = $this->pdo->prepare('SELECT usuario_id FROM samba_compartilhamento_portal_usuarios WHERE compartilhamento_id = ?');
        $stmt->execute([$compartilhamentoId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Substitui a lista de usuarios (portal) autorizados a ver este compartilhamento no seletor de anexo. */
    public function salvarUsuariosPortal(int $compartilhamentoId, array $usuarioIds): void
    {
        $this->pdo->beginTransaction();

        $this->pdo->prepare('DELETE FROM samba_compartilhamento_portal_usuarios WHERE compartilhamento_id = ?')
            ->execute([$compartilhamentoId]);

        $stmt = $this->pdo->prepare(
            'INSERT INTO samba_compartilhamento_portal_usuarios (compartilhamento_id, usuario_id) VALUES (?, ?)'
        );
        foreach ($usuarioIds as $usuarioId) {
            $stmt->execute([$compartilhamentoId, (int)$usuarioId]);
        }

        $this->pdo->commit();
    }
}
