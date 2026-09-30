using System.Drawing;
using System.Drawing.Drawing2D;
using System.Text.Json;
using System.Windows.Forms;

namespace RdIntranetAgente;

/// <summary>
/// Página "Network" do painel: adaptadores desta máquina, configuração
/// TCP/IP (DHCP ou manual, com snapshot pra reverter) e diagnósticos
/// (ping, velocidade, trace route) e MTR contínuo, com as funções do WinMTR.
/// Mesmo visual da página Chamados -- seções numeradas e controles de
/// ControlesModernos.cs.
/// </summary>
public sealed class NetworkPagina : Panel
{
    private readonly Label _status;
    private readonly FlowLayoutPanel _pilha;
    private readonly Panel _rolagem;

    private readonly ListaAdaptadores _lista;
    private readonly CartaoSecao _cartaoAdaptadores;

    private readonly SeletorSegmentado _modo;
    private readonly CaixaTexto _ip;
    private readonly CaixaTexto _mascara;
    private readonly CaixaTexto _gateway;
    private readonly CaixaTexto _dns;
    private readonly BotaoTema _botaoAplicar;
    private readonly BotaoTema _botaoReverter;

    private readonly CaixaTexto _alvoPing;
    private readonly ResultadoTerminal _resultadoPing;
    private readonly ResultadoTerminal _resultadoVelocidade;
    private readonly CaixaTexto _alvoTrace;
    private readonly ResultadoTerminal _resultadoTrace;

    // MTR
    private readonly CaixaTexto _mtrDestino;
    private readonly BotaoTema _mtrBotao;
    private readonly SeletorSegmentado _mtrIntervalo;
    private readonly CampoNumerico _mtrTamanho;
    private readonly CampoNumerico _mtrSaltos;
    private readonly InterruptorModerno _mtrResolver;
    private readonly Label _mtrStatus;
    private readonly TabelaMtr _mtrTabela;
    private readonly CartaoSecao _cartaoMtr;
    private MtrService? _mtr;
    private CancellationTokenSource? _mtrCancelar;
    private int _mtrRodadas;

    private const int LinhaCampo = 74;
    private const int AlturaBaseMtr = 30 + 50 + 74 + 74 + 52;
    private const int AlturaBaseAdaptadores = 30 + 50;

