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

    public static (string Saida, string Erro, int CodigoSaida) ExecutarCmd(string comando, bool elevado, string? usuarioElevacao = null, string? senhaElevacao = null)
    {
        // chcp 65001 forca UTF-8 na sessao do CMD antes do comando de
        // verdade rodar -- sem isso, acentos saem trocados (CMD usa a
        // codepage OEM do Windows por padrao, ex: 850/860, nao UTF-8).
        var argumentos = $"/c chcp 65001>nul & {comando}";
        return elevado ? ExecutarElevado("cmd.exe", argumentos, usuarioElevacao, senhaElevacao) : ExecutarProcesso("cmd.exe", argumentos);
    }

    public static (string Saida, string Erro, int CodigoSaida) ExecutarPowerShell(string comando, bool elevado, string? usuarioElevacao = null, string? senhaElevacao = null)
    {
        // -EncodedCommand (Base64 de UTF-16LE) evita todo o problema de
        // escapar aspas/caracteres especiais que -Command "..." teria --
        // e forca a saida em UTF-8 tambem, mesmo motivo do chcp no CMD.
        var comandoComEncoding = "[Console]::OutputEncoding = [System.Text.Encoding]::UTF8; " + comando;
        var comandoBase64 = System.Convert.ToBase64String(Encoding.Unicode.GetBytes(comandoComEncoding));
        var argumentos = $"-NoProfile -NonInteractive -ExecutionPolicy Bypass -EncodedCommand {comandoBase64}";

        return elevado ? ExecutarElevado("powershell.exe", argumentos, usuarioElevacao, senhaElevacao) : ExecutarProcesso("powershell.exe", argumentos);
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
    /// apaga. Sem uma credencial explícita (usuarioElevacao/senhaElevacao),
    /// só funciona de verdade se a conta que roda o agente já for
    /// administrador local desta máquina -- não existe truque que dê
    /// privilégio de admin pra quem não tem; a elevação evita é só o
    /// PROMPT interativo do UAC. Quando uma credencial é passada (conta
    /// admin cadastrada no portal), a tarefa roda com /ru /rp nessa outra
    /// conta -- funciona mesmo com o agente rodando como usuário comum.
    /// Saída/erro/código não dá pra capturar direto do schtasks, então o
    /// próprio .bat temporário grava tudo em arquivo, que a gente lê
    /// depois -- usa C:\Windows\Temp (em vez do %TEMP% do usuário atual)
    /// porque esse arquivo precisa ser legível pela OUTRA conta quando
    /// /ru é usado, e o %TEMP% de um usuário normal não é acessível pelo
    /// perfil de outra conta.
    /// </summary>
    private static (string Saida, string Erro, int CodigoSaida) ExecutarElevado(string arquivo, string argumentos, string? usuarioElevacao = null, string? senhaElevacao = null)
    {
        var nomeTarefa = $"RDIntranetElevado_{System.Guid.NewGuid():N}";
        var pastaTemp = Path.Combine(System.Environment.GetFolderPath(System.Environment.SpecialFolder.Windows), "Temp", "RDIntranetAgenteExec");
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
            var comCredencial = !string.IsNullOrWhiteSpace(usuarioElevacao) && !string.IsNullOrWhiteSpace(senhaElevacao);
            var argumentosCriar = $"/create /tn \"{nomeTarefa}\" /tr \"cmd /c \\\"{scriptBat}\\\"\" /sc once /st 00:00 /rl highest /f";
            if (comCredencial)
            {
                // /ru + /rp: a tarefa roda como essa conta especifica (nao a do agente),
                // guardando a senha no cofre de credenciais do Windows so pra essa tarefa
                // temporaria -- apagada junto com a tarefa no "finally" abaixo.
                argumentosCriar += $" /ru \"{usuarioElevacao}\" /rp \"{senhaElevacao}\"";
            }

            var (_, criarErro, criarCodigo) = ExecutarProcesso("schtasks.exe", argumentosCriar, 15000);

            if (criarCodigo != 0)
            {
                var dica = comCredencial
                    ? "confira se o usuário/senha cadastrados em Ativos > Dashboard estão corretos e se essa conta é administradora local"
                    : "a conta que roda o agente talvez não seja administradora nesta máquina -- cadastre uma credencial de elevação em Ativos > Dashboard";
                return ("", $"Falha ao criar tarefa elevada ({dica}): {criarErro}", criarCodigo);
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
