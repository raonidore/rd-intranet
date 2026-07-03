<?php

namespace App\Core\Samba;

class SambaConfigWriter
{
    private string $tempFile = '/tmp/rd_smb.conf';

    public function writeTemp(string $config): string
    {
        file_put_contents($this->tempFile, $config);

        return $this->tempFile;
    }
}
