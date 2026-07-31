<?php

namespace App\Services;

use App\Repositories\BackupDestinoRepository;
use App\Repositories\ConfigBackupRepository;

/**
 * Backup TOTAL de configuração do RD Intranet (Sistema > Configurações):
 * dump completo do banco + arquivos do sistema operacional sem fonte no
 * banco (chave de criptografia, senha dos usuários Samba, PKI de VPN
 * etc). Existe pra cobrir o cenário de desastre (servidor reinstalado do
 * zero) -- ver ConfigRestauracaoService pro lado da restauração.
 *
 * Reaproveita a MESMA infraestrutura de credenciais de nuvem do módulo
 * Backup em Nuvem (rclone.conf já configurado) pra também enviar uma
 * cópia do pacote pra fora do servidor, sem pedir credencial de novo.
 */
class ConfigBackupService
{
    private const STATUS_DIR = '/var/www/rd.intranet/storage/config_backup_status';
    private const DESTINO_DIR = '/var/www/rd.intranet/storage/config_backups';
    private const CHAVE_SENHA_AGENDADA = 'config_backup_agendado_senha_cifrada';
    private const NOME_JOB_CRON = 'Backup de configuração (Sistema > Configurações)';

    private LinuxService $linux;
    private ConfigBackupRepository $repo;
    private BackupDestinoRepository $destinoRepo;

    public function __construct()
    {
        $this->linux = new LinuxService();
        $this->repo = new ConfigBackupRepository();
        $this->destinoRepo = new BackupDestinoRepository();
    }

    public function historico(int $limite = 30): array
    {
        return $this->repo->listar($limite);
    }

    /**
     * @return array{success: bool, message: string, execucao_id?: int}
     */
    public function gerar(string $senha, string $tipo = 'manual', ?int $usuarioId = null): array
    {
        if (trim($senha) === '') {
            return ['success' => false, 'message' => 'Informe a senha de criptografia do backup.'];
        }

        $execucaoId = $this->repo->criar($tipo, $usuarioId);

        $argumentos = [(string)$execucaoId];

        $destinoAtivo = $this->destinoAtivoParaEnvio();
        if ($destinoAtivo) {
            $argumentos[] = BackupService::nomeRemote((int)$destinoAtivo['id']);
            $argumentos[] = (new BackupService())->destinoRemoto($destinoAtivo);
        }

        $this->linux->executarScriptEmSegundoPlanoComEntrada(
            '/opt/rdtecnologia/scripts/config_backup_gerar_web.sh',
            $argumentos,
            $senha
        );

        return [
            'success' => true,
            'execucao_id' => $execucaoId,
            'message' => 'Backup de configuração iniciado em segundo plano...',
        ];
    }

    /**
     * Lê o status (escrito pelo próprio script em segundo plano) e finaliza
     * a linha em config_backups na primeira vez em que o polling detecta um
     * estado terminal -- mesmo idioma de BackupService::statusExecucao().
     */
    public function status(int $execucaoId): array
    {
        $arquivo = self::STATUS_DIR . "/{$execucaoId}.json";

        if (!is_file($arquivo)) {
            return ['status' => 'desconhecido'];
        }

        $dados = json_decode((string)file_get_contents($arquivo), true);
        if (!is_array($dados)) {
            return ['status' => 'desconhecido'];
        }

        $status = $dados['status'] ?? '';

        if (in_array($status, ['concluido', 'erro'], true)) {
            $execucao = $this->repo->buscar($execucaoId);

            if ($execucao && $execucao['status'] === 'executando') {
                $this->repo->finalizar(
                    $execucaoId,
                    $status,
                    $dados['arquivo'] ?? null,
                    (int)($dados['tamanho_bytes'] ?? 0),
                    (bool)($dados['enviado_nuvem'] ?? false),
                    $status === 'erro' ? ($dados['mensagem'] ?: 'Erro desconhecido ao gerar o backup.') : null
                );
            }
        }

        return $dados;
    }

