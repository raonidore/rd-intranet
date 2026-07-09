<?php

namespace App\Services;

/**
 * Traduz regras de firewall (estruturadas, do banco, ou linhas cruas do
 * iptables-save) para frases em portugues. Nao interpreta 100% de tudo que
 * o iptables aceita -- cobre os casos comuns que o construtor desta tela
 * gera e os padroes mais frequentes de regras feitas na mao.
 */
class IptablesExplicadorService
{
    private const ACAO_LABEL = [
        'ACCEPT' => 'permite',
        'DROP' => 'descarta (silenciosamente)',
        'REJECT' => 'rejeita (avisa a origem)',
        'MASQUERADE' => 'mascara (NAT de saída)',
        'DNAT' => 'redireciona (NAT de destino)',
        'SNAT' => 'troca o IP de origem (NAT)',
        'LOG' => 'registra no log',
        'NONE' => 'apenas rastreia (sem decidir aceitar/bloquear)',
    ];

    private const CADEIA_LABEL = [
        'INPUT' => 'tráfego chegando para este servidor',
        'OUTPUT' => 'tráfego saindo deste servidor',
        'FORWARD' => 'tráfego atravessando (roteado por) este servidor',
        'PREROUTING' => 'tráfego assim que chega, antes de rotear',
        'POSTROUTING' => 'tráfego assim que sai, depois de rotear',
    ];

    public function explicarRegra(array $r): string
    {
        $partes = [];

        $acao = self::ACAO_LABEL[$r['acao']] ?? strtolower($r['acao']);
        $partes[] = ucfirst($acao);

        if ($r['protocolo'] !== 'all') {
            $partes[] = strtoupper($r['protocolo']);
        }

        if (!empty($r['porta_destino'])) {
            $partes[] = "na porta de destino {$r['porta_destino']}";
        }
        if (!empty($r['porta_origem'])) {
            $partes[] = "vindo da porta de origem {$r['porta_origem']}";
        }
        if (!empty($r['ip_origem'])) {
            $partes[] = "com origem em {$r['ip_origem']}";
        }
        if (!empty($r['ip_destino'])) {
            $partes[] = "com destino a {$r['ip_destino']}";
        }
        if (!empty($r['interface_entrada'])) {
            $partes[] = "entrando pela interface {$r['interface_entrada']}";
        }
        if (!empty($r['interface_saida'])) {
            $partes[] = "saindo pela interface {$r['interface_saida']}";
        }
        if (!empty($r['nat_destino'])) {
            $partes[] = "redirecionando para {$r['nat_destino']}";
        }
        if (!empty($r['extra'])) {
            $partes[] = "({$r['extra']})";
        }

        $contexto = self::CADEIA_LABEL[$r['cadeia']] ?? $r['cadeia'];

        return implode(' ', $partes) . " — cadeia {$r['cadeia']} ({$contexto}).";
    }

    /**
     * Explicacao best-effort de uma linha crua do iptables-save
     * (ex: "-A INPUT -p tcp -m tcp --dport 22 -j ACCEPT").
     */
    public function explicarLinha(string $linha): string
    {
        $linha = trim($linha);

        if (!preg_match('/-j\s+(\S+)/', $linha, $m)) {
            return 'Regra sem alvo (-j) reconhecível.';
        }
        $alvo = strtoupper($m[1]);
        $acao = self::ACAO_LABEL[$alvo] ?? $alvo;

        $partes = [ucfirst($acao)];

        if (preg_match('/-p\s+(\S+)/', $linha, $m)) {
            $partes[] = strtoupper($m[1]);
        }
        if (preg_match('/--dport\s+(\S+)/', $linha, $m)) {
            $partes[] = "porta destino {$m[1]}";
        }
        if (preg_match('/--sport\s+(\S+)/', $linha, $m)) {
            $partes[] = "porta origem {$m[1]}";
        }
        if (preg_match('/(?<!!)-s\s+(\S+)/', $linha, $m)) {
            $partes[] = "origem {$m[1]}";
        }
        if (preg_match('/(?<!!)-d\s+(\S+)/', $linha, $m)) {
            $partes[] = "destino {$m[1]}";
        }
        if (preg_match('/\s-i\s+(\S+)/', $linha, $m)) {
            $partes[] = "entrando por {$m[1]}";
        }
        if (preg_match('/\s-o\s+(\S+)/', $linha, $m)) {
            $partes[] = "saindo por {$m[1]}";
        }
        if (preg_match('/--to-destination\s+(\S+)/', $linha, $m)) {
            $partes[] = "para {$m[1]}";
        }
        if (preg_match('/--to-source\s+(\S+)/', $linha, $m)) {
            $partes[] = "com origem trocada para {$m[1]}";
        }
        if (str_contains($linha, 'conntrack') || str_contains($linha, 'state')) {
            if (preg_match('/--ctstate\s+(\S+)|--state\s+(\S+)/', $linha, $m)) {
                $partes[] = 'conexões ' . strtolower($m[1] ?? $m[2]);
            }
        }
        if (str_contains($linha, 'recent')) {
            $partes[] = '(proteção por taxa/repetição — anti força-bruta)';
        }
        if (str_contains($linha, 'limit')) {
            $partes[] = '(limitado por taxa)';
        }

        return implode(', ', array_filter($partes)) . '.';
    }
}