    public NetworkPagina()
    {
        Dock = DockStyle.Fill;
        BackColor = Tema.Fundo;
        Visible = false;

        var titulo = new Panel { Height = 68, BackColor = Tema.Fundo, Dock = DockStyle.Top };
        titulo.Controls.Add(new Label { Text = "Network", Location = new Point(0, 0), Size = new Size(600, 34), ForeColor = Tema.Texto, Font = Tema.FonteSemibold(19F) });
        titulo.Controls.Add(new Label { Text = "Adaptadores, TCP/IP e diagnósticos desta máquina.", Location = new Point(0, 38), Size = new Size(690, 24), ForeColor = Tema.TextoSecundario, Font = Tema.Fonte(9F) });
        _status = new Label { Dock = DockStyle.Top, Height = 30, ForeColor = Tema.TextoSecundario, TextAlign = ContentAlignment.MiddleLeft, AutoEllipsis = true, Text = "Selecione um adaptador para ver e alterar a configuração." };

        _rolagem = new Panel { Dock = DockStyle.Fill, AutoScroll = true, BackColor = Tema.Fundo };
        _pilha = new FlowLayoutPanel
        {
            FlowDirection = FlowDirection.TopDown,
            WrapContents = false,
            AutoSize = true,
            AutoSizeMode = AutoSizeMode.GrowAndShrink,
            Location = Point.Empty,
            BackColor = Tema.Fundo,
            Margin = Padding.Empty,
            Padding = new Padding(0, 4, 0, 8)
        };
        _rolagem.Controls.Add(_pilha);

        // ---------------------------------------------------------- 1. adaptadores
        _lista = new ListaAdaptadores { Dock = DockStyle.Top };
        _lista.SelecaoAlterada += (s, e) => SelecionarAdaptador();
        _lista.Copiar += (s, e) => CopiarAdaptador();
        _lista.AlturaAlterada += (s, e) => _cartaoAdaptadores!.Height = AlturaBaseAdaptadores + _lista.Height;
        var botaoAtualizar = new BotaoTema("Atualizar", BotaoTema.Variante.Secundario) { Width = 120, Anchor = AnchorStyles.Top | AnchorStyles.Right };
        botaoAtualizar.Click += async (s, e) => await AtualizarAdaptadoresAsync();
        _cartaoAdaptadores = Cartao(new CabecalhoSecao("1", "Adaptadores de rede", "Ativos primeiro. Clique para configurar; Ctrl+C copia os dados."), _lista, AlturaBaseAdaptadores + _lista.Height);
        _cartaoAdaptadores.Controls.Add(botaoAtualizar);
        botaoAtualizar.BringToFront();
        _cartaoAdaptadores.Resize += (s, e) => botaoAtualizar.Location = new Point(_cartaoAdaptadores.Width - botaoAtualizar.Width - 20, 18);

        // ---------------------------------------------------------- 2. tcp/ip
        _modo = new SeletorSegmentado();
        _modo.DefinirOpcoes(new[] { ("dhcp", "Automático (DHCP)", Tema.Sucesso), ("manual", "Manual", Tema.Acento) }, "dhcp");
        _modo.SelecaoAlterada += (s, e) => AtualizarCampos();
        _ip = new CaixaTexto { PlaceholderText = "192.168.0.10" };
        _mascara = new CaixaTexto { PlaceholderText = "255.255.255.0" };
        _gateway = new CaixaTexto { PlaceholderText = "192.168.0.1" };
        _dns = new CaixaTexto { PlaceholderText = "8.8.8.8, 1.1.1.1" };
        _botaoAplicar = new BotaoTema("Salvar e aplicar", BotaoTema.Variante.Primario) { Width = 170, Dock = DockStyle.Left };
        _botaoAplicar.Click += async (s, e) => await AplicarAsync();
        _botaoReverter = new BotaoTema("Reverter última alteração", BotaoTema.Variante.Perigo) { Width = 210, Dock = DockStyle.Left, Enabled = false };
        _botaoReverter.Click += async (s, e) => await ReverterAsync();
        var linhaAcoes = new Panel { Dock = DockStyle.Fill, BackColor = Tema.Superficie, Padding = new Padding(0, 6, 0, 4) };
        linhaAcoes.Controls.Add(_botaoReverter);
        linhaAcoes.Controls.Add(new Panel { Width = 10, Dock = DockStyle.Left, BackColor = Tema.Superficie });
        linhaAcoes.Controls.Add(_botaoAplicar);

        var gradeTcp = Grade(2);
        AdicionarLinha(gradeTcp, LinhaCampo, Campo("Modo de endereçamento", _modo));
        AdicionarLinha(gradeTcp, LinhaCampo, Campo("Endereço IPv4", _ip), Campo("Máscara", _mascara));
        AdicionarLinha(gradeTcp, LinhaCampo, Campo("Gateway", _gateway), Campo("DNS  ·  até 2, separados por vírgula", _dns));
        AdicionarLinha(gradeTcp, 50, linhaAcoes);
        var cartaoTcp = Cartao(new CabecalhoSecao("2", "Configuração TCP/IP", "A configuração anterior fica salva para reverter se a conexão cair."), gradeTcp, 30 + 50 + LinhaCampo * 3 + 50);

        // ---------------------------------------------------------- 3. diagnóstico
        _alvoPing = new CaixaTexto(icone: Icones.Rede) { PlaceholderText = "192.168.0.1" };
        _resultadoPing = new ResultadoTerminal();
        _resultadoVelocidade = new ResultadoTerminal { FonteMaior = true };
        _alvoTrace = new CaixaTexto(icone: Icones.Rota) { PlaceholderText = "203.0.113.1" };
        _resultadoTrace = new ResultadoTerminal();
        _alvoPing.Caixa.KeyDown += async (s, e) => { if (e.KeyCode == Keys.Enter) { e.SuppressKeyPress = true; await PingAsync(); } };
        _alvoTrace.Caixa.KeyDown += async (s, e) => { if (e.KeyCode == Keys.Enter) { e.SuppressKeyPress = true; await TraceAsync(); } };

        var botaoPing = new BotaoTema("Ping", BotaoTema.Variante.Secundario);
        botaoPing.Click += async (s, e) => await PingAsync();
        var botaoVelocidade = new BotaoTema("Testar velocidade", BotaoTema.Variante.Secundario);
        botaoVelocidade.Click += async (s, e) => await VelocidadeAsync();
        var botaoTrace = new BotaoTema("Trace route", BotaoTema.Variante.Secundario);
        botaoTrace.Click += async (s, e) => await TraceAsync();

        var gradeDiag = Grade(2);
        AdicionarLinha(gradeDiag, 214,
            Ferramenta("Ping", "Quatro pacotes para um endereço IPv4.", _alvoPing, botaoPing, _resultadoPing),
            Ferramenta("Velocidade", "Teste Cloudflare · transfere até 9 MB.", null, botaoVelocidade, _resultadoVelocidade));
        AdicionarLinha(gradeDiag, 250, Ferramenta("Trace route", "Os saltos até o IP de destino (máximo 12).", _alvoTrace, botaoTrace, _resultadoTrace));
        var cartaoDiag = Cartao(new CabecalhoSecao("3", "Diagnóstico", "Testes rápidos de conectividade a partir desta máquina."), gradeDiag, 30 + 50 + 214 + 250);

        // ---------------------------------------------------------- 4. MTR
        _mtrDestino = new CaixaTexto(icone: Icones.Globo) { PlaceholderText = "8.8.8.8 ou um nome, ex.: google.com" };
        _mtrDestino.Caixa.KeyDown += async (s, e) => { if (e.KeyCode == Keys.Enter) { e.SuppressKeyPress = true; await AlternarMtrAsync(); } };
        _mtrBotao = new BotaoTema("Iniciar", BotaoTema.Variante.Primario) { Width = 130, Dock = DockStyle.Right };
        _mtrBotao.Click += async (s, e) => await AlternarMtrAsync();
        var linhaDestino = new Panel { Dock = DockStyle.Top, Height = 40, BackColor = Tema.Superficie };
        _mtrDestino.Dock = DockStyle.Fill;
        linhaDestino.Controls.Add(_mtrDestino);
        linhaDestino.Controls.Add(new Panel { Width = 10, Dock = DockStyle.Right, BackColor = Tema.Superficie });
        linhaDestino.Controls.Add(_mtrBotao);
        var campoDestino = new Panel { Dock = DockStyle.Fill, BackColor = Tema.Superficie };
        campoDestino.Controls.Add(linhaDestino);
        campoDestino.Controls.Add(new RotuloCampo("Destino (host)"));

        _mtrIntervalo = new SeletorSegmentado();
        _mtrIntervalo.DefinirOpcoes(new[] { ("0.5", "0,5 s", Tema.Ciano), ("1", "1 s", Tema.Ciano), ("2", "2 s", Tema.Ciano), ("5", "5 s", Tema.Ciano) }, "1");
        _mtrTamanho = new CampoNumerico(32, 1400, 64, "");
        _mtrSaltos = new CampoNumerico(5, 64, 30, "");
        _mtrResolver = new InterruptorModerno("Resolver nomes", ligado: true);
        var gradeOpcoes = new TableLayoutPanel { Dock = DockStyle.Fill, ColumnCount = 4, BackColor = Tema.Superficie, Margin = Padding.Empty };
        foreach (var peso in new[] { 34F, 22F, 22F, 22F }) gradeOpcoes.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, peso));
        gradeOpcoes.RowStyles.Add(new RowStyle(SizeType.Percent, 100));
        var opcoes = new (string Rotulo, Control Campo)[] { ("Intervalo", _mtrIntervalo), ("Tamanho do ping (bytes)", _mtrTamanho), ("Máximo de saltos", _mtrSaltos), ("Nomes", _mtrResolver) };
        for (var i = 0; i < opcoes.Length; i++)
        {
            var celula = Campo(opcoes[i].Rotulo, opcoes[i].Campo);
            celula.Margin = new Padding(i == 0 ? 0 : 8, 0, i == opcoes.Length - 1 ? 0 : 8, 0);
            gradeOpcoes.Controls.Add(celula, i, 0);
        }

        _mtrStatus = new Label { Dock = DockStyle.Fill, ForeColor = Tema.TextoSecundario, BackColor = Tema.Superficie, TextAlign = ContentAlignment.MiddleLeft, AutoEllipsis = true, Text = "Parado." };
        var acoesMtr = new FlowLayoutPanel { Dock = DockStyle.Right, Width = 430, BackColor = Tema.Superficie, WrapContents = false, FlowDirection = FlowDirection.RightToLeft, Padding = new Padding(0, 6, 0, 0) };
        foreach (var (texto, acao) in new (string, Action)[]
        {
            ("Exportar HTML", () => ExportarMtr(html: true)),
            ("Exportar TXT", () => ExportarMtr(html: false)),
            ("Copiar HTML", () => CopiarMtr(html: true)),
            ("Copiar texto", () => CopiarMtr(html: false))
        })
        {
            var botao = new BotaoTema(texto, BotaoTema.Variante.Secundario) { Width = 100, Height = 32, Margin = new Padding(6, 0, 0, 0), Font = Tema.FonteSemibold(8.5F) };
            botao.Click += (s, e) => acao();
            acoesMtr.Controls.Add(botao);
        }
        var linhaAcoesMtr = new Panel { Dock = DockStyle.Fill, BackColor = Tema.Superficie };
        linhaAcoesMtr.Controls.Add(_mtrStatus);
        linhaAcoesMtr.Controls.Add(acoesMtr);

        _mtrTabela = new TabelaMtr { Dock = DockStyle.Fill };
        var gradeMtr = Grade(1);
        AdicionarLinha(gradeMtr, LinhaCampo, campoDestino);
        AdicionarLinha(gradeMtr, LinhaCampo, gradeOpcoes);
        AdicionarLinha(gradeMtr, 52, linhaAcoesMtr);
        AdicionarLinha(gradeMtr, _mtrTabela.AlturaNecessaria, _mtrTabela);
        _mtrTabela.AlturaAlterada += (s, e) =>
        {
            gradeMtr.RowStyles[3].Height = _mtrTabela.AlturaNecessaria;
            _cartaoMtr!.Height = AlturaBaseMtr + _mtrTabela.AlturaNecessaria + 8;
        };
        _cartaoMtr = Cartao(new CabecalhoSecao("4", "MTR · rota contínua", "Como o WinMTR: perda e latência de cada salto até o destino, atualizadas em tempo real."), gradeMtr, AlturaBaseMtr + _mtrTabela.AlturaNecessaria + 8);

        _pilha.Controls.AddRange(new Control[] { _cartaoAdaptadores, cartaoTcp, cartaoDiag, _cartaoMtr });
        // Janela do painel escondida (fechar = volta pra bandeja): para o MTR, não fica pingando à toa.
        VisibleChanged += (s, e) => { if (FindForm() is { Visible: false }) PararMtr(); };
        _rolagem.Resize += (s, e) => AjustarLarguras();

        Controls.Add(_rolagem);
        Controls.Add(_status);
        Controls.Add(titulo);
        AjustarLarguras();
        AtualizarCampos();
    }

    private void AjustarLarguras()
    {
        var largura = Math.Max(420, _rolagem.ClientSize.Width - SystemInformation.VerticalScrollBarWidth - 4);
        foreach (Control c in _pilha.Controls) c.Width = largura;
    }

    private void Status(string texto, Color? cor = null)
    {
        _status.Text = texto;
        _status.ForeColor = cor ?? Tema.TextoSecundario;
    }

    // ================================================================ adaptadores

    public async Task AtualizarAdaptadoresAsync()
    {
        var idSelecionado = _lista.Selecionado?.AdapterId;
        Status("Consultando adaptadores...");
        try
        {
            var adaptadores = await Task.Run(NetworkService.ListarAdaptadores);
            _lista.Definir(adaptadores, idSelecionado);
            Status(adaptadores.Count > 0
                ? $"{adaptadores.Count} adaptador(es) TCP/IP encontrado(s)."
                : "Nenhum adaptador com TCP/IP habilitado foi encontrado.");
        }
        catch (Exception ex)
        {
            Status($"Falha ao consultar adaptadores: {ex.Message}", Tema.Perigo);
        }
    }

    private void CopiarAdaptador()
    {
        if (_lista.Selecionado is not { } a) return;
        try
        {
            Clipboard.SetText(string.Join("\t", a.Nome, a.Ativo ? "UP" : a.Status, a.Mac, a.Ip ?? "—", a.Dhcp ? "DHCP" : "Manual", a.WifiSsid ?? "—"));
            Status("Dados do adaptador copiados para a área de transferência.", Tema.Sucesso);
        }
        catch (Exception ex)
        {
            Status($"Não foi possível copiar os dados: {ex.Message}", Tema.Perigo);
        }
    }

    private void SelecionarAdaptador()
    {
        if (_lista.Selecionado is not { } a) return;
        _modo.DefinirOpcoes(new[] { ("dhcp", "Automático (DHCP)", Tema.Sucesso), ("manual", "Manual", Tema.Acento) }, a.Dhcp ? "dhcp" : "manual");
        _ip.Text = a.Ip ?? "";
        _mascara.Text = a.Mascara ?? "";
        _gateway.Text = a.Gateway ?? "";
        _dns.Text = string.Join(", ", a.Dns);
        _botaoReverter.Enabled = a.ReversaoDisponivel;
        AtualizarCampos();
    }

    private void AtualizarCampos()
    {
        var manual = _modo.ChaveSelecionada == "manual";
        _ip.Enabled = _mascara.Enabled = _gateway.Enabled = _dns.Enabled = manual;
    }

    private async Task AplicarAsync()
    {
        if (_lista.Selecionado is not { } adaptador)
        {
            Status("Selecione um adaptador primeiro.", Tema.Alerta);
            return;
        }
        if (!Confirmar("Aplicar a configuração neste adaptador? A conexão pode cair se os dados estiverem incorretos.")) return;

        var dns = _dns.Text.Split(',', StringSplitOptions.TrimEntries | StringSplitOptions.RemoveEmptyEntries);
        var parametro = JsonSerializer.Serialize(new
        {
            adapter_id = adaptador.AdapterId,
            dhcp = _modo.ChaveSelecionada != "manual",
            ip = _ip.Text.Trim(),
            mascara = _mascara.Text.Trim(),
            gateway = _gateway.Text.Trim(),
            dns
        });
        try
        {
            _botaoAplicar.Enabled = false;
            Status("Aplicando configuração e salvando snapshot...");
            await Task.Run(() => NetworkService.Aplicar(parametro));
            Status("Configuração aplicada. A anterior está salva para reversão.", Tema.Sucesso);
            await AtualizarAdaptadoresAsync();
        }
        catch (Exception ex)
        {
            Status($"Falha ao aplicar: {ex.Message}", Tema.Perigo);
        }
        finally
        {
            _botaoAplicar.Enabled = true;
        }
    }

    private async Task ReverterAsync()
    {
        if (_lista.Selecionado is not { } adaptador) return;
        if (!Confirmar("Restaurar a configuração de rede salva antes da última alteração?")) return;
        try
        {
            Status("Restaurando a configuração anterior...");
            await Task.Run(() => NetworkService.Reverter(adaptador.AdapterId.ToString()));
            Status("Configuração anterior restaurada.", Tema.Sucesso);
            await AtualizarAdaptadoresAsync();
        }
        catch (Exception ex)
        {
            Status($"Falha ao reverter: {ex.Message}", Tema.Perigo);
        }
    }

    private bool Confirmar(string mensagem) =>
        MessageBox.Show(FindForm(), mensagem, "RD Intranet - Rede", MessageBoxButtons.YesNo, MessageBoxIcon.Warning) == DialogResult.Yes;

    // ================================================================ diagnóstico

    private async Task PingAsync()
    {
        try
        {
            _resultadoPing.Mostrar("Testando...", Tema.TextoSecundario);
            var parametro = JsonSerializer.Serialize(new { ip = _alvoPing.Text.Trim(), quantidade = 4 });
            var json = JsonSerializer.Serialize(await Task.Run(() => NetworkService.PingAsync(parametro)));
            using var resultado = JsonDocument.Parse(json);
            var linhas = resultado.RootElement.GetProperty("respostas").EnumerateArray().Select((r, i) => r.GetProperty("sucesso").GetBoolean()
                ? $"Resposta {i + 1}: {r.GetProperty("tempo_ms").GetInt64()} ms"
                : $"Pacote {i + 1}: {r.GetProperty("status").GetString()}").ToList();
            var texto = string.Join(Environment.NewLine, linhas);
            _resultadoPing.Mostrar(texto, texto.Contains("TimedOut", StringComparison.OrdinalIgnoreCase) ? Tema.Alerta : Tema.Sucesso);
        }
        catch (Exception ex)
        {
            _resultadoPing.Mostrar(ex.Message, Tema.Perigo);
        }
    }

    private async Task VelocidadeAsync()
    {
        try
        {
            _resultadoVelocidade.Mostrar("Testando Cloudflare...", Tema.TextoSecundario);
            var json = JsonSerializer.Serialize(await NetworkService.TestarVelocidadeAsync());
            using var resultado = JsonDocument.Parse(json);
            var dados = resultado.RootElement;
            _resultadoVelocidade.Mostrar($"↓ {dados.GetProperty("download_mbps").GetDouble():0.##} Mbps{Environment.NewLine}↑ {dados.GetProperty("upload_mbps").GetDouble():0.##} Mbps", Tema.Sucesso);
        }
        catch (Exception ex)
        {
            _resultadoVelocidade.Mostrar(ex.Message, Tema.Perigo);
        }
    }

    private async Task TraceAsync()
    {
        try
        {
            _resultadoTrace.Mostrar("Rastreando rota...", Tema.TextoSecundario);
            var parametro = JsonSerializer.Serialize(new { ip = _alvoTrace.Text.Trim() });
            var json = JsonSerializer.Serialize(await Task.Run(() => NetworkService.TracerouteAsync(parametro)));
            using var resultado = JsonDocument.Parse(json);
            var dados = resultado.RootElement;
            _resultadoTrace.Mostrar(dados.GetProperty("saida").GetString() ?? "Sem saída do Windows.", dados.GetProperty("timeout").GetBoolean() ? Tema.Alerta : Tema.Texto);
        }
        catch (Exception ex)
        {
            _resultadoTrace.Mostrar(ex.Message, Tema.Perigo);
        }
    }

    // ================================================================ MTR

    private async Task AlternarMtrAsync()
    {
        if (_mtrCancelar != null)
        {
            PararMtr();
            return;
        }

        var destino = _mtrDestino.Text.Trim();
        if (destino == "")
        {
            _mtrDestino.MarcarErro();
            _mtrStatus.Text = "Informe o destino (IP ou nome).";
            _mtrStatus.ForeColor = Tema.Perigo;
            return;
        }

        var intervalo = double.Parse(_mtrIntervalo.ChaveSelecionada ?? "1", System.Globalization.CultureInfo.InvariantCulture);
        var opcoes = new OpcoesMtr(intervalo, _mtrTamanho.Valor, _mtrSaltos.Valor, _mtrResolver.Ligado);
        _mtr = new MtrService();
        _mtrCancelar = new CancellationTokenSource();
        _mtrRodadas = 0;
        _mtrBotao.Text = "Parar";
        _mtrTabela.Definir(new List<SaltoMtr>());
        _mtrStatus.Text = $"Resolvendo {destino}...";
        _mtrStatus.ForeColor = Tema.TextoSecundario;

        var mtr = _mtr;
        mtr.Atualizado += () =>
        {
            // Vem de thread de fundo; a tela redesenha no thread dela.
            if (!IsHandleCreated || IsDisposed) return;
            BeginInvoke(() =>
            {
                if (_mtr != mtr) return;
                _mtrRodadas++;
                var saltos = mtr.Fotografia();
                _mtrTabela.Definir(saltos);
                var destinoFinal = saltos.LastOrDefault();
                _mtrStatus.ForeColor = Tema.Sucesso;
                _mtrStatus.Text = $"●  Rodando · {saltos.Count} saltos · {_mtrRodadas} rodada(s)"
                    + (destinoFinal is { Recebidos: > 0 } ? $" · destino {destinoFinal.Media:0} ms · perda {destinoFinal.PerdaPercentual:0}%" : "");
            });
        };

        LogAtividade.Registrar(NivelAtividade.Info, "REDE", $"MTR iniciado para {destino}.");
        try
        {
            await mtr.ExecutarAsync(destino, opcoes, _mtrCancelar.Token);
        }
        catch (OperationCanceledException)
        {
            // parado pelo usuário
        }
        catch (Exception ex)
        {
            _mtrStatus.Text = ex.Message;
            _mtrStatus.ForeColor = Tema.Perigo;
        }
        finally
        {
            if (_mtr == mtr)
            {
                _mtrCancelar?.Dispose();
                _mtrCancelar = null;
                _mtrBotao.Text = "Iniciar";
                if (_mtrStatus.ForeColor != Tema.Perigo)
                {
                    _mtrStatus.Text = $"Parado · {_mtrRodadas} rodada(s) para {mtr.Destino}. Os números ficam na tabela para exportar.";
                    _mtrStatus.ForeColor = Tema.TextoSecundario;
                }
            }
        }
    }

    private void PararMtr() => _mtrCancelar?.Cancel();

    private void CopiarMtr(bool html)
    {
        if (_mtr?.Destino == null)
        {
            _mtrStatus.Text = "Rode o MTR antes de copiar.";
            return;
        }
        try
        {
            Clipboard.SetText(html ? _mtr.ComoHtml() : _mtr.ComoTexto());
            _mtrStatus.Text = html ? "Tabela copiada em HTML." : "Tabela copiada em texto.";
        }
        catch (Exception ex)
        {
            _mtrStatus.Text = $"Não foi possível copiar: {ex.Message}";
        }
    }

    private void ExportarMtr(bool html)
    {
        if (_mtr?.Destino == null)
        {
            _mtrStatus.Text = "Rode o MTR antes de exportar.";
            return;
        }

        var nomeSeguro = string.Concat(_mtr.HostDigitado.Select(c => Path.GetInvalidFileNameChars().Contains(c) ? '_' : c));
        using var dialogo = new SaveFileDialog
        {
            FileName = $"mtr-{nomeSeguro}-{DateTime.Now:yyyyMMdd-HHmm}.{(html ? "html" : "txt")}",
            Filter = html ? "Página HTML (*.html)|*.html" : "Texto (*.txt)|*.txt",
            InitialDirectory = Environment.GetFolderPath(Environment.SpecialFolder.Desktop)
        };
        if (dialogo.ShowDialog(FindForm()) != DialogResult.OK) return;

        try
        {
            File.WriteAllText(dialogo.FileName, html
                ? $"<!doctype html><html><head><meta charset=\"utf-8\"><title>MTR {System.Net.WebUtility.HtmlEncode(_mtr.HostDigitado)}</title></head><body>{_mtr.ComoHtml()}</body></html>"
                : _mtr.ComoTexto(), System.Text.Encoding.UTF8);
            _mtrStatus.Text = $"Salvo em {dialogo.FileName}";
        }
        catch (Exception ex)
        {
            _mtrStatus.Text = $"Não foi possível salvar: {ex.Message}";
        }
    }

    // ================================================================ layout

    /// <summary>Bloco de uma ferramenta de diagnóstico: título, descrição, campo + botão e o resultado.</summary>
    private static Panel Ferramenta(string titulo, string descricao, CaixaTexto? entrada, BotaoTema botao, ResultadoTerminal resultado)
    {
        var painel = new Panel { Dock = DockStyle.Fill, BackColor = Tema.Superficie, Padding = new Padding(0, 0, 0, 12) };
        var linha = new Panel { Dock = DockStyle.Top, Height = 44, BackColor = Tema.Superficie, Padding = new Padding(0, 0, 0, 4) };
        botao.Width = entrada != null ? 110 : 170;
        botao.Dock = entrada != null ? DockStyle.Right : DockStyle.Left;
        if (entrada != null)
        {
            entrada.Dock = DockStyle.Fill;
            linha.Controls.Add(entrada);
            linha.Controls.Add(new Panel { Width = 8, Dock = DockStyle.Right, BackColor = Tema.Superficie });
        }
        linha.Controls.Add(botao);

        resultado.Dock = DockStyle.Fill;
        painel.Controls.Add(resultado);
        painel.Controls.Add(new Panel { Dock = DockStyle.Top, Height = 6, BackColor = Tema.Superficie });
        painel.Controls.Add(linha);
        painel.Controls.Add(new Label { Dock = DockStyle.Top, Height = 20, Text = descricao, ForeColor = Tema.TextoSecundario, BackColor = Tema.Superficie, Font = Tema.Fonte(8.5F) });
        painel.Controls.Add(new Label { Dock = DockStyle.Top, Height = 22, Text = titulo, ForeColor = Tema.Texto, BackColor = Tema.Superficie, Font = Tema.FonteSemibold(9.5F) });
        return painel;
    }

    private static CartaoSecao Cartao(CabecalhoSecao cabecalho, Control conteudo, int altura)
    {
        var cartao = new CartaoSecao { Height = altura };
        if (conteudo.Dock != DockStyle.Top) conteudo.Dock = DockStyle.Fill;
        cartao.Controls.Add(conteudo);
        cartao.Controls.Add(cabecalho);
        return cartao;
    }

    private static TableLayoutPanel Grade(int colunas)
    {
        var grade = new TableLayoutPanel { ColumnCount = colunas, BackColor = Tema.Superficie, Margin = Padding.Empty, Padding = Padding.Empty };
        for (var i = 0; i < colunas; i++) grade.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 100F / colunas));
        return grade;
    }

    private static void AdicionarLinha(TableLayoutPanel grade, int altura, Control esquerda, Control? direita = null)
    {
        var linha = grade.RowCount;
        grade.RowCount = linha + 1;
        grade.RowStyles.Add(new RowStyle(SizeType.Absolute, altura));
        esquerda.Margin = new Padding(0, 0, direita != null ? 10 : 0, 0);
        grade.Controls.Add(esquerda, 0, linha);
        if (direita == null)
        {
            if (grade.ColumnCount > 1) grade.SetColumnSpan(esquerda, grade.ColumnCount);
        }
        else
        {
            direita.Margin = new Padding(10, 0, 0, 0);
            grade.Controls.Add(direita, 1, linha);
        }
    }

    private static Panel Campo(string rotulo, Control campo)
    {
        var painel = new Panel { Dock = DockStyle.Fill, BackColor = Tema.Superficie };
        campo.Dock = DockStyle.Top;
        painel.Controls.Add(campo);
        painel.Controls.Add(new RotuloCampo(rotulo));
        return painel;
    }
}

