<?php

namespace App\Services;

use App\Repositories\AntivirusRepository;

class AntivirusService
{
    private const CAMINHO_PADRAO = '/srv/samba/Compartilhamentos';

    private LinuxService $linux;
    private AntivirusRepository $repo;

    public function __construct()
    {
        $this->linux = new LinuxService();
        $this->repo = new AntivirusRepository();
    }

    public static function caminhoPadrao(): string
    {
        return self::CAMINHO_PADRAO;
    }

    public function status(): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/antivirus_status_web.sh');

        $status = [
            'instalado' => false,
            'clamd_ativo' => false,
            'freshclam_ativo' => false,
            'versao' => null,
            'tempo_real_ativo' => false,
        ];

        foreach (explode("\n", trim($resultado['output'])) as $linha) {
            $linha = trim($linha);
            if ($linha === '' || !str_contains($linha, '|')) continue;

            [$chave, $valor] = explode('|', $linha, 2);
            $valor = trim($valor);

            switch ($chave) {
                case 'instalado':
                    $status['instalado'] = $valor === '1';
                    break;
                case 'clamd_ativo':
                    $status['clamd_ativo'] = $valor === '1';
                    break;
                case 'freshclam_ativo':
                    $status['freshclam_ativo'] = $valor === '1';
                    break;
                case 'versao':
                    $status['versao'] = $valor !== '' ? $valor : null;
                    break;
                case 'tempo_real_ativo':
                    $status['tempo_real_ativo'] = $valor === '1';
                    break;
            }
        }

        return $status;
    }

    public function instalar(): array
    {
        // apt update + install pode demorar mais que o max_execution_time padrao
        set_time_limit(180);

        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/antivirus_instalar_web.sh');
        $dados = json_decode(trim($resultado['output']), true);

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }

    /**
     * @return array{success: bool, message: string, verificacao_id: ?int}
     */
    public function verificarAgora(?string $caminho, string $tipo = 'manual'): array
    {
        // escanear uma pasta grande pode demorar
        set_time_limit(300);

        $caminho = $caminho ?: self::CAMINHO_PADRAO;
        $verificacaoId = $this->repo->criarVerificacao($tipo, $caminho);

        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/antivirus_verificar_web.sh', [$caminho]);

        $total = 0;
        $ameacas = 0;
        $erro = null;

        foreach (explode("\n", trim($resultado['output'])) as $linha) {
            $linha = trim($linha);
            if ($linha === '') continue;

            $partes = explode('|', $linha);

            if ($partes[0] === 'ERRO') {
                $erro = $partes[1] ?? 'Erro desconhecido.';
                continue;
            }
            if ($partes[0] === 'TOTAL') {
                $total = (int)($partes[1] ?? 0);
                continue;
            }
            if ($partes[0] === 'AMEACA') {
                $ameacas++;
                $this->repo->registrarAmeaca(
                    $verificacaoId,
                    $partes[1] ?? '',
                    ($partes[2] ?? '') !== '' ? $partes[2] : null,
                    $partes[3] ?? '?'
                );
            }
        }

        $status = $erro !== null ? 'erro' : 'concluida';
        $this->repo->finalizarVerificacao($verificacaoId, $status, $total, $ameacas, $erro ?? $resultado['output']);

        return [
            'success' => $erro === null,
            'message' => $erro ?? "Verificação concluída: {$total} arquivo(s) verificado(s), {$ameacas} ameaça(s) encontrada(s).",
            'verificacao_id' => $verificacaoId,
        ];
    }

    public function ativarTempoReal(): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/antivirus_tempo_real_web.sh', ['ativar']);
        $dados = json_decode(trim($resultado['output']), true);

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }

    public function desativarTempoReal(): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/antivirus_tempo_real_web.sh', ['desativar']);
        $dados = json_decode(trim($resultado['output']), true);

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }

    public function historico(int $limite = 20): array
    {
        return $this->repo->listarVerificacoes($limite);
    }

    public function quarentena(): array
    {
        return $this->repo->listarQuarentena();
    }

    public function excluirDaQuarentena(int $id): array
    {
        $ameaca = $this->repo->buscarAmeaca($id);
        if (!$ameaca || !$ameaca['caminho_quarentena']) {
            return ['success' => false, 'message' => 'Item não encontrado.'];
        }

        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/antivirus_quarentena_excluir_web.sh',
            [$ameaca['caminho_quarentena']]
        );
        $dados = json_decode(trim($resultado['output']), true);

        if (is_array($dados) && !empty($dados['success'])) {
            $this->repo->atualizarAcaoAmeaca($id, 'excluido');
        }

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }
}
