<?php

namespace App\Core\Vpn;

class IpsecConfigWriter
{
    private string $tempConf = '/etc/rd-intranet/ikev2/tmp/ipsec.conf.tmp';
    private string $tempSecrets = '/etc/rd-intranet/ikev2/tmp/ipsec.secrets.tmp';
    private string $tempCaDir = '/etc/rd-intranet/ikev2/tmp/cacerts';

    /**
     * @param array<string,string> $caCerts nome_arquivo => conteudo PEM (CAs de conexoes de saida EAP)
     */
    public function writeTemp(string $conf, string $secrets, array $caCerts = []): void
    {
        if (file_put_contents($this->tempConf, $conf) === false) {
            throw new \RuntimeException(
                "Não foi possível escrever {$this->tempConf} (diretório existe? www-data tem permissão de escrita?)"
            );
        }
        if (file_put_contents($this->tempSecrets, $secrets) === false) {
            throw new \RuntimeException(
                "Não foi possível escrever {$this->tempSecrets} (diretório existe? www-data tem permissão de escrita?)"
            );
        }

        if (!is_dir($this->tempCaDir) && !mkdir($this->tempCaDir, 0775, true) && !is_dir($this->tempCaDir)) {
            throw new \RuntimeException("Não foi possível criar {$this->tempCaDir}.");
        }
        foreach (glob($this->tempCaDir . '/*.pem') ?: [] as $antigo) {
            @unlink($antigo);
        }
        foreach ($caCerts as $nomeArquivo => $conteudo) {
            if (file_put_contents($this->tempCaDir . '/' . $nomeArquivo, $conteudo) === false) {
                throw new \RuntimeException("Não foi possível escrever o certificado CA \"{$nomeArquivo}\".");
            }
        }
    }
}