/// <summary>Lista de adaptadores desenhada em cartões: ícone, nome, estado, IPv4, MAC, modo e Wi-Fi.</summary>
public sealed class ListaAdaptadores : Control
{
    private const int AlturaLinha = 64;
    private const int Espaco = 8;
    private List<AdaptadorRedeInfo> _itens = new();
    private int _selecionado = -1;
    private int _hover = -1;

    public event EventHandler? SelecaoAlterada;
    public event EventHandler? Copiar;
    public event EventHandler? AlturaAlterada;

    public ListaAdaptadores()
    {
        SetStyle(ControlStyles.AllPaintingInWmPaint | ControlStyles.UserPaint | ControlStyles.OptimizedDoubleBuffer | ControlStyles.ResizeRedraw | ControlStyles.Selectable, true);
        BackColor = Tema.Superficie;
        TabStop = true;
        Height = AlturaLinha;
        var menu = new ContextMenuStrip { Renderer = new RenderizadorMenuEscuro() };
        menu.Items.Add("Copiar dados do adaptador", null, (s, e) => Copiar?.Invoke(this, EventArgs.Empty));
        ContextMenuStrip = menu;
        MouseLeave += (s, e) => { _hover = -1; Invalidate(); };
    }

    public AdaptadorRedeInfo? Selecionado => _selecionado >= 0 && _selecionado < _itens.Count ? _itens[_selecionado] : null;

