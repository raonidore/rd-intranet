using System.Collections.Generic;
using System.IO;

namespace RdIntranetAgente;

public enum NivelAtividade
{
    Info,
    Sucesso,
    Aviso,
    Erro,
    Seguranca
}

public sealed record EntradaAtividade(DateTime Quando, NivelAtividade Nivel, string Categoria, string Mensagem);

/// <summary>
/// Linha do tempo do que o agente fez (checkins, comandos, detecções) --
/// mostrada na aba "Atividade" do painel e gravada em agente.log na pasta
/// de dados (menu "Abrir pasta de logs"), com rotação simples em 1 MB pra
/// não crescer sem limite em máquina que fica meses ligada.
/// </summary>
public static class LogAtividade
{
    private const int MaximoEmMemoria = 300;
    private const long TamanhoMaximoArquivo = 1024 * 1024;

    private static readonly object Trava = new();
    // Rotina (check-in/heartbeat) em lista própria: um check-in a cada 5 min enchia as 300
    // posições em um dia e empurrava pra fora as falhas e os avisos de segurança.
    private static readonly LinkedList<EntradaAtividade> Entradas = new();
    private static readonly LinkedList<EntradaAtividade> EntradasRotina = new();

    /// <summary>Check-in e heartbeat (com sucesso ou falha) -- aba "Check-in e conexão" da Atividade.</summary>
    public static bool EhConexao(EntradaAtividade e) => e.Categoria is "CHECKIN" or "HEARTBEAT";

    /// <summary>Disparado em qualquer thread -- quem atualiza UI precisa fazer Invoke.</summary>
    public static event Action<EntradaAtividade>? Adicionada;

    private static string CaminhoArquivo => Path.Combine(
        Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
        "RDIntranetAgent", "agente.log");

    public static void Registrar(NivelAtividade nivel, string categoria, string mensagem)
    {
        var entrada = new EntradaAtividade(DateTime.Now, nivel, categoria, mensagem);

        lock (Trava)
        {
            var lista = EhConexao(entrada) ? EntradasRotina : Entradas;
            lista.AddFirst(entrada);
            while (lista.Count > MaximoEmMemoria)
            {
                lista.RemoveLast();
            }

            GravarEmArquivo(entrada);
        }

        try
        {
            Adicionada?.Invoke(entrada);
        }
        catch
        {
            // quem escuta (painel) nunca pode derrubar quem registra
        }
    }

    public static List<EntradaAtividade> Recentes(int limite = MaximoEmMemoria)
    {
        lock (Trava)
        {
            return Entradas.Concat(EntradasRotina).OrderByDescending(e => e.Quando).Take(limite).ToList();
        }
    }

    private static void GravarEmArquivo(EntradaAtividade e)
    {
        try
        {
            var caminho = CaminhoArquivo;
            Directory.CreateDirectory(Path.GetDirectoryName(caminho)!);

            var info = new FileInfo(caminho);
            if (info.Exists && info.Length > TamanhoMaximoArquivo)
            {
                File.Copy(caminho, caminho + ".1", overwrite: true);
                File.Delete(caminho);
            }

            File.AppendAllText(caminho, $"{e.Quando:yyyy-MM-dd HH:mm:ss} [{e.Nivel.ToString().ToUpperInvariant(),-9}] {e.Categoria,-10} {e.Mensagem}{Environment.NewLine}");
        }
        catch
        {
            // log em disco é conveniência -- sem permissão/disco cheio, segue só em memória
        }
    }
}
