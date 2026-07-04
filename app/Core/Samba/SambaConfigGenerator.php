<?php

namespace App\Core\Samba;

class SambaConfigGenerator
{
    public function generate(array $shares): string
    {
        $config = "# Arquivo gerado pela RD Intranet\n";
        $config .= "# Não edite manualmente. Altere pela interface web.\n\n";

        foreach ($shares as $share) {
            if (($share['status'] ?? 'ativo') !== 'ativo') {
                continue;
            }

            $config .= SambaTemplate::share($share);
        }

        return trim($config) . PHP_EOL;
    }
}