    public void Definir(List<AdaptadorRedeInfo> itens, int? idSelecionado)
    {
        _itens = itens;
        var indice = idSelecionado.HasValue ? _itens.FindIndex(a => a.AdapterId == idSelecionado.Value) : -1;
        _selecionado = indice >= 0 ? indice : (_itens.Count > 0 ? 0 : -1);
        Height = Math.Max(1, _itens.Count) * (AlturaLinha + Espaco);
        AlturaAlterada?.Invoke(this, EventArgs.Empty);
        Invalidate();
        SelecaoAlterada?.Invoke(this, EventArgs.Empty);
    }

    private int IndiceEm(int y) => y / (AlturaLinha + Espaco) is var i && i < _itens.Count && y % (AlturaLinha + Espaco) < AlturaLinha ? i : -1;

    protected override void OnMouseMove(MouseEventArgs e)
    {
        base.OnMouseMove(e);
        var i = IndiceEm(e.Y);
        Cursor = i >= 0 ? Cursors.Hand : Cursors.Default;
        if (i != _hover) { _hover = i; Invalidate(); }
    }

    protected override void OnMouseDown(MouseEventArgs e)
    {
        base.OnMouseDown(e);
        Focus();
        var i = IndiceEm(e.Y);
        if (i >= 0) Selecionar(i);
    }

