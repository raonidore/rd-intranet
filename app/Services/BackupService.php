<?php

namespace App\Services;

use App\Repositories\BackupDestinoRepository;
use App\Repositories\BackupExecucaoRepository;
use App\Repositories\SambaCompartilhamentoRepository;

class BackupService
{
    private const STATUS_DIR = '/var/www/rd.intranet/storage/backup_status';
    private const PROVIDERS = ['b2', 's3', 'drive'];

    private LinuxService $linux;
    private BackupDestinoRepository $repo;
    private BackupExecucaoRepository $execucaoRepo;
    private SambaCompartilhamentoRepository $compartilhamentoRepo;

    public function __construct()
    {
        $this->linux = new LinuxService();
        $this->repo = new BackupDestinoRepository();
        $this->execucaoRepo = new BackupExecucaoRepository();
        $this->compartilhamentoRepo = new SambaCompartilhamentoRepository();
    }

    /** Compartilhamentos do Samba marcados para backup em nuvem (ver Samba > Compartilhamentos). */
    public function compartilhamentosAtivos(): array
    {
        return $this->compartilhamentoRepo->listarAtivosParaBackup();
    }

    public static function nomeRemote(int $id): string
    {
        return "destino-{$id}";
    }

    public function listarDestinos(): array
    {
        return $this->repo->listar();
    }

    public function buscarDestino(int $id): ?array
    {
        return $this->repo->buscar($id);
    }

    public function execucaoEmAndamento(): ?array
    {
        return $this->execucaoRepo->buscarEmAndamento();
    }

    public function historico(int $limite = 30): array
    {
        return $this->execucaoRepo->listar($limite);
    }

    /**
     * @return array{success: bool, message: string, id?: int}
     */
    public function salvarDestino(array $post, ?int $id): array
    {
        $provider = $post['provider'] ?? '';
        if (!in_array($provider, self::PROVIDERS, true)) {
            return ['success' => false, 'message' => 'Provedor inválido.'];
        }

        $nome = trim($post['nome'] ?? '');
        if ($nome === '') {
            return ['success' => false, 'message' => 'Informe um nome para o destino.'];
        }

        $existente = $id ? $this->repo->buscar($id) : null;

        $emailsValidos = EmailService::normalizarLista($post['email_notificacao'] ?? []);

        $dados = [
            'provider' => $provider,
            'nome' => $nome,
            'ativo' => !empty($post['ativo']),
            'retencao_dias' => max(1, (int)($post['retencao_dias'] ?? 30)),
            'relatorio_diario_ativo' => !empty($post['relatorio_diario_ativo']),
            'alerta_falha_ativo' => !empty($post['alerta_falha_ativo']),
            'email_notificacao' => !empty($emailsValidos) ? implode(', ', $emailsValidos) : null,
        ];

        if (($dados['relatorio_diario_ativo'] || $dados['alerta_falha_ativo']) && empty($emailsValidos)) {
            return ['success' => false, 'message' => 'Informe pelo menos um e-mail válido de notificação para usar o relatório diário ou o alerta de falha.'];
        }

        $erro = $this->preencherCredenciais($dados, $post, $existente);
        if ($erro !== null) {
            return ['success' => false, 'message' => $erro];
        }

        if ($id) {
            $this->repo->atualizar($id, $dados);
        } else {
            $id = $this->repo->criar($dados);
        }

        if ($dados['ativo']) {
            $this->repo->desativarTodos();
            $this->repo->definirAtivo($id, true);
        }

        $aplicar = $this->aplicarConfig();

        return [
            'success' => true,
            'id' => $id,
            'message' => $aplicar['success']
                ? 'Destino de backup salvo com sucesso.'
                : 'Destino salvo, mas houve falha ao aplicar a configuração do rclone: ' . $aplicar['message'],
        ];
    }

    public function excluirDestino(int $id): array
    {
        $this->repo->excluir($id);
        $aplicar = $this->aplicarConfig();

        return [
            'success' => true,
            'message' => $aplicar['success']
                ? 'Destino excluído.'
                : 'Destino excluído, mas houve falha ao atualizar a configuração do rclone: ' . $aplicar['message'],
        ];
    }

