<?php

namespace App\Services;

use App\Repositories\SambaCompartilhamentoRepository;

class SambaDiagnosticoService
{
    private LinuxService $linux;
    private SambaCompartilhamentoRepository $compartilhamentoRepository;

    public function __construct()
    {
        $this->linux = new LinuxService();
        $this->compartilhamentoRepository = new SambaCompartilhamentoRepository();
    }

    public function executar(): array
    {
        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/diagnostico_samba_web.sh'
        );

        $output = $resultado['output'] ?? '';

        $pastasLinux = $this->extrairPastas($output);
        $compartilhamentosBanco = $this->compartilhamentoRepository->listar();

        return [
            'success' => $resultado['success'],
            'raw' => $output,
            'servicos' => $this->extrairServicos($output),
            'testparm' => $this->extrairTestparm($output),
            'pastas' => $pastasLinux,
            'logs' => $this->extrairSecao($output, 'LOGS_RECENTES'),
            'smbstatus' => $this->extrairSecao($output, 'SMBSTATUS'),
            'comparacao' => $this->compararBancoLinux($compartilhamentosBanco, $pastasLinux),
        ];
    }

    private function compararBancoLinux(array $banco, array $linux): array
    {
        $bancoPorNome = [];
        $linuxPorNome = [];

        foreach ($banco as $c) {
            $bancoPorNome[strtolower($c['nome'])] = $c;
        }

        foreach ($linux as $p) {
            $linuxPorNome[strtolower($p['nome'])] = $p;
        }

        $sincronizados = [];
        $orfãosLinux = [];
        $ausentesLinux = [];

        foreach ($bancoPorNome as $nome => $compartilhamento) {
            if (isset($linuxPorNome[$nome])) {
                $sincronizados[] = [
                    'nome' => $compartilhamento['nome'],
                    'banco' => $compartilhamento,
                    'linux' => $linuxPorNome[$nome],
                ];
            } else {
                $ausentesLinux[] = $compartilhamento;
            }
        }

        foreach ($linuxPorNome as $nome => $pasta) {
            if (!isset($bancoPorNome[$nome])) {
                $orfãosLinux[] = $pasta;
            }
        }

        return [
            'banco_total' => count($banco),
            'linux_total' => count($linux),
            'sincronizados' => $sincronizados,
            'orfaos_linux' => $orfãosLinux,
            'ausentes_linux' => $ausentesLinux,
        ];
    }

    private function extrairServicos(string $output): array
    {
        return [
            'smbd' => $this->valor($output, 'SMBD_STATUS') ?: 'unknown',
            'nmbd' => $this->valor($output, 'NMBD_STATUS') ?: 'unknown',
            'smbd_enabled' => $this->valor($output, 'SMBD_ENABLED') ?: 'unknown',
            'nmbd_enabled' => $this->valor($output, 'NMBD_ENABLED') ?: 'unknown',
        ];
    }

    private function extrairTestparm(string $output): array
    {
        return [
            'status' => $this->valor($output, 'TESTPARM_STATUS') ?: 'unknown',
            'texto' => $this->extrairSecao($output, 'TESTPARM'),
        ];
    }

    private function extrairPastas(string $output): array
    {
        $secao = $this->extrairSecao($output, 'PASTAS');
        $linhas = array_filter(array_map('trim', explode("\n", $secao)));

        $pastas = [];

        foreach ($linhas as $linha) {
            if (!str_contains($linha, '|')) {
                continue;
            }

            [$nome, $path, $owner, $grupo, $modo, $tamanho] = array_pad(explode('|', $linha), 6, '');

            $pastas[] = [
                'nome'    => trim($nome),
                'path'    => trim($path),
                'owner'   => trim($owner),
                'grupo'   => trim($grupo),
                'modo'    => trim($modo),
                'tamanho' => trim($tamanho) ?: '-',
            ];
        }

        return $pastas;
    }

    private function valor(string $output, string $chave): ?string
    {
        if (preg_match('/^' . preg_quote($chave, '/') . '=(.*)$/m', $output, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    private function extrairSecao(string $output, string $secao): string
    {
        $pattern = '/### ' . preg_quote($secao, '/') . '\n(.*?)(?=\n### |\z)/s';

        if (preg_match($pattern, $output, $m)) {
            return trim($m[1]);
        }

        return '';
    }

    public function interpretarLogs(string $logs): array
    {
        $achados = [];

        if (stripos($logs, 'NT_STATUS_ACCESS_DENIED') !== false) {
            $achados[] = [
                'nivel' => 'warning',
                'titulo' => 'Acessos negados detectados',
                'descricao' => 'O Samba registrou tentativas negadas. Isso normalmente indica usuário sem grupo, ACL incorreta ou credencial Windows cacheada.'
            ];
        }

        if (stripos($logs, 'failed') !== false || stripos($logs, 'error') !== false) {
            $achados[] = [
                'nivel' => 'danger',
                'titulo' => 'Erros recentes no serviço',
                'descricao' => 'Foram encontrados erros recentes nos logs do Samba. Consulte os detalhes técnicos.'
            ];
        }

        if (empty($achados)) {
            $achados[] = [
                'nivel' => 'success',
                'titulo' => 'Nenhum erro crítico recente',
                'descricao' => 'Não foram encontrados padrões críticos nos logs recentes analisados.'
            ];
        }

        return $achados;
    }
}
