using System.Net;
using System.Text;
using System.Text.Json;
using System.Text.Json.Serialization;

namespace RdIntranetAgente;

/// <summary>
/// Escuta só em 127.0.0.1 (nunca na rede) pra receber pedidos de impressão
/// de etiqueta vindos direto do navegador -- sem passar pelo checkin
/// periódico, senão a pessoa esperaria até o próximo ciclo (padrão 15min)
/// pra etiqueta sair. Mesmo princípio do "Zebra Browser Print" da própria
/// Zebra: a página abre em https://rd.intranet/... e chama
/// http://127.0.0.1:8734 diretamente -- navegadores tratam loopback como
/// "contexto seguro" mesmo vindo de uma página HTTPS.
///
/// Só aceita pedidos com o header Origin batendo com o ServerUrl
/// configurado -- evita que outra aba/site mande imprimir sem querer.
/// </summary>
public class PrintListener : IDisposable
{
    public const int Porta = 8734;

    private readonly HttpListener _listener = new();
    private readonly Func<Config> _obterConfig;
    private CancellationTokenSource? _cts;

    public PrintListener(Func<Config> obterConfig)
    {
        _obterConfig = obterConfig;
        _listener.Prefixes.Add($"http://127.0.0.1:{Porta}/");
    }

    /// <summary>Best-effort: se a porta já estiver em uso (outra instância, outro programa), segue sem o listener -- checkin/coleta continuam normais.</summary>
    public void Iniciar()
    {
        try
        {
            _listener.Start();
        }
        catch
        {
            return;
        }

        _cts = new CancellationTokenSource();
        _ = Task.Run(() => EscutarAsync(_cts.Token));
    }

    private async Task EscutarAsync(CancellationToken token)
    {
        while (!token.IsCancellationRequested && _listener.IsListening)
        {
            HttpListenerContext contexto;
            try
            {
                contexto = await _listener.GetContextAsync();
            }
            catch
            {
                break; // listener parado (Dispose)
            }

            _ = Task.Run(() => Processar(contexto));
        }
    }

    private void Processar(HttpListenerContext contexto)
    {
        var req = contexto.Request;
        var res = contexto.Response;

        try
        {
            var origemPermitida = OrigemPermitida(req.Headers["Origin"]);

            if (origemPermitida != null)
            {
                res.Headers.Add("Access-Control-Allow-Origin", origemPermitida);
                res.Headers.Add("Access-Control-Allow-Methods", "POST, OPTIONS");
                res.Headers.Add("Access-Control-Allow-Headers", "Content-Type");
            }

            if (req.HttpMethod == "OPTIONS")
            {
                res.StatusCode = 204;
                res.Close();
                return;
            }

            if (origemPermitida == null)
            {
                Responder(res, 403, false, "Origem não autorizada.");
                return;
            }

            if (req.Url?.AbsolutePath != "/imprimir" || req.HttpMethod != "POST")
            {
                Responder(res, 404, false, "Rota não encontrada.");
                return;
            }

            var config = _obterConfig();
            if (string.IsNullOrWhiteSpace(config.ImpressoraEtiqueta))
            {
                Responder(res, 400, false, "Nenhuma impressora de etiqueta configurada (menu do ícone > Configurações...).");
                return;
            }

            string corpo;
            using (var leitor = new StreamReader(req.InputStream, req.ContentEncoding))
            {
                corpo = leitor.ReadToEnd();
            }

            PedidoImpressao? dados;
            try
            {
                dados = JsonSerializer.Deserialize<PedidoImpressao>(corpo, new JsonSerializerOptions { PropertyNameCaseInsensitive = true });
            }
            catch
            {
                Responder(res, 400, false, "JSON inválido.");
                return;
            }

            if (dados == null || string.IsNullOrWhiteSpace(dados.Zpl))
            {
                Responder(res, 400, false, "Conteúdo da etiqueta (ZPL) não informado.");
                return;
            }

            var sucesso = RawPrinterHelper.EnviarTexto(config.ImpressoraEtiqueta, dados.Zpl);

            Responder(res, sucesso ? 200 : 500, sucesso,
                sucesso ? "Enviado pra impressora." : "Falha ao enviar pra impressora -- confira o nome configurado e se está ligada/conectada.");
        }
        catch (Exception ex)
        {
            try { Responder(res, 500, false, "Erro no agente: " + ex.Message); } catch { /* conexão já pode ter caído */ }
        }
    }

    private string? OrigemPermitida(string? origem)
    {
        if (string.IsNullOrWhiteSpace(origem)) return null;

        var config = _obterConfig();
        if (string.IsNullOrWhiteSpace(config.ServerUrl)) return null;

        try
        {
            var origemHost = new Uri(origem).Host;
            var configuradaHost = new Uri(config.ServerUrl).Host;
            return origemHost.Equals(configuradaHost, StringComparison.OrdinalIgnoreCase) ? origem : null;
        }
        catch
        {
            return null;
        }
    }

    private static void Responder(HttpListenerResponse res, int status, bool sucesso, string mensagem)
    {
        res.StatusCode = status;
        res.ContentType = "application/json; charset=utf-8";
        var bytes = Encoding.UTF8.GetBytes(JsonSerializer.Serialize(new { success = sucesso, message = mensagem }));
        res.ContentLength64 = bytes.Length;
        res.OutputStream.Write(bytes, 0, bytes.Length);
        res.Close();
    }

    public void Dispose()
    {
        _cts?.Cancel();
        try { _listener.Stop(); } catch { /* ja parado */ }
        try { _listener.Close(); } catch { /* ja fechado */ }
    }
}

public class PedidoImpressao
{
    [JsonPropertyName("zpl")]
    public string? Zpl { get; set; }
}
