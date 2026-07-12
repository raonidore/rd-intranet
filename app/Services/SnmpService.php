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
        }

        return $dados;
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