    private void Selecionar(int i)
    {
        if (i == _selecionado || i < 0 || i >= _itens.Count) return;
        _selecionado = i;
        Invalidate();
        SelecaoAlterada?.Invoke(this, EventArgs.Empty);
    }

    protected override bool IsInputKey(Keys keyData) => keyData is Keys.Up or Keys.Down || base.IsInputKey(keyData);

    protected override void OnKeyDown(KeyEventArgs e)
    {
        base.OnKeyDown(e);
        if (e.KeyCode == Keys.Down) Selecionar(Math.Min(_itens.Count - 1, _selecionado + 1));
        else if (e.KeyCode == Keys.Up) Selecionar(Math.Max(0, _selecionado - 1));
        else if (e.Control && e.KeyCode == Keys.C) { Copiar?.Invoke(this, EventArgs.Empty); e.Handled = true; }
    }

    protected override void OnGotFocus(EventArgs e) { base.OnGotFocus(e); Invalidate(); }
    protected override void OnLostFocus(EventArgs e) { base.OnLostFocus(e); Invalidate(); }

    protected override void OnPaint(PaintEventArgs e)
    {
        var g = e.Graphics;
        g.SmoothingMode = SmoothingMode.AntiAlias;
        g.Clear(BackColor);

        if (_itens.Count == 0)
        {
            TextRenderer.DrawText(g, "Clique em Atualizar para listar os adaptadores.", Tema.Fonte(9F), new Rectangle(0, 0, Width, AlturaLinha), Tema.TextoSecundario, TextFormatFlags.VerticalCenter);
            return;
        }

        using var fonteIcone = Icones.Fonte(15F);
        using var fonteNome = Tema.FonteSemibold(9.5F);
        using var fonteDetalhe = Tema.Fonte(8.5F);
        using var fonteMono = Tema.FonteMono(9F);
        using var fontePilula = Tema.FonteSemibold(7.5F);

        for (var i = 0; i < _itens.Count; i++)
        {
            var a = _itens[i];
            var r = new Rectangle(1, i * (AlturaLinha + Espaco) + 1, Width - 3, AlturaLinha - 2);
            var selecionado = i == _selecionado;
            using (var caminho = Tema.Arredondado(r, 10))
            {
                using var fundo = new SolidBrush(selecionado ? Color.FromArgb(28, 88, 166, 255) : i == _hover ? Tema.SuperficieElevada : Color.FromArgb(14, 18, 24));
                g.FillPath(fundo, caminho);
                using var borda = new Pen(selecionado ? Tema.Acento : Focused && i == _selecionado ? Tema.Acento : Tema.Borda, selecionado ? 1.5F : 1F);
                g.DrawPath(borda, caminho);
            }

            // ícone: Wi-Fi ou cabo
            var ehWifi = !string.IsNullOrEmpty(a.WifiSsid) || a.Nome.Contains("Wi-Fi", StringComparison.OrdinalIgnoreCase) || a.Descricao.Contains("Wireless", StringComparison.OrdinalIgnoreCase);
            var caixaIcone = new Rectangle(r.X + 12, r.Y + (r.Height - 40) / 2, 40, 40);
            using (var caminhoIcone = Tema.Arredondado(caixaIcone, 9))
            {
                if (a.Ativo)
                {
                    using var gradiente = new LinearGradientBrush(caixaIcone, Tema.AcentoEscuro, Tema.Ciano, 45F);
                    g.FillPath(gradiente, caminhoIcone);
                }
                else
                {
                    using var cinza = new SolidBrush(Tema.SuperficieElevada);
                    g.FillPath(cinza, caminhoIcone);
                }
            }
            TextRenderer.DrawText(g, ehWifi ? "" : "", fonteIcone, caixaIcone, a.Ativo ? Color.White : Tema.TextoApagado,
                TextFormatFlags.HorizontalCenter | TextFormatFlags.VerticalCenter);

            // nome + descrição (SSID ou modelo da placa)
            var x = caixaIcone.Right + 12;
            var larguraNome = Math.Max(120, (int)(r.Width * 0.34));
            TextRenderer.DrawText(g, a.Nome, fonteNome, new Rectangle(x, r.Y + 12, larguraNome, 20), Tema.Texto, TextFormatFlags.EndEllipsis | TextFormatFlags.NoPrefix);
            var sub = !string.IsNullOrEmpty(a.WifiSsid) ? $"Wi-Fi · {a.WifiSsid}" : a.Descricao;
            TextRenderer.DrawText(g, sub, fonteDetalhe, new Rectangle(x, r.Y + 33, larguraNome, 18), Tema.TextoSecundario, TextFormatFlags.EndEllipsis | TextFormatFlags.NoPrefix);

            // IPv4 e MAC
            var xIp = x + larguraNome + 12;
            var larguraIp = Math.Max(110, r.Right - xIp - 170);
            TextRenderer.DrawText(g, a.Ip ?? "sem IPv4", fonteMono, new Rectangle(xIp, r.Y + 12, larguraIp, 20), a.Ip != null ? Tema.Texto : Tema.TextoApagado, TextFormatFlags.EndEllipsis);
            TextRenderer.DrawText(g, a.Mac, fonteDetalhe, new Rectangle(xIp, r.Y + 33, larguraIp, 18), Tema.TextoSecundario, TextFormatFlags.EndEllipsis);

            // pílulas: estado e modo
            var linkDown = a.Status.Equals("Down", StringComparison.OrdinalIgnoreCase);
            var (textoEstado, corEstado) = a.Ativo ? ("CONECTADO", Tema.Sucesso) : linkDown ? ("DESCONECTADO", Tema.Perigo) : ("DESCONHECIDO", Tema.TextoSecundario);
            DesenharPilula(g, fontePilula, textoEstado, corEstado, new Point(r.Right - 12, r.Y + 12));
            DesenharPilula(g, fontePilula, a.Dhcp ? "DHCP" : "MANUAL", a.Dhcp ? Tema.Ciano : Tema.Alerta, new Point(r.Right - 12, r.Y + 36));
        }
    }

