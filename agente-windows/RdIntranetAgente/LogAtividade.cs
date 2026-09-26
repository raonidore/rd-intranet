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
    private static readonly LinkedList<EntradaAtividade> Entradas = new();

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
            Entradas.AddFirst(entrada);
            while (Entradas.Count > MaximoEmMemoria)
            {
                Entradas.RemoveLast();
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
            return Entradas.Take(limite).ToList();
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
