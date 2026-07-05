<?php

namespace App\Services;

class SambaGlobalConfigService
{
    private LinuxService $linux;

    // Parâmetros gerenciados, agrupados por categoria
    public static array $grupos = [
        'identidade' => [
            'titulo' => 'Identidade do Servidor',
            'icone'  => 'bi-server',
            'campos' => [
                ['key' => 'workgroup',     'label' => 'Workgroup',            'tipo' => 'text',
                 'help' => 'Nome do domínio/grupo de trabalho Windows (ex: WORKGROUP, EMPRESA)'],
                ['key' => 'server string', 'label' => 'Descrição do servidor', 'tipo' => 'text',
                 'help' => 'Texto exibido ao navegar em Rede no Windows Explorer'],
                ['key' => 'netbios name',  'label' => 'Nome NetBIOS',          'tipo' => 'text',
                 'help' => 'Nome do servidor visível na rede Windows (máx. 15 caracteres)'],
            ],
        ],
        'seguranca' => [
            'titulo' => 'Segurança e Autenticação',
            'icone'  => 'bi-shield-lock',
            'campos' => [
                ['key' => 'security', 'label' => 'Modo de segurança', 'tipo' => 'select',
                 'opcoes' => ['user' => 'user — Usuários locais (padrão)', 'ads' => 'ads — Active Directory', 'domain' => 'domain — Domínio NT'],
                 'help' => 'Define como os usuários se autenticam no servidor'],
                ['key' => 'map to guest', 'label' => 'Acesso anônimo', 'tipo' => 'select',
                 'opcoes' => ['Never' => 'Never — Nunca (recomendado)', 'Bad User' => 'Bad User — Redirecionar usuário inválido', 'Bad Password' => 'Bad Password — Redirecionar senha errada'],
                 'help' => 'Controla se usuários sem conta Samba recebem acesso de convidado'],
                ['key' => 'server min protocol', 'label' => 'Protocolo mínimo (servidor)', 'tipo' => 'select',
                 'opcoes' => ['SMB2' => 'SMB2 (recomendado)', 'SMB2_02' => 'SMB2_02', 'SMB2_10' => 'SMB2_10', 'SMB3' => 'SMB3', 'SMB3_11' => 'SMB3_11', 'NT1' => 'NT1 / SMB1 (inseguro)'],
                 'help' => 'Versão mínima do protocolo SMB que o servidor aceita'],
                ['key' => 'client min protocol', 'label' => 'Protocolo mínimo (cliente)', 'tipo' => 'select',
                 'opcoes' => ['SMB2' => 'SMB2 (recomendado)', 'SMB2_02' => 'SMB2_02', 'SMB3' => 'SMB3', 'NT1' => 'NT1 / SMB1'],
                 'help' => 'Versão mínima aceita ao conectar como cliente'],
            ],
        ],
        'rede' => [
            'titulo' => 'Rede e Conectividade',
            'icone'  => 'bi-diagram-3',
            'campos' => [
                ['key' => 'interfaces', 'label' => 'Interfaces de rede', 'tipo' => 'text',
                 'help' => 'Interfaces que o Samba irá escutar (ex: lo enp6s18). Separar por espaço.'],
                ['key' => 'bind interfaces only', 'label' => 'Apenas interfaces listadas', 'tipo' => 'select',
                 'opcoes' => ['yes' => 'Sim — Escutar somente as interfaces listadas', 'no' => 'Não — Escutar em todas'],
                 'help' => 'Recomendado "Sim" em servidores com múltiplas interfaces'],
                ['key' => 'hosts allow', 'label' => 'Hosts permitidos', 'tipo' => 'text',
                 'help' => 'IPs ou redes permitidas globalmente (ex: 192.168.1. 10.0.0.0/8). Deixar vazio para todos.'],
                ['key' => 'hosts deny', 'label' => 'Hosts negados', 'tipo' => 'text',
                 'help' => 'IPs ou redes bloqueadas globalmente. Deixar vazio para nenhum.'],
            ],
        ],
        'logs' => [
            'titulo' => 'Logs e Auditoria',
            'icone'  => 'bi-journal-text',
            'campos' => [
                ['key' => 'log file',    'label' => 'Arquivo de log',    'tipo' => 'text',
                 'help' => 'Caminho do arquivo de log. Use %m para separar por máquina (ex: /var/log/samba/%m.log)'],
                ['key' => 'max log size','label' => 'Tamanho máximo (KB)','tipo' => 'number',
                 'help' => 'Tamanho máximo do log em KB antes de rotacionar (0 = ilimitado)'],
                ['key' => 'logging',     'label' => 'Modo de logging',   'tipo' => 'select',
                 'opcoes' => ['file' => 'Arquivo', 'syslog' => 'Syslog', 'systemd' => 'Systemd Journal'],
                 'help' => 'Destino dos logs do Samba'],
                ['key' => 'log level',   'label' => 'Nível de detalhe',  'tipo' => 'select',
                 'opcoes' => ['0' => '0 — Apenas erros', '1' => '1 — Avisos (padrão)', '2' => '2 — Operações', '3' => '3 — Detalhado', '5' => '5 — Debug'],
                 'help' => 'Maior nível = mais detalhes no log, mais consumo de disco'],
            ],
        ],
        'charset' => [
            'titulo' => 'Codificação de Caracteres',
            'icone'  => 'bi-fonts',
            'campos' => [
                ['key' => 'unix charset', 'label' => 'Charset Unix', 'tipo' => 'select',
                 'opcoes' => ['UTF-8' => 'UTF-8 (recomendado)', 'ISO8859-1' => 'ISO 8859-1', 'UTF-8@ctype=pt_BR' => 'UTF-8 pt_BR'],
                 'help' => 'Codificação usada pelo sistema Linux'],
                ['key' => 'dos charset', 'label' => 'Charset DOS/Windows', 'tipo' => 'select',
                 'opcoes' => ['CP850' => 'CP850 — Europa Ocidental (padrão)', 'CP1252' => 'CP1252 — Windows', 'ASCII' => 'ASCII'],
                 'help' => 'Codificação usada pelos clientes Windows mais antigos'],
            ],
        ],
        'funcionalidades' => [
            'titulo' => 'Funcionalidades Avançadas',
            'icone'  => 'bi-gear',
            'campos' => [
                ['key' => 'vfs objects',       'label' => 'Módulos VFS',              'tipo' => 'text',
                 'help' => 'Módulos de sistema de arquivos virtual. Separar por espaço (ex: acl_xattr recycle)'],
                ['key' => 'map acl inherit',   'label' => 'Herdar ACLs (POSIX→Win)',  'tipo' => 'select',
                 'opcoes' => ['yes' => 'Sim', 'no' => 'Não'],
                 'help' => 'Permite que ACLs POSIX sejam visíveis como permissões Windows'],
                ['key' => 'store dos attributes','label' => 'Armazenar atributos DOS', 'tipo' => 'select',
                 'opcoes' => ['yes' => 'Sim', 'no' => 'Não'],
                 'help' => 'Preserva atributos como oculto/somente leitura via xattrs'],
                ['key' => 'access based share enum', 'label' => 'Ocultar compartilhamentos sem acesso', 'tipo' => 'select',
                 'opcoes' => ['yes' => 'Sim — Usuário vê só o que tem acesso', 'no' => 'Não — Todos os compartilhamentos visíveis'],
                 'help' => 'Recomendado "Sim" para segurança'],
                ['key' => 'hide unreadable',   'label' => 'Ocultar arquivos ilegíveis', 'tipo' => 'select',
                 'opcoes' => ['yes' => 'Sim', 'no' => 'Não'],
                 'help' => 'Oculta arquivos que o usuário não tem permissão de ler'],
                ['key' => 'follow symlinks',   'label' => 'Seguir links simbólicos', 'tipo' => 'select',
                 'opcoes' => ['no' => 'Não (seguro)', 'yes' => 'Sim (risco de path traversal)'],
                 'help' => 'Desabilitar é mais seguro'],
                ['key' => 'wide links',        'label' => 'Links externos ao share', 'tipo' => 'select',
                 'opcoes' => ['no' => 'Não (seguro)', 'yes' => 'Sim'],
                 'help' => 'Permite links simbólicos que apontam para fora do compartilhamento'],
            ],
        ],
        'lixeira' => [
            'titulo' => 'Lixeira Administrativa',
            'icone'  => 'bi-trash3',
            'campos' => [
                ['key' => 'recycle:repository', 'label' => 'Pasta da lixeira',   'tipo' => 'text',
                 'help' => 'Nome da pasta de lixeira dentro de cada compartilhamento (ex: .recycle)'],
                ['key' => 'recycle:keeptree',   'label' => 'Preservar estrutura', 'tipo' => 'select',
                 'opcoes' => ['yes' => 'Sim — Mantém caminho original', 'no' => 'Não'],
                 'help' => 'Mantém a estrutura de pastas ao mover para lixeira'],
                ['key' => 'recycle:versions',   'label' => 'Versionar arquivos',  'tipo' => 'select',
                 'opcoes' => ['yes' => 'Sim — Não sobrescreve', 'no' => 'Não'],
                 'help' => 'Renomeia ao invés de sobrescrever arquivos na lixeira'],
                ['key' => 'recycle:touch',      'label' => 'Atualizar data',      'tipo' => 'select',
                 'opcoes' => ['yes' => 'Sim', 'no' => 'Não'],
                 'help' => 'Atualiza data de modificação ao mover para lixeira'],
            ],
        ],
        'impressoras' => [
            'titulo' => 'Impressoras',
            'icone'  => 'bi-printer',
            'campos' => [
                ['key' => 'load printers',   'label' => 'Compartilhar impressoras', 'tipo' => 'select',
                 'opcoes' => ['no' => 'Não (recomendado — apenas arquivos)', 'yes' => 'Sim'],
                 'help' => 'Habilita compartilhamento de impressoras via Samba'],
                ['key' => 'disable spoolss', 'label' => 'Desabilitar spooler',      'tipo' => 'select',
                 'opcoes' => ['yes' => 'Sim (recomendado)', 'no' => 'Não'],
                 'help' => 'Desabilita o spooler de impressão quando não usa impressoras'],
            ],
        ],
    ];

