<?php

namespace App\Services;

class LinuxService
{
    /**
     * Executa um comando Linux.
     */
    public function executar(string $comando): array
    {
        $saida = [];
        $retorno = 0;

        exec($comando . ' 2>&1', $saida, $retorno);

        return [
            'success' => $retorno === 0,
            'exitCode' => $retorno,
            'output' => implode("\n", $saida)
        ];
    }

    /**
     * Verifica se um usuário existe.
     */
    public function usuarioExiste(string $login): bool
    {
        exec(
            "id " . escapeshellarg($login) . " >/dev/null 2>&1",
            $o,
            $ret
        );

        return $ret === 0;
    }

    /**
     * Executa um script da RD Tecnologia.
     */
    public function executarScript(string $script, array $parametros = []): array
    {
        $cmd = "sudo " . escapeshellarg($script);

        foreach ($parametros as $valor) {
            $cmd .= " " . escapeshellarg($valor);
        }

        return $this->executar($cmd);
    }

    /**
     * Lista grupos Linux.
     */
    public function grupos(): array
    {
        exec(
            "cut -d: -f1 /etc/group",
            $grupos
        );

        sort($grupos);

        return $grupos;
    }

    /**
     * Verifica se um grupo Linux existe.
     */
    public function grupoExiste(string $grupo): bool
    {
        exec(
            "getent group " . escapeshellarg($grupo) . " >/dev/null 2>&1",
            $o,
            $ret
        );

        return $ret === 0;
    }
}
