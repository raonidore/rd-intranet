<?php

namespace App\Services;

/**
 * Conversores serial/Ethernet da Jinan USR IoT (ex.: USR-TCP232-302) --
 * só têm interface web (HTTP Basic, servidor lwIP): sem SNMP, Telnet ou
 * SSH. Esta classe só faz GET em duas páginas de leitura:
 *
 * - status.shtml: o firmware preenche os valores logo depois de
 *   comentários `<!--#chave-->` (dev_name, firm_ver, sn, mac, ip, ip01..05
 *   = "ip_conectado/ N byte / N byte", tx_rx).
 * - port.shtml: a configuração da serial vem em variáveis JS
 *   (`var _br = 9600;`, `var rip = '192.168.0.249';`...).
 *
 * Nada aqui grava configuração no equipamento.
 */
class UsrIotService
{
    private const TIMEOUT_SEGUNDOS = 6;

    /** Valores de `tnm` (modo de trabalho) -- mesmos do <select name="tnm"> de port.shtml. */
    private const MODOS = [
        0 => 'Cliente TCP',
        1 => 'Servidor TCP',
        2 => 'Cliente UDP',
        3 => 'Servidor UDP',
        4 => 'Cliente HTTP',
        5 => 'MQTT',
    ];

    private const PARIDADES = [0 => 'N', 1 => 'O', 2 => 'E', 3 => 'M', 4 => 'S'];

    /**
     * Sem login: só diz se o IP "parece" um USR IoT (HTTP Basic + servidor
     * lwIP) -- usado pra sugerir cadastrar a credencial.
     */
    public function pareceUsrIot(string $ip): bool
    {
        $resposta = $this->get($ip, '/', null, null);

        return $resposta['codigo'] === 401 && stripos($resposta['cabecalhos'], 'lwIP') !== false;
    }

    /**
     * @return array{success:bool, message:string, dados?:array}
     */
    public function coletar(string $ip, string $usuario, string $senha): array
    {
        $status = $this->get($ip, '/status.shtml', $usuario, $senha);

        if ($status['codigo'] === 401) {
            return ['success' => false, 'message' => 'O equipamento recusou o usuário/senha da interface web.'];
        }

        if ($status['codigo'] !== 200 || !str_contains($status['corpo'], '<!--#dev_name-->')) {
            return ['success' => false, 'message' => 'Não respondeu como um conversor USR IoT (página de status não encontrada).'];
        }

        $valores = [];
        if (preg_match_all('/<!--#(\w+)-->\s*([^<]*)/', $status['corpo'], $m, PREG_SET_ORDER)) {
            foreach ($m as $item) {
                $valores[$item[1]] = trim(html_entity_decode($item[2]));
            }
        }

        // ip01..ip05: "192.168.0.249/ 2703 byte / 60 byte" -- 0.0.0.0 = slot livre.
        $conexoes = [];
        for ($n = 1; $n <= 5; $n++) {
            $texto = $valores[sprintf('ip%02d', $n)] ?? '';
            if (preg_match('/^(\d{1,3}(?:\.\d{1,3}){3})\s*\/\s*(\d+)\s*byte\s*\/\s*(\d+)\s*byte/i', $texto, $c) && $c[1] !== '0.0.0.0') {
                $conexoes[] = ['ip' => $c[1], 'tx' => (int)$c[2], 'rx' => (int)$c[3]];
            }
        }

        $total = ['tx' => 0, 'rx' => 0];
        if (preg_match('/(\d+)\s*byte\s*\/\s*(\d+)\s*byte/i', $valores['tx_rx'] ?? '', $t)) {
            $total = ['tx' => (int)$t[1], 'rx' => (int)$t[2]];
        }

        $porta = $this->lerPorta($ip, $usuario, $senha);

        return [
            'success' => true,
            'message' => 'Dados lidos da interface web.',
            'dados' => [
                'modelo' => $valores['dev_name'] ?? '',
                'firmware' => $valores['firm_ver'] ?? '',
                'serial' => $valores['sn'] ?? '',
                'mac' => $valores['mac'] ?? '',
                'conexoes' => $conexoes,
                'tx_total' => $total['tx'],
                'rx_total' => $total['rx'],
                'porta' => $porta,
            ],
        ];
    }

    /** Configuração da porta serial (port.shtml) -- vazio se não conseguir ler. */
    private function lerPorta(string $ip, string $usuario, string $senha): array
    {
        $resposta = $this->get($ip, '/port.shtml', $usuario, $senha);

        if ($resposta['codigo'] !== 200) {
            return [];
        }

        $vars = [];
        // Só valor literal numérico/IP -- as funções JS da página também têm
        // `var tnm = document.config.tnm.value;`, e vêm ANTES da linha que o
        // firmware preenche (`var tnm = 0;`).
        if (preg_match_all("/var\s+(_?\w+)\s*=\s*'?(\d[\d.]*)'?\s*;/", $resposta['corpo'], $m, PREG_SET_ORDER)) {
            foreach ($m as $item) {
                $vars[$item[1]] ??= $item[2];
            }
        }

        if (!isset($vars['_br'])) {
            return [];
        }

        $modo = isset($vars['tnm']) && is_numeric($vars['tnm']) ? (int)$vars['tnm'] : null;

        return [
            'baud' => (int)$vars['_br'],
            'formato' => ($vars['bc'] ?? '8') . (self::PARIDADES[(int)($vars['par'] ?? 0)] ?? '?') . ($vars['sb'] ?? '1'),
            'modo' => $modo !== null ? (self::MODOS[$modo] ?? "Modo {$modo}") : '',
            'cliente' => in_array($modo, [0, 2, 4], true),
            'destino_ip' => $vars['rip'] ?? '',
            'destino_porta' => isset($vars['_trp']) ? (int)$vars['_trp'] : null,
            'porta_local' => isset($vars['_tlp']) ? (int)$vars['_tlp'] : null,
        ];
    }

    /** @return array{codigo:int, cabecalhos:string, corpo:string} */
    private function get(string $ip, string $caminho, ?string $usuario, ?string $senha): array
    {
        $ch = curl_init('http://' . $ip . $caminho);
        $opcoes = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_SEGUNDOS,
            CURLOPT_TIMEOUT => self::TIMEOUT_SEGUNDOS,
        ];
        if ($usuario !== null) {
            $opcoes[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
            $opcoes[CURLOPT_USERPWD] = $usuario . ':' . $senha;
        }
        curl_setopt_array($ch, $opcoes);

        $resposta = curl_exec($ch);
        $codigo = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $tamanhoCabecalho = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($resposta === false) {
            return ['codigo' => 0, 'cabecalhos' => '', 'corpo' => ''];
        }

        // O firmware tem textos em chinês/GBK misturados -- só o que é ASCII interessa aqui.
        return [
            'codigo' => $codigo,
            'cabecalhos' => substr($resposta, 0, $tamanhoCabecalho),
            'corpo' => substr($resposta, $tamanhoCabecalho),
        ];
    }
}
