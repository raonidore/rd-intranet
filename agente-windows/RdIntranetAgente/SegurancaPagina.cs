using System.Diagnostics;
using System.Drawing;
using System.Net;
using System.Text;
using System.Text.Json;
using System.Windows.Forms;

namespace RdIntranetAgente;

/// <summary>
/// Página "Segurança" do painel do agente: quem está na frente da máquina
/// vê e age na hora, sem depender do portal. Abas:
///  - Eventos: o que os detectores acharam, com o detalhe completo;
///  - Processos: o que está rodando com pontuação de risco (motivos);
///  - Inicialização: tudo que o Windows inicia sozinho, com desativar/reativar;
///  - Conexões: quem conversa com quem, consulta de reputação do IP;
///  - Firewall: estado, regras, ligar/desligar, bloquear com um clique;
///  - Quarentena: o que foi isolado, restaurar ou excluir de vez.
/// Toda ação reversível (AcoesSeguranca) e confirmada antes.
/// </summary>
public sealed class SegurancaPagina : Panel
{
    private readonly SegurancaService _seguranca;
    private readonly MonitorRedeService _monitor;

    private readonly Label _avisoIsolamento;
    private readonly Label _cartaoCanary, _cartaoFim, _cartaoShadow, _cartaoMonitor;
    private readonly SeletorSegmentado _abas;
    private readonly FlowLayoutPanel _barra;
    private readonly TextBox _filtro;
    private readonly Label _status;
    private readonly ListView _lista;
    private readonly TextBox _detalhe;

    private string _aba = "eventos";
    private string _assinaturaEventos = "";
    private bool _mostrarTudo;
    private bool _ocupado;

    private List<ItemInicializacao>? _inicializacao;
    private DateTime _inicializacaoEm;

    private sealed record LinhaProcesso(ProcessoInfo Processo, InfoExecutavel Exe, Avaliacao Avaliacao);
    private sealed record LinhaConexao(ProcessoInfo? Processo, int Pid, string Estado, string Ip, int Porta, int Qtd, bool Publico, Avaliacao Avaliacao);

