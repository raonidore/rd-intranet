<?php

namespace App\Services;

/**
 * Auditoria de Arquivos (Samba > Auditoria) -- registra quem
 * renomeou/moveu, excluiu ou gravou conteúdo em arquivos dos
 * compartilhamentos, via módulo VFS full_audit (ver
 * samba_auditoria_web.sh pro porquê de cada escolha de configuração --
 * validado ao vivo contra a versão real do Samba instalada, não
 * copiado de tutorial genérico).
 */
class SambaAuditoriaService
{
    private LinuxService $linux;

    /** Recycle bin (sempre ativa) intercepta delete e vira um renameat pra dentro dessa pasta -- ver comentário em samba_auditoria_web.sh. */
    private const PASTA_LIXEIRA = '/.recycle/';

    public function __construct()
    {
        $this->linux = new LinuxService();
    }

    public function ativa(): bool
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/samba_auditoria_web.sh', ['status']);

        return trim($resultado['output']) === '1';
    }

    public function ativar(): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/samba_auditoria_web.sh', ['ativar']);
        $dados = json_decode(trim($resultado['output']), true);

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }

    public function desativar(): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/samba_auditoria_web.sh', ['desativar']);
        $dados = json_decode(trim($resultado['output']), true);

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }

    /**
     * Lê e interpreta as últimas entradas do log (janela limitada às
     * últimas 5000 linhas -- histórico completo fica pro próprio
     * /var/log/samba/audit.log, rotacionado por setup_samba_auditoria.sh,
     * não pensado pra consulta ilimitada por aqui).
     *
     * @param array{usuario?: string, compartilhamento?: string, acao?: string, busca?: string} $filtros
     */
    public function listar(array $filtros = [], int $limite = 300): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/samba_auditoria_logs_web.sh');
        $linhas = $this->parsear($resultado['output'] ?? '');
        $linhas = $this->colapsarGravacoes($linhas);

        $usuario = trim($filtros['usuario'] ?? '');
        $compartilhamento = trim($filtros['compartilhamento'] ?? '');
        $acao = trim($filtros['acao'] ?? '');
        $busca = trim($filtros['busca'] ?? '');

        if ($usuario !== '' || $compartilhamento !== '' || $acao !== '' || $busca !== '') {
            $linhas = array_values(array_filter($linhas, function (array $l) use ($usuario, $compartilhamento, $acao, $busca) {
                if ($usuario !== '' && stripos($l['usuario'], $usuario) === false) return false;
                if ($compartilhamento !== '' && strcasecmp($l['compartilhamento'], $compartilhamento) !== 0) return false;
                if ($acao !== '' && $l['acao'] !== $acao) return false;
                if ($busca !== '' && stripos($l['arquivo'], $busca) === false && stripos($l['arquivo_destino'] ?? '', $busca) === false) return false;
                return true;
            }));
        }

        // Mais recente primeiro -- parsear() devolve na ordem do arquivo (cronológica).
        $linhas = array_reverse($linhas);

        return array_slice($linhas, 0, $limite);
    }

    public function compartilhamentosNoLog(): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/samba_auditoria_logs_web.sh');
        $linhas = $this->parsear($resultado['output'] ?? '');

        $nomes = array_unique(array_column($linhas, 'compartilhamento'));
        sort($nomes, SORT_FLAG_CASE | SORT_STRING);

        return $nomes;
    }

    /**
     * Cada linha do rsyslog vem como "<timestamp ISO> <host> smbd_audit:
     * <prefixo>|<operacao>|<resultado>|<caminho>[|<caminho2>]", onde
     * prefixo = "%u|%I|%m|%S" (usuario|ip|maquina|compartilhamento) --
     * ver full_audit:prefix em samba_auditoria_web.sh.
     */
    private function parsear(string $saida): array
    {
        $linhas = [];

        foreach (explode("\n", $saida) as $linhaBruta) {
            $pos = strpos($linhaBruta, 'smbd_audit: ');
            if ($pos === false) {
                continue;
            }

            $cabecalho = trim(substr($linhaBruta, 0, $pos));
            $payload = substr($linhaBruta, $pos + strlen('smbd_audit: '));
            $partes = explode('|', trim($payload));

            if (count($partes) < 6) {
                continue; // linha truncada/corrompida -- ignora em vez de estourar índice
            }

            [$usuario, $ip, $maquina, $compartilhamento, $operacao, $resultado] = array_slice($partes, 0, 6);
            $caminho = $partes[6] ?? '';
            $caminhoDestino = $partes[7] ?? null;

            if ($resultado !== 'ok') {
                continue; // full_audit:failure = none -- nao deveria aparecer, mas ignora se aparecer
            }

            $dataHora = $this->extrairDataHora($cabecalho);

            $acao = match (true) {
                $operacao === 'renameat' && $caminhoDestino !== null && str_contains($caminhoDestino, self::PASTA_LIXEIRA) => 'excluido',
                $operacao === 'renameat' => 'renomeado',
                $operacao === 'unlinkat' => 'excluido',
                $operacao === 'pwrite_recv' => 'gravado',
                default => null,
            };

            if ($acao === null) {
                continue;
            }

            $linhas[] = [
                'data_hora' => $dataHora,
                'usuario' => $usuario,
                'ip' => $ip,
                'maquina' => $maquina,
                'compartilhamento' => $compartilhamento,
                'acao' => $acao,
                // "excluido" via renameat: o caminho que importa pro usuario e o ORIGINAL (onde o arquivo estava), nao o destino dentro da lixeira.
                'arquivo' => $caminho,
                'arquivo_destino' => ($acao === 'renomeado') ? $caminhoDestino : null,
            ];
        }

        return $linhas;
    }

    private function extrairDataHora(string $cabecalhoRsyslog): string
    {
        // rsyslog (template padrao) comeca a linha com um timestamp ISO-8601 -- pega so a parte "YYYY-MM-DDTHH:MM:SS".
        if (preg_match('/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})/', $cabecalhoRsyslog, $m)) {
            return str_replace('T', ' ', $m[1]);
        }

        return $cabecalhoRsyslog;
    }

    /**
     * Uma gravação de arquivo grande dispara VÁRIOS pwrite_recv
     * (um por bloco enviado pelo protocolo SMB2) -- sem isso, salvar um
     * único arquivo grande lotaria a tela com dezenas de linhas
     * idênticas. Colapsa gravações consecutivas do MESMO usuário no
     * MESMO arquivo dentro de uma janela de 5s numa linha só.
     */
    private function colapsarGravacoes(array $linhas): array
    {
        $resultado = [];

        foreach ($linhas as $linha) {
            $anterior = end($resultado);

            if (
                $anterior !== false
                && $linha['acao'] === 'gravado'
                && $anterior['acao'] === 'gravado'
                && $anterior['usuario'] === $linha['usuario']
                && $anterior['compartilhamento'] === $linha['compartilhamento']
                && $anterior['arquivo'] === $linha['arquivo']
                && (strtotime($linha['data_hora']) - strtotime($anterior['data_hora'])) <= 5
            ) {
                // já tem uma gravação recente pra esse mesmo arquivo -- so atualiza o horario (mostra o momento mais recente da sequencia), nao duplica linha
                $resultado[array_key_last($resultado)]['data_hora'] = $linha['data_hora'];
                continue;
            }

            $resultado[] = $linha;
        }

        return $resultado;
    }
}