    public function __construct()
    {
        $this->linux = new LinuxService();
    }

    public function lerConfigAtual(): array
    {
        $raw = shell_exec('sudo /opt/rdtecnologia/scripts/ler_smb_conf_web.sh 2>/dev/null') ?? '';
        return $this->parseGlobal($raw);
    }

    public function listarBackups(): array
    {
        $raw  = shell_exec('sudo /opt/rdtecnologia/scripts/listar_backups_samba_web.sh 2>/dev/null') ?? '';
        $linhas = array_filter(explode("\n", trim($raw)));
        $backups = [];
        foreach ($linhas as $linha) {
            [$arquivo, $size, $mtime] = array_pad(explode('|', $linha), 3, '0');
            if (!$arquivo) continue;
            $backups[] = [
                'arquivo'   => trim($arquivo),
                'nome'      => basename(trim($arquivo)),
                'tamanho'   => $this->formatBytes((int)$size),
                'data'      => $mtime ? date('d/m/Y H:i:s', (int)$mtime) : '-',
            ];
        }
        return $backups;
    }

    public function aplicar(array $params): array
    {
        $conteudo = $this->gerarSmbConf($params);
        $tmpFile  = tempnam('/tmp', 'samba_global_');
        file_put_contents($tmpFile, $conteudo);

        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/apply_smb_conf_web.sh',
            [$tmpFile]
        );

