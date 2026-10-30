<?php

namespace App\Core\Samba;

use App\Repositories\SambaCompartilhamentoExcecaoRepository;
use App\Repositories\SambaCompartilhamentoRepository;
use App\Services\LinuxService;

class SambaConfigGenerator
{
    private LinuxService $linux;
    private SambaCompartilhamentoRepository $repository;
    private SambaCompartilhamentoExcecaoRepository $excecoes;

    public function __construct(?LinuxService $linux = null, ?SambaCompartilhamentoRepository $repository = null)
    {
        $this->linux = $linux ?? new LinuxService();
        $this->repository = $repository ?? new SambaCompartilhamentoRepository();
        $this->excecoes = new SambaCompartilhamentoExcecaoRepository();
    }

    /**
     * Gera o conteúdo do shares.conf. Compartilhamentos cujo grupo Unix não
     * existe de verdade são pulados (em vez de gerar "valid users = @grupo"
     * com um grupo inexistente, o que trancaria o compartilhamento inteiro
     * silenciosamente) e retornados em 'ignorados' para o chamador avisar o admin.
     */
    public function generate(array $shares): array
    {
        $config = "# Arquivo gerado pela RD Intranet\n";
        $config .= "# Não edite manualmente. Altere pela interface web.\n\n";

        $ignorados = [];

        foreach ($shares as $share) {
            if (($share['status'] ?? 'ativo') !== 'ativo') {
                continue;
            }

            $grupo = $share['grupo'] ?? '';

            if ($grupo === '' || !$this->linux->grupoExiste($grupo)) {
                $ignorados[] = $share['nome'] . " (grupo '{$grupo}' não existe no sistema)";
                continue;
            }

            $autorizados = array_column(
                $this->repository->usuariosAutorizadosComLogin((int)$share['id']),
                'login'
            );

            $config .= SambaTemplate::share($share, $autorizados);

            // Exceção por pasta: mesmo compartilhamento (permissões, lixeira,
            // somente leitura), só que na subpasta e sem as extensões liberadas
            // no veto -- o Samba não aceita veto diferente por subpasta.
            foreach ($this->excecoes->listarPorCompartilhamento((int)$share['id']) as $excecao) {
                $config .= SambaTemplate::share(
                    array_merge($share, [
                        'nome' => $excecao['nome_compartilhamento'],
                        'caminho' => rtrim($share['caminho'], '/') . '/' . $excecao['pasta'],
                    ]),
                    $autorizados,
                    array_filter(explode(',', $excecao['extensoes']))
                );
            }
        }

        return [
            'conteudo' => trim($config) . PHP_EOL,
            'ignorados' => $ignorados,
        ];
    }
}
