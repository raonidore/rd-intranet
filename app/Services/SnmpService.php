<?php

namespace App\Services;

/**
 * Coleta somente-leitura via SNMP v2c usando os binarios snmpget/snmpwalk
 * (pacote `snmp`) -- mesmos OIDs padrao MIB-II/Printer-MIB usados pela
 * maioria dos switches e impressoras de rede, sem precisar de MIB de
 * fabricante. Roda sem sudo (leitura SNMP nao exige root), via
 * LinuxService::executar() -- todo argumento vindo de fora (ip, community)
 * passa por escapeshellarg().
 */
class SnmpService
{
    private const OID_SYS_DESCR = '1.3.6.1.2.1.1.1.0';
    private const OID_SYS_UPTIME = '1.3.6.1.2.1.1.3.0';
    private const OID_PRINTER_PAGINAS = '1.3.6.1.2.1.43.10.2.1.4.1.1';
    private const OID_PRINTER_SUPRIMENTO_ATUAL = '1.3.6.1.2.1.43.11.1.1.9';
    private const OID_PRINTER_SUPRIMENTO_MAXIMO = '1.3.6.1.2.1.43.11.1.1.8';
    private const OID_PRINTER_MODELO = '1.3.6.1.2.1.25.3.2.1.3.1';
    private const OID_PRINTER_SERIAL = '1.3.6.1.2.1.43.5.1.1.17.1';

    /**
     * Brother (brInfoMaintenance): Hex-STRING em registros de 7 bytes --
     * código, 0x01, 0x04, valor de 4 bytes -- terminado em 0xFF. Vida
     * restante vem em centésimos de %. Necessário porque pelo Printer-MIB
     * padrão essas Brother só dizem "tem algum toner" (-3) sem percentual.
     * Códigos conferidos contra a página de manutenção de uma MFC-8480DN.
     */
    private const OID_BROTHER_MANUTENCAO = '1.3.6.1.4.1.2435.2.3.9.4.2.1.5.5.8.0';
    private const BROTHER_CODIGOS = [
        0x6F => 'nivel_toner',
        0x41 => 'vida_cilindro',
        0x6A => 'vida_fusor',
        0x6B => 'vida_laser',
    ];

    private LinuxService $linux;

    public function __construct()
    {
        $this->linux = new LinuxService();
    }

    /**
     * @return array<string, string> campos coletados, prontos pra fazer
     *   merge em cima de `ativos.detalhes` (mesmas chaves usadas pelos
     *   campos manuais em AtivoService::CAMPOS_DETALHES).
     */
    public function coletar(string $ip, string $community, string $tipo): array
    {
        $dados = [];

        $sysDescr = $this->get($ip, $community, self::OID_SYS_DESCR);
        if ($sysDescr !== null) {
            $dados['snmp_sys_descr'] = $sysDescr;
        }

        $sysUpTime = $this->get($ip, $community, self::OID_SYS_UPTIME);
        if ($sysUpTime !== null) {
            $dados['snmp_uptime'] = $sysUpTime;
        }

        if ($tipo === 'impressora') {
            $paginas = $this->walkPrimeiroValor($ip, $community, self::OID_PRINTER_PAGINAS);
            if ($paginas !== null) {
                $dados['contador_paginas'] = $paginas;
            }

            $atual = $this->walkPrimeiroValor($ip, $community, self::OID_PRINTER_SUPRIMENTO_ATUAL);
            $maximo = $this->walkPrimeiroValor($ip, $community, self::OID_PRINTER_SUPRIMENTO_MAXIMO);

            if ($atual !== null && $maximo !== null && (int)$maximo > 0) {
                $dados['nivel_toner'] = round(((int)$atual / (int)$maximo) * 100) . '%';
            }

            $modelo = $this->get($ip, $community, self::OID_PRINTER_MODELO);
            if ($modelo !== null && $modelo !== '') {
                $dados['snmp_modelo'] = $modelo;
            }

            $serial = $this->get($ip, $community, self::OID_PRINTER_SERIAL);
            if ($serial !== null && $serial !== '') {
                $dados['snmp_serial'] = $serial;
            }

            // Percentuais da Brother -- o de toner só entra se o padrão não deu um.
            foreach ($this->manutencaoBrother($ip, $community) as $campo => $percentual) {
                if (!isset($dados[$campo])) {
                    $dados[$campo] = $percentual;
                }
            }
        }

        return $dados;
    }

    /** @return array<string, string> campo => "94%" (vazio se não for Brother) */
    private function manutencaoBrother(string $ip, string $community): array
    {
        $comando = 'snmpget -v2c -t 2 -r 1 -O qvx -c '
            . escapeshellarg($community) . ' '
            . escapeshellarg($ip) . ' '
            . escapeshellarg(self::OID_BROTHER_MANUTENCAO);

        $resultado = $this->linux->executar($comando);
        $saida = $this->limpar($resultado['output'] ?? '');

        // Outro fabricante responde "No Such Object..." -- só aceita hex puro.
        if (!$resultado['success'] || !preg_match('/^[0-9A-Fa-f\s]+$/', $saida)) {
            return [];
        }

        $bytes = array_map('hexdec', preg_split('/\s+/', trim($saida)));
        $dados = [];

        for ($i = 0; $i + 6 < count($bytes) && $bytes[$i] !== 0xFF; $i += 7) {
            $campo = self::BROTHER_CODIGOS[$bytes[$i]] ?? null;
            if ($campo === null || $bytes[$i + 1] !== 0x01 || $bytes[$i + 2] !== 0x04) {
                continue;
            }

            $valor = ($bytes[$i + 3] << 24) | ($bytes[$i + 4] << 16) | ($bytes[$i + 5] << 8) | $bytes[$i + 6];
            $dados[$campo] = round($valor / 100) . '%';
        }

        return $dados;
    }

    /**
     * Só testa se o dispositivo responde SNMP com essa community -- usado
     * pelo IP Scanner (escaneio de portas por host) pra sinalizar quem já é
     * elegível pra coleta automática de Ativos antes mesmo de cadastrar.
     * Mesmo OID (sysDescr) e binário (snmpget) que coletar() já usa.
     *
     * @return array{disponivel:bool, sys_descr:?string}
     */
    public function disponivel(string $ip, string $community): array
    {
        $sysDescr = $this->get($ip, $community, self::OID_SYS_DESCR);

        return [
            'disponivel' => $sysDescr !== null,
            'sys_descr' => $sysDescr,
        ];
    }

    private function get(string $ip, string $community, string $oid): ?string
    {
        $comando = 'snmpget -v2c -t 2 -r 1 -O qv -c '
            . escapeshellarg($community) . ' '
            . escapeshellarg($ip) . ' '
            . escapeshellarg($oid);

        $resultado = $this->linux->executar($comando);

        if (!$resultado['success'] || trim($resultado['output']) === '') {
            return null;
        }

        return $this->limpar($resultado['output']);
    }

    private function walkPrimeiroValor(string $ip, string $community, string $oidBase): ?string
    {
        $comando = 'snmpwalk -v2c -t 2 -r 1 -O qv -c '
            . escapeshellarg($community) . ' '
            . escapeshellarg($ip) . ' '
            . escapeshellarg($oidBase);

        $resultado = $this->linux->executar($comando);

        if (!$resultado['success'] || trim($resultado['output']) === '') {
            return null;
        }

        $linhas = explode("\n", trim($resultado['output']));

        return $this->limpar($linhas[0]);
    }

    private function limpar(string $valor): string
    {
        return trim(trim($valor), '"');
    }
}