    public SegurancaPagina(SegurancaService seguranca, MonitorRedeService monitor)
    {
        _seguranca = seguranca;
        _monitor = monitor;
        Dock = DockStyle.Fill;
        BackColor = Tema.Fundo;
        Visible = false;

        var titulo = new Panel { Height = 68, BackColor = Tema.Fundo };
        titulo.Controls.Add(new Label { Text = "Segurança", Location = new Point(0, 0), Size = new Size(600, 34), ForeColor = Tema.Texto, Font = Tema.FonteSemibold(19F) });
        titulo.Controls.Add(new Label { Text = "O que os detectores encontraram e o que fazer, aqui mesmo nesta máquina.", Location = new Point(0, 38), Size = new Size(690, 24), ForeColor = Tema.TextoSecundario, Font = Tema.Fonte(9F) });

        _avisoIsolamento = new Label
        {
            Height = 38, BackColor = Color.FromArgb(54, Tema.Perigo), ForeColor = Tema.Perigo, Font = Tema.FonteSemibold(9F),
            TextAlign = ContentAlignment.MiddleLeft, Padding = new Padding(12, 0, 0, 0), Visible = false,
            Text = "REDE ISOLADA PELA TI  ·  A conectividade desta máquina foi bloqueada."
        };

        var cartoes = new TableLayoutPanel { Height = 58, ColumnCount = 4, RowCount = 1, BackColor = Tema.Fundo, Padding = Padding.Empty };
        for (var i = 0; i < 4; i++) cartoes.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 25F));
        _cartaoCanary = Cartao(); _cartaoFim = Cartao(); _cartaoShadow = Cartao(); _cartaoMonitor = Cartao();
        cartoes.Controls.Add(_cartaoCanary, 0, 0);
        cartoes.Controls.Add(_cartaoFim, 1, 0);
        cartoes.Controls.Add(_cartaoShadow, 2, 0);
        cartoes.Controls.Add(_cartaoMonitor, 3, 0);

        _abas = new SeletorSegmentado { Height = 40 };
        _abas.DefinirOpcoes(new[]
        {
            ("eventos", "Eventos", Tema.Acento), ("processos", "Processos", Tema.Acento), ("inicializacao", "Inicialização", Tema.Acento),
            ("conexoes", "Conexões", Tema.Acento), ("firewall", "Firewall", Tema.Acento), ("quarentena", "Quarentena", Tema.Acento)
        }, "eventos");
        _abas.SelecaoAlterada += (s, e) => { _aba = _abas.ChaveSelecionada ?? "eventos"; _mostrarTudo = false; _filtro.Text = ""; MontarAba(); };

        _barra = new FlowLayoutPanel { Height = 46, BackColor = Tema.Fundo, WrapContents = false, Padding = new Padding(0, 6, 0, 0) };
        _filtro = new TextBox { Width = 200, BorderStyle = BorderStyle.FixedSingle, BackColor = Tema.Campo, ForeColor = Tema.Texto, PlaceholderText = "Filtrar...", Margin = new Padding(0, 6, 10, 0) };
        _filtro.TextChanged += (s, e) => Preencher();

        _status = new Label { Height = 24, ForeColor = Tema.TextoSecundario, TextAlign = ContentAlignment.MiddleLeft, AutoEllipsis = true };

        _lista = MainForm.CriarLista(new[] { ("", 10) });
        _lista.MultiSelect = false;
        _lista.SelectedIndexChanged += (s, e) => MostrarDetalhe();
        _lista.DoubleClick += (s, e) => MostrarDetalhe();

        _detalhe = new TextBox
        {
            Height = 170, Multiline = true, ReadOnly = true, ScrollBars = ScrollBars.Vertical, WordWrap = true,
            BackColor = Tema.Superficie, ForeColor = Tema.Texto, BorderStyle = BorderStyle.None, Font = Tema.FonteMono(9F)
        };
        var molduraDetalhe = new Panel { Height = 182, BackColor = Tema.Fundo, Padding = new Padding(0, 8, 0, 0) };
        _detalhe.Dock = DockStyle.Fill;
        molduraDetalhe.Controls.Add(_detalhe);

        // Empilha de cima pra baixo (Dock Top), lista no meio, detalhe embaixo.
        foreach (var bloco in new Control[] { titulo, _avisoIsolamento, cartoes, Espaco(10), _abas, _barra, _status })
        {
            bloco.Dock = DockStyle.Top;
            Controls.Add(bloco);
            bloco.BringToFront();
        }
        molduraDetalhe.Dock = DockStyle.Bottom;
        Controls.Add(molduraDetalhe);
        _lista.Dock = DockStyle.Fill;
        Controls.Add(_lista);
        _lista.BringToFront();

        MontarAba();
    }

    private static Label Cartao() => new()
    {
        Dock = DockStyle.Fill, BackColor = Tema.Superficie, ForeColor = Tema.TextoSecundario, Font = Tema.FonteSemibold(8.5F),
        TextAlign = ContentAlignment.MiddleCenter, Margin = new Padding(0, 0, 8, 0)
    };

    private static Panel Espaco(int altura) => new() { Height = altura, BackColor = Tema.Fundo };

    /// <summary>Troca de aba por código (modo de teste com prints).</summary>
    internal void SelecionarAba(string aba)
    {
        _abas.DefinirOpcoes(new[]
        {
            ("eventos", "Eventos", Tema.Acento), ("processos", "Processos", Tema.Acento), ("inicializacao", "Inicialização", Tema.Acento),
            ("conexoes", "Conexões", Tema.Acento), ("firewall", "Firewall", Tema.Acento), ("quarentena", "Quarentena", Tema.Acento)
        }, aba);
        _aba = aba;
        _mostrarTudo = false;
        MontarAba();
    }

    internal bool Carregando => _status.Text.StartsWith("Lendo") || _status.Text.StartsWith("Analisando");

    /// <summary>Chamado pelo timer do painel (1,5 s) com a página visível: cartões e eventos novos.</summary>
    public void Atualizar()
    {
        var status = _seguranca.ObterStatus();
        Definir(_cartaoCanary, "ARQUIVOS-ISCA", status.CanaryAtivo, $"{status.CanaryArquivos} arquivos");
        Definir(_cartaoFim, "MUDANÇA EM MASSA", status.FimAtivo, $"{status.FimAlteracoesJanela} na janela");
        Definir(_cartaoShadow, "SHADOW COPIES", status.ShadowAtivo, status.ShadowContagem.HasValue ? $"{status.ShadowContagem} cópias" : status.ShadowErro ?? "aguardando");
        Definir(_cartaoMonitor, "CONEXÕES E PROCESSOS", _monitor.Ativo,
            _monitor.Ativo ? $"a cada {_monitor.IntervaloSegundos} s" + (_monitor.UltimaAmostraEm is { } u ? $" · {u:HH:mm:ss}" : "") : "desligado no servidor");
        _avisoIsolamento.Visible = status.Configuracao?.Isolado == true;

        if (_aba == "eventos")
        {
            var assinatura = string.Join("|", status.EventosRecentes.Select(e => $"{e.OcorridoEm.Ticks}:{e.Enviado}"));
            if (assinatura != _assinaturaEventos)
            {
                _assinaturaEventos = assinatura;
                Preencher();
            }
        }
    }

    private static void Definir(Label cartao, string titulo, bool ativo, string detalhe)
    {
        cartao.Text = $"{titulo}\n{(ativo ? "ATIVO" : "DESATIVADO")}  ·  {detalhe}";
        cartao.ForeColor = ativo ? Tema.Sucesso : Tema.TextoSecundario;
    }

    // ------------------------------------------------------------------
    // Abas
    // ------------------------------------------------------------------

    private void MontarAba()
    {
        _barra.Controls.Clear();
        _barra.Controls.Add(_filtro);
        _lista.Items.Clear();
        _lista.Columns.Clear();
        _lista.ListViewItemSorter = null; // colunas mudam de uma aba pra outra
        _detalhe.Text = "";

        switch (_aba)
        {
            case "eventos":
                Colunas(("Quando", 110), ("Severidade", 85), ("Tipo", 190), ("Detecção", 400));
                Botao("Copiar detalhe", () => { if (_detalhe.Text != "") Clipboard.SetText(_detalhe.Text); });
                _status.Text = "Clique num evento para ver tudo o que foi encontrado (todos os itens, motivos e caminhos).";
                _assinaturaEventos = "";
                Preencher();
                break;

            case "processos":
                Colunas(("Nível", 75), ("Pontos", 55), ("Processo", 170), ("PID", 60), ("Assinatura", 170), ("Caminho", 380));
                Botao("Atualizar", () => CarregarProcessos());
                Botao(_mostrarTudo ? "Só suspeitos" : "Mostrar todos", () => { _mostrarTudo = !_mostrarTudo; MontarAba(); });
                Botao("Encerrar", () => AcaoProcesso("encerrar"), BotaoTema.Variante.Perigo);
                Botao("Quarentena", () => AcaoProcesso("quarentena"), BotaoTema.Variante.Perigo);
                Botao("Bloquear rede", () => AcaoProcesso("bloquear"));
                Botao("Confiar...", () => Confiar(Selecionado<LinhaProcesso>()?.Processo is { } p ? AnaliseExecutavel.ProgramaReal(p.Caminho, p.LinhaComando) : null));
                Botao("Abrir local", () => AbrirLocal(Selecionado<LinhaProcesso>()?.Processo.Caminho));
                Botao("VirusTotal", () => VirusTotal(Selecionado<LinhaProcesso>()?.Processo.Caminho));
                CarregarProcessos();
                break;

            case "inicializacao":
                Colunas(("Nível", 75), ("Tipo", 110), ("Nome", 230), ("Estado", 150), ("Caminho", 380));
                Botao("Atualizar", () => CarregarInicializacao(true));
                Botao(_mostrarTudo ? "Ocultar Microsoft" : "Mostrar Microsoft", () => { _mostrarTudo = !_mostrarTudo; MontarAba(); });
                Botao("Desativar", () => AcaoInicializacao(false), BotaoTema.Variante.Perigo);
                Botao("Reativar", () => AcaoInicializacao(true));
                Botao("Quarentena do arquivo", () => AcaoInicializacaoQuarentena(), BotaoTema.Variante.Perigo);
                Botao("Confiar...", () => Confiar(Selecionado<ItemInicializacao>() is { } i ? AnaliseExecutavel.ProgramaReal(i.Exe.Caminho, i.Comando) : null));
                Botao("Abrir local", () => AbrirLocal(Selecionado<ItemInicializacao>()?.Exe.Caminho));
                CarregarInicializacao(false);
                break;

            case "conexoes":
                Colunas(("Nível", 75), ("Programa", 170), ("Estado", 100), ("Remoto", 210), ("Conexões", 75), ("Caminho", 330));
                Botao("Atualizar", () => CarregarConexoes());
                Botao("Consultar IP", () => ConsultarIp());
                Botao("Bloquear IP", () => AcaoConexao("bloquear_ip"), BotaoTema.Variante.Perigo);
                Botao("Bloquear programa", () => AcaoConexao("bloquear_programa"), BotaoTema.Variante.Perigo);
                Botao("Encerrar", () => AcaoConexao("encerrar"), BotaoTema.Variante.Perigo);
                Botao("Confiar...", () => Confiar(Selecionado<LinhaConexao>()?.Processo?.Caminho));
                CarregarConexoes();
                break;

            case "firewall":
                Colunas(("Ação", 80), ("Direção", 75), ("Ativa", 55), ("Regra", 330), ("Programa / IP", 380));
                Botao("Atualizar", () => CarregarFirewall());
                Botao(_mostrarTudo ? "Só bloqueios" : "Todas as regras", () => { _mostrarTudo = !_mostrarTudo; MontarAba(); });
                Botao("Bloquear IP...", () => BloquearIpManual(), BotaoTema.Variante.Perigo);
                Botao("Bloquear programa...", () => BloquearProgramaManual(), BotaoTema.Variante.Perigo);
                Botao("Permitir programa...", () => PermitirProgramaManual());
                Botao("Remover regra do RD", () => RemoverRegra());
                CarregarFirewall();
                break;

            case "quarentena":
                Colunas(("Quando", 120), ("Original", 420), ("Motivo", 360));
                Botao("Atualizar", () => Preencher());
                Botao("Restaurar", () => AcaoQuarentena(true));
                Botao("Excluir definitivamente", () => AcaoQuarentena(false), BotaoTema.Variante.Perigo);
                Botao("Abrir pasta", () => { Directory.CreateDirectory(AcoesSeguranca.PastaQuarentena); Process.Start("explorer.exe", $"\"{AcoesSeguranca.PastaQuarentena}\""); });
                _status.Text = $"Arquivos movidos para {AcoesSeguranca.PastaQuarentena} com extensão .quarentena (não executam).";
                Preencher();
                break;
        }
    }

    /// <summary>
    /// Um botão só, que segue o estado real: com tudo ligado oferece
    /// "Desligar firewall"; com algum perfil desligado, "Ligar firewall" em
    /// destaque. Antes eram dois botões ("Ligar firewall" e um "Desligar"
    /// vermelho) e o vermelho era lido como status "desligado".
    /// </summary>
    private void BotoesCamadas(Dictionary<string, object?> estado)
    {
        foreach (var antigo in _barra.Controls.OfType<BotaoTema>().Where(b => (b.Tag as string) == "fw-liga").ToList())
        {
            _barra.Controls.Remove(antigo);
            antigo.Dispose();
        }
        if (estado.GetValueOrDefault("perfis") is not IEnumerable<object> perfis) return;

        var posicao = 3; // depois de Filtro, Atualizar e Todas as regras
        foreach (var perfil in perfis)
        {
            var json = JsonSerializer.SerializeToElement(perfil);
            var chave = json.GetProperty("perfil").GetString() ?? "";
            var ligado = json.GetProperty("ligado").GetBoolean();
            var rotulo = AcoesSeguranca.Perfis.FirstOrDefault(p => p.Chave == chave).Rotulo ?? chave;
            var texto = $"{(ligado ? "●" : "○")}  {rotulo}: {(ligado ? "ligado" : "DESLIGADO")}";
            var botao = new BotaoTema(texto, ligado ? BotaoTema.Variante.Secundario : BotaoTema.Variante.Primario)
            {
                Tag = "fw-liga",
                Width = TextRenderer.MeasureText(texto, Tema.FonteSemibold(9.5F)).Width + 30,
                Margin = new Padding(0, 0, 6, 0),
                ForeColor = ligado ? Tema.Sucesso : Color.White
            };
            var dica = new ToolTip();
            dica.SetToolTip(botao, ligado ? $"Clique para DESLIGAR a camada {rotulo}" : $"Clique para LIGAR a camada {rotulo}");
            botao.Click += (s, e) => { if (!_ocupado) AcaoFirewallPerfil(!ligado, chave, rotulo); };
            _barra.Controls.Add(botao);
            _barra.Controls.SetChildIndex(botao, Math.Min(posicao++, _barra.Controls.Count - 1));
        }
    }

    private void Colunas(params (string titulo, int largura)[] colunas)
    {
        foreach (var (t, l) in colunas) _lista.Columns.Add(t, l);
    }

    private void Botao(string texto, Action acao, BotaoTema.Variante variante = BotaoTema.Variante.Secundario)
    {
        var botao = new BotaoTema(texto, variante) { AutoSize = false, Width = TextRenderer.MeasureText(texto, Tema.FonteSemibold(9.5F)).Width + 28, Margin = new Padding(0, 0, 6, 0) };
        botao.Click += (s, e) => { if (!_ocupado) acao(); };
        _barra.Controls.Add(botao);
    }

    // ------------------------------------------------------------------
    // Carga de dados (pesada roda fora da tela)
    // ------------------------------------------------------------------

    private object? _dados;

    private void Carregar(string mensagem, Func<object> buscar)
    {
        var aba = _aba;
        _status.Text = mensagem;
        Task.Run(() =>
        {
            object resultado;
            try { resultado = buscar(); }
            catch (Exception ex) { resultado = ex; }
            if (IsDisposed || !IsHandleCreated) return;
            BeginInvoke(() =>
            {
                if (_aba != aba) return;
                if (resultado is Exception erro) { _status.Text = "Falha: " + erro.Message; return; }
                _dados = resultado;
                Preencher();
            });
        });
    }

    private void CarregarProcessos() => Carregar("Analisando os processos em execução...", () =>
    {
        var processos = CatalogoProcessos.Obter(Array.Empty<int>(), forcar: true);
        return processos.Values
            .Where(p => !string.IsNullOrEmpty(p.Caminho))
            .Select(p =>
            {
                var exe = AnaliseExecutavel.Analisar(p.Caminho);
                return new LinhaProcesso(p, exe, AnaliseExecutavel.Avaliar(exe, p.LinhaComando, p.ComoSystem, p.Nome));
            })
            .OrderByDescending(l => l.Avaliacao.Pontos).ThenBy(l => l.Processo.Nome)
            .ToList();
    });

    private void CarregarInicializacao(bool forcar)
    {
        if (!forcar && _inicializacao != null && DateTime.Now - _inicializacaoEm < TimeSpan.FromMinutes(10))
        {
            _dados = _inicializacao;
            Preencher();
            return;
        }
        Carregar("Lendo serviços, tarefas, registro e pastas de inicialização (alguns segundos)...", () =>
        {
            _inicializacao = InicializacaoService.Coletar();
            _inicializacaoEm = DateTime.Now;
            return _inicializacao;
        });
    }

    private void CarregarConexoes() => Carregar("Lendo as conexões...", () =>
    {
        var conexoes = TabelaTcp.Ler();
        var processos = CatalogoProcessos.Obter(conexoes.Select(c => c.Pid).Distinct(), forcar: true);
        return conexoes
            .Where(c => c.Estado is "ESTABLISHED" or "SYN_SENT" && !TabelaTcp.EhLocal(c.Remoto))
            .GroupBy(c => (c.Pid, c.Estado, Ip: TabelaTcp.Texto(c.Remoto), c.PortaRemota))
            .Select(g =>
            {
                processos.TryGetValue(g.Key.Pid, out var p);
                var avaliacao = p?.Caminho != null ? AnaliseExecutavel.Avaliar(AnaliseExecutavel.Analisar(p.Caminho), p.LinhaComando, p.ComoSystem, p.Nome) : new Avaliacao();
                return new LinhaConexao(p, g.Key.Pid, g.Key.Estado, g.Key.Ip, g.Key.PortaRemota, g.Count(), TabelaTcp.EhPublico(g.First().Remoto), avaliacao);
            })
            .OrderByDescending(l => l.Avaliacao.Pontos).ThenByDescending(l => l.Publico).ThenBy(l => l.Processo?.Nome)
            .ToList();
    });

    private void CarregarFirewall() => Carregar("Lendo o firewall do Windows...", () => (FirewallService.Estado(), FirewallService.ListarRegras()));

    // ------------------------------------------------------------------
    // Lista
    // ------------------------------------------------------------------

    private void Preencher()
    {
        var filtro = _filtro.Text.Trim();
        bool Passa(params string?[] textos) => filtro == "" || textos.Any(t => t != null && t.Contains(filtro, StringComparison.OrdinalIgnoreCase));
        var itens = new List<ListViewItem>();

        switch (_aba)
        {
            case "eventos":
                foreach (var e in _seguranca.ObterStatus().EventosRecentes.Where(e => Passa(e.Resumo, e.Tipo)))
                {
                    itens.Add(Linha(e, e.Severidade == "CRITICAL" ? Tema.Perigo : e.Severidade == "WARNING" ? Tema.Alerta : Tema.Texto,
                        e.OcorridoEm.LocalDateTime.ToString("dd/MM HH:mm:ss"), e.Severidade, RotuloTipo(e.Tipo), e.Resumo));
                }
                break;

            case "processos" when _dados is List<LinhaProcesso> processos:
                var visiveis = processos.Where(l => (_mostrarTudo || l.Avaliacao.Pontos >= 15) && Passa(l.Processo.Nome, l.Processo.Caminho)).ToList();
                foreach (var l in visiveis)
                {
                    itens.Add(Linha(l, RotuloNivel(l.Avaliacao) == "Confiável" ? Tema.Sucesso : Cor(l.Avaliacao.Nivel), RotuloNivel(l.Avaliacao), l.Avaliacao.Pontos.ToString(), l.Processo.Nome, l.Processo.Pid.ToString(),
                        RotuloAssinatura(l.Exe), l.Processo.Caminho ?? ""));
                }
                _status.Text = $"{processos.Count(l => l.Avaliacao.Pontos >= 35)} suspeito(s) entre {processos.Count} processos. "
                    + (_mostrarTudo ? "Mostrando todos." : "Mostrando os que têm algum ponto de atenção.");
                break;

            case "inicializacao" when _dados is List<ItemInicializacao> inicio:
                foreach (var i in inicio.Where(i => (_mostrarTudo || !(i.Exe.Microsoft && i.Avaliacao.Pontos == 0)) && Passa(i.Nome, i.Comando, i.Origem)))
                {
                    var situacao = string.Join(" · ", new[] { i.Estado, i.Inicio }.Where(x => !string.IsNullOrEmpty(x)));
                    itens.Add(Linha(i, i.Estado == "Desativado pelo RD.Intranet" ? Tema.TextoApagado : Cor(i.Avaliacao.Nivel),
                        i.Exe.Microsoft && i.Avaliacao.Pontos == 0 ? "Microsoft" : RotuloNivel(i.Avaliacao), RotuloTipoInicio(i.Tipo), i.Nome, situacao, i.Exe.Caminho));
                }
                _status.Text = $"{inicio.Count(i => i.Avaliacao.Pontos >= 35)} suspeito(s) entre {inicio.Count} itens · lido às {_inicializacaoEm:HH:mm}."
                    + " Desativar guarda backup: dá para reativar depois.";
                break;

            case "conexoes" when _dados is List<LinhaConexao> conexoes:
                foreach (var c in conexoes.Where(c => Passa(c.Processo?.Nome, c.Ip, c.Porta.ToString(), c.Processo?.Caminho)))
                {
                    itens.Add(Linha(c, c.Estado == "SYN_SENT" ? Tema.Alerta : RotuloNivel(c.Avaliacao) == "Confiável" ? Tema.Sucesso : Cor(c.Avaliacao.Nivel), RotuloNivel(c.Avaliacao), c.Processo?.Nome ?? $"PID {c.Pid}",
                        c.Estado == "SYN_SENT" ? "tentando" : "conectado", $"{c.Ip}:{c.Porta}{(c.Publico ? "" : "  (rede local)")}", c.Qtd.ToString(), c.Processo?.Caminho ?? ""));
                }
                _status.Text = $"{conexoes.Count(c => c.Publico && c.Estado == "ESTABLISHED")} conversa(s) com a internet, {conexoes.Count(c => c.Estado == "SYN_SENT")} tentativa(s) em andamento. "
                    + "\"Consultar IP\" abre a reputação no AbuseIPDB.";
                break;

            case "firewall" when _dados is ValueTuple<Dictionary<string, object?>, List<FirewallService.RegraFirewall>> fw:
                var (estado, regras) = fw;
                foreach (var r in regras.Where(r => (_mostrarTudo || r.Acao == "bloquear" || r.DoRd) && Passa(r.Nome, r.Programa, r.Ips)).OrderByDescending(r => r.DoRd).ThenBy(r => r.Nome))
                {
                    itens.Add(Linha(r, r.DoRd ? (r.Acao == "permitir" ? Tema.Sucesso : Tema.Ciano) : r.Acao == "bloquear" ? Tema.Alerta : Tema.Texto, r.Acao, r.Direcao, r.Habilitada ? "sim" : "não",
                        r.Nome, r.Programa ?? r.Ips ?? (r.Portas != null ? "portas " + r.Portas : "qualquer")));
                }
                var algumDesligado = TextoPerfis(estado).Contains("DESLIGADO");
                _status.Text = (algumDesligado ? "⚠ " : "✓ ") + TextoPerfis(estado) + $"  ·  {regras.Count} regras ({regras.Count(r => r.DoRd)} do RD.Intranet)";
                _status.ForeColor = algumDesligado ? Tema.Alerta : Tema.Sucesso;
                BotoesCamadas(estado);
                break;

            case "quarentena":
                foreach (var q in AcoesSeguranca.ListarQuarentena().OrderByDescending(q => q.Quando).Where(q => Passa(q.Original, q.Motivo)))
                {
                    itens.Add(Linha(q, Tema.Texto, q.Quando.ToString("dd/MM/yy HH:mm"), q.Original + (q.Pasta ? "  (pasta)" : ""), q.Motivo));
                }
                break;
        }

        if (_aba != "firewall") _status.ForeColor = Tema.TextoSecundario;
        _lista.BeginUpdate();
        _lista.Items.Clear();
        _lista.Items.AddRange(itens.ToArray());
        _lista.EndUpdate();
        if (_lista.Items.Count > 0 && _lista.SelectedItems.Count == 0) _lista.Items[0].Selected = true;
        _lista.PerformLayout(); // última coluna ocupa a sobra (sem isso o cabeçalho deixava uma faixa branca)
    }

    private static ListViewItem Linha(object tag, Color cor, params string[] colunas)
    {
        var item = new ListViewItem(colunas[0]) { Tag = tag, ForeColor = cor };
        foreach (var c in colunas.Skip(1)) item.SubItems.Add(c);
        return item;
    }

    private T? Selecionado<T>() where T : class => _lista.SelectedItems.Count > 0 ? _lista.SelectedItems[0].Tag as T : null;

    private static string TextoPerfis(Dictionary<string, object?> estado)
    {
        if (estado.TryGetValue("erro", out var erro) && erro != null) return "Firewall: " + erro;
        if (estado.GetValueOrDefault("perfis") is not IEnumerable<object> perfis) return "";
        var partes = perfis.Select(p =>
        {
            var json = JsonSerializer.SerializeToElement(p);
            var nome = json.GetProperty("perfil").GetString() switch { "dominio" => "Domínio", "privado" => "Privado", _ => "Público" };
            return $"{nome}: {(json.GetProperty("ligado").GetBoolean() ? "ligado" : "DESLIGADO")}";
        });
        return "Firewall do Windows -- " + string.Join(" · ", partes) + $"  (em uso: {estado.GetValueOrDefault("perfil_ativo")})";
    }

    // ------------------------------------------------------------------
    // Detalhe
    // ------------------------------------------------------------------

    private void MostrarDetalhe()
    {
        if (_lista.SelectedItems.Count == 0) { _detalhe.Text = ""; return; }
        var t = new StringBuilder();
        void L(string texto = "") => t.AppendLine(texto);
        void Motivos(IEnumerable<string> motivos) { foreach (var m in motivos) L("  • " + m); }

        switch (_lista.SelectedItems[0].Tag)
        {
            case EventoSeguranca e:
                L($"{RotuloTipo(e.Tipo)} ({e.Severidade}) em {e.OcorridoEm.LocalDateTime:dd/MM/yyyy HH:mm:ss}{(e.Enviado ? "" : "  -- ainda não enviado ao servidor")}");
                L(e.Resumo);
                L();
                EscreverDetalhes(t, e.Detalhes);
                break;

            case LinhaProcesso p:
                L($"{p.Processo.Nome}  (PID {p.Processo.Pid})  --  {RotuloNivel(p.Avaliacao.Nivel)}, {p.Avaliacao.Pontos} pontos");
                L($"Arquivo: {p.Processo.Caminho}");
                L($"Assinatura: {RotuloAssinatura(p.Exe)}{(p.Exe.Oculto ? "  ·  ARQUIVO OCULTO" : "")}{(p.Exe.CriadoEm is { } criadoEm ? $"  ·  criado em {criadoEm:dd/MM/yyyy}" : "")}");
                if (p.Processo.Servico != null) L($"Serviço do Windows: {p.Processo.Servico}{(p.Processo.ComoSystem ? " (SYSTEM)" : "")}");
                var catalogo = CatalogoProcessos.Obter(Array.Empty<int>());
                if (catalogo.TryGetValue(p.Processo.PaiPid, out var pai)) L($"Aberto por: {pai.Nome} (PID {pai.Pid})  {pai.Caminho}");
                var filhos = catalogo.Values.Where(f => f.PaiPid == p.Processo.Pid).Select(f => $"{f.Nome} ({f.Pid})").ToList();
                if (filhos.Count > 0) L("Abriu: " + string.Join(", ", filhos));
                if (!string.IsNullOrEmpty(p.Processo.LinhaComando)) L($"Linha de comando: {p.Processo.LinhaComando}");
                if (p.Avaliacao.Motivos.Count > 0) { L(); L("Por que chamou atenção:"); Motivos(p.Avaliacao.Motivos); }
                break;

            case ItemInicializacao i:
                L($"{i.Nome}  --  {RotuloTipoInicio(i.Tipo)}, {RotuloNivel(i.Avaliacao.Nivel)} ({i.Avaliacao.Pontos} pontos)");
                L($"Comando: {i.Comando}");
                L($"Arquivo: {i.Exe.Caminho}{(i.Exe.Existe ? "" : "  (NÃO EXISTE)")}");
                L($"Assinatura: {RotuloAssinatura(i.Exe)}{(i.Exe.Oculto ? "  ·  ARQUIVO OCULTO" : "")}");
                L($"Configurado em: {i.Origem}{(i.Conta != null ? $"  ·  conta: {i.Conta}" : "")}");
                if (i.Estado != null || i.Inicio != null) L($"Estado: {i.Estado} {i.Inicio}");
                if (i.Avaliacao.Motivos.Count > 0) { L(); L("Por que chamou atenção:"); Motivos(i.Avaliacao.Motivos); }
                break;

            case LinhaConexao c:
                L($"{c.Processo?.Nome ?? "PID " + c.Pid} {(c.Estado == "SYN_SENT" ? "tentando conectar em" : "conectado a")} {c.Ip}:{c.Porta} ({c.Qtd} conexão(ões)) -- {(c.Publico ? "internet" : "rede local")}");
                L($"Programa: {c.Processo?.Caminho}");
                if (c.Avaliacao.Motivos.Count > 0) { L(); L("Por que o programa chamou atenção:"); Motivos(c.Avaliacao.Motivos); }
                L(); L("\"Consultar IP\" mostra se esse endereço já foi denunciado por ataques (AbuseIPDB).");
                break;

            case FirewallService.RegraFirewall r:
                L($"{r.Nome}");
                L($"{r.Acao} · {r.Direcao} · {(r.Habilitada ? "ativa" : "desativada")}{(r.Grupo != null ? " · grupo " + r.Grupo : "")}");
                if (r.Programa != null) L($"Programa: {r.Programa}");
                if (r.Ips != null) L($"IPs remotos: {r.Ips}");
                if (r.Portas != null) L($"Portas remotas: {r.Portas}");
                L(); L(r.DoRd ? "Criada pelo RD.Intranet -- pode ser removida aqui." : "Regra de outro programa ou do Windows -- só leitura aqui.");
                break;

            case ItemQuarentena q:
                L($"Original: {q.Original}{(q.Pasta ? "  (pasta inteira)" : "")}");
                L($"Na quarentena: {q.Destino}");
                L($"Quando: {q.Quando:dd/MM/yyyy HH:mm:ss}");
                if (q.Sha256 != null) L($"SHA256: {q.Sha256}");
                L($"Motivo: {q.Motivo}");
                break;
        }
        _detalhe.Text = t.ToString();
    }

    private static void EscreverDetalhes(StringBuilder t, Dictionary<string, object?> detalhes)
    {
        if (detalhes.TryGetValue("itens", out var itens) && itens is IEnumerable<object> lista)
        {
            var n = 0;
            foreach (var item in lista)
            {
                var d = JsonSerializer.SerializeToElement(item);
                string P(string k) => d.TryGetProperty(k, out var v) && v.ValueKind != JsonValueKind.Null ? v.ToString() : "";
                t.AppendLine($"{++n}. {P("nome")}  [{P("tipo")}, {P("nivel")}]");
                if (P("caminho") != "") t.AppendLine($"   arquivo: {P("caminho")}");
                if (P("comando") != "" && P("comando") != P("caminho")) t.AppendLine($"   comando: {P("comando")}");
                if (d.TryGetProperty("motivos", out var m) && m.ValueKind == JsonValueKind.Array)
                    foreach (var motivo in m.EnumerateArray()) t.AppendLine("   • " + motivo.GetString());
                t.AppendLine();
            }
            t.AppendLine("Para desativar ou pôr em quarentena: aba Inicialização.");
            return;
        }

        foreach (var (chave, valor) in detalhes)
        {
            if (valor == null || chave is "resumo_agente") continue;
            var json = JsonSerializer.SerializeToElement(valor);
            var texto = json.ValueKind switch
            {
                JsonValueKind.Array => string.Join(chave == "motivos" ? "\n   • " : ", ", json.EnumerateArray().Select(x => x.ValueKind == JsonValueKind.Object ? string.Join(" ", x.EnumerateObject().Select(p => p.Value.ToString())) : x.ToString())),
                _ => json.ToString()
            };
            t.AppendLine(chave == "motivos" ? $"motivos:\n   • {texto}" : $"{chave.Replace('_', ' ')}: {texto}");
        }
    }

    // ------------------------------------------------------------------
    // Ações
    // ------------------------------------------------------------------

    private bool Confirmar(string texto) =>
        MessageBox.Show(FindForm(), texto, "RD Intranet - Segurança", MessageBoxButtons.YesNo, MessageBoxIcon.Warning, MessageBoxDefaultButton.Button2) == DialogResult.Yes;

    private void Executar(string mensagem, Func<ResultadoAcao> acao, Action? depois = null)
    {
        _ocupado = true;
        _status.Text = mensagem;
        _status.ForeColor = Tema.Acento;
        Task.Run(() =>
        {
            ResultadoAcao r;
            try { r = acao(); } catch (Exception ex) { r = new ResultadoAcao(false, ex.Message); }
            if (IsDisposed || !IsHandleCreated) return;
            BeginInvoke(() =>
            {
                _ocupado = false;
                MessageBox.Show(FindForm(), r.Mensagem, "RD Intranet - Segurança", MessageBoxButtons.OK, r.Sucesso ? MessageBoxIcon.Information : MessageBoxIcon.Error);
                _status.Text = r.Mensagem;
                _status.ForeColor = r.Sucesso ? Tema.Sucesso : Tema.Perigo;
                depois?.Invoke();
                _monitor.ForcarAtualizacao(); // o portal vê o resultado em segundos
            });
        });
    }

    private static ResultadoAcao DeObjeto(object resposta)
    {
        var json = JsonSerializer.SerializeToElement(resposta);
        return new ResultadoAcao(json.TryGetProperty("sucesso", out var s) && s.GetBoolean(), json.TryGetProperty("mensagem", out var m) ? m.GetString() ?? "" : "");
    }

    private void AcaoProcesso(string acao)
    {
        var l = Selecionado<LinhaProcesso>();
        if (l == null) return;
        var p = l.Processo;
        switch (acao)
        {
            case "encerrar" when Confirmar($"Encerrar agora {p.Nome} (PID {p.Pid})?\n\nSe um serviço ou vigia o relança, ele volta -- use Quarentena ou desative a origem em Inicialização."):
                Executar("Encerrando...", () => DeObjeto(FirewallService.EncerrarProcesso(JsonSerializer.Serialize(new { pid = p.Pid, caminho = p.Caminho }), null, null)), CarregarProcessos);
                break;
            case "quarentena" when Confirmar($"Pôr em quarentena:\n{p.Caminho}\n\nO processo é encerrado e o arquivo vai para a quarentena do agente (não executa mais). Dá para restaurar depois."):
                Executar("Movendo para a quarentena...", () => AcoesSeguranca.Quarentena(p.Caminho!, $"Processo suspeito ({string.Join("; ", l.Avaliacao.Motivos.Take(3))})"), CarregarProcessos);
                break;
            case "bloquear" when Confirmar($"Bloquear toda a rede (entrada e saída) de:\n{p.Caminho}\n\nno firewall do Windows?"):
                Executar("Criando a regra no firewall...", () => DeObjeto(FirewallService.Bloquear(JsonSerializer.Serialize(new { tipo = "programa", valor = p.Caminho, motivo = "Bloqueado pela tela do agente", por = Environment.UserName }), null, null)));
                break;
        }
    }

    private void AcaoInicializacao(bool ativar)
    {
        var i = Selecionado<ItemInicializacao>();
        if (i == null) return;
        var verbo = ativar ? "Reativar" : "Desativar";
        if (!Confirmar($"{verbo} {RotuloTipoInicio(i.Tipo).ToLower()} \"{i.Nome}\"?\n\n{i.Comando}\n\n{(ativar ? "Volta a iniciar com o Windows." : "Para de iniciar com o Windows (o original fica guardado para reativar).")}")) return;
        Executar($"{verbo}...", () => AcoesSeguranca.AlternarInicializacao(i.Tipo, i.Identificador, ativar), () => CarregarInicializacao(true));
    }

    private void AcaoInicializacaoQuarentena()
    {
        var i = Selecionado<ItemInicializacao>();
        if (i == null || !i.Exe.Existe) return;
        if (!Confirmar($"Desativar \"{i.Nome}\" e pôr o arquivo em quarentena?\n\n{i.Exe.Caminho}\n\nO que estiver rodando esse arquivo é encerrado.")) return;
        Executar("Desativando e movendo para a quarentena...", () =>
        {
            var desativar = i.Identificador.StartsWith("rd|") ? new ResultadoAcao(true, "") : AcoesSeguranca.AlternarInicializacao(i.Tipo, i.Identificador, false);
            var quarentena = AcoesSeguranca.Quarentena(i.Exe.Caminho, $"Inicialização suspeita: {i.Nome} ({string.Join("; ", i.Avaliacao.Motivos.Take(3))})");
            return new ResultadoAcao(desativar.Sucesso && quarentena.Sucesso, string.Join("\n", new[] { desativar.Mensagem, quarentena.Mensagem }.Where(m => m != "")));
        }, () => CarregarInicializacao(true));
    }

    private void AcaoConexao(string acao)
    {
        var c = Selecionado<LinhaConexao>();
        if (c == null) return;
        switch (acao)
        {
            case "bloquear_ip" when Confirmar($"Bloquear a saída desta máquina para {c.Ip} no firewall do Windows?"):
                Executar("Criando a regra no firewall...", () => DeObjeto(FirewallService.Bloquear(JsonSerializer.Serialize(new { tipo = "ip", valor = c.Ip, motivo = $"Conexão de {c.Processo?.Nome} bloqueada pela tela do agente", por = Environment.UserName }), null, null)));
                break;
            case "bloquear_programa" when c.Processo?.Caminho != null && Confirmar($"Bloquear toda a rede de:\n{c.Processo.Caminho}?"):
                Executar("Criando a regra no firewall...", () => DeObjeto(FirewallService.Bloquear(JsonSerializer.Serialize(new { tipo = "programa", valor = c.Processo.Caminho, motivo = "Bloqueado pela tela do agente", por = Environment.UserName }), null, null)));
                break;
            case "encerrar" when c.Pid > 4 && Confirmar($"Encerrar {c.Processo?.Nome} (PID {c.Pid})?"):
                Executar("Encerrando...", () => DeObjeto(FirewallService.EncerrarProcesso(JsonSerializer.Serialize(new { pid = c.Pid, caminho = c.Processo?.Caminho }), null, null)), CarregarConexoes);
                break;
        }
    }

    private void ConsultarIp()
    {
        var c = Selecionado<LinhaConexao>();
        if (c == null) return;
        Abrir($"https://www.abuseipdb.com/check/{WebUtility.UrlEncode(c.Ip)}");
    }

    private void AcaoFirewallPerfil(bool ligar, string perfil, string rotulo)
    {
        var usoAgora = rotulo.Equals("Domínio") ? "rede de domínio (empresa com Active Directory)" : rotulo.Equals("Privado") ? "rede privada (a rede da empresa sem domínio, em geral)" : "rede pública (Wi-Fi de hotel, aeroporto...)";
        if (!Confirmar(ligar
                ? $"Ligar a camada {rotulo} do firewall do Windows?\n\nVale quando a máquina está em {usoAgora}. Entrada e saída ficam LIBERADAS por padrão -- o que funciona hoje continua funcionando; só as regras de bloqueio passam a valer."
                : $"DESLIGAR a camada {rotulo} do firewall do Windows?\n\nEnquanto a máquina estiver em {usoAgora}, nenhum bloqueio vale (inclusive os do RD.Intranet).")) return;
        Executar(ligar ? $"Ligando a camada {rotulo}..." : $"Desligando a camada {rotulo}...", () =>
        {
            var r = AcoesSeguranca.Firewall(ligar, perfil: perfil);
            if (!r.Sucesso && ligar && r.Mensagem.Contains("passariam a valer"))
            {
                var confirmou = (bool)Invoke(() => Confirmar(r.Mensagem + "\n\nLigar mesmo assim?"));
                if (confirmou) r = AcoesSeguranca.Firewall(true, forcar: true, perfil: perfil);
            }
            return r;
        }, CarregarFirewall);
    }

    private void BloquearIpManual()
    {
        var ip = Perguntar("Bloquear IP", "IP ou rede para bloquear a saída (ex.: 77.90.185.12 ou 77.90.185.0/24):");
        if (string.IsNullOrWhiteSpace(ip)) return;
        Executar("Criando a regra no firewall...", () => DeObjeto(FirewallService.Bloquear(JsonSerializer.Serialize(new { tipo = "ip", valor = ip.Trim(), motivo = "Bloqueado pela tela do agente", por = Environment.UserName }), null, null)), CarregarFirewall);
    }

    private void BloquearProgramaManual()
    {
        using var dialogo = new OpenFileDialog { Title = "Programa para bloquear a rede", Filter = "Programas|*.exe|Todos|*.*" };
        if (dialogo.ShowDialog(FindForm()) != DialogResult.OK) return;
        var caminho = dialogo.FileName;
        if (!Confirmar($"Bloquear toda a rede de:\n{caminho}?")) return;
        Executar("Criando a regra no firewall...", () => DeObjeto(FirewallService.Bloquear(JsonSerializer.Serialize(new { tipo = "programa", valor = caminho, motivo = "Bloqueado pela tela do agente", por = Environment.UserName }), null, null)), CarregarFirewall);
    }

    private void RemoverRegra()
    {
        var r = Selecionado<FirewallService.RegraFirewall>();
        if (r == null) return;
        if (!r.DoRd) { MessageBox.Show(FindForm(), "Só regras criadas pelo RD.Intranet podem ser removidas por aqui.", "RD Intranet", MessageBoxButtons.OK, MessageBoxIcon.Information); return; }
        if (r.Nome.StartsWith(ConfiaveisService.PrefixoRegra) && r.Programa != null)
        {
            if (!Confirmar($"Deixar de confiar em {Path.GetFileName(r.Programa)}?\n\nAs regras de permissão são removidas e o programa volta para a análise de risco.")) return;
            Executar("Removendo...", () => ConfiaveisService.Desconfiar(r.Programa), CarregarFirewall);
            return;
        }
        if (!Confirmar($"Remover o bloqueio \"{r.Nome}\"?")) return;
        Executar("Removendo...", () => DeObjeto(FirewallService.Desbloquear(r.Nome, null, null)), CarregarFirewall);
    }

    /// <summary>"Confiar...": pede a descrição e as portas (já preenchidas com as que o programa usa agora).</summary>
    private void Confiar(string? caminho)
    {
        if (string.IsNullOrEmpty(caminho)) return;
        var atual = ConfiaveisService.Buscar(caminho);
        if (atual != null)
        {
            if (Confirmar($"{Path.GetFileName(caminho)} já é confiável ({atual.Descricao}).\n\nDeixar de confiar? As regras de permissão são removidas."))
                Executar("Removendo...", () => ConfiaveisService.Desconfiar(caminho), RecarregarAba);
            return;
        }
        var (descricao, portas) = PerguntarConfianca(caminho);
        if (descricao == null) return;
        Executar("Marcando como confiável...", () => ConfiaveisService.Confiar(caminho, descricao, portas ?? "", Environment.UserName), RecarregarAba);
    }

    private void PermitirProgramaManual()
    {
        using var dialogo = new OpenFileDialog { Title = "Programa confiável (vai ser liberado no firewall)", Filter = "Programas|*.exe|Todos|*.*" };
        if (dialogo.ShowDialog(FindForm()) == DialogResult.OK) Confiar(dialogo.FileName);
    }

    private void RecarregarAba()
    {
        switch (_aba)
        {
            case "processos": CarregarProcessos(); break;
            case "inicializacao": CarregarInicializacao(true); break;
            case "conexoes": CarregarConexoes(); break;
            case "firewall": CarregarFirewall(); break;
        }
    }

    private (string? descricao, string? portas) PerguntarConfianca(string caminho)
    {
        using var janela = new Form
        {
            Text = "Confiar no programa", FormBorderStyle = FormBorderStyle.FixedDialog, StartPosition = FormStartPosition.CenterParent,
            Size = new Size(560, 300), MaximizeBox = false, MinimizeBox = false, BackColor = Tema.Fundo, ForeColor = Tema.Texto, Font = Tema.Fonte(9F)
        };
        Tema.BarraTituloEscura(janela);
        var exe = AnaliseExecutavel.Analisar(caminho);
        var cabecalho = new Label
        {
            Text = $"{Path.GetFileName(caminho)}\n{caminho}\nAssinatura: {(exe.Assinado ? exe.Assinante : "sem assinatura")}",
            Location = new Point(14, 10), Size = new Size(520, 52), ForeColor = Tema.TextoSecundario
        };
        var rotuloDesc = new Label { Text = "Para que serve (fica na regra do firewall e na lista de confiáveis):", Location = new Point(14, 68), Size = new Size(520, 18) };
        var descricao = new TextBox { Location = new Point(14, 88), Width = 516, BackColor = Tema.Campo, ForeColor = Tema.Texto, BorderStyle = BorderStyle.FixedSingle,
            PlaceholderText = "Ex.: Driver de comunicação com os equipamentos do laboratório (ARIIS)" };
        var rotuloPortas = new Label { Text = "Portas TCP que recebem conexão (opcional, separadas por vírgula):", Location = new Point(14, 120), Size = new Size(520, 18) };
        var portas = new TextBox { Location = new Point(14, 140), Width = 220, BackColor = Tema.Campo, ForeColor = Tema.Texto, BorderStyle = BorderStyle.FixedSingle, Text = ConfiaveisService.PortasEmUso(caminho) };
        var aviso = new Label { Text = "O programa fica liberado no firewall (entrada e saída) e deixa de ser marcado na análise de risco.", Location = new Point(14, 172), Size = new Size(520, 32), ForeColor = Tema.TextoApagado };
        var ok = new BotaoTema("Confiar", BotaoTema.Variante.Primario) { Location = new Point(334, 212), Width = 96, DialogResult = DialogResult.OK };
        var cancelar = new BotaoTema("Cancelar") { Location = new Point(436, 212), Width = 96, DialogResult = DialogResult.Cancel };
        janela.Controls.AddRange(new Control[] { cabecalho, rotuloDesc, descricao, rotuloPortas, portas, aviso, ok, cancelar });
        janela.AcceptButton = ok;
        janela.CancelButton = cancelar;
        return janela.ShowDialog(FindForm()) == DialogResult.OK && descricao.Text.Trim().Length >= 3 ? (descricao.Text.Trim(), portas.Text) : (null, null);
    }

    private void AcaoQuarentena(bool restaurar)
    {
        var q = Selecionado<ItemQuarentena>();
        if (q == null) return;
        if (!Confirmar(restaurar ? $"Restaurar para {q.Original}?\n\nSó restaure se tiver certeza de que não é malicioso." : $"Excluir definitivamente {q.Original}?\n\nNão dá para desfazer.")) return;
        Executar(restaurar ? "Restaurando..." : "Excluindo...", () => restaurar ? AcoesSeguranca.RestaurarQuarentena(q.Id) : AcoesSeguranca.ExcluirDaQuarentena(q.Id), Preencher);
    }

    private static void AbrirLocal(string? caminho)
    {
        if (string.IsNullOrEmpty(caminho)) return;
        if (File.Exists(caminho)) Process.Start("explorer.exe", $"/select,\"{caminho}\"");
        else if (Directory.Exists(Path.GetDirectoryName(caminho))) Process.Start("explorer.exe", $"\"{Path.GetDirectoryName(caminho)}\"");
    }

    private static void VirusTotal(string? caminho)
    {
        if (string.IsNullOrEmpty(caminho)) return;
        var hash = AnaliseExecutavel.Sha256(caminho);
        if (hash != null) Abrir($"https://www.virustotal.com/gui/file/{hash}");
    }

    private static void Abrir(string url)
    {
        try { Process.Start(new ProcessStartInfo(url) { UseShellExecute = true }); } catch { }
    }

    private string? Perguntar(string titulo, string texto)
    {
        using var janela = new Form
        {
            Text = titulo, FormBorderStyle = FormBorderStyle.FixedDialog, StartPosition = FormStartPosition.CenterParent,
            Size = new Size(460, 170), MaximizeBox = false, MinimizeBox = false, BackColor = Tema.Fundo, ForeColor = Tema.Texto, Font = Tema.Fonte(9F)
        };
        Tema.BarraTituloEscura(janela);
        var rotulo = new Label { Text = texto, Location = new Point(14, 12), Size = new Size(420, 36) };
        var campo = new TextBox { Location = new Point(14, 52), Width = 416, BackColor = Tema.Campo, ForeColor = Tema.Texto, BorderStyle = BorderStyle.FixedSingle };
        var ok = new BotaoTema("Bloquear", BotaoTema.Variante.Perigo) { Location = new Point(244, 88), Width = 90, DialogResult = DialogResult.OK };
        var cancelar = new BotaoTema("Cancelar") { Location = new Point(340, 88), Width = 90, DialogResult = DialogResult.Cancel };
        janela.Controls.AddRange(new Control[] { rotulo, campo, ok, cancelar });
        janela.AcceptButton = ok;
        janela.CancelButton = cancelar;
        return janela.ShowDialog(FindForm()) == DialogResult.OK ? campo.Text : null;
    }

    // ------------------------------------------------------------------
    // Rótulos
    // ------------------------------------------------------------------

    private static Color Cor(string nivel) => nivel switch
    {
        "critico" => Tema.Perigo,
        "suspeito" => Color.FromArgb(240, 136, 62),
        "atencao" => Tema.Alerta,
        _ => Tema.Texto
    };

    private static string RotuloNivel(string nivel) => nivel switch { "critico" => "CRÍTICO", "suspeito" => "Suspeito", "atencao" => "Atenção", _ => "Normal" };
    private static string RotuloNivel(Avaliacao a) => a.Motivos.FirstOrDefault()?.StartsWith("confiável") == true ? "Confiável" : RotuloNivel(a.Nivel);

    private static string RotuloAssinatura(InfoExecutavel exe) => exe.Assinatura switch
    {
        "valida" or "catalogo" or "pacote" => "✓ " + (exe.Assinante ?? "assinado"),
        "expirada" => (exe.Assinante ?? "assinado") + " (expirado)",
        "nao_confiavel" => (exe.Assinante ?? "assinado") + " (certificado próprio)",
        "invalida" => "✗ assinatura inválida",
        "ausente" => "sem assinatura",
        _ => exe.Existe ? "?" : "arquivo não existe"
    };

    private static string RotuloTipoInicio(string tipo) => tipo switch
    {
        "servico" => "Serviço", "tarefa" => "Tarefa agendada", "registro" => "Registro (Run)", "pasta_inicializar" => "Pasta Inicializar",
        "winlogon" => "Winlogon", "ifeo" => "IFEO (sequestro)", "wmi" => "Assinatura WMI", "appinit" => "AppInit", "politica" => "Política (trava)", _ => tipo
    };

    private static string RotuloTipo(string tipo) => tipo switch
    {
        "CANARY_TRIGGERED" => "Arquivo-isca alterado",
        "SHADOW_COPY_DELETE_ATTEMPT" => "Shadow copies apagadas",
        "MASS_FILE_CHANGE" => "Mudança em massa",
        "MASS_FILE_DELETE" => "Exclusão em massa",
        "RANSOM_NOTE_CREATED" => "Nota de resgate",
        "NETWORK_SCAN" => "Varredura (internet)",
        "NETWORK_SCAN_LAN" => "Varredura (rede local)",
        "SUSPICIOUS_CONNECTION" => "Conexão suspeita",
        "SUSPICIOUS_PROCESS" => "Processo suspeito",
        "SUSPICIOUS_STARTUP" => "Inicialização suspeita",
        _ => tipo
    };
}
