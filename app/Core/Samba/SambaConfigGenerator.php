<?php

namespace App\Core\Samba;

class SambaConfigGenerator
{
    public function generate(array $shares): string
    {
        $config = SambaTemplate::global();

        foreach ($shares as $share) {
            if (($share['status'] ?? 'ativo') !== 'ativo') {
                continue;
            }

            $config .= SambaTemplate::share($share);
        }

        return trim($config) . PHP_EOL;
    }
}