    /** Caminho do arquivo local pra download, validado contra o registro em config_backups (evita path traversal via id arbitrário). */
    public function caminhoArquivo(int $id): ?string
    {
        $registro = $this->repo->buscar($id);
        if (!$registro || $registro['status'] !== 'concluido' || empty($registro['arquivo'])) {
            return null;
        }

        // basename() descarta qualquer diretorio embutido no nome -- o
        // proprio script so grava nomes no formato config-backup-*.tar.enc,
        // isto e so uma segunda camada, mesmo raciocinio de outros pontos
        // do projeto que nunca confiam soo na origem do dado.
        $caminho = self::DESTINO_DIR . '/' . basename($registro['arquivo']);

        return is_file($caminho) ? $caminho : null;
    }

    public function agendamentoAtual(): ?array
    {
        return $this->buscarJobAgendado();
    }

    public function senhaAgendadaConfigurada(): bool
    {
        return (ConfigService::get(self::CHAVE_SENHA_AGENDADA, '') ?: '') !== '';
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function agendar(string $expressao, string $senhaAgendada): array
    {
        $expressao = trim($expressao) ?: '0 4 * * *';

        if (trim($senhaAgendada) === '' && !$this->senhaAgendadaConfigurada()) {
            return ['success' => false, 'message' => 'Informe a senha que os backups agendados vão usar pra cifrar o pacote (fica guardada cifrada no servidor, mesmo cofre que já protege a senha SMTP e as credenciais de nuvem).'];
        }

        if (trim($senhaAgendada) !== '') {
            ConfigService::set(self::CHAVE_SENHA_AGENDADA, CryptoService::encriptar($senhaAgendada));
        }

        $cron = new CronService();
        $jobExistente = $this->buscarJobAgendado();

        $dadosJob = [
            'nome' => self::NOME_JOB_CRON,
            'descricao' => 'Gera o backup total de configuração (banco + arquivos críticos do SO) -- Sistema > Configurações.',
            'expressao' => $expressao,
            'usuario_execucao' => 'www-data',
            'comando' => 'php /var/www/rd.intranet/rd config-backup:gerar',
            'ativo' => true,
        ];

        $resultado = $jobExistente
            ? $cron->atualizar((int)$jobExistente['id'], $dadosJob)
            : $cron->criar($dadosJob);

        return $resultado;
    }

    public function desagendar(): array
    {
        $job = $this->buscarJobAgendado();
        if ($job) {
            (new CronService())->excluir((int)$job['id']);
        }

        ConfigService::set(self::CHAVE_SENHA_AGENDADA, '');

        return ['success' => true, 'message' => 'Agendamento removido.'];
    }

    /** Usado pelo comando de CLI (cron) -- lê e decifra a senha dedicada de backups agendados. */
    public function senhaAgendada(): ?string
    {
        $cifrada = ConfigService::get(self::CHAVE_SENHA_AGENDADA, '');
        if (!$cifrada) {
            return null;
        }

        try {
            return CryptoService::decriptar($cifrada);
        } catch (\RuntimeException $e) {
            return null;
        }
    }

    private function buscarJobAgendado(): ?array
    {
        foreach ((new CronService())->listar() as $job) {
            if ($job['nome'] === self::NOME_JOB_CRON) {
                return $job;
            }
        }

        return null;
    }

    /**
     * Só B2/S3 por enquanto -- destinoRemoto() do Google Drive retorna
     * string vazia (ele é escopado por root_folder_id, não por bucket/
     * prefixo de caminho), então o "<remote>:<destino>/_config_backup/..."
     * que o script monta não é válido pra esse provider sem um tratamento
     * dedicado. Sem cliente usando Drive ainda pra justificar o teste.
     */
    private function destinoAtivoParaEnvio(): ?array
    {
        foreach ($this->destinoRepo->listar() as $destino) {
            if (!empty($destino['ativo']) && in_array($destino['provider'], ['b2', 's3'], true)) {
                return $destino;
            }
        }

        return null;
    }
}
