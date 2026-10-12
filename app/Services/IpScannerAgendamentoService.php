<?php

namespace App\Services;

/**
 * Varredura automática do IP Scanner. A tela só guarda a agenda (config
 * `ip_scanner_agendamento`, JSON); quem dispara é um job de cron que roda
 * a cada minuto (`rd rede:scanner-agendado`) e chama executarSeDevido().
 *
 * Por que cron a cada minuto e não uma expressão por horário: a agenda
 * aceita vários horários + dias da semana/do mês combinados, o que não
 * cabe numa expressão só; e checar "chegou a hora?" em PHP permite tolerar
 * um atraso (JANELA_MINUTOS) se o servidor estava ocupado/reiniciando.
 *
 * A varredura é a mesma do botão da tela (ip_scanner_web.sh, nmap -sn), só
 * que rodando até o fim aqui mesmo e gravando no histórico sozinha -- na
 * tela quem grava é o navegador ao ver "concluido", o que não existe aqui.
 */
class IpScannerAgendamentoService
{
    private const CHAVE = 'ip_scanner_agendamento';
    private const NOME_JOB_CRON = 'IP Scanner - varredura automática';
    private const JANELA_MINUTOS = 15;
    private const MAX_HORARIOS = 8;
    private const STATUS_DIR = '/var/www/rd.intranet/storage/ip_scanner_status';
    private const LOCK = '/tmp/rd_ip_scanner_agendado.lock';

    public const MODOS = ['diario', 'semanal', 'mensal', 'personalizado'];

    public function obter(): array
    {
        $salvo = json_decode((string)(ConfigService::get(self::CHAVE, '') ?: ''), true);

        return array_merge([
            'ativo' => false,
            'faixas' => [],
            'modo' => 'diario',
            'horarios' => ['02:00'],
            'dias_semana' => [1],      // 0 = domingo
            'dias_mes' => [1],
            'ativado_em' => null,
            'ultimo_disparo' => null,
            'ultimo_resultado' => null,
        ], is_array($salvo) ? $salvo : []);
    }

    private function gravar(array $agenda): void
    {
        ConfigService::set(self::CHAVE, json_encode($agenda, JSON_UNESCAPED_UNICODE));
    }

    /** @return array{success:bool, message:string, agenda?:array, proximas?:array} */
    public function salvar(array $dados): array
    {
        $atual = $this->obter();
        $ativo = !empty($dados['ativo']);

        $modo = in_array($dados['modo'] ?? '', self::MODOS, true) ? $dados['modo'] : 'diario';

        $faixas = array_values(array_unique(array_filter(array_map('trim', (array)($dados['faixas'] ?? [])))));
        if ($ativo && empty($faixas)) {
            return ['success' => false, 'message' => 'Informe pelo menos uma faixa de IP.'];
        }
        if (count($faixas) > 8) {
            return ['success' => false, 'message' => 'Máximo de 8 faixas.'];
        }
        $rede = new NetworkToolsService();
        foreach ($faixas as $faixa) {
            $validacao = $rede->validarFaixaScan($faixa);
            if (!$validacao['valido']) {
                return ['success' => false, 'message' => "{$faixa}: {$validacao['mensagem']}"];
            }
        }

        $horarios = [];
        foreach ((array)($dados['horarios'] ?? []) as $h) {
            if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', trim((string)$h))) {
                $horarios[] = trim((string)$h);
            }
        }
        $horarios = array_values(array_unique($horarios));
        sort($horarios);
        if (empty($horarios)) {
            return ['success' => false, 'message' => 'Escolha pelo menos um horário.'];
        }
        if (count($horarios) > self::MAX_HORARIOS) {
            return ['success' => false, 'message' => 'Máximo de ' . self::MAX_HORARIOS . ' horários.'];
        }

        $diasSemana = array_values(array_unique(array_filter(array_map('intval', (array)($dados['dias_semana'] ?? [])), fn ($d) => $d >= 0 && $d <= 6)));
        sort($diasSemana);
        $diasMes = array_values(array_unique(array_filter(array_map('intval', (array)($dados['dias_mes'] ?? [])), fn ($d) => $d >= 1 && $d <= 31)));
        sort($diasMes);

        if ($modo === 'semanal' && empty($diasSemana)) {
            return ['success' => false, 'message' => 'Escolha pelo menos um dia da semana.'];
        }
        if (in_array($modo, ['mensal', 'personalizado'], true) && empty($diasMes)) {
            return ['success' => false, 'message' => 'Escolha pelo menos um dia do mês.'];
        }
        if ($modo === 'mensal') {
            $diasMes = [$diasMes[0]];
        }

