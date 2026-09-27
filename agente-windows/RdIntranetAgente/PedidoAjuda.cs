using System.Drawing;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Text;
using System.Text.Json;
using System.Windows.Forms;

namespace RdIntranetAgente;

/// <summary>
/// Botão "Pedir ajuda": o usuário descreve o problema em uma frase e o
/// pedido aparece na hora em Chamados &gt; Suporte Remoto no portal, com a
/// máquina já identificada -- sem navegador, site ou download.
/// </summary>
public static class PedidoAjuda
{
    public static async Task AbrirAsync(IWin32Window? dono, Config config)
    {
        if (!config.EstaConfigurado)
        {
            MessageBox.Show(dono, "O agente ainda não está configurado.", "RD Intranet", MessageBoxButtons.OK, MessageBoxIcon.Warning);
            return;
        }

        using var form = new PedidoAjudaForm();
        if (form.ShowDialog(dono) != DialogResult.OK)
        {
            return;
        }

        var (ok, mensagem) = await EnviarAsync(config, form.Mensagem);
        LogAtividade.Registrar(ok ? NivelAtividade.Sucesso : NivelAtividade.Erro, "SUPORTE",
            ok ? "Pedido de ajuda enviado ao suporte." : $"Falha ao enviar pedido de ajuda: {mensagem}");

        MessageBox.Show(dono,
            ok ? "Pedido enviado! O suporte foi avisado e vai entrar em contato." : $"Não consegui enviar o pedido agora.\n\n{mensagem}",
            "RD Intranet - Pedir ajuda", MessageBoxButtons.OK, ok ? MessageBoxIcon.Information : MessageBoxIcon.Warning);
    }

    private static async Task<(bool ok, string mensagem)> EnviarAsync(Config config, string mensagem)
    {
        string? guid;
        try
        {
            guid = CollectorService.ObterMachineGuid();
        }
        catch
        {
            guid = null;
        }
        if (string.IsNullOrEmpty(guid))
        {
            return (false, "Não consegui identificar esta máquina.");
        }

        var json = JsonSerializer.Serialize(new
        {
            machine_guid = guid,
            usuario = Environment.UserName,
            mensagem
        });

        var handler = new HttpClientHandler { ServerCertificateCustomValidationCallback = (m, c, ch, e) => true };
        using var cliente = new HttpClient(handler) { Timeout = TimeSpan.FromSeconds(15) };
        cliente.DefaultRequestHeaders.Add("X-RD-Agente-Chave", config.ApiKey);
        var conteudo = new StringContent(json, Encoding.UTF8);
        conteudo.Headers.ContentType = new MediaTypeHeaderValue("application/json") { CharSet = "utf-8" };

        try
        {
            var resposta = await cliente.PostAsync(config.ServerUrl.TrimEnd('/') + "/api/ativos/suporte/pedido", conteudo);
            var texto = await resposta.Content.ReadAsStringAsync();
            string? retorno = null;
            try
            {
                using var doc = JsonDocument.Parse(texto);
                if (doc.RootElement.TryGetProperty("message", out var m)) retorno = m.GetString();
            }
            catch
            {
                // resposta fora do formato -- usa o status HTTP
            }

            return resposta.IsSuccessStatusCode
                ? (true, retorno ?? "")
                : (false, retorno ?? $"Servidor respondeu HTTP {(int)resposta.StatusCode}.");
        }
        catch (Exception ex)
        {
            return (false, "Sem comunicação com o servidor: " + ex.Message);
        }
    }
}

/// <summary>Janelinha do "Pedir ajuda", no visual escuro do painel.</summary>
public sealed class PedidoAjudaForm : Form
{
    private readonly TextBox _campo;

    public string Mensagem => _campo.Text.Trim();

    public PedidoAjudaForm()
    {
        SuspendLayout();
        AutoScaleDimensions = new SizeF(96F, 96F);
        AutoScaleMode = AutoScaleMode.Dpi;

        Text = "RD Intranet - Pedir ajuda";
        FormBorderStyle = FormBorderStyle.FixedDialog;
        MaximizeBox = false;
        MinimizeBox = false;
        StartPosition = FormStartPosition.CenterScreen;
        ClientSize = new Size(440, 270);
        BackColor = Tema.Fundo;
        ForeColor = Tema.Texto;
        Font = Tema.Fonte(9.5F);
        ShowInTaskbar = true;
        TopMost = true;
        try { Icon = Icon.ExtractAssociatedIcon(Application.ExecutablePath); } catch { }
        Tema.BarraTituloEscura(this);

        var titulo = new Label
        {
            Text = "Precisa de ajuda do suporte?",
            Font = Tema.FonteSemibold(14F),
            ForeColor = Tema.Texto,
            AutoSize = true,
            Location = new Point(22, 18)
        };
        var subtitulo = new Label
        {
            Text = "Conte em poucas palavras o que está acontecendo. O suporte recebe o pedido na hora, já sabendo qual é esta máquina.",
            ForeColor = Tema.TextoSecundario,
            Location = new Point(22, 52),
            Size = new Size(396, 40)
        };
        _campo = new TextBox
        {
            Multiline = true,
            Location = new Point(22, 98),
            Size = new Size(396, 104),
            MaxLength = 500,
            ScrollBars = ScrollBars.Vertical
        };
        Tema.EstilizarCampo(_campo);
        Tema.TemaEscuroNativo(_campo);

        var enviar = new BotaoTema("Enviar pedido", BotaoTema.Variante.Primario) { Location = new Point(218, 218), Width = 120, DialogResult = DialogResult.OK };
        var cancelar = new BotaoTema("Cancelar") { Location = new Point(346, 218), Width = 72, DialogResult = DialogResult.Cancel };

        Controls.AddRange(new Control[] { titulo, subtitulo, _campo, enviar, cancelar });
        AcceptButton = null; // Enter quebra linha no texto
        CancelButton = cancelar;
        ResumeLayout(false);
        PerformLayout();

        Shown += (s, e) => _campo.Focus();
    }
}
