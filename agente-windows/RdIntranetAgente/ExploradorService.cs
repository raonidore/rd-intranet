using System.Collections.Generic;
using System.Diagnostics;
using System.IO;
using System.Linq;
using System.Text;
using System.Threading;

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

    private const int TamanhoMaximoSaida = 100 * 1024; // 100KB por saida (stdout/stderr) -- corta o resto, evita um comando "solto" travar tudo

    public static (string Saida, string Erro, int CodigoSaida) ExecutarCmd(string comando, bool elevado)
    {
        // chcp 65001 forca UTF-8 na sessao do CMD antes do comando de
        // verdade rodar -- sem isso, acentos saem trocados (CMD usa a
        // codepage OEM do Windows por padrao, ex: 850/860, nao UTF-8).
        var argumentos = $"/c chcp 65001>nul & {comando}";
        return elevado ? ExecutarElevado("cmd.exe", argumentos) : ExecutarProcesso("cmd.exe", argumentos);
    }

    public static (string Saida, string Erro, int CodigoSaida) ExecutarPowerShell(string comando, bool elevado)
    {
        // -EncodedCommand (Base64 de UTF-16LE) evita todo o problema de
        // escapar aspas/caracteres especiais que -Command "..." teria --
        // e forca a saida em UTF-8 tambem, mesmo motivo do chcp no CMD.
        var comandoComEncoding = "[Console]::OutputEncoding = [System.Text.Encoding]::UTF8; " + comando;
        var comandoBase64 = System.Convert.ToBase64String(Encoding.Unicode.GetBytes(comandoComEncoding));
        var argumentos = $"-NoProfile -NonInteractive -ExecutionPolicy Bypass -EncodedCommand {comandoBase64}";

        return elevado ? ExecutarElevado("powershell.exe", argumentos) : ExecutarProcesso("powershell.exe", argumentos);
    }

    private static string Truncar(string texto)
    {
        if (texto.Length <= TamanhoMaximoSaida) return texto;
        return texto[..TamanhoMaximoSaida] + "\n[... saida cortada em " + (TamanhoMaximoSaida / 1024) + "KB ...]";
    }

    private static (string Saida, string Erro, int CodigoSaida) ExecutarProcesso(string arquivo, string argumentos, int timeoutMs = 30000)
    {
        try
        {
            using var processo = new Process
            {
                StartInfo = new ProcessStartInfo
                {
                    FileName = arquivo,
                    Arguments = argumentos,
                    RedirectStandardOutput = true,
                    RedirectStandardError = true,
                    UseShellExecute = false,
                    CreateNoWindow = true,
                    StandardOutputEncoding = Encoding.UTF8,
                    StandardErrorEncoding = Encoding.UTF8
                }
            };

            processo.Start();

            // Le as duas saidas de forma assincrona ANTES de esperar o
            // processo terminar -- se ler depois (ou so uma de cada vez),
            // um comando que escreve muito pode travar esperando o buffer
            // esvaziar enquanto ninguem esta lendo (deadlock classico).
            var tarefaSaida = processo.StandardOutput.ReadToEndAsync();
            var tarefaErro = processo.StandardError.ReadToEndAsync();

            if (!processo.WaitForExit(timeoutMs))
            {
                try { processo.Kill(true); } catch { /* melhor esforco */ }
                return ("", $"Comando cancelado -- não terminou em {timeoutMs / 1000}s.", -1);
            }

            var saida = Truncar(tarefaSaida.GetAwaiter().GetResult());
            var erro = Truncar(tarefaErro.GetAwaiter().GetResult());

            return (saida, erro, processo.ExitCode);
        }
        catch (System.Exception ex)
        {
            return ("", ex.Message, -1);
        }
    }

    /// <summary>
    /// "Elevação" sem prompt de UAC via Agendador de Tarefas: cria uma
    /// tarefa temporária configurada "Executar com privilégios mais
    /// altos" (/rl highest), dispara na hora (/run), espera terminar e
    /// apaga. Só funciona de verdade se a conta que roda o agente já for
    /// administrador local desta máquina -- não existe truque que dê
    /// privilégio de admin pra quem não tem; a elevação evita é só o
    /// PROMPT interativo do UAC, que travaria uma execução remota
    /// desassistida de qualquer forma. Saída/erro/código não dá pra
    /// capturar direto do schtasks, então o próprio .bat temporário
    /// grava tudo em arquivo, que a gente lê depois.
    /// </summary>
    private static (string Saida, string Erro, int CodigoSaida) ExecutarElevado(string arquivo, string argumentos)
    {
        var nomeTarefa = $"RDIntranetElevado_{System.Guid.NewGuid():N}";
        var pastaTemp = Path.Combine(Path.GetTempPath(), "RDIntranetAgenteExec");
        Directory.CreateDirectory(pastaTemp);

        var arquivoSaida = Path.Combine(pastaTemp, $"{nomeTarefa}_out.txt");
        var arquivoErro = Path.Combine(pastaTemp, $"{nomeTarefa}_err.txt");
        var arquivoCodigo = Path.Combine(pastaTemp, $"{nomeTarefa}_code.txt");
        var scriptBat = Path.Combine(pastaTemp, $"{nomeTarefa}.bat");

        File.WriteAllText(scriptBat,
            "@echo off\r\n" +
            "chcp 65001>nul\r\n" +
            $"\"{arquivo}\" {argumentos} > \"{arquivoSaida}\" 2> \"{arquivoErro}\"\r\n" +
            $"echo %errorlevel% > \"{arquivoCodigo}\"\r\n");

        try
        {
            var (_, criarErro, criarCodigo) = ExecutarProcesso("schtasks.exe",
                $"/create /tn \"{nomeTarefa}\" /tr \"cmd /c \\\"{scriptBat}\\\"\" /sc once /st 00:00 /rl highest /f", 15000);

            if (criarCodigo != 0)
            {
                return ("", $"Falha ao criar tarefa elevada (a conta que roda o agente talvez não seja administrador nesta máquina): {criarErro}", criarCodigo);
            }

            ExecutarProcesso("schtasks.exe", $"/run /tn \"{nomeTarefa}\"", 15000);

            // /run e assincrono -- espera o arquivo de codigo de saida
            // aparecer (sinal de que o .bat terminou), com um teto de tempo.
            var limite = System.DateTime.Now.AddSeconds(30);
            while (!File.Exists(arquivoCodigo) && System.DateTime.Now < limite)
            {
                Thread.Sleep(500);
            }

            var saida = File.Exists(arquivoSaida) ? Truncar(File.ReadAllText(arquivoSaida, Encoding.UTF8)) : "";
            var erro = File.Exists(arquivoErro) ? Truncar(File.ReadAllText(arquivoErro, Encoding.UTF8)) : "";
            var codigoTexto = File.Exists(arquivoCodigo) ? File.ReadAllText(arquivoCodigo).Trim() : "";

            if (!int.TryParse(codigoTexto, out var codigo))
            {
                codigo = -1;
                erro += "\n[Tarefa elevada não terminou a tempo (30s) ou não foi possível confirmar a conclusão]";
            }

            return (saida, erro, codigo);
        }
        finally
        {
            ExecutarProcesso("schtasks.exe", $"/delete /tn \"{nomeTarefa}\" /f", 10000);
            try { File.Delete(scriptBat); } catch { }
            try { File.Delete(arquivoSaida); } catch { }
            try { File.Delete(arquivoErro); } catch { }
            try { File.Delete(arquivoCodigo); } catch { }
        }
    }
}
