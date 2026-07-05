<?php

namespace App\Services;

class ApacheGlobalConfigService
{
    private LinuxService $linux;

    // Diretivas gerenciadas, agrupadas por categoria (mesmo padrão da
    // Config. Global Samba). Ficam num arquivo próprio da RD Intranet
    // (conf-available/rd-intranet.conf), nunca no apache2.conf da distro.
    public static array $grupos = [
        'identidade' => [
            'titulo' => 'Identidade do Servidor',
            'icone' => 'bi-server',
            'campos' => [
                ['key' => 'ServerName', 'label' => 'Nome do servidor (ServerName)', 'tipo' => 'text',
                 'help' => 'Suprime o aviso "Could not reliably determine the server\'s fully qualified domain name" (ex: rd.intranet)'],
            ],
        ],
        'conexoes' => [
            'titulo' => 'Conexões e Performance',
            'icone' => 'bi-speedometer2',
            'campos' => [
                ['key' => 'Timeout', 'label' => 'Timeout (segundos)', 'tipo' => 'number',
                 'help' => 'Tempo limite para receber/enviar dados antes de encerrar a conexão'],
                ['key' => 'KeepAlive', 'label' => 'Conexões persistentes (KeepAlive)', 'tipo' => 'select',
                 'opcoes' => ['On' => 'Ligado (recomendado)', 'Off' => 'Desligado'],
                 'help' => 'Permite múltiplas requisições numa mesma conexão TCP'],
                ['key' => 'MaxKeepAliveRequests', 'label' => 'Máx. requisições por conexão', 'tipo' => 'number',
                 'help' => 'Quantas requisições uma conexão persistente pode atender antes de fechar'],
                ['key' => 'KeepAliveTimeout', 'label' => 'Timeout do KeepAlive (segundos)', 'tipo' => 'number',
                 'help' => 'Tempo de espera pela próxima requisição na mesma conexão'],
            ],
        ],
        'seguranca' => [
            'titulo' => 'Segurança',
            'icone' => 'bi-shield-lock',
            'campos' => [
                ['key' => 'ServerTokens', 'label' => 'Informações do servidor (ServerTokens)', 'tipo' => 'select',
                 'opcoes' => ['Prod' => 'Prod — Só "Apache" (recomendado)', 'Major' => 'Major', 'Minor' => 'Minor', 'Min' => 'Min', 'OS' => 'OS', 'Full' => 'Full — Tudo (não recomendado)'],
                 'help' => 'Controla quanto detalhe do servidor aparece no header HTTP e em páginas de erro'],
                ['key' => 'ServerSignature', 'label' => 'Assinatura em páginas de erro', 'tipo' => 'select',
                 'opcoes' => ['Off' => 'Desligada (recomendado)', 'On' => 'Ligada', 'EMail' => 'Ligada com e-mail'],
                 'help' => 'Mostra (ou não) a versão do Apache no rodapé de páginas de erro geradas pelo servidor'],
            ],
        ],
        'logs' => [
            'titulo' => 'Logs',
            'icone' => 'bi-journal-text',
            'campos' => [
                ['key' => 'LogLevel', 'label' => 'Nível de log', 'tipo' => 'select',
                 'opcoes' => ['warn' => 'warn (padrão)', 'error' => 'error', 'info' => 'info', 'debug' => 'debug'],
                 'help' => 'Nível de detalhe do error.log'],
            ],
        ],
    ];

    public function __construct()
    {
        $this->linux = new LinuxService();
    }

    public function lerConfigAtual(): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/ler_apache_config_web.sh');
        return $this->parseDiretivas($resultado['output']);
    }

    public function listarBackups(): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/listar_backups_apache_web.sh');

        $backups = [];

        foreach (array_filter(explode("\n", trim($resultado['output']))) as $linha) {
            [$arquivo, $size, $mtime] = array_pad(explode('|', $linha), 3, '0');

            if (!$arquivo) {
                continue;
            }

            $backups[] = [
                'arquivo' => trim($arquivo),
                'nome' => basename(trim($arquivo)),
                'tamanho' => $this->formatBytes((int)$size),
                'data' => $mtime ? date('d/m/Y H:i:s', (int)$mtime) : '-',
            ];
        }

        return $backups;
    }

    public function aplicar(array $params): array
    {
        $conteudo = $this->gerarConf($params);
        $tmpFile = tempnam('/tmp', 'apache_global_');
        file_put_contents($tmpFile, $conteudo);

        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/apply_apache_config_web.sh',
            [$tmpFile]
        );

        @unlink($tmpFile);

        return $resultado;
    }

    public function restaurarBackup(string $arquivo): array
    {
        if (!preg_match('#^/etc/apache2/rd/backups/rd-intranet_[\w.]+\.conf$#', $arquivo)) {
            return ['success' => false, 'output' => 'Arquivo de backup inválido.'];
        }

        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/restaurar_backup_apache_web.sh',
            [$arquivo]
        );

        $decodificado = json_decode(trim($resultado['output']), true);

        if (is_array($decodificado)) {
            return ['success' => $decodificado['success'], 'output' => $decodificado['message']];
        }

        return ['success' => false, 'output' => 'Resposta inesperada.'];
    }

    private function parseDiretivas(string $conteudo): array
    {
        $valores = [];

        foreach (explode("\n", $conteudo) as $linha) {
            $linha = trim($linha);

            if ($linha === '' || str_starts_with($linha, '#')) {
                continue;
            }

            if (preg_match('/^(\S+)\s+(.+)$/', $linha, $m)) {
                $valores[$m[1]] = trim($m[2]);
            }
        }

        return $valores;
    }

    private function gerarConf(array $params): string
    {
        $linhas = [
            '# Arquivo gerado pela RD Intranet em ' . date('d/m/Y H:i:s'),
            '# Não edite manualmente. Use a interface web (Apache > Config. Global).',
            '',
        ];

        foreach ($params as $chave => $valor) {
            if ($valor !== '' && $valor !== null) {
                $linhas[] = "$chave $valor";
            }
        }

        return implode("\n", $linhas) . "\n";
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '-';
        }

        $unidades = ['B', 'KB', 'MB'];
        $i = (int)floor(log($bytes, 1024));

        return round($bytes / (1024 ** $i), 1) . ' ' . $unidades[$i];
    }
}