        @unlink($tmpFile);
        return $resultado;
    }

    public function restaurarBackup(string $arquivo): array
    {
        // Validate path
        if (!preg_match('#^/etc/samba/smb\.conf\.bkp\.\d+#', $arquivo)) {
            return ['success' => false, 'output' => 'Arquivo de backup inválido.'];
        }

        $raw    = shell_exec('sudo /opt/rdtecnologia/scripts/restaurar_backup_samba_web.sh ' . escapeshellarg($arquivo) . ' 2>/dev/null') ?? '';
        $result = json_decode(trim($raw), true);

        if (is_array($result)) {
            return ['success' => $result['success'], 'output' => $result['message']];
        }

        return ['success' => false, 'output' => 'Resposta inesperada.'];
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    private function parseGlobal(string $content): array
    {
        $global    = [];
        $emGlobal  = false;

        foreach (explode("\n", $content) as $linha) {
            $t = trim($linha);

            if (preg_match('/^\[(.+)\]$/', $t, $m)) {
                $emGlobal = strtolower($m[1]) === 'global';
                continue;
            }

            if (!$emGlobal || empty($t) || str_starts_with($t, '#') || str_starts_with($t, ';')) {
                continue;
            }

            if (str_contains($t, '=')) {
                [$key, $value] = array_map('trim', explode('=', $t, 2));
                $global[$key] = $value;
            }
        }

        return $global;
    }

    private function gerarSmbConf(array $params): string
    {
        $linhas = [
            "# Arquivo gerado pela RD Intranet em " . date('d/m/Y H:i:s'),
            "# Não edite manualmente. Use a interface web.",
            "",
            "[global]",
        ];

        foreach ($params as $chave => $valor) {
            if ($valor !== '' && $valor !== null) {
                $linhas[] = "   $chave = $valor";
            }
        }

        $linhas[] = "";
        $linhas[] = "include = /etc/samba/shares.conf";

        return implode("\n", $linhas) . "\n";
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) return '-';
        $u = ['B', 'KB', 'MB'];
        $i = (int)floor(log($bytes, 1024));
        return round($bytes / pow(1024, $i), 1) . ' ' . $u[$i];
    }
}