    public function ativar(int $id): array
    {
        $this->repo->desativarTodos();
        $this->repo->definirAtivo($id, true);

        return ['success' => true, 'message' => 'Destino marcado como ativo.'];
    }

    /**
     * Regenera /etc/rd-intranet/rclone/rclone.conf inteiro a partir dos
     * destinos cadastrados no banco (fonte da verdade) -- mesmo idioma de
     * CronService::regenerarArquivo() (shares.conf, cron.d).
     *
     * @return array{success: bool, message: string}
     */
    public function aplicarConfig(): array
    {
        $linhas = [
            '# Arquivo gerado automaticamente pela RD Intranet (Backup > Configuração).',
            '# NAO EDITE MANUALMENTE -- sobrescrito a cada criacao/edicao/exclusao de destino.',
            '',
        ];

        foreach ($this->repo->listar() as $destino) {
            $linhas[] = '[' . self::nomeRemote((int)$destino['id']) . ']';
            $linhas = array_merge($linhas, $this->linhasSecao($destino));
            $linhas[] = '';
        }

        $conteudo = implode("\n", $linhas) . "\n";

        $tmp = tempnam(sys_get_temp_dir(), 'rd_backup_');
        file_put_contents($tmp, $conteudo);

        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/backup_aplicar_config_web.sh', [$tmp]);
        @unlink($tmp);

        $dados = json_decode(trim($resultado['output']), true);

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }

