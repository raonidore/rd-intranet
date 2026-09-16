using System.Net;
using Lextm.SharpSnmpLib;
using Lextm.SharpSnmpLib.Messaging;

namespace RdIntranetBridge;

/// <summary>
/// Porta em C# de App\Services\SnmpService.php -- mesmos OIDs (MIB-II /
/// Printer-MIB padrão, sem depender de MIB de fabricante), pra que um
/// ativo coletado através do Bridge caia exatamente nas mesmas chaves de
/// `ativos.detalhes` que uma coleta direta (feita quando o servidor
/// alcança a rede sem precisar de Bridge) já usa. Usa SharpSnmpLib (puro
/// gerenciado) em vez de invocar snmpget/snmpwalk como o lado PHP faz --
/// aqui precisa rodar igual no Windows e no Linux, e não dá pra contar
/// com o pacote `net-snmp` instalado nos dois.
/// </summary>
public class SnmpCollector
{
    private const string OidSysDescr = "1.3.6.1.2.1.1.1.0";
    private const string OidSysUpTime = "1.3.6.1.2.1.1.3.0";
    private const string OidPrinterPaginas = "1.3.6.1.2.1.43.10.2.1.4.1.1";
    private const string OidPrinterSuprimentoAtual = "1.3.6.1.2.1.43.11.1.1.9";
    private const string OidPrinterSuprimentoMaximo = "1.3.6.1.2.1.43.11.1.1.8";

    private const int TimeoutMs = 2000;

    public Dictionary<string, string> Coletar(string ip, string community, string tipoSlug)
    {
        var dados = new Dictionary<string, string>();

        var sysDescr = Get(ip, community, OidSysDescr);
        if (sysDescr != null) dados["snmp_sys_descr"] = sysDescr;

        var sysUpTime = Get(ip, community, OidSysUpTime);
        if (sysUpTime != null) dados["snmp_uptime"] = sysUpTime;

        if (tipoSlug == "impressora")
        {
            var paginas = WalkPrimeiroValor(ip, community, OidPrinterPaginas);
            if (paginas != null) dados["contador_paginas"] = paginas;

            var atual = WalkPrimeiroValor(ip, community, OidPrinterSuprimentoAtual);
            var maximo = WalkPrimeiroValor(ip, community, OidPrinterSuprimentoMaximo);

            if (atual != null && maximo != null
                && int.TryParse(atual, out var atualNum) && int.TryParse(maximo, out var maximoNum) && maximoNum > 0)
            {
                var percentual = (int)Math.Round(atualNum / (double)maximoNum * 100);
                dados["nivel_toner"] = $"{percentual}%";
            }
        }

        return dados;
    }

    private string? Get(string ip, string community, string oid)
    {
        try
        {
            var resultado = Messenger.Get(
                VersionCode.V2,
                new IPEndPoint(IPAddress.Parse(ip), 161),
                new OctetString(community),
                new List<Variable> { new Variable(new ObjectIdentifier(oid)) },
                TimeoutMs);

            return resultado.Count > 0 ? Limpar(resultado[0].Data.ToString()) : null;
        }
        catch
        {
            // timeout, host fora do ar, community errada, SNMP desligado
            // no equipamento -- tudo cai aqui igual ao "!$resultado['success']"
            // do lado PHP, sem distinguir motivo (o admin já vê "não
            // respondeu" na ficha do ativo de qualquer forma).
            return null;
        }
    }

    private string? WalkPrimeiroValor(string ip, string community, string oidBase)
    {
        try
        {
            var resultados = new List<Variable>();
            Messenger.Walk(
                VersionCode.V2,
                new IPEndPoint(IPAddress.Parse(ip), 161),
                new OctetString(community),
                new ObjectIdentifier(oidBase),
                resultados,
                TimeoutMs,
                WalkMode.WithinSubtree);

            return resultados.Count > 0 ? Limpar(resultados[0].Data.ToString()) : null;
        }
        catch
        {
            return null;
        }
    }

    private static string Limpar(string? valor) => (valor ?? "").Trim().Trim('"');
}
