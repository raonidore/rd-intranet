<?php

namespace App\Core\Samba;

class SambaConfigWriter
{
    private string $tempFile = '/etc/samba/rd/tmp/shares.conf.tmp';

    public function writeTemp(string $config): string
    {
        if (file_put_contents($this->tempFile, $config) === false) {
            throw new \RuntimeException(
                "Não foi possível escrever {$this->tempFile} (diretório existe? www-data tem permissão de escrita?)"
            );
        }

        return $this->tempFile;
    }
}
