using System.Collections.Generic;
using System.Diagnostics;
using System.IO;
using System.Linq;

namespace RdIntranetAgente;

/// <summary>
/// Coleta sob demanda pro explorador de arquivos e gerenciador de
/// processos da ficha do ativo -- diferente da coleta periódica
/// (CollectorService), que roda sozinha a cada N minutos, isto só roda
/// quando o admin pede pelo portal (ver SolicitacaoItem/heartbeat).
/// Melhor esforço item a item: uma pasta/processo que dá erro de acesso
/// não derruba o resto da listagem, só fica de fora.
/// </summary>
public static class ExploradorService
{
    public static List<Dictionary<string, object?>> ListarArquivos(string caminho)
    {
        var itens = new List<Dictionary<string, object?>>();

        foreach (var pasta in Directory.EnumerateDirectories(caminho))
        {
            try
            {
                var info = new DirectoryInfo(pasta);
                itens.Add(new Dictionary<string, object?>
                {
                    ["nome"] = info.Name,
                    ["tipo"] = "pasta",
                    ["tamanho"] = null,
                    ["modificado_em"] = info.LastWriteTime.ToString("yyyy-MM-dd HH:mm:ss")
                });
            }
            catch
            {
                // sem permissao de ler atributos dessa pasta especifica -- pula, sem derrubar o resto
            }
        }

        foreach (var arquivo in Directory.EnumerateFiles(caminho))
        {
            try
            {
                var info = new FileInfo(arquivo);
                itens.Add(new Dictionary<string, object?>
                {
                    ["nome"] = info.Name,
                    ["tipo"] = "arquivo",
                    ["tamanho"] = info.Length,
                    ["modificado_em"] = info.LastWriteTime.ToString("yyyy-MM-dd HH:mm:ss")
                });
            }
            catch
            {
                // idem, por arquivo
            }
        }

        return itens
            .OrderBy(i => (string)i["tipo"]! == "arquivo" ? 1 : 0)
            .ThenBy(i => (string)i["nome"]!, System.StringComparer.OrdinalIgnoreCase)
            .ToList();
    }

    public static List<Dictionary<string, object?>> ListarProcessos()
    {
        var itens = new List<Dictionary<string, object?>>();

        foreach (var processo in Process.GetProcesses())
        {
            try
            {
                string? janela = null;
                try { janela = string.IsNullOrWhiteSpace(processo.MainWindowTitle) ? null : processo.MainWindowTitle; } catch { /* alguns processos nao expoem isso */ }

                string? iniciadoEm = null;
                try { iniciadoEm = processo.StartTime.ToString("yyyy-MM-dd HH:mm:ss"); } catch { /* processos do sistema costumam negar acesso a isso */ }

                long memoriaMb = 0;
                try { memoriaMb = processo.WorkingSet64 / 1024 / 1024; } catch { /* idem */ }

                itens.Add(new Dictionary<string, object?>
                {
                    ["pid"] = processo.Id,
                    ["nome"] = processo.ProcessName,
                    ["memoria_mb"] = memoriaMb,
                    ["janela"] = janela,
                    ["iniciado_em"] = iniciadoEm
                });
            }
            catch
            {
                // processo pode ja ter encerrado entre o GetProcesses() e aqui -- pula
            }
            finally
            {
                processo.Dispose();
            }
        }

        return itens
            .OrderByDescending(i => (long)i["memoria_mb"]!)
            .ToList();
    }
}