    /**
     * Testa uma credencial ANTES de salvar (botão "Testar conexão"). Monta
     * um rclone.conf temporário em memória, nunca persistido.
     */
    public function testarConexao(array $post): array
    {
        $provider = $post['provider'] ?? '';
        if (!in_array($provider, self::PROVIDERS, true)) {
            return ['success' => false, 'message' => 'Provedor inválido.'];
        }

        $destinoTemp = ['provider' => $provider];
        $erro = $this->preencherCredenciais($destinoTemp, $post, null, true);
        if ($erro !== null) {
            return ['success' => false, 'message' => $erro];
        }

        $linhas = array_merge(['[teste]'], $this->linhasSecao($destinoTemp), ['']);

        $tmp = tempnam(sys_get_temp_dir(), 'rd_backup_teste_');
        file_put_contents($tmp, implode("\n", $linhas) . "\n");

        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/backup_testar_conexao_web.sh', [
            $tmp,
            'teste',
            $this->destinoRemoto($destinoTemp),
        ]);
        @unlink($tmp);

        $dados = json_decode(trim($resultado['output']), true);

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }

    /**
     * @return array{success: bool, message: string, execucao_id?: int}
     */
    public function executarAgora(int $destinoId, string $tipo = 'manual'): array
    {
        $destino = $this->repo->buscar($destinoId);
        if (!$destino) {
            return ['success' => false, 'message' => 'Destino não encontrado.'];
        }

        $compartilhamentos = $this->compartilhamentosAtivos();
        if (empty($compartilhamentos)) {
            return [
                'success' => false,
                'message' => 'Nenhum compartilhamento está marcado para backup em nuvem. '
                    . 'Ative pelo menos um em Samba > Compartilhamentos (botão "Backup nuvem").',
            ];
        }

        $execucaoId = $this->execucaoRepo->criar($destinoId, $tipo);

        $argumentos = [
            (string)$execucaoId,
            self::nomeRemote($destinoId),
            $this->destinoRemoto($destino),
            (string)$destino['retencao_dias'],
        ];
        foreach ($compartilhamentos as $c) {
            $argumentos[] = $c['nome'];
            $argumentos[] = $c['caminho'];
        }

        $this->linux->executarScriptEmSegundoPlano('/opt/rdtecnologia/scripts/backup_executar_web.sh', $argumentos);

        return [
            'success' => true,
            'execucao_id' => $execucaoId,
            'message' => 'Backup iniciado em segundo plano...',
        ];
    }

    /**
     * Lê o status (escrito pelo próprio script em segundo plano) de uma
     * execução, e finaliza a linha em backup_execucoes na primeira vez em
     * que o polling detecta um estado terminal (idempotente).
     */
    public function statusExecucao(int $execucaoId): array
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

        if ($status === 'erro' && !empty($dados['mensagem'])) {
            $dados['mensagem_amigavel'] = self::mensagemAmigavel($dados['mensagem']);
        }

        if (in_array($status, ['concluida', 'erro'], true)) {
            $execucao = $this->execucaoRepo->buscar($execucaoId);

            if ($execucao && $execucao['status'] === 'executando') {
                $mensagemErro = $status === 'erro' ? ($dados['mensagem'] ?: 'Erro desconhecido ao executar o backup.') : null;

                $this->execucaoRepo->finalizar(
                    $execucaoId,
                    $status,
                    (int)($dados['arquivos_enviados'] ?? 0),
                    (int)($dados['bytes_enviados'] ?? 0),
                    (int)($dados['versoes_criadas'] ?? 0),
                    $mensagemErro
                );

                $destino = $this->repo->buscar((int)$execucao['destino_id']);

                if ($destino && $status === 'concluida' && $destino['provider'] === 'drive') {
                    $this->atualizarTokenDriveAposExecucao((int)$destino['id']);
                }

                if ($destino) {
                    $this->enviarNotificacoes($destino, $status, $dados, $mensagemErro);
                }
            }
        }

        return $dados;
    }

    /**
     * Dispara o relatório diário e/ou o alerta de falha do destino, se as
     * flags estiverem ativas (Backup > Configuração, editar destino).
     * Um SMTP fora do ar ou mal configurado nunca deve derrubar o backup
     * em si -- por isso engole qualquer falha de envio, só registra no
     * log de erros do PHP.
     */
    private function enviarNotificacoes(array $destino, string $status, array $dados, ?string $mensagemErro): void
    {
        $deveEnviar = ($status === 'concluida' && !empty($destino['relatorio_diario_ativo']))
            || ($status === 'erro' && !empty($destino['alerta_falha_ativo']));

        if (!$deveEnviar || empty($destino['email_notificacao'])) {
            return;
        }

        try {
            $email = new EmailService();
            $nome = htmlspecialchars($destino['nome']);
            $quando = date('d/m/Y \à\s H:i');

            if ($status === 'concluida') {
                $assunto = '✓ Backup concluído -- ' . $destino['nome'];
                $miolo = '<p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#3c4043;">'
                        . 'O backup do destino <strong>' . $nome . '</strong> foi concluído com sucesso.</p>'
                    . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>'
                    . '<td width="33.3%" style="padding:0 6px 0 0;">' . $this->statTileHtml((string)(int)($dados['arquivos_enviados'] ?? 0), 'arquivo(s) enviado(s)') . '</td>'
                    . '<td width="33.3%" style="padding:0 6px;">' . $this->statTileHtml($this->formatarBytesEmail((int)($dados['bytes_enviados'] ?? 0)), 'total enviado') . '</td>'
                    . '<td width="33.3%" style="padding:0 0 0 6px;">' . $this->statTileHtml((string)(int)($dados['versoes_criadas'] ?? 0), 'versão(ões) preservada(s)') . '</td>'
                    . '</tr></table>'
                    . '<p style="margin:20px 0 0;font-size:13px;color:#80868b;">Concluído em ' . $quando . '.</p>';

                $corpo = $this->montarEmailHtml('BACKUP EM NUVEM · RELATÓRIO', 'Backup concluído', '#0d6efd', $miolo);
            } else {
                $assunto = '⚠ Falha no backup em nuvem -- ' . $destino['nome'];
                $diagnostico = htmlspecialchars(self::mensagemAmigavel((string)$mensagemErro));
                $tecnico = htmlspecialchars((string)$mensagemErro);

                $miolo = '<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#3c4043;">'
                        . 'O backup do destino <strong>' . $nome . '</strong> falhou.</p>'
                    . '<div style="background:#fdecea;border:1px solid #f5c2c7;border-radius:8px;padding:16px 18px;margin-bottom:16px;">'
                    . '<div style="font-size:11px;font-weight:700;letter-spacing:.5px;color:#b02a37;margin-bottom:4px;">DIAGNÓSTICO</div>'
                    . '<div style="font-size:14px;color:#58151c;line-height:1.5;">' . $diagnostico . '</div>'
                    . '</div>'
                    . '<div style="background:#f8f9fa;border:1px solid #e9ecef;border-radius:8px;padding:14px 18px;margin-bottom:16px;">'
                    . '<div style="font-size:11px;font-weight:700;letter-spacing:.5px;color:#80868b;margin-bottom:4px;">DETALHE TÉCNICO</div>'
                    . '<div style="font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:12.5px;color:#3c4043;line-height:1.5;word-break:break-word;">' . $tecnico . '</div>'
                    . '</div>'
                    . '<p style="margin:0;font-size:13px;color:#80868b;">Ocorrido em ' . $quando . '. Corrija a causa e rode o backup novamente -- ele retoma de onde parou.</p>';

                $corpo = $this->montarEmailHtml('BACKUP EM NUVEM · ALERTA', 'Falha no backup', '#dc3545', $miolo);
            }

            $email->enviar($destino['email_notificacao'], $assunto, $corpo);
        } catch (\Throwable $e) {
            error_log('Backup: falha ao enviar notificação por e-mail do destino ' . $destino['id'] . ': ' . $e->getMessage());
        }
    }

    /** Moldura padrão dos e-mails do módulo (cabeçalho colorido + rodapé) -- CSS inline, exigência de cliente de e-mail. */
    private function montarEmailHtml(string $rotulo, string $titulo, string $cor, string $conteudoHtml): string
    {
        return '<div style="background:#f1f3f5;padding:32px 16px;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">'
            . '<div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.08);">'
            . '<div style="background:' . $cor . ';padding:28px 32px;">'
            . '<div style="color:rgba(255,255,255,.85);font-size:12px;font-weight:600;letter-spacing:.8px;">' . htmlspecialchars($rotulo) . '</div>'
            . '<div style="color:#ffffff;font-size:22px;font-weight:700;margin-top:6px;">' . htmlspecialchars($titulo) . '</div>'
            . '</div>'
            . '<div style="padding:32px;">' . $conteudoHtml . '</div>'
            . '<div style="padding:16px 32px;background:#f8f9fa;border-top:1px solid #eee;font-size:11.5px;color:#adb5bd;">'
            . 'Enviado automaticamente pelo módulo Backup em Nuvem da RD Intranet. Não responda este e-mail.'
            . '</div>'
            . '</div>'
            . '</div>';
    }

    /** Um "tile" de estatística (número grande + rótulo pequeno) pro corpo do e-mail de relatório. */
    private function statTileHtml(string $valor, string $label): string
    {
        return '<div style="background:#f8f9fa;border-radius:8px;padding:16px 8px;text-align:center;">'
            . '<div style="font-size:19px;font-weight:700;color:#212529;">' . htmlspecialchars($valor) . '</div>'
            . '<div style="font-size:11px;color:#80868b;margin-top:2px;">' . htmlspecialchars($label) . '</div>'
            . '</div>';
    }

    private function formatarBytesEmail(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $unidades = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = min((int)floor(log($bytes, 1024)), count($unidades) - 1);

        return round($bytes / (1024 ** $i), 1) . ' ' . $unidades[$i];
    }

    /**
     * Traduz mensagens técnicas comuns do rclone/provedores pra um
     * diagnóstico direto em português, com o que fazer -- a mensagem
     * técnica original continua disponível ao lado (histórico e e-mail),
     * isto é só um resumo pra quem não conhece a API do provedor.
     */
    public static function mensagemAmigavel(string $mensagemTecnica): string
    {
        $msg = strtolower($mensagemTecnica);

        if (str_contains($msg, 'storage_cap_exceeded') || str_contains($msg, 'storage cap exceeded')) {
            return 'Limite de armazenamento atingido no provedor de nuvem. Aumente a cota (ou adicione crédito) '
                . 'no painel do provedor e rode o backup de novo -- ele retoma de onde parou, sem reenviar o que já foi enviado.';
        }

        if (str_contains($msg, 'unauthorized') || str_contains($msg, 'invalid_authorization') || str_contains($msg, 'bad auth') || str_contains($msg, 'invalidaccesskeyid') || str_contains($msg, 'signaturedoesnotmatch')) {
            return 'Credenciais inválidas ou sem permissão. Confira Key ID/Application Key (B2) ou Access Key/Secret Key (S3) em Backup > Configuração.';
        }

        if (str_contains($msg, 'no such bucket') || (str_contains($msg, 'bucket') && (str_contains($msg, 'not found') || str_contains($msg, '404')))) {
            return 'Bucket não encontrado. Confira o nome do bucket em Backup > Configuração.';
        }

        if (str_contains($msg, 'permission denied')) {
            return 'Permissão negada -- confira se a Application Key/credencial tem acesso de escrita ao bucket configurado.';
        }

        if (str_contains($msg, 'timeout') || str_contains($msg, 'connection refused') || str_contains($msg, 'no such host') || str_contains($msg, 'context deadline exceeded')) {
            return 'Falha de conexão com o provedor de nuvem. Verifique a internet do servidor e tente novamente.';
        }

        return 'Erro ao enviar para o provedor de nuvem -- veja o detalhe técnico abaixo.';
    }

    /**
     * @return string|null mensagem de erro, ou null se ok
     */
    private function preencherCredenciais(array &$dados, array $post, ?array $existente, bool $paraTeste = false): ?string
    {
        switch ($dados['provider']) {
            case 'b2':
                $dados['b2_key_id'] = trim($post['b2_key_id'] ?? '');
                $dados['b2_bucket'] = trim($post['b2_bucket'] ?? '');
                $dados['b2_prefixo'] = trim($post['b2_prefixo'] ?? '') ?: null;

                $chave = trim($post['b2_application_key'] ?? '');
                $dados['b2_application_key_cifrada'] = $chave !== ''
                    ? CryptoService::encriptar($chave)
                    : ($existente['b2_application_key_cifrada'] ?? null);

                if ($dados['b2_key_id'] === '' || $dados['b2_bucket'] === '' || !$dados['b2_application_key_cifrada']) {
                    return 'Preencha Key ID, Application Key e Bucket do Backblaze B2.';
                }
                break;

            case 's3':
                $dados['s3_access_key_id'] = trim($post['s3_access_key_id'] ?? '');
                $dados['s3_bucket'] = trim($post['s3_bucket'] ?? '');
                $dados['s3_regiao'] = trim($post['s3_regiao'] ?? '');
                $dados['s3_endpoint'] = trim($post['s3_endpoint'] ?? '') ?: null;
                $dados['s3_prefixo'] = trim($post['s3_prefixo'] ?? '') ?: null;

                $chave = trim($post['s3_secret_access_key'] ?? '');
                $dados['s3_secret_access_key_cifrada'] = $chave !== ''
                    ? CryptoService::encriptar($chave)
                    : ($existente['s3_secret_access_key_cifrada'] ?? null);

                if ($dados['s3_access_key_id'] === '' || $dados['s3_bucket'] === '' || $dados['s3_regiao'] === '' || !$dados['s3_secret_access_key_cifrada']) {
                    return 'Preencha Access Key ID, Secret Access Key, Região e Bucket do S3.';
                }
                break;

            case 'drive':
                $dados['drive_client_id'] = trim($post['drive_client_id'] ?? '') ?: null;
                $dados['drive_pasta_id'] = trim($post['drive_pasta_id'] ?? '') ?: null;

                $clientSecret = trim($post['drive_client_secret'] ?? '');
                $dados['drive_client_secret_cifrada'] = $clientSecret !== ''
                    ? CryptoService::encriptar($clientSecret)
                    : ($existente['drive_client_secret_cifrada'] ?? null);

                $token = trim($post['drive_token'] ?? '');
                $dados['drive_token_cifrado'] = $token !== ''
                    ? CryptoService::encriptar($token)
                    : ($existente['drive_token_cifrado'] ?? null);

                if (!$dados['drive_token_cifrado']) {
                    return 'Cole o token gerado pelo comando "rclone authorize drive" (rode-o uma vez na sua máquina, com um navegador).';
                }
                break;
        }

        return null;
    }

    /**
     * Monta as linhas "chave = valor" da seção do rclone.conf de um
     * destino (persistido ou temporário, ver testarConexao()). Valores
     * nunca podem conter quebra de linha -- um valor malicioso/colado com
     * "\n[outro]\nkey = x" poderia injetar uma seção inteira no arquivo.
     */
    private function linhasSecao(array $destino): array
    {
        switch ($destino['provider']) {
            case 'b2':
                return [
                    'type = b2',
                    'account = ' . $this->limpar((string)($destino['b2_key_id'] ?? '')),
                    'key = ' . $this->limpar(CryptoService::decriptar((string)$destino['b2_application_key_cifrada'])),
                ];

            case 's3':
                $linhas = [
                    'type = s3',
                    'provider = ' . (!empty($destino['s3_endpoint']) ? 'Other' : 'AWS'),
                    'access_key_id = ' . $this->limpar((string)($destino['s3_access_key_id'] ?? '')),
                    'secret_access_key = ' . $this->limpar(CryptoService::decriptar((string)$destino['s3_secret_access_key_cifrada'])),
                    'region = ' . $this->limpar((string)($destino['s3_regiao'] ?? '')),
                ];
                if (!empty($destino['s3_endpoint'])) {
                    $linhas[] = 'endpoint = ' . $this->limpar((string)$destino['s3_endpoint']);
                }
                return $linhas;

            case 'drive':
                $linhas = ['type = drive'];
                if (!empty($destino['drive_client_id'])) {
                    $linhas[] = 'client_id = ' . $this->limpar((string)$destino['drive_client_id']);
                }
                if (!empty($destino['drive_client_secret_cifrada'])) {
                    $linhas[] = 'client_secret = ' . $this->limpar(CryptoService::decriptar((string)$destino['drive_client_secret_cifrada']));
                }
                $linhas[] = 'token = ' . $this->limpar(CryptoService::decriptar((string)$destino['drive_token_cifrado']));
                if (!empty($destino['drive_pasta_id'])) {
                    $linhas[] = 'root_folder_id = ' . $this->limpar((string)$destino['drive_pasta_id']);
                }
                return $linhas;
        }

        return [];
    }

    /** bucket[/prefixo] (B2/S3) ou vazio (Drive, escopado por root_folder_id) */
    private function destinoRemoto(array $destino): string
    {
        switch ($destino['provider']) {
            case 'b2':
                $base = trim((string)($destino['b2_bucket'] ?? ''), '/');
                $prefixo = trim((string)($destino['b2_prefixo'] ?? ''), '/');
                break;
            case 's3':
                $base = trim((string)($destino['s3_bucket'] ?? ''), '/');
                $prefixo = trim((string)($destino['s3_prefixo'] ?? ''), '/');
                break;
            default:
                return '';
        }

        return $prefixo !== '' ? "{$base}/{$prefixo}" : $base;
    }

    private function limpar(string $valor): string
    {
        return str_replace(["\r", "\n"], '', $valor);
    }

    /**
     * O rclone renova (e reescreve no rclone.conf) o access_token do
     * Google Drive a cada uso do refresh_token -- sem isso, o token
     * cifrado salvo em backup_destinos fica cada vez mais desatualizado
     * até parar de funcionar.
     */
    private function atualizarTokenDriveAposExecucao(int $destinoId): void
    {
        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/backup_ler_config_web.sh',
            [self::nomeRemote($destinoId)]
        );

        foreach (explode("\n", $resultado['output']) as $linha) {
            if (preg_match('/^\s*token\s*=\s*(.+)$/', $linha, $m)) {
                $this->repo->atualizarDriveToken($destinoId, CryptoService::encriptar(trim($m[1])));
                return;
            }
        }
    }
}
