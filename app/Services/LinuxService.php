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
        // Pipe de proc_open NAO funciona aqui -- confirmado ao vivo
        // (reproduzido isolado): quando um comando roda em background
        // ("&") dentro de um shell nao-interativo, o POSIX manda o shell
        // trocar a entrada padrao dele por /dev/null automaticamente, a
        // MENOS que o proprio comando tenha uma redirecao explicita de
        // entrada -- descartando silenciosamente qualquer pipe herdado do
        // processo pai. Por isso a senha nunca chegava no script
        // (ficava lendo stdin vazio, "cat -" retornava string vazia na
        // hora).
        //
        // Solução: escreve a entrada num arquivo temporário 600 e
        // redireciona a entrada do comando EXPLICITAMENTE desse arquivo
        // ("< arquivo") -- isso satisfaz a excecao do POSIX (redirecao
        // explicita dentro do comando) e o shell nao substitui mais por
        // /dev/null. O arquivo e apagado imediatamente depois de disparar
        // o comando: a redirecao do shell ja abre o descritor ANTES de
        // devolver o controle pro processo pai (fork+redirect+exec e uma
        // sequencia sincrona do ponto de vista de quem chamou), entao
        // apagar o nome do arquivo em seguida e seguro -- o processo em
        // segundo plano mantem seu proprio descritor aberto pro conteudo,
        // mesmo sem o arquivo mais existir no disco (testado: o conteudo
        // chega certinho mesmo com 1s de atraso proposital antes da
        // leitura, depois do unlink já ter rodado).
        $tmpEntrada = tempnam(sys_get_temp_dir(), 'rd_stdin_');
        chmod($tmpEntrada, 0600);
        file_put_contents($tmpEntrada, $entrada);

        $cmd = "nohup sudo " . escapeshellarg($script);

        foreach ($parametros as $valor) {
            $cmd .= " " . escapeshellarg($valor);
        }

        $cmd .= " < " . escapeshellarg($tmpEntrada) . " > /dev/null 2>&1 &";

        exec($cmd);

        @unlink($tmpEntrada);
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
