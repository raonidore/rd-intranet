<?php

namespace App\Services;

class SystemServiceManager
{
    private const CHAVE_CONFIG = 'servicos_gerenciados';
    private const PADRAO = ['smbd', 'apache2', 'mariadb', 'ssh'];

    private LinuxService $linux;

    public function __construct()
    {
        $this->linux = new LinuxService();
    }

    public function status(string $service): array
    {
        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/services_web.sh',
            [$service, 'status']
        );

        $dados = [
            'service' => $service,
            'unit' => '-',
            'status' => 'unknown',
            'enabled' => 'unknown',
            'uptime' => '-',
            'raw' => $resultado['output']
        ];

        foreach (explode("\n", $resultado['output']) as $linha) {
            if (!str_contains($linha, '=')) {
                continue;
            }

            [$chave, $valor] = explode('=', $linha, 2);

            match ($chave) {
                'SERVICE' => $dados['service'] = $valor,
                'UNIT' => $dados['unit'] = $valor,
                'STATUS' => $dados['status'] = $valor,
                'ENABLED' => $dados['enabled'] = $valor,
                'UPTIME' => $dados['uptime'] = $valor,
                default => null,
            };
        }

        return $dados;
    }

    public function listarServicos(): array
    {
        $unidades = $this->unidadesGerenciadas();
        $servicos = [];

        foreach ($unidades as $unidade) {
            $servicos[$unidade] = $this->nomeAmigavel($unidade);
        }

        return $servicos;
    }

    /**
     * Todas as unidades .service instaladas no sistema, para a tela de seleção
     * -- com nome, estado de inicializacao, status (online/offline/falha) e
     * "ativo desde" de cada uma, pra dar pra identificar visualmente qual
     * unidade esta com problema (ex: a que faz a "saude do servidor" cair)
     * sem precisar abrir terminal. Tudo isso vem de UM SO "systemctl show"
     * pedindo todas as unidades de uma vez -- antes cada linha do catalogo
     * disparava seu proprio "systemctl show" so pra pegar a descricao (N+1
     * shell-outs pra uma lista que pode passar de 200 unidades); em lote
     * fica mais rapido e ainda traz mais dado.
     */
    public function catalogoDisponivel(): array
    {
        $resultado = $this->linux->executar("systemctl list-unit-files --type=service --no-legend --plain 2>/dev/null");
        $gerenciadas = $this->unidadesGerenciadas();

        $unidades = [];
        foreach (explode("\n", trim($resultado['output'])) as $linha) {
            $linha = trim($linha);
            if ($linha === '') continue;

            $partes = preg_split('/\s+/', $linha);
            $unidade = preg_replace('/\.service$/', '', $partes[0] ?? '');
            if ($unidade === '' || str_contains($unidade, '@')) continue;

            $unidades[] = $unidade;
        }

        $detalhes = $this->detalhesEmLote($unidades);

        $catalogo = [];
        foreach ($unidades as $i => $unidade) {
            $d = $detalhes[$i] ?? [];

            $statusLabel = $this->traduzirStatus($d['activeState'] ?? '', $d['subState'] ?? '');

            $catalogo[] = [
                'unidade'          => $unidade,
                'unidadeCompleta'  => $unidade . '.service',
                'nome'             => ($d['descricao'] ?? '') ?: $unidade,
                'gerenciado'       => in_array($unidade, $gerenciadas, true),
                'inicializacao'    => $this->traduzirEstadoInicializacao($d['unitFileState'] ?? ''),
                'statusLabel'      => $statusLabel,
                'ativoDesde'       => $d['ativoDesde'] ?? '',
                'motivoFalha'      => $statusLabel['texto'] === 'Falha' ? $this->motivoFalha($d['result'] ?? '', $d['execMainStatus'] ?? '') : '',
            ];
        }

        usort($catalogo, fn($a, $b) => strcmp($a['unidade'], $b['unidade']));

        return $catalogo;
    }

    /**
     * Casa cada bloco de saida do "systemctl show" com a unidade pedida NA
     * MESMA POSICAO (nao pelo "Id=" retornado) -- unidades "alias" (ex:
     * mysql -> mariadb.service, syslog -> rsyslog.service, smb -> smbd.service)
     * fazem o systemd devolver o Id do unit REAL por tras do alias, que não
     * bate com o nome pedido; indexar pelo Id perdia essas unidades
     * silenciosamente. A ordem dos blocos de saida acompanha a ordem dos
     * argumentos (garantia do systemctl show), entao casar por posicao e'
     * o jeito confiavel.
     *
     * @param string[] $unidades nomes sem ".service"
     * @return array<int, array{descricao: string, unitFileState: string, activeState: string, subState: string, ativoDesde: string}>
     */
    private function detalhesEmLote(array $unidades): array
    {
        if (empty($unidades)) {
            return [];
        }

        $args = implode(' ', array_map(fn($u) => escapeshellarg($u . '.service'), $unidades));
        $resultado = $this->linux->executar(
            "systemctl show {$args} --property=Id,Description,UnitFileState,ActiveState,SubState,ActiveEnterTimestamp,Result,ExecMainStatus 2>/dev/null"
        );

        $blocos = preg_split('/\n\s*\n/', trim($resultado['output']));

        $detalhes = [];
        foreach ($blocos as $i => $bloco) {
            $atual = [];
            foreach (explode("\n", $bloco) as $linha) {
                $linha = rtrim($linha);
                if (!str_contains($linha, '=')) {
                    continue;
                }
                [$chave, $valor] = explode('=', $linha, 2);
                $atual[$chave] = $valor;
            }

            $detalhes[$i] = [
                'descricao'      => $atual['Description'] ?? '',
                'unitFileState'  => $atual['UnitFileState'] ?? '',
                'activeState'    => $atual['ActiveState'] ?? '',
                'subState'       => $atual['SubState'] ?? '',
                'ativoDesde'     => $atual['ActiveEnterTimestamp'] ?? '',
                'result'         => $atual['Result'] ?? '',
                'execMainStatus' => $atual['ExecMainStatus'] ?? '',
            ];
        }

        return $detalhes;
    }

    public function traduzirEstadoInicializacao(string $estado): string
    {
        return match ($estado) {
            'enabled', 'enabled-runtime' => 'Habilitado',
            'disabled' => 'Desabilitado',
            'static' => 'Estático',
            'masked', 'masked-runtime' => 'Mascarado',
            'alias' => 'Alias',
            'indirect' => 'Indireto',
            'generated' => 'Gerado',
            'transient' => 'Transitório',
            'bad' => 'Inválido',
            '' => '-',
            'unknown' => 'Desconhecido',
            default => $estado,
        };
    }

    /**
     * @return array{texto: string, cor: string}
     */
    private function traduzirStatus(string $activeState, string $subState): array
    {
        return match (true) {
            $activeState === 'active' => ['texto' => 'Online', 'cor' => 'success'],
            $activeState === 'failed' => ['texto' => 'Falha', 'cor' => 'danger'],
            $activeState === 'activating' => ['texto' => 'Iniciando', 'cor' => 'warning'],
            $activeState === 'deactivating' => ['texto' => 'Parando', 'cor' => 'warning'],
            $subState === 'dead', $activeState === 'inactive' => ['texto' => 'Offline', 'cor' => 'secondary'],
            default => ['texto' => 'Desconhecido', 'cor' => 'secondary'],
        };
    }

    /**
     * Traduz o "Result" que o systemd atribui a uma unidade em falha (por que
     * ela caiu, nao so QUE caiu) -- junto com o codigo de saida quando
     * disponivel, da pra entender o tipo de problema sem abrir terminal.
     * Vem de graca no mesmo "systemctl show" em lote de catalogoDisponivel(),
     * sem shell-out extra.
     */
    private function motivoFalha(string $result, string $execMainStatus): string
    {
        $texto = match ($result) {
            'exit-code' => 'Encerrou com código de saída diferente de zero',
            'signal' => 'Encerrado por sinal (processo morto/crash)',
            'core-dump' => 'Encerrado com core dump (crash)',
            'watchdog' => 'Watchdog expirou (processo travado, sem resposta)',
            'start-limit-hit' => 'Excedeu o limite de tentativas de reinício automático',
            'resources' => 'Falha ao alocar recursos do sistema pra iniciar',
            'timeout' => 'Tempo limite excedido ao iniciar ou parar',
            'protocol' => 'Erro de protocolo com o systemd (ex: sinal de "pronto" nunca chegou)',
            'oom-kill' => 'Morto pelo OOM killer do Linux (o servidor ficou sem memória disponível)',
            '', '-' => 'Motivo não informado pelo systemd',
            default => ucfirst(str_replace('-', ' ', $result)),
        };

        // O numero so' faz sentido como "codigo de saida" pra exit-code --
        // pros demais resultados (sinal, oom-kill, etc.) ExecMainStatus e'
        // o numero do SINAL, nao um codigo de erro, e so' confundiria.
        if ($result === 'exit-code' && $execMainStatus !== '' && $execMainStatus !== '0') {
            $texto .= " (código {$execMainStatus})";
        }

        return $texto;
    }

    /**
     * Ultimas linhas do log da unidade -- pra entender O QUE deu errado de
     * verdade (a mensagem real do processo), nao so a categoria generica do
     * "Result". Diferente de logs() (usado pela tela de Serviços, restrita
     * as unidades ja aprovadas na allowlist), esta e' usada pela tela de
     * Configurar Servicos -- que existe justamente pra olhar unidades ANTES
     * de decidir gerencia-las, entao nao faz sentido exigir allowlist aqui.
     * So' leitura (journalctl), sem allowlist mas com a MESMA validacao de
     * formato/existencia do services_web.sh -- nao da pra alterar nada do
     * sistema com isso.
     */
    public function diagnostico(string $unidade): array
    {
        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/servico_diagnostico_web.sh',
            [$unidade]
        );

        return ['success' => $resultado['success'], 'output' => $resultado['output']];
    }

    /**
     * Persiste a seleção de serviços gerenciados, validando contra as unidades reais do sistema.
     */
    public function salvarSelecao(array $unidadesEscolhidas): bool
    {
        $validas = array_column($this->catalogoDisponivel(), 'unidade');

        $selecao = array_values(array_intersect($unidadesEscolhidas, $validas));

        $efetiva = !empty($selecao) ? $selecao : self::PADRAO;

        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/salvar_permitidos_web.sh',
            $efetiva
        );

        if (!$resultado['success']) {
            return false;
        }

        ConfigService::set(self::CHAVE_CONFIG, json_encode($selecao));

        return true;
    }

    private function unidadesGerenciadas(): array
    {
        $bruto = ConfigService::get(self::CHAVE_CONFIG);

        if ($bruto === null || $bruto === '') {
            return self::PADRAO;
        }

        $decodificado = json_decode($bruto, true);

        return is_array($decodificado) && !empty($decodificado) ? $decodificado : self::PADRAO;
    }

    private function nomeAmigavel(string $unidade): string
    {
        $resultado = $this->linux->executar(
            "systemctl show " . escapeshellarg($unidade) . ".service --property=Description --value 2>/dev/null"
        );

        $descricao = trim($resultado['output']);

        return $descricao !== '' ? $descricao : $unidade;
    }

    public function reiniciar(string $service): void
    {
        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/services_web.sh',
            [$service, 'restart']
        );

        if ($resultado['success']) {
            AuditService::registrar('Serviços', 'Reiniciar', "Serviço {$service} reiniciado.");
            NotificationService::success("Serviço {$service} reiniciado com sucesso.", $resultado['output']);
        } else {
            NotificationService::error("Erro ao reiniciar {$service}.", $resultado['output']);
        }
    }

    public function recarregar(string $service): void
    {
        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/services_web.sh',
            [$service, 'reload']
        );

        if ($resultado['success']) {
            AuditService::registrar('Serviços', 'Recarregar', "Serviço {$service} recarregado.");
            NotificationService::success("Serviço {$service} recarregado com sucesso.", $resultado['output']);
        } else {
            NotificationService::error("Erro ao recarregar {$service}.", $resultado['output']);
        }
    }

    public function logs(string $service): array
    {
        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/services_web.sh',
            [$service, 'logs']
        );

        return [
            'success' => $resultado['success'],
            'output' => $resultado['output']
        ];
    }
}