    /// <summary>Pílula alinhada pela direita em <paramref name="direitaTopo"/>.</summary>
    private static void DesenharPilula(Graphics g, Font fonte, string texto, Color cor, Point direitaTopo)
    {
        var tamanho = TextRenderer.MeasureText(texto, fonte);
        var r = new Rectangle(direitaTopo.X - tamanho.Width - 18, direitaTopo.Y, tamanho.Width + 18, 18);
        using var caminho = Tema.Arredondado(r, 9);
        using var fundo = new SolidBrush(Color.FromArgb(38, cor));
        g.FillPath(fundo, caminho);
        using var ponto = new SolidBrush(cor);
        g.FillEllipse(ponto, r.X + 7, r.Y + 7, 5, 5);
        TextRenderer.DrawText(g, texto, fonte, new Rectangle(r.X + 12, r.Y, r.Width - 12, r.Height), cor, TextFormatFlags.HorizontalCenter | TextFormatFlags.VerticalCenter);
    }
}

/// <summary>Área de resultado com cara de terminal: fundo escuro arredondado e fonte monoespaçada.</summary>
public sealed class ResultadoTerminal : Panel
{
    private readonly RichTextBox _texto;
    private bool _fonteMaior;

    public ResultadoTerminal()
    {
        SetStyle(ControlStyles.AllPaintingInWmPaint | ControlStyles.UserPaint | ControlStyles.OptimizedDoubleBuffer | ControlStyles.ResizeRedraw, true);
        BackColor = Tema.Superficie;
        Padding = new Padding(12, 10, 6, 8);
        _texto = new RichTextBoxRepassaRolagem
        {
            Dock = DockStyle.Fill,
            ReadOnly = true,
            DetectUrls = false,
            BorderStyle = BorderStyle.None,
            ScrollBars = RichTextBoxScrollBars.Vertical,
            BackColor = Color.FromArgb(8, 11, 15),
            ForeColor = Tema.TextoApagado,
            Font = Tema.FonteMono(9F),
            Text = "Aguardando teste..."
        };
        Tema.TemaEscuroNativo(_texto);
        Controls.Add(_texto);
    }

