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
        // Aplicado no servidor ao entregar a lista pública de ransomware -- vale pra qualquer versão do agente.
        'assinaturas' => [
            'extensoes_ignoradas' => [],
        ],
        // Agente 1.0.56+: monitor de conexões/processos/inicialização (o intervalo
        // de verificação NÃO fica aqui -- é por servidor/máquina, na Central de Segurança).
        'rede' => [
            'varredura_ips_minimo' => 40,
            'varredura_lan_minimo' => 20,
            'janela_minutos' => 5,
            'retrato_minutos' => 5,
            'inicializacao_horas' => 6,
            'portas_sensiveis' => [21, 22, 23, 135, 139, 445, 1433, 1521, 3306, 3389, 5432, 5900, 5985, 5986,
                3380, 3381, 3382, 3383, 3384, 3385, 3386, 3387, 3388, 3390, 3391, 3392, 3393, 3394, 3395, 3396, 3397, 3398, 3399],
            'processos_ignorados' => [],
        ],
        // Agente 1.0.61+: coleta contínua de desempenho -- o que conta como PICO.
        'desempenho' => [
            'cpu_pico' => 90,
            'memoria_pico' => 90,
            'disco_pico' => 90,
            'rede_pico_percentual' => 80,
            'duracao_minima_segundos' => 60,
            'retrato_minutos' => 15,
        ],
    ];

    /** [mínimo, máximo] aceitos pra cada número. */
    private const FAIXAS = [
        'shadow.fracao_minima_restante' => [0.1, 1.0],
        'shadow.temporaria_horas' => [0, 24],
        'shadow.expirada_dias' => [7, 365],
        'shadow.sem_explicacao_minimo' => [1, 20],
        'arquivos.proporcao_exclusao' => [0.5, 1.0],
        'rede.varredura_ips_minimo' => [10, 1000],
        'rede.varredura_lan_minimo' => [5, 500],
        'rede.janela_minutos' => [1, 60],
        'rede.retrato_minutos' => [1, 60],
        'rede.inicializacao_horas' => [1, 48],
        'desempenho.cpu_pico' => [50, 100],
        'desempenho.memoria_pico' => [50, 100],
        'desempenho.disco_pico' => [50, 100],
        'desempenho.rede_pico_percentual' => [10, 100],
        'desempenho.duracao_minima_segundos' => [10, 3600],
        'desempenho.retrato_minutos' => [1, 120],
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
        $regras['assinaturas']['extensoes_ignoradas'] = $this->lista($bruto['assinaturas']['extensoes_ignoradas'] ?? [], '/^\.?[a-z0-9_\-$~!]{1,40}$/i', true);

        $portas = array_values(array_unique(array_filter(
            array_map('intval', is_array($bruto['rede']['portas_sensiveis'] ?? null) ? array_slice($bruto['rede']['portas_sensiveis'], 0, 200) : []),
            fn (int $p) => $p > 0 && $p < 65536
        )));
        $regras['rede']['portas_sensiveis'] = $portas ?: self::PADRAO['rede']['portas_sensiveis'];
        $regras['rede']['processos_ignorados'] = $this->lista($bruto['rede']['processos_ignorados'] ?? [], '/^[^<>"|?*]{3,200}$/', false);

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
