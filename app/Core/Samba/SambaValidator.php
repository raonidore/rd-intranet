<?php

namespace App\Core\Samba;

class SambaValidator
{
    public function validateFile(string $file): array
    {
        $cmd = 'testparm -s ' . escapeshellarg($file) . ' 2>&1';

        $output = [];
        $exitCode = 0;

        exec($cmd, $output, $exitCode);

        return [
            'success' => $exitCode === 0,
            'exitCode' => $exitCode,
            'output' => implode("\n", $output),
        ];
    }
}