        $agenda = array_merge($atual, [
            'ativo' => $ativo,
            'faixas' => $faixas,
            'modo' => $modo,
            'horarios' => $horarios,
            'dias_semana' => $diasSemana ?: $atual['dias_semana'],
            'dias_mes' => $diasMes ?: $atual['dias_mes'],
            // Horário que já passou no momento de salvar não dispara na hora.
            'ativado_em' => date('Y-m-d H:i:s'),
        ]);

        $this->gravar($agenda);
        $cron = $this->sincronizarCron($ativo);

        AuditService::registrar('Rede', 'IP Scanner', $ativo
            ? 'Varredura automática ativada: ' . $this->descrever($agenda) . ' -- ' . implode(', ', $faixas) . '.'
            : 'Varredura automática desativada.');

        if (!$cron['success']) {
            return ['success' => false, 'message' => 'Agenda salva, mas falhou ao configurar o cron: ' . $cron['message']];
        }

        return [
            'success' => true,
            'message' => $ativo ? 'Varredura automática ativada.' : 'Varredura automática desativada.',
            'agenda' => $agenda,
            'proximas' => $ativo ? $this->proximasExecucoes(5) : [],
        ];
    }

    /** Job único, a cada minuto -- criado na primeira ativação, só liga/desliga depois. */
    private function sincronizarCron(bool $ativo): array
    {
        $cron = new CronService();

        foreach ($cron->listar() as $job) {
            if ($job['nome'] === self::NOME_JOB_CRON) {
                return (bool)$job['ativo'] === $ativo ? ['success' => true, 'message' => ''] : $cron->alternarAtivo((int)$job['id'], $ativo);
            }
        }

        if (!$ativo) {
            return ['success' => true, 'message' => ''];
        }

        return $cron->criar([
            'nome' => self::NOME_JOB_CRON,
            'descricao' => 'Confere a cada minuto se chegou a hora de uma varredura automática do IP Scanner (agenda em Infraestrutura > Rede > IP Scanner).',
            'expressao' => '* * * * *',
            'usuario_execucao' => 'www-data',
            'comando' => 'php /var/www/rd.intranet/rd rede:scanner-agendado',
            'ativo' => true,
        ]);
    }

    /** "Diário às 02:00", "Seg, Qua às 08:00 e 18:00"... */
    public function descrever(array $agenda): string
    {
        $horas = $this->juntar($agenda['horarios']);
        $semana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

        return match ($agenda['modo']) {
            'semanal' => 'Toda ' . $this->juntar(array_map(fn ($d) => $semana[$d], $agenda['dias_semana'])) . ' às ' . $horas,
            'mensal' => 'Todo dia ' . $agenda['dias_mes'][0] . ' do mês às ' . $horas,
            'personalizado' => 'Dias ' . $this->juntar($agenda['dias_mes']) . ' de cada mês às ' . $horas,
            default => 'Diário às ' . $horas,
        };
    }

    private function juntar(array $itens): string
    {
        $itens = array_values($itens);
        if (count($itens) <= 1) {
            return (string)($itens[0] ?? '');
        }

        return implode(', ', array_slice($itens, 0, -1)) . ' e ' . end($itens);
    }

    /**
     * Horários agendados de um dia (vazio se o dia não está na agenda).
     * Dia do mês que não existe no mês (ex.: 31 em abril) vira o último dia.
     *
     * @return list<\DateTimeImmutable>
     */
    private function horariosDoDia(array $agenda, \DateTimeImmutable $dia): array
    {
        $diaMes = (int)$dia->format('j');
        $ultimoDia = (int)$dia->format('t');

        $vale = match ($agenda['modo']) {
            'semanal' => in_array((int)$dia->format('w'), $agenda['dias_semana'], true),
            'mensal', 'personalizado' => (bool)array_filter($agenda['dias_mes'], fn ($d) => min((int)$d, $ultimoDia) === $diaMes),
            default => true,
        };

        if (!$vale) {
            return [];
        }

        return array_map(fn ($h) => $dia->setTime((int)substr($h, 0, 2), (int)substr($h, 3, 2)), $agenda['horarios']);
    }

    /** @return list<string> "Y-m-d H:i" */
    public function proximasExecucoes(int $quantidade = 5): array
    {
        $agenda = $this->obter();
        $agora = new \DateTimeImmutable();
        $proximas = [];

        for ($i = 0; $i < 400 && count($proximas) < $quantidade; $i++) {
            foreach ($this->horariosDoDia($agenda, $agora->modify("+{$i} day")->setTime(0, 0)) as $quando) {
                if ($quando > $agora && count($proximas) < $quantidade) {
                    $proximas[] = $quando->format('Y-m-d H:i');
                }
            }
        }

        return $proximas;
    }

    /** Chamado pelo cron a cada minuto -- devolve '' quando não havia nada a fazer (não enche o log). */
    public function executarSeDevido(): string
    {
        $agenda = $this->obter();

        if (!$agenda['ativo'] || empty($agenda['faixas'])) {
            return '';
        }

        $agora = new \DateTimeImmutable();
        $devido = null;
        foreach ($this->horariosDoDia($agenda, $agora->setTime(0, 0)) as $quando) {
            if ($quando <= $agora) {
                $devido = $quando;
            }
        }

        $limite = max($agenda['ultimo_disparo'] ?? '', $agenda['ativado_em'] ?? '');
        if ($devido === null
            || $devido->format('Y-m-d H:i:s') <= $limite
            || $agora->getTimestamp() - $devido->getTimestamp() > self::JANELA_MINUTOS * 60) {
            return '';
        }

        $lock = fopen(self::LOCK, 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            return 'Outra varredura automática ainda está rodando.';
        }

        // Marca antes de varrer -- o cron do minuto seguinte não dispara de novo.
        $agenda['ultimo_disparo'] = $devido->format('Y-m-d H:i:s');
        $this->gravar($agenda);

        $resultado = $this->varrer($agenda['faixas']);

        $agenda = $this->obter();
        $agenda['ultimo_resultado'] = ['em' => date('Y-m-d H:i:s')] + $resultado;
        $this->gravar($agenda);

        flock($lock, LOCK_UN);
        fclose($lock);

        return $resultado['mensagem'];
    }

    /** @return array{success:bool, mensagem:string, total?:int, novos?:int, sumiram?:int} */
    private function varrer(array $faixas): array
    {
        $execucaoId = bin2hex(random_bytes(8));
        $comando = 'sudo ' . escapeshellarg('/opt/rdtecnologia/scripts/ip_scanner_web.sh') . ' ' . escapeshellarg($execucaoId);
        foreach ($faixas as $faixa) {
            $comando .= ' ' . escapeshellarg($faixa);
        }

        exec($comando . ' > /dev/null 2>&1');

        $arquivo = self::STATUS_DIR . "/{$execucaoId}.json";
        $status = is_file($arquivo) ? json_decode((string)file_get_contents($arquivo), true) : null;
        @unlink($arquivo);

        if (($status['status'] ?? '') !== 'concluido') {
            $mensagem = 'Varredura automática falhou: ' . ($status['mensagem'] ?? 'o script não terminou.');
            AuditService::registrar('Rede', 'IP Scanner', $mensagem);

            return ['success' => false, 'mensagem' => $mensagem];
        }

        $hosts = $status['resultados'] ?? [];
        $cidr = implode(',', $faixas);
        $comparacao = (new NetworkToolsService())->registrarResultadoEComparar($cidr, $hosts, null);

        $mensagem = 'Varredura automática de ' . implode(', ', $faixas) . ': ' . count($hosts) . ' dispositivo(s)'
            . (!$comparacao['primeira_execucao'] ? ', ' . count($comparacao['novos']) . ' novo(s), ' . count($comparacao['sumiram']) . ' sumiram' : '') . '.';
        AuditService::registrar('Rede', 'IP Scanner', $mensagem);

        // A varredura acabou de encher a ARP do servidor e o histórico -- é a
        // hora em que a coleta dos switches mais acerta "quem está em cada porta".
        if ((new OmadaService())->configurado() && (new OmadaService())->modoSsh()) {
            try {
                (new AtivoService())->coletarOmadaTodos();
            } catch (\Throwable $e) {
                error_log('IP Scanner agendado: falha ao atualizar switches -- ' . $e->getMessage());
            }
        }

        return [
            'success' => true,
            'mensagem' => $mensagem,
            'total' => count($hosts),
            'novos' => count($comparacao['novos']),
            'sumiram' => count($comparacao['sumiram']),
            'execucao_id' => $comparacao['execucao_id'],
        ];
    }
}
