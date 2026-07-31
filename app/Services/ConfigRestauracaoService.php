<?php

namespace App\Services;

/**
 * Lado da restauração do backup total de configuração (ver
 * ConfigBackupService pro lado da geração). Cenário esperado: servidor
 * recém-reinstalado (install.sh já rodou, app no ar com banco recém-
 * migrado) -- Sistema > Configurações > Restaurar.
 *
 * Dividido em duas partes: iniciar()/status() acompanham o script
 * privilegiado (chave, import do dump, arquivos do SO -- tudo que exige
 * root), rodando em segundo plano; finalizar() roda em PHP puro, DEPOIS
 * que o script já terminou com sucesso, reaproveitando os mesmos métodos
 * de "aplicar" que cada módulo já usa nos seus próprios botões (não
 * duplica lógica de regeneração de config em shell).
 */
class ConfigRestauracaoService
{
    private const STATUS_DIR = '/var/www/rd.intranet/storage/config_restore_status';

    private LinuxService $linux;

    public function __construct()
    {
        $this->linux = new LinuxService();
    }

    /**
     * @return array{success: bool, message: string, execucao_id?: string}
     */
    public function iniciar(string $arquivoTmpUpload, string $senha, ?int $usuarioId): array
    {
        if (trim($senha) === '') {
            return ['success' => false, 'message' => 'Informe a senha de criptografia do backup.'];
        }

        if (!is_uploaded_file($arquivoTmpUpload)) {
            return ['success' => false, 'message' => 'Upload inválido.'];
        }

        $execucaoId = bin2hex(random_bytes(8));
        $diretorio = self::STATUS_DIR . "/{$execucaoId}";

        if (!mkdir($diretorio, 0700, true) && !is_dir($diretorio)) {
            return ['success' => false, 'message' => 'Falha ao preparar o diretório de trabalho no servidor.'];
        }

        $destino = "{$diretorio}/pacote.tar.enc";
        if (!move_uploaded_file($arquivoTmpUpload, $destino)) {
            return ['success' => false, 'message' => 'Falha ao salvar o arquivo enviado no servidor.'];
        }
        chmod($destino, 0600);

        $this->linux->executarScriptEmSegundoPlanoComEntrada(
            '/opt/rdtecnologia/scripts/config_backup_restaurar_web.sh',
            [$execucaoId, $destino],
            $senha
        );

        AuditService::registrar('Sistema', 'Restaurar configuração', "Restauração iniciada (execução {$execucaoId}).");

        return [
            'success' => true,
            'execucao_id' => $execucaoId,
            'message' => 'Restauração iniciada em segundo plano...',
        ];
    }

    public function status(string $execucaoId): array
    {
        $arquivo = $this->arquivoStatus($execucaoId);
        if ($arquivo === null || !is_file($arquivo)) {
            return ['status' => 'desconhecido'];
        }

        $dados = json_decode((string)file_get_contents($arquivo), true);

        return is_array($dados) ? $dados : ['status' => 'desconhecido'];
    }

    /**
     * Chamado pelo front só depois que status() reportar "concluido" pro
     * script privilegiado. Regenera, a partir do banco recém-restaurado,
     * tudo que cada módulo já sabe regenerar sozinho -- melhor esforço:
     * uma falha num passo não impede os seguintes, o resultado agregado de
     * cada um fica disponível pro admin conferir manualmente o que precisa
     * de atenção.
     *
     * @return array{success: bool, passos: array<string, array{success: bool, message: string}>}
     */
    public function finalizar(string $execucaoId): array
    {
        $statusScript = $this->status($execucaoId);
        if (($statusScript['status'] ?? '') !== 'concluido') {
            return ['success' => false, 'passos' => [], 'message' => 'A restauração ainda não terminou a etapa de sistema -- aguarde.'];
        }

        $passos = [];

        $passos['migrations'] = $this->passo(fn() => (new MigrationService())->aplicar());
        $passos['samba'] = $this->passo(fn() => (new SambaConfigDeployService())->deploy());
        $passos['cron'] = $this->passo(fn() => (new CronService())->regenerarArquivo());
        $passos['firewall'] = $this->passo(function () {
            $aplicar = (new IptablesService())->aplicar();
            if ($aplicar['success']) {
                // confirma na hora -- sem isso o firewall reverteria
                // sozinho ~90s depois, sem ninguem pra clicar "confirmar"
                // durante uma restauracao
                return (new IptablesService())->confirmar();
            }
            return $aplicar;
        });
        $passos['rclone'] = $this->passo(fn() => (new BackupService())->aplicarConfig());

        $cloudflare = new CloudflareTunnelService();
        if ($cloudflare->configurado() && $cloudflare->tunelCriado()) {
            $passos['cloudflare_tunnel'] = $this->passo(fn() => $cloudflare->reconectarServicoLocal());
        }

        $sucessoGeral = !in_array(false, array_column($passos, 'success'), true);

        AuditService::registrar('Sistema', 'Restaurar configuração', "Restauração finalizada (execução {$execucaoId}), sucesso geral: " . ($sucessoGeral ? 'sim' : 'não') . '.');

        // a sessao atual pode nao corresponder a nenhum usuario da tabela
        // que acabou de ser restaurada -- forca novo login
        unset($_SESSION['usuario']);

        return ['success' => $sucessoGeral, 'passos' => $passos];
    }

    private function passo(callable $fn): array
    {
        try {
            $resultado = $fn();
            return is_array($resultado) && isset($resultado['success'])
                ? $resultado
                : ['success' => true, 'message' => 'OK'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function arquivoStatus(string $execucaoId): ?string
    {
        if (!preg_match('/^[0-9a-f]+$/', $execucaoId)) {
            return null;
        }

        return self::STATUS_DIR . "/{$execucaoId}.json";
    }
}
