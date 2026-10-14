using System.Diagnostics;
using System.Text.Json;

namespace RdIntranetAgente;

/// <summary>
/// Modo de teste (--teste-seguranca-rede): roda as mesmas análises do
/// monitor e devolve um JSON legível, sem enviar nada a servidor nenhum --
/// pra validar falso positivo numa máquina antes de publicar o agente.
/// </summary>
public static class DiagnosticoSegurancaRede
{
    /// <summary>
    /// Simula uma varredura RDP contra 198.51.100.0/24 (TEST-NET-2, RFC 5737:
    /// reservada pra documentação, não existe na internet -- nada sai daqui)
    /// e roda a detecção real. Eventos ficam locais: sem machine_guid o
    /// SegurancaService não envia nada.
    /// </summary>
    public static List<object> SimularVarredura()
    {
        var eventos = new List<object>();
        var seguranca = new SegurancaService(() => new Config(), () => null);
        seguranca.Detectado += e => eventos.Add(new { e.Tipo, e.Severidade, e.Resumo, e.Detalhes });
        using var monitor = new MonitorRedeService(() => new Config(), () => null, seguranca);

        var clientes = new List<System.Net.Sockets.TcpClient>();
        for (var i = 1; i <= 60; i++)
        {
            var c = new System.Net.Sockets.TcpClient();
            _ = c.ConnectAsync($"198.51.100.{i}", 3380 + (i % 18)).ContinueWith(_ => { });
            clientes.Add(c);
        }
        Thread.Sleep(1500);
        monitor.CicloDeDeteccao();
        foreach (var c in clientes) c.Dispose();
        seguranca.Dispose();
        return eventos;
    }

    /// <summary>Desenha a página Segurança (cada aba) em PNG -- conferência visual sem abrir o agente.</summary>
    public static void PrintsDaTela(string pasta)
    {
        Directory.CreateDirectory(pasta);
        System.Windows.Forms.Application.EnableVisualStyles();
        var seguranca = new SegurancaService(() => new Config(), () => null);
        seguranca.RegistrarExterno(new EventoSeguranca
        {
            Tipo = "SUSPICIOUS_STARTUP", Severidade = "CRITICAL",
            Resumo = "5 item(ns) suspeito(s) na inicialização do Windows: taskmgr.exe, SQLite, \\JavaUpdateInstallerNL e mais 2 -- veja todos em Segurança > Eventos",
            Detalhes = new Dictionary<string, object?> { ["itens"] = InicializacaoService.Coletar().Take(5).Select(i => i.ParaEnvio()).ToList() }
        });
        using var monitor = new MonitorRedeService(() => new Config(), () => null, seguranca);
        using var janela = new System.Windows.Forms.Form { Width = 1200, Height = 760, BackColor = Tema.Fundo, Padding = new System.Windows.Forms.Padding(24), ShowInTaskbar = false };
        var pagina = new SegurancaPagina(seguranca, monitor) { Visible = true };
        janela.Controls.Add(pagina);
        janela.Show();
        foreach (var aba in new[] { "eventos", "processos", "inicializacao", "conexoes", "firewall", "quarentena" })
        {
            pagina.SelecionarAba(aba);
            var limite = DateTime.Now.AddSeconds(40);
            do { System.Windows.Forms.Application.DoEvents(); Thread.Sleep(100); } while ((pagina.Carregando || DateTime.Now < limite.AddSeconds(-38)) && DateTime.Now < limite);
            pagina.Atualizar();
            System.Windows.Forms.Application.DoEvents();
            using var imagem = new System.Drawing.Bitmap(janela.ClientSize.Width, janela.ClientSize.Height);
            janela.DrawToBitmap(imagem, new System.Drawing.Rectangle(System.Drawing.Point.Empty, janela.ClientSize));
            imagem.Save(Path.Combine(pasta, $"seguranca-{aba}.png"));
        }
        janela.Controls.Clear();
        using var monitorDesempenho = new MonitorDesempenhoService(() => new Config(), () => null);
        var desempenho = new DesempenhoPagina(monitorDesempenho);
        janela.Controls.Add(desempenho);
        desempenho.Visible = false;
        desempenho.Visible = true;
        // Carga simulada por alguns segundos pros gráficos terem o que desenhar.
        var carga = Task.Run(() => { var fim = DateTime.Now.AddSeconds(14); while (DateTime.Now < fim) { Math.Sqrt(DateTime.Now.Ticks); } });
        var ate = DateTime.Now.AddSeconds(22);
        while (DateTime.Now < ate) { System.Windows.Forms.Application.DoEvents(); Thread.Sleep(100); }
        void Print(string nome)
        {
            using var imagem = new System.Drawing.Bitmap(janela.ClientSize.Width, janela.ClientSize.Height);
            janela.DrawToBitmap(imagem, new System.Drawing.Rectangle(System.Drawing.Point.Empty, janela.ClientSize));
            imagem.Save(Path.Combine(pasta, nome));
        }
        Print("desempenho.png");
        // Coleta contínua por ~40 s (intervalo 2 s) pra ter histórico, programas e IPs.
        monitorDesempenho.AplicarServidor(true, 2);
        var ateColeta = DateTime.Now.AddSeconds(40);
        while (DateTime.Now < ateColeta) { System.Windows.Forms.Application.DoEvents(); Thread.Sleep(100); }
        foreach (var aba in new[] { "historico", "programas", "rede", "picos" })
        {
            desempenho.SelecionarAba(aba);
            for (var i = 0; i < 10; i++) { System.Windows.Forms.Application.DoEvents(); Thread.Sleep(100); }
            Print($"desempenho-{aba}.png");
        }
        monitorDesempenho.AplicarServidor(false, 2);
        janela.Close();
        seguranca.Dispose();
    }

