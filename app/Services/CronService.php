<?php

namespace App\Services;

use App\Repositories\CronJobRepository;

class CronService
{
    private const ARQUIVO_DESTINO = '/etc/cron.d/rd-intranet';

    private const ATALHOS = [
        '@reboot', '@yearly', '@annually', '@monthly', '@weekly', '@daily', '@midnight', '@hourly',
    ];

    private const CAMPO_CRON = '/^(\*|[0-9]+(-[0-9]+)?)(\/[0-9]+)?(,(\*|[0-9]+(-[0-9]+)?)(\/[0-9]+)?)*$/';

    private CronJobRepository $repo;
    private LinuxService $linux;

    public function __construct()
    {
        $this->repo = new CronJobRepository();
        $this->linux = new LinuxService();
    }

    public function listar(): array
    {
        return $this->repo->listar();
    }

    public function buscar(int $id): ?array
    {
        return $this->repo->buscar($id);
    }

    public function validarExpressao(string $expressao): bool
    {
        $expressao = trim($expressao);

        if (in_array($expressao, self::ATALHOS, true)) {
            return true;
        }

        $campos = preg_split('/\s+/', $expressao);

        if (count($campos) !== 5) {
            return false;
        }

        foreach ($campos as $campo) {
            if (!preg_match(self::CAMPO_CRON, $campo)) {
                return false;
            }
        }

        return true;
    }

