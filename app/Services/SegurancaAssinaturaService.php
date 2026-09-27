<?php

namespace App\Services;

/**
 * Lista pública de extensões e notas de resgate de ransomware, opcional
 * (desligada por padrão) -- liga/desliga na Central de Segurança, aba
 * "Exceções e falsos positivos".
 *
 * Fonte: github.com/dannyroemhild/ransomware-fileext-list (GPL-3.0,
 * atualizada semanalmente a partir de pcrisk.com e avisos de segurança).
 * Por ser GPL, a lista é BAIXADA em uso pelo servidor e nunca copiada pro
 * repositório. Ela traz padrões genéricos demais pra usar às cegas
 * ("*.lock", "*.#", "*.0000", "*@aol.com") -- filtrarLista() só aproveita
 * extensões simples e específicas e nomes exatos de nota de resgate.
 *
 * No agente (1.0.35+) a lista só REFORÇA a detecção de mudança em massa:
 * renomear vários arquivos pra uma extensão conhecida de ransomware, ou
 * criar a mesma nota de resgate em pastas diferentes, vira crítico mesmo
 * abaixo do limiar de volume.
 */
class SegurancaAssinaturaService
{
    public const FONTE_URL = 'https://raw.githubusercontent.com/dannyroemhild/ransomware-fileext-list/master/fileextlist.txt';
    public const FONTE_PAGINA = 'https://github.com/dannyroemhild/ransomware-fileext-list';

    private const CHAVE_ATIVO = 'seguranca_assinaturas_ativo';
    private const CHAVE_ATUALIZADO = 'seguranca_assinaturas_atualizado_em';
    private const CHAVE_STATUS = 'seguranca_assinaturas_status';
    private const INTERVALO_SEGUNDOS = 86400;
    private const TIMEOUT_SEGUNDOS = 8;

    /** Extensões comuns que aparecem na lista pública mas são usadas por programas legítimos. */
    private const EXTENSOES_BENIGNAS = [
        'lock', 'lck', 'tmp', 'temp', 'bak', 'old', 'log', 'dat', 'db', 'txt', 'doc', 'docx', 'xls', 'xlsx',
        'ppt', 'pptx', 'pdf', 'jpg', 'jpeg', 'png', 'gif', 'zip', 'rar', '7z', 'gz', 'tar', 'xml', 'json',
        'html', 'htm', 'csv', 'ini', 'cfg', 'conf', 'exe', 'dll', 'sys', 'bin', 'iso', 'img', 'mp3', 'mp4',
        'avi', 'mov', 'wav', 'bmp', 'tif', 'tiff', 'odt', 'ods', 'rtf', 'msg', 'eml', 'pst', 'ost', 'sql',
        'mdb', 'accdb', 'js', 'css', 'php', 'py', 'cs', 'java', 'c', 'h', 'cpp', 'ps1', 'bat', 'cmd', 'vbs',
        'key', 'pem', 'crt', 'cer', 'pfx', 'p12', 'enc', 'backup', 'cache', 'part', 'partial', 'download',
        'crdownload', 'swp', 'orig', 'new', 'copy', 'dmp', 'etl', 'evtx', 'cab', 'msi', 'lnk', 'url', 'aspx',
        'asp', 'jsp', 'md', 'yml', 'yaml', 'toml', 'svg', 'webp', 'heic', 'raw', 'psd', 'ai', 'eps', 'dwg',
        'vhd', 'vhdx', 'vmdk', 'ova', 'ovf', 'sqlite', 'ldf', 'mdf', 'ndf', 'frm', 'ibd', 'myd', 'myi',
    ];

    /** Nomes de arquivo genéricos demais pra servirem de sinal de nota de resgate. */
    private const NOTAS_GENERICAS = [
        'readme.txt', 'read_me.txt', 'read me.txt', 'readme.html', 'readme.md', 'readme.hta', 'help.txt',
        'info.txt', 'instructions.txt', 'instruction.txt', 'note.txt', 'notes.txt', 'index.html', 'desktop.ini',
    ];

    private string $arquivoCache;

    public function __construct()
    {
        $this->arquivoCache = __DIR__ . '/../../storage/seguranca/assinaturas.json';
    }

    public function ativo(): bool
    {
        return ConfigService::get(self::CHAVE_ATIVO, '0') === '1';
    }

    public function definirAtivo(bool $ativo): void
    {
        ConfigService::set(self::CHAVE_ATIVO, $ativo ? '1' : '0');
        if ($ativo && $this->dados() === null) {
            $this->atualizar();
        }
    }

    /** @return array{versao: string, extensoes: list<string>, notas: list<string>, total_fonte: int, atualizado_em: int}|null */
    public function dados(): ?array
    {
        if (!is_file($this->arquivoCache)) {
            return null;
        }
        $dados = json_decode((string)file_get_contents($this->arquivoCache), true);

        return is_array($dados) && isset($dados['versao']) ? $dados : null;
    }

