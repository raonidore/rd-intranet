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
     * Dispara um script da RD Tecnologia em segundo plano e retorna na
     * hora, sem esperar terminar -- usado por operações que podem demorar
     * minutos (ex: aplicar ACL recursiva num compartilhamento grande) e
     * que travariam a requisição HTTP (e no fim das contas a própria
     * conexão) se rodassem de forma síncrona. "nohup ... &" desacopla o
     * processo do ciclo de vida do request: mesmo depois do Apache/PHP
     * finalizar essa requisição, o script continua rodando. Quem chama é
     * responsável por acompanhar o progresso/resultado por algum outro
     * meio (ex: um arquivo de status que o próprio script escreve).
     */
    public function executarScriptEmSegundoPlano(string $script, array $parametros = []): void
    {
        $cmd = "nohup sudo " . escapeshellarg($script);

        foreach ($parametros as $valor) {
            $cmd .= " " . escapeshellarg($valor);
        }

        $cmd .= " > /dev/null 2>&1 &";

        exec($cmd);
    }

    /**
     * Combina executarScriptEmSegundoPlano() (não bloqueia a requisição) com
     * executarComEntrada() (segredo via stdin, nunca em disco/argv/"ps aux")
     * -- usado quando uma operação sensível E potencialmente demorada
     * precisa das duas coisas ao mesmo tempo (ex: gerar o backup de
     * configuração, que recebe uma senha de criptografia e pode levar
     * minutos fazendo mysqldump + tar de PKI). Nenhum método existente
     * cobria essa combinação: "nohup ... &" sozinho não tem como receber
     * stdin, e proc_open() de executarComEntrada() bloqueia até o processo
     * terminar.
     *
     * O pipe de stdin é escrito e fechado imediatamente (o script já
     * recebeu tudo que precisa); stdout/stderr vão direto para um arquivo
     * (nunca um pipe) para não travar o processo filho quando ele tentar
     * escrever depois que este método já retornou -- um pipe sem ninguém
     * lendo do outro lado enche o buffer do kernel e trava o filho. Sem
     * proc_close(), que bloquearia esperando o processo terminar.
     */
    public function executarScriptEmSegundoPlanoComEntrada(string $script, array $parametros, string $entrada): void
    {
        $cmd = "nohup sudo " . escapeshellarg($script);

        foreach ($parametros as $valor) {
            $cmd .= " " . escapeshellarg($valor);
        }

        // "nohup ... &" -- mesmo mecanismo de executarScriptEmSegundoPlano()
        // pra desacoplar o processo do ciclo de vida do request (sobrevive
        // ao fim do request mesmo que o processo intermediario do proc_open
        // seja encerrado). stdout/stderr redirecionados no proprio comando
        // (nao via descritor do proc_open) porque, uma vez em background
        // com "&", o filho real herda os fds do "sh -c" que o lancou -- os
        // descritores 1/2 do proc_open abaixo cobrem esse "sh -c" em si.
        $cmd .= " > /dev/null 2>&1 &";

        $descritores = [
            0 => ['pipe', 'r'],
            1 => ['file', '/dev/null', 'a'],
            2 => ['file', '/dev/null', 'a'],
        ];

        $processo = proc_open($cmd, $descritores, $pipes);
        if (!is_resource($processo)) {
            return;
        }

        fwrite($pipes[0], $entrada);
        fclose($pipes[0]);
        proc_close($processo);
        // proc_close() aqui so espera o "sh -c" IMEDIATO terminar (que so
        // dispara o "&" e retorna na hora) -- nao espera o script em si,
        // que ja foi desacoplado pelo nohup+&. Sem isso o pipe as vezes
        // fica com o descritor preso, mesmo com fclose() no lado de escrita.
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