    public function validarUsuario(string $usuario): bool
    {
        return (bool)preg_match('/^[a-zA-Z_][a-zA-Z0-9_-]{0,31}$/', $usuario)
            && $this->linux->usuarioExiste($usuario);
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function criar(array $dados): array
    {
        $erro = $this->validar($dados);
        if ($erro !== null) {
            return ['success' => false, 'message' => $erro];
        }

        $this->repo->criar($dados);

        $sync = $this->regenerarArquivo();

        return [
            'success' => true,
            'message' => $sync['success']
                ? 'Job de cron criado e aplicado com sucesso.'
                : 'Job criado, mas houve falha ao aplicar no cron do sistema: ' . $sync['message'],
        ];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function atualizar(int $id, array $dados): array
    {
        $erro = $this->validar($dados);
        if ($erro !== null) {
            return ['success' => false, 'message' => $erro];
        }

        $this->repo->atualizar($id, $dados);

        $sync = $this->regenerarArquivo();

        return [
            'success' => true,
            'message' => $sync['success']
                ? 'Job de cron atualizado e aplicado com sucesso.'
                : 'Job atualizado, mas houve falha ao aplicar no cron do sistema: ' . $sync['message'],
        ];
    }

    public function excluir(int $id): array
    {
        $this->repo->excluir($id);

        $sync = $this->regenerarArquivo();

        return [
            'success' => true,
            'message' => $sync['success']
                ? 'Job excluído e cron do sistema atualizado.'
                : 'Job excluído, mas houve falha ao aplicar no cron do sistema: ' . $sync['message'],
        ];
    }

    public function alternarAtivo(int $id, bool $ativo): array
    {
        $this->repo->definirAtivo($id, $ativo);

        $sync = $this->regenerarArquivo();

        return [
            'success' => true,
            'message' => $sync['success']
                ? ($ativo ? 'Job ativado.' : 'Job desativado.')
                : 'Status alterado, mas houve falha ao aplicar no cron do sistema: ' . $sync['message'],
        ];
    }

    /**
     * Roda o comando do job imediatamente (fora do agendamento), pra o
     * admin testar sem esperar o próximo disparo do cron.
     */
    public function executarAgora(int $id): array
    {
        $job = $this->repo->buscar($id);

        if (!$job) {
            return ['success' => false, 'output' => 'Job não encontrado.'];
        }

        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/cron_executar_web.sh',
            [$job['usuario_execucao'], base64_encode($job['comando'])]
        );

        // cron_executar_web.sh imprime JSON no stdout; se o sudo falhar antes
        // do script rodar (ex: pede senha), o stdout nao vai ser JSON valido
        // -- nesse caso cai no fallback com a saida crua do sudo/exec.
        $dados = json_decode(trim($resultado['output']), true);
        $final = is_array($dados) && isset($dados['success'])
            ? $dados
            : ['success' => false, 'output' => $resultado['output']];

        $this->repo->registrarExecucao($id, (bool)$final['success'], (string)$final['output']);

        return $final;
    }

    public function statusSincronizacao(): array
    {
        return [
            'em' => ConfigService::get('cron_sync_em'),
            'erro' => ConfigService::get('cron_sync_erro'),
        ];
    }

    /**
     * Ultimas linhas do log das execucoes agendadas do job (nao inclui as
     * execucoes manuais via "executar agora", essas ficam em
     * ultima_execucao_saida no banco). O diretorio e 1777 (como /tmp) pra
     * qualquer usuario configurado num job conseguir gravar seu proprio
     * arquivo -- so leitura aqui, sem sudo necessario.
     */
    public function logAgendado(int $id, int $linhas = 200): string
    {
        $arquivo = self::diretorioLogs() . "/{$id}.log";

        if (!is_file($arquivo) || !is_readable($arquivo)) {
            return '';
        }

        $conteudo = @file($arquivo, FILE_IGNORE_NEW_LINES);
        if ($conteudo === false) {
            return '';
        }

        return implode("\n", array_slice($conteudo, -$linhas));
    }

    public static function diretorioLogs(): string
    {
        return '/var/log/rd-intranet-cron';
    }

    private function validar(array $dados): ?string
    {
        if (trim($dados['nome'] ?? '') === '') {
            return 'Informe um nome para o job.';
        }

        if (!$this->validarExpressao($dados['expressao'] ?? '')) {
            return 'Expressão de cron inválida. Use 5 campos (min hora dia mês dia-semana) ou um atalho como @daily.';
        }

        if (!$this->validarUsuario($dados['usuario_execucao'] ?? '')) {
            return 'Usuário de execução inválido ou inexistente no sistema.';
        }

        if (trim($dados['comando'] ?? '') === '') {
            return 'Informe o comando a ser executado.';
        }

        if (str_contains($dados['comando'], "\n") || str_contains($dados['comando'], "\r")) {
            return 'O comando não pode conter quebras de linha.';
        }

        return null;
    }

    /**
     * Reconstroi o /etc/cron.d/rd-intranet inteiro a partir dos jobs ativos
     * no banco (fonte da verdade) e instala via script root. Falha na
     * aplicacao nao desfaz a alteracao no banco -- fica registrada em
     * `configuracoes` (cron_sync_em/cron_sync_erro) pra a tela avisar que
     * o cron do sistema pode estar fora de sincronia.
     */
    private function regenerarArquivo(): array
    {
        $linhas = [
            '# Arquivo gerado automaticamente pela RD Intranet (Infraestrutura > Cron).',
            '# NAO EDITE MANUALMENTE -- qualquer alteracao aqui sera sobrescrita na',
            '# proxima vez que um job for criado/editado/excluido/ativado pela tela web.',
            'SHELL=/bin/bash',
            'PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
            '',
        ];

        $logDir = self::diretorioLogs();

        foreach ($this->repo->listarAtivos() as $job) {
            $linhas[] = "# job #{$job['id']}: {$job['nome']}";
            // comando do admin isolado num subshell: se ele ja tiver seu
            // proprio redirecionamento (padrao comum), esse redirect
            // continua valendo por estar "dentro" do (); so o que sobrar
            // (comandos sem redirect proprio) cai no log por job.
            $linhas[] = "{$job['expressao']} {$job['usuario_execucao']} ( {$job['comando']} ) >> {$logDir}/{$job['id']}.log 2>&1";
        }

        $conteudo = implode("\n", $linhas) . "\n";

        $tmp = tempnam(sys_get_temp_dir(), 'rd_cron_');
        file_put_contents($tmp, $conteudo);

        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/cron_aplicar_web.sh', [$tmp]);

        @unlink($tmp);

        $dados = json_decode(trim($resultado['output']), true);
        $sucesso = is_array($dados) ? (bool)($dados['success'] ?? false) : false;
        $mensagem = is_array($dados) ? ($dados['message'] ?? '') : $resultado['output'];

        if ($sucesso) {
            ConfigService::set('cron_sync_em', date('Y-m-d H:i:s'));
            ConfigService::set('cron_sync_erro', '');
        } else {
            ConfigService::set('cron_sync_erro', $mensagem ?: 'Erro desconhecido ao aplicar o cron.');
        }

        return ['success' => $sucesso, 'message' => $mensagem];
    }
}
