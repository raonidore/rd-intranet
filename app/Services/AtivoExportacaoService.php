<?php

namespace App\Services;

/**
 * Exportação da lista de Ativos (Ativos > Lista > Exportar) para Excel e
 * PDF. Usa os mesmos filtros/ordenação da tela e as colunas que o usuário
 * deixou visíveis. O Excel leva também o inventário (marca, modelo, série,
 * hardware...), que não cabe na largura de uma folha de PDF.
 */
class AtivoExportacaoService
{
    /** Só no Excel: campos do cadastro e do inventário que não são colunas da tela. */
    private const EXTRAS_EXCEL = [
        'marca' => 'Marca',
        'modelo' => 'Modelo',
        'numero_serie' => 'Nº de série',
        'processador' => 'Processador',
        'memoria_ram' => 'Memória RAM',
        'armazenamento' => 'Armazenamento',
        'usuario_logado' => 'Usuário logado',
        'ultimo_contato' => 'Último contato',
        'observacoes' => 'Observações',
    ];

    /** @return string[] chaves válidas de AtivoService::COLUNAS_LISTA, na ordem da tela; vazio = colunas padrão */
    public function colunasPedidas(string $csv): array
    {
        $pedidas = array_filter(array_map('trim', explode(',', $csv)));
        $validas = array_values(array_filter(array_keys(AtivoService::COLUNAS_LISTA), fn ($c) => in_array($c, $pedidas, true)));

        return $validas ?: array_keys(array_filter(AtivoService::COLUNAS_LISTA, fn ($info) => $info['padrao']));
    }

    /** @return array{cabecalho: string[], linhas: array<int, array<int, string>>} */
    public function tabela(array $ativos, array $colunas, bool $comExtras): array
    {
        $cabecalho = array_map(fn ($c) => AtivoService::COLUNAS_LISTA[$c]['label'], $colunas);
        if ($comExtras) {
            $cabecalho = array_merge($cabecalho, array_values(self::EXTRAS_EXCEL));
        }

        $linhas = [];
        foreach ($ativos as $a) {
            $detalhes = json_decode($a['detalhes'] ?? '', true) ?: [];
            $linha = array_map(fn ($c) => $this->valor($c, $a, $detalhes), $colunas);
            if ($comExtras) {
                foreach (array_keys(self::EXTRAS_EXCEL) as $extra) {
                    $linha[] = $this->valorExtra($extra, $a, $detalhes);
                }
            }
            $linhas[] = $linha;
        }

        return ['cabecalho' => $cabecalho, 'linhas' => $linhas];
    }

    /** "Tipo: Computador · Status: Ativo · Busca: "sala"" -- vai no subtítulo do PDF. */
    public function descreverFiltros(array $filtros, array $tipos, array $unidades): string
    {
        $partes = [];
        if ($filtros['tipo_id'] !== '') {
            $nome = array_column($tipos, 'nome', 'id')[(int)$filtros['tipo_id']] ?? null;
            if ($nome) {
                $partes[] = 'Tipo: ' . $nome;
            }
        }
        if ($filtros['unidade_id'] !== '') {
            $nome = array_column($unidades, 'nome', 'id')[(int)$filtros['unidade_id']] ?? null;
            if ($nome) {
                $partes[] = 'Unidade: ' . $nome;
            }
        }
        if ($filtros['status'] !== '' && isset(AtivoService::STATUS[$filtros['status']])) {
            $partes[] = 'Status: ' . AtivoService::STATUS[$filtros['status']];
        }
        if ($filtros['busca'] !== '') {
            $partes[] = 'Busca: "' . $filtros['busca'] . '"';
        }

        return $partes ? implode(' · ', $partes) : 'Todos os ativos';
    }

    private function valor(string $coluna, array $a, array $detalhes): string
    {
        return match ($coluna) {
            'codigo' => (string)$a['codigo_patrimonio'],
            'nome' => (string)$a['nome'],
            'apelido' => (string)($a['apelido'] ?? ''),
            'tipo' => (string)($a['tipo_nome'] ?? ''),
            'status' => AtivoService::STATUS[$a['status']] ?? (string)$a['status'],
            'condicao' => $this->condicao($a, $detalhes),
            'setor' => (string)($a['setor_nome'] ?? ''),
            'localizacao' => (string)($a['localizacao_nome'] ?? ''),
            'unidade' => (string)($a['unidade_nome'] ?? ''),
            'responsavel' => (string)($a['responsavel'] ?? ''),
            'versao_agente' => match (true) {
                empty($a['agente_versao']) => '',
                $a['agente_versao'] === 'ps1' => 'script (.ps1)',
                default => 'v' . $a['agente_versao'],
            },
            'ip' => (string)($a['ip'] ?? ''),
            'so' => (string)($detalhes['sistema_operacional'] ?? ''),
            default => '',
        };
    }

    private ?array $varreduras = null;

    /** Mesma regra da coluna "Condição" da tela (AtivoService::condicao). */
    private function condicao(array $a, array $detalhes): string
    {
        $this->varreduras ??= (new AtivoService())->varredurasRecentes();
        $c = AtivoService::condicao($a, $detalhes, $this->varreduras);

        return $c === null ? '' : ($c['ligado'] ? 'Ligado' : 'Desligado');
    }

    private function valorExtra(string $campo, array $a, array $detalhes): string
    {
        if (in_array($campo, ['marca', 'modelo', 'numero_serie', 'observacoes'], true)) {
            return trim((string)($a[$campo] ?? ''));
        }
        if ($campo === 'ultimo_contato') {
            $quando = $a['ultimo_heartbeat'] ?? $a['ultimo_checkin'] ?? null;
            return $quando ? date('d/m/Y H:i', strtotime($quando)) : '';
        }
        $valor = $detalhes[$campo] ?? '';

        return is_scalar($valor) ? (string)$valor : '';
    }
}
