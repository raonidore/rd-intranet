<?php

namespace App\Services;

class ApacheStatusService
{
    private LinuxService $linux;

    public function __construct()
    {
        $this->linux = new LinuxService();
    }

    public function snapshot(): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/apache_status_web.sh');
        $texto = $resultado['output'];

        $dados = [
            'versao' => '-',
            'servico_status' => 'unknown',
            'servico_enabled' => 'unknown',
            'configtest' => 'ERRO',
            'sites_disponiveis' => 0,
            'sites_habilitados' => 0,
            'modulos_disponiveis' => 0,
            'modulos_habilitados' => 0,
            'ssl_modulo' => false,
            'ssl_porta' => false,
            'logs' => [],
        ];

        $secao = '';

        foreach (explode("\n", $texto) as $linha) {
            $linha = trim($linha);

            if ($linha === '') {
                continue;
            }

            if (str_starts_with($linha, '### ')) {
                $secao = substr($linha, 4);
                continue;
            }

            switch ($secao) {
                case 'VERSAO':
                    $dados['versao'] = $linha;
                    break;

                case 'SERVICO':
                    if (str_starts_with($linha, 'STATUS=')) {
                        $dados['servico_status'] = substr($linha, 7);
                    } elseif (str_starts_with($linha, 'ENABLED=')) {
                        $dados['servico_enabled'] = substr($linha, 8);
                    }
                    break;

                case 'CONFIGTEST':
                    $dados['configtest'] = $linha;
                    break;

                case 'SITES':
                    if (str_starts_with($linha, 'DISPONIVEIS=')) {
                        $dados['sites_disponiveis'] = (int)substr($linha, 12);
                    } elseif (str_starts_with($linha, 'HABILITADOS=')) {
                        $dados['sites_habilitados'] = (int)substr($linha, 12);
                    }
                    break;

                case 'MODULOS':
                    if (str_starts_with($linha, 'DISPONIVEIS=')) {
                        $dados['modulos_disponiveis'] = (int)substr($linha, 12);
                    } elseif (str_starts_with($linha, 'HABILITADOS=')) {
                        $dados['modulos_habilitados'] = (int)substr($linha, 12);
                    }
                    break;

                case 'SSL':
                    if (str_starts_with($linha, 'MODULO=')) {
                        $dados['ssl_modulo'] = substr($linha, 7) === 'sim';
                    } elseif (str_starts_with($linha, 'PORTA_443=')) {
                        $dados['ssl_porta'] = substr($linha, 10) === 'sim';
                    }
                    break;

                case 'LOGS':
                    [$nome, $tamanho] = array_pad(explode('|', $linha, 2), 2, '0');
                    $dados['logs'][] = [
                        'nome' => $nome,
                        'tamanho' => $this->formatarBytes((int)$tamanho),
                    ];
                    break;
            }
        }

        return $dados;
    }

    /**
     * Le as ultimas linhas de um log do Apache. Precisa de sudo --
     * /var/log/apache2 e root:adm 640 (o proprio apache2 abre o arquivo
     * como root antes de baixar privilegio pra www-data; um processo
     * PHP separado, mesmo rodando como www-data, nao consegue ler
     * direto -- confirmado testando ao vivo).
     */
    public function verLog(string $nome, int $linhas = 200): string
    {
        if (!preg_match('/^[a-zA-Z0-9_.-]+\.log$/', $nome)) {
            return 'Nome de log inválido.';
        }

        $linhas = max(10, min($linhas, 1000));

        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/apache_log_ver_web.sh', [$nome, (string)$linhas]);

        return $resultado['success'] ? $resultado['output'] : 'Não foi possível ler o arquivo: ' . $resultado['output'];
    }

    private function formatarBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }
}