    /// <summary>Resultado curto em destaque (ex.: velocidade em Mbps).</summary>
    public bool FonteMaior
    {
        get => _fonteMaior;
        set => _fonteMaior = value;
    }

    public void Mostrar(string texto, Color cor)
    {
        _texto.Font = _fonteMaior && cor == Tema.Sucesso ? Tema.FonteSemibold(15F) : Tema.FonteMono(9F);
        _texto.ForeColor = cor;
        _texto.Text = texto;
    }

    protected override void OnPaint(PaintEventArgs e)
    {
        var g = e.Graphics;
        g.SmoothingMode = SmoothingMode.AntiAlias;
        g.Clear(Parent?.BackColor ?? Tema.Superficie);
        using var caminho = Tema.Arredondado(new Rectangle(0, 0, Width - 1, Height - 1), 8);
        using var fundo = new SolidBrush(Color.FromArgb(8, 11, 15));
        g.FillPath(fundo, caminho);
        using var borda = new Pen(Tema.BordaSuave);
        g.DrawPath(borda, caminho);
    }
}

/// <summary>
/// RichTextBox que devolve a rodinha pra página quando o texto cabe inteiro --
/// a RichTextBox padrão fica com a mensagem mesmo sem ter o que rolar, e a
/// página parava de rolar com o ponteiro em cima de um resultado.
/// </summary>
internal sealed class RichTextBoxRepassaRolagem : RichTextBox
{
    private const int WmMouseWheel = 0x020A;

    [System.Runtime.InteropServices.DllImport("user32.dll")]
    private static extern IntPtr SendMessage(IntPtr hwnd, int msg, IntPtr wParam, IntPtr lParam);

    protected override void WndProc(ref Message m)
    {
        if (m.Msg == WmMouseWheel && Parent != null)
        {
            var fimDoTexto = GetPositionFromCharIndex(Math.Max(0, TextLength - 1)).Y + Font.Height;
            var cabe = GetPositionFromCharIndex(0).Y >= 0 && fimDoTexto <= ClientSize.Height;
            if (cabe)
            {
                SendMessage(Parent.Handle, m.Msg, m.WParam, m.LParam);
                return;
            }
        }
        base.WndProc(ref m);
    }
}
