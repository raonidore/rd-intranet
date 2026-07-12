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
     * Executa um comando passando dados pelo stdin, sem tocar em disco --
     * usado quando o conteudo em si e sensivel (ex: gerar QR code de uma
     * chave privada de VPN) e nao deve nem passar por um arquivo
     * temporario nem virar argumento de linha de comando (visivel via
     * "ps aux" enquanto o processo roda).
     */
    public function executarComEntrada(string $comando, string $entrada): array
    {
        $descritores = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $processo = proc_open($comando, $descritores, $pipes);
        if (!is_resource($processo)) {
            return ['success' => false, 'exitCode' => -1, 'output' => 'Falha ao iniciar processo.'];
        }

        fwrite($pipes[0], $entrada);
        fclose($pipes[0]);

        $saida = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $retorno = proc_close($processo);

        return [
            'success' => $retorno === 0,
            'exitCode' => $retorno,
            'output' => $saida,
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