    public function status(): array
    {
        $dados = $this->dados();

        return [
            'ativo' => $this->ativo(),
            'atualizado_em' => (int)ConfigService::get(self::CHAVE_ATUALIZADO, '0'),
            'mensagem' => (string)ConfigService::get(self::CHAVE_STATUS, ''),
            'extensoes' => $dados ? count($dados['extensoes']) : 0,
            'notas' => $dados ? count($dados['notas']) : 0,
            'total_fonte' => $dados['total_fonte'] ?? 0,
            'versao' => $dados['versao'] ?? null,
        ];
    }

    /** Versão enviada no checkin -- null quando desligado (o agente então descarta a lista). */
    public function versaoParaAgente(): ?string
    {
        return $this->ativo() ? ($this->dados()['versao'] ?? null) : null;
    }

    /** Chamado no checkin: renova no máximo uma vez por dia, só com a opção ligada. Nunca lança. */
    public function atualizarSeNecessario(): void
    {
        try {
            if (!$this->ativo()) {
                return;
            }
            $ultimaTentativa = (int)ConfigService::get(self::CHAVE_ATUALIZADO, '0');
            if (time() - $ultimaTentativa < self::INTERVALO_SEGUNDOS) {
                return;
            }
            $this->atualizar();
        } catch (\Throwable) {
            // lista externa nunca pode atrapalhar o checkin
        }
    }

    /** @return array{success: bool, message: string} */
    public function atualizar(): array
    {
        // Marca a tentativa antes de baixar: com a fonte fora do ar, não
        // tenta de novo a cada checkin -- só no dia seguinte (ou no botão).
        ConfigService::set(self::CHAVE_ATUALIZADO, (string)time());

        $contexto = stream_context_create([
            'http' => ['timeout' => self::TIMEOUT_SEGUNDOS, 'user_agent' => 'RD-Intranet'],
        ]);
        $texto = @file_get_contents(self::FONTE_URL, false, $contexto);

        if ($texto === false || strlen($texto) < 1000) {
            $mensagem = 'Falha ao baixar a lista em ' . date('d/m/Y H:i') . ' -- mantida a última versão baixada.';
            ConfigService::set(self::CHAVE_STATUS, $mensagem);
            return ['success' => false, 'message' => $mensagem];
        }

        [$extensoes, $notas, $totalFonte] = self::filtrarLista($texto);
        $dados = [
            'versao' => substr(sha1(implode("\n", $extensoes) . '|' . implode("\n", $notas)), 0, 16),
            'extensoes' => $extensoes,
            'notas' => $notas,
            'total_fonte' => $totalFonte,
            'atualizado_em' => time(),
        ];

        $pasta = dirname($this->arquivoCache);
        if (!is_dir($pasta)) {
            @mkdir($pasta, 0775, true);
        }
        if (@file_put_contents($this->arquivoCache, json_encode($dados)) === false) {
            $mensagem = 'Lista baixada, mas não consegui gravar em storage/seguranca (permissão da pasta).';
            ConfigService::set(self::CHAVE_STATUS, $mensagem);
            return ['success' => false, 'message' => $mensagem];
        }

        $mensagem = sprintf('Atualizada em %s: %d extensões e %d notas de resgate aproveitadas de %d padrões da fonte.',
            date('d/m/Y H:i'), count($extensoes), count($notas), $totalFonte);
        ConfigService::set(self::CHAVE_STATUS, $mensagem);

        return ['success' => true, 'message' => $mensagem];
    }

    /** @return array{0: list<string>, 1: list<string>, 2: int} */
    public static function filtrarLista(string $texto): array
    {
        $extensoes = [];
        $notas = [];
        $total = 0;

        foreach (preg_split('/\r\n|\r|\n/', $texto) as $linha) {
            $linha = trim($linha);
            if ($linha === '' || str_starts_with($linha, '#')) {
                continue;
            }
            $total++;

            // "*.ext" simples: extensão específica, com letra, 3+ caracteres,
            // fora da lista de extensões comuns (só número = parte de .7z/.rar).
            if (preg_match('/^\*\.([A-Za-z0-9_\-$~!]{3,40})$/', $linha, $m)) {
                $ext = mb_strtolower($m[1]);
                if (preg_match('/[a-z]/', $ext) && !in_array($ext, self::EXTENSOES_BENIGNAS, true)) {
                    $extensoes[] = '.' . $ext;
                }
                continue;
            }

            // Nome exato de nota de resgate (sem curinga), específico o bastante.
            if (!str_contains($linha, '*') && !str_contains($linha, '?') && preg_match('/\.[A-Za-z0-9]{2,5}$/', $linha)) {
                $nome = mb_strtolower($linha);
                if (mb_strlen($nome) >= 10 && !in_array($nome, self::NOTAS_GENERICAS, true)) {
                    $notas[] = $nome;
                }
            }
        }

        $extensoes = array_values(array_unique($extensoes));
        $notas = array_values(array_unique($notas));
        sort($extensoes);
        sort($notas);

        return [$extensoes, $notas, $total];
    }
}
