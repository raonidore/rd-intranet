<?php

namespace App\Services;

/**
 * Regras de comportamento do anti-ransomware do agente (limiares que antes
 * eram fixos no código do agente). Fonte: config/seguranca-regras.json no
 * repositório git. "rd agente:sincronizar" (cron, 30 min) lê a versão de
 * origin/main sem mexer na working tree e guarda em storage/seguranca/
 * regras.json; o módulo de segurança manda "regras_versao" no heartbeat e
 * o agente baixa as regras quando a versão muda -- ajuste sem recompilar.
 *
 * Todo valor passa por validação com faixa: JSON com erro de digitação nunca
 * deixa o detector cego (fora da faixa ou ausente = valor padrão).
 */
class SegurancaRegrasService
{
    /** Mesmos valores fixos do agente 1.0.50 -- usados quando não há arquivo nenhum. */
    public const PADRAO = [
        'shadow' => [
            'fracao_minima_restante' => 0.5,
            'temporaria_horas' => 2,
            'expirada_dias' => 55,
            'sem_explicacao_minimo' => 2,
        ],
        'arquivos' => [
            'proporcao_exclusao' => 0.8,
            'extensoes_comuns_extra' => [],
            'pastas_ignoradas_extra' => [],
        ],
    ];

    /** [mínimo, máximo] aceitos pra cada número. */
    private const FAIXAS = [
        'shadow.fracao_minima_restante' => [0.1, 1.0],
        'shadow.temporaria_horas' => [0, 24],
        'shadow.expirada_dias' => [7, 365],
        'shadow.sem_explicacao_minimo' => [1, 20],
        'arquivos.proporcao_exclusao' => [0.5, 1.0],
    ];

    private string $arquivoCache;
    private string $arquivoRepositorio;

    public function __construct()
    {
        $this->arquivoCache = __DIR__ . '/../../storage/seguranca/regras.json';
        $this->arquivoRepositorio = __DIR__ . '/../../config/seguranca-regras.json';
    }

    /** Regras em vigor: a sincronizada do repositório; senão a do código instalado; senão o padrão. */
    public function dados(): array
    {
        foreach ([$this->arquivoCache, $this->arquivoRepositorio] as $arquivo) {
            if (is_file($arquivo)) {
                $bruto = json_decode((string)file_get_contents($arquivo), true);
                if (is_array($bruto)) {
                    return $this->normalizar($bruto);
                }
            }
        }

        return ['versao' => 'padrao'] + self::PADRAO;
    }

    public function versao(): string
    {
        return (string)$this->dados()['versao'];
    }

    /**
     * Guarda as regras lidas do repositório (JSON cru). Devolve se mudou de versão.
     *
     * @return array{success: bool, mudou?: bool, versao?: string, message?: string}
     */
    public function guardarDoRepositorio(string $json): array
    {
        $bruto = json_decode($json, true);
        if (!is_array($bruto) || empty($bruto['versao'])) {
            return ['success' => false, 'message' => 'config/seguranca-regras.json inválido ou sem "versao".'];
        }

        $regras = $this->normalizar($bruto);
        $anterior = $this->versao();
        if ($regras['versao'] === $anterior && is_file($this->arquivoCache)) {
            return ['success' => true, 'mudou' => false, 'versao' => $anterior];
        }

        $pasta = dirname($this->arquivoCache);
        if (!is_dir($pasta)) {
            mkdir($pasta, 0775, true);
        }
        if (file_put_contents($this->arquivoCache, json_encode($regras, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
            return ['success' => false, 'message' => 'Não consegui gravar ' . $this->arquivoCache . ' (permissão de escrita?).'];
        }

        AuditService::registrar('Segurança', 'Regras do anti-ransomware', "Regras de comportamento atualizadas do repositório: {$anterior} -> {$regras['versao']}.");

        return ['success' => true, 'mudou' => true, 'versao' => $regras['versao']];
    }

    /** Aplica faixas e padrões -- nunca devolve valor fora do aceito. */
    private function normalizar(array $bruto): array
    {
        $regras = ['versao' => mb_substr(preg_replace('/[^0-9A-Za-z._-]/', '', (string)($bruto['versao'] ?? 'padrao')) ?: 'padrao', 0, 40)] + self::PADRAO;

        foreach (self::FAIXAS as $caminho => [$min, $max]) {
            [$grupo, $chave] = explode('.', $caminho);
            $valor = $bruto[$grupo][$chave] ?? null;
            if (is_numeric($valor) && $valor >= $min && $valor <= $max) {
                $regras[$grupo][$chave] = is_float(self::PADRAO[$grupo][$chave]) ? (float)$valor : (int)$valor;
            }
        }

        $regras['arquivos']['extensoes_comuns_extra'] = $this->lista($bruto['arquivos']['extensoes_comuns_extra'] ?? [], '/^\.?[a-z0-9]{1,15}$/i', true);
        $regras['arquivos']['pastas_ignoradas_extra'] = $this->lista($bruto['arquivos']['pastas_ignoradas_extra'] ?? [], '/^[^<>"|?*]{3,200}$/', false);

        return $regras;
    }

    private function lista(mixed $valores, string $padrao, bool $extensao): array
    {
        if (!is_array($valores)) {
            return [];
        }

        $saida = [];
        foreach (array_slice($valores, 0, 200) as $valor) {
            $texto = trim((string)$valor);
            if ($texto === '' || !preg_match($padrao, $texto)) {
                continue;
            }
            $saida[] = $extensao ? '.' . ltrim(strtolower($texto), '.') : $texto;
        }

        return array_values(array_unique($saida));
    }
}
