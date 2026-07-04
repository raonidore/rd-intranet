<?php

namespace App\Core\Samba;

class SambaConfigWriter
{
    private string $tempFile = '/etc/samba/rd/tmp/shares.conf.tmp';

    public function writeTemp(string $config): string
    {
        file_put_contents($this->tempFile, $config);

        return $this->tempFile;
    }
}
