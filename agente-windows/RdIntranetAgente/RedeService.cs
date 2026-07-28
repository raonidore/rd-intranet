using System.Diagnostics;
using System.Text;
using System.Text.RegularExpressions;

namespace RdIntranetAgente;

/// <summary>
/// Autentica o processo do agente (roda como SYSTEM, via tarefa
/// agendada) numa unidade de rede mapeada -- SYSTEM não enxerga o
/// mapeamento feito pela sessão do usuário que está logado na tela
/// (unidade de rede é por usuário/sessão, não por máquina), então
/// "Explorar arquivos" num caminho \\servidor\pasta falha com "usuário
/// ou senha incorretos" mesmo a unidade funcionando normalmente pra
/// quem está logado. Contorno: `net use` autentica a SESSÃO DO PRÓPRIO
/// SYSTEM contra aquele servidor, usando uma credencial cadastrada no
/// portal (por máquina, ver AtivoService::credenciaisRedeParaAgente).
/// </summary>
public static class RedeService
{
    private static readonly Regex RaizCompartilhamento = new(@"^(\\\\[^\\]+\\[^\\]+)", RegexOptions.Compiled);

    public static void ConectarSeNecessario(string? caminho, string? usuario, string? senha)
    {
        if (string.IsNullOrWhiteSpace(caminho) || !caminho.StartsWith(@"\\")) return;
        if (string.IsNullOrWhiteSpace(usuario) || string.IsNullOrWhiteSpace(senha)) return;

        var raiz = RaizCompartilhamento.Match(caminho);
        if (!raiz.Success) return;

        var compartilhamento = raiz.Groups[1].Value;

        // Desconecta uma sessao anterior primeiro (silencioso, ok falhar
        // se nao havia nenhuma) -- `net use` recusa autenticar de novo
        // num servidor que ja tem uma conexao ativa com credencial
        // diferente ("Multiple connections... not allowed").
        ExecutarNetUse($"use \"{compartilhamento}\" /delete /y");

        ExecutarNetUse($"use \"{compartilhamento}\" \"{senha}\" /user:\"{usuario}\"");
    }

    private static void ExecutarNetUse(string argumentos)
    {
        try
        {
            using var processo = new Process
            {
                StartInfo = new ProcessStartInfo
                {
                    FileName = "net.exe",
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
            processo.WaitForExit(10000);
        }
        catch
        {
            // melhor esforco -- se a autenticacao falhar mesmo assim, a
            // chamada de arquivo que vem em seguida falha com o proprio
            // erro do Windows (ex: "usuario ou senha incorretos"), que ja
            // chega pro admin via ResponderErroAsync
        }
    }
}
