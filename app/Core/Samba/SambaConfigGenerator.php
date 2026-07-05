<?php

namespace App\Core\Samba;

use App\Services\LinuxService;

class SambaConfigGenerator
{
    private LinuxService $linux;

    public function __construct(?LinuxService $linux = null)
    {
        $this->linux = $linux ?? new LinuxService();
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

            $config .= SambaTemplate::share($share);
        }

        return [
            'conteudo' => trim($config) . PHP_EOL,
            'ignorados' => $ignorados,
        ];
    }
}