    public static string AnalisarArquivos(IEnumerable<string> arquivos) => JsonSerializer.Serialize(arquivos.Select(a =>
    {
        var exe = AnaliseExecutavel.Analisar(a);
        var linha = a.EndsWith(".exe", StringComparison.OrdinalIgnoreCase) && exe.TipoReal == "zip" ? $"javaw.exe -jar \"{a}\"" : null;
        var avaliacao = AnaliseExecutavel.Avaliar(exe, linha, true, Path.GetFileName(a));
        return new { arquivo = a, exe.Assinatura, exe.Assinante, exe.Oculto, exe.TipoReal, pontos = avaliacao.Pontos, nivel = avaliacao.Nivel, motivos = avaliacao.Motivos };
    }), new JsonSerializerOptions { WriteIndented = true, Encoder = System.Text.Encodings.Web.JavaScriptEncoder.UnsafeRelaxedJsonEscaping });

    public static string Executar()
    {
        var relogio = Stopwatch.StartNew();
        var tempos = new Dictionary<string, long>();

        var conexoes = TabelaTcp.Ler();
        tempos["tabela_tcp_ms"] = relogio.ElapsedMilliseconds;

        relogio.Restart();
        var processos = CatalogoProcessos.Obter(conexoes.Select(c => c.Pid).Distinct());
        tempos["processos_ms"] = relogio.ElapsedMilliseconds;

        relogio.Restart();
        var programas = conexoes
            .Where(c => !TabelaTcp.EhLocal(c.Remoto) || c.Estado == "LISTEN")
            .GroupBy(c => c.Pid)
            .Select(g =>
            {
                processos.TryGetValue(g.Key, out var p);
                var exe = p?.Caminho != null ? AnaliseExecutavel.Analisar(p.Caminho) : null;
                var avaliacao = exe != null ? AnaliseExecutavel.Avaliar(exe, p!.LinhaComando, p.ComoSystem, p.Nome) : new Avaliacao();
                return new
                {
                    pid = g.Key,
                    nome = p?.Nome,
                    caminho = p?.Caminho,
                    servico = p?.Servico,
                    assinatura = exe?.Assinatura,
                    assinante = exe?.Assinante,
                    pontos = avaliacao.Pontos,
                    nivel = avaliacao.Nivel,
                    motivos = avaliacao.Motivos,
                    estados = g.GroupBy(c => c.Estado).ToDictionary(e => e.Key, e => e.Count()),
                    internet = g.Where(c => c.Estado == "ESTABLISHED" && TabelaTcp.EhPublico(c.Remoto)).Select(c => $"{TabelaTcp.Texto(c.Remoto)}:{c.PortaRemota}").Distinct().Take(10).ToList()
                };
            })
            .OrderByDescending(x => x.pontos)
            .ToList();
        tempos["analise_conexoes_ms"] = relogio.ElapsedMilliseconds;

        relogio.Restart();
        var suspeitos = processos.Values
            .Where(p => !string.IsNullOrEmpty(p.Caminho))
            .Select(p => (p, a: AnaliseExecutavel.Avaliar(AnaliseExecutavel.Analisar(p.Caminho), p.LinhaComando, p.ComoSystem, p.Nome)))
            .Where(x => x.a.Pontos >= 15)
            .OrderByDescending(x => x.a.Pontos)
            .Select(x => new { x.p.Pid, x.p.Nome, x.p.Caminho, pontos = x.a.Pontos, nivel = x.a.Nivel, motivos = x.a.Motivos })
            .ToList();
        tempos["analise_processos_ms"] = relogio.ElapsedMilliseconds;

        relogio.Restart();
        var inicializacao = InicializacaoService.Coletar();
        tempos["inicializacao_ms"] = relogio.ElapsedMilliseconds;

        relogio.Restart();
        var firewall = FirewallService.Estado();
        tempos["firewall_ms"] = relogio.ElapsedMilliseconds;

        return JsonSerializer.Serialize(new
        {
            maquina = Environment.MachineName,
            tempos,
            totais = new
            {
                conexoes = conexoes.Count,
                processos = processos.Count,
                programas_com_rede = programas.Count,
                processos_com_pontos = suspeitos.Count,
                itens_inicializacao = inicializacao.Count,
                inicializacao_por_nivel = inicializacao.GroupBy(i => i.Avaliacao.Nivel).ToDictionary(g => g.Key, g => g.Count())
            },
            programas_com_rede = programas.Take(40),
            processos_com_pontos = suspeitos.Take(60),
            inicializacao_com_pontos = inicializacao.Where(i => i.Avaliacao.Pontos > 0).Select(i => i.ParaEnvio()).Take(80),
            firewall
        }, new JsonSerializerOptions { WriteIndented = true, Encoder = System.Text.Encodings.Web.JavaScriptEncoder.UnsafeRelaxedJsonEscaping });
    }
}
