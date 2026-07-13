using System.Collections.Generic;
using System.Diagnostics;
using System.Drawing;
using System.IO;
using System.Reflection;
using System.Threading.Tasks;
using System.Windows.Forms;
using Microsoft.Win32;
using RdIntranetAgente.Models;

namespace RdIntranetAgente;

public class TrayApplicationContext : ApplicationContext
{
    private readonly NotifyIcon _icone;
    private readonly System.Windows.Forms.Timer _timer;
    private Config _config;
    private readonly AppState _estado;
    private bool _coletando;

    public TrayApplicationContext()
    {
        _config = Config.Carregar();
        _estado = AppState.Carregar();

        var menu = new ContextMenuStrip();
        menu.Items.Add("Coletar agora", null, async (s, e) => await ColetarEEnviarAsync(manual: true));
        menu.Items.Add("Configurações...", null, (s, e) => AbrirConfiguracoes());
        menu.Items.Add("Abrir pasta de logs", null, (s, e) => AbrirPastaDados());
        menu.Items.Add(new ToolStripSeparator());
        menu.Items.Add("Sair", null, (s, e) => Encerrar());

        _icone = new NotifyIcon
        {
            Icon = ObterIconeApp(),
            Text = "RD Intranet - Agente",
            Visible = true,
            ContextMenuStrip = menu
        };

        _icone.DoubleClick += async (s, e) => await ColetarEEnviarAsync(manual: true);

        RegistrarInicioAutomatico();
        AtualizarTooltip();

        _timer = new System.Windows.Forms.Timer { Interval = IntervaloEmMs() };
        _timer.Tick += async (s, e) =>
        {
            await ColetarEEnviarAsync(manual: false);
            await VerificarAtualizacaoAsync();
        };

        if (!_config.EstaConfigurado)
        {
            AbrirConfiguracoes();
        }

        if (_config.EstaConfigurado)
        {
            _timer.Start();
            _ = ColetarEEnviarAsync(manual: false);
            _ = VerificarAtualizacaoAsync();
        }
    }

    private int IntervaloEmMs() => Math.Max(5, _config.IntervaloMinutos) * 60 * 1000;

    private void AbrirConfiguracoes()
    {
        using var form = new ConfigForm(_config);

        if (form.ShowDialog() != DialogResult.OK)
        {
            return;
        }

        _config = form.ConfigResultante;
        _config.Salvar();
        _timer.Interval = IntervaloEmMs();

        if (_config.EstaConfigurado)
        {
            _timer.Stop();
            _timer.Start();
            _ = ColetarEEnviarAsync(manual: true);
        }
    }

    private async Task ColetarEEnviarAsync(bool manual)
    {
        if (_coletando)
        {
            return;
        }

        if (!_config.EstaConfigurado)
        {
            if (manual)
            {
                MessageBox.Show("Configure o servidor e a chave de API primeiro.", "RD Intranet",
                    MessageBoxButtons.OK, MessageBoxIcon.Warning);
            }
            return;
        }

        _coletando = true;
        _icone.Text = "RD Intranet - Coletando...";

        try
        {
            var payload = await Task.Run(() => CollectorService.Coletar(_estado.MarcaEventos));
            var resultado = await new CheckinClient(_config).EnviarAsync(payload);

            _estado.UltimoCheckinEm = DateTime.Now;
            _estado.UltimoCheckinSucesso = resultado.Sucesso;
            _estado.UltimaMensagem = resultado.Mensagem;
            _estado.UltimoEnvioBytes = resultado.BytesEnviados;
            _estado.UltimoRecebimentoBytes = resultado.BytesRecebidos;
            _estado.TotalBytesEnviados += resultado.BytesEnviados;
            _estado.TotalBytesRecebidos += resultado.BytesRecebidos;

            if (resultado.Sucesso)
            {
                _estado.MarcaEventos = DateTime.Now;
            }

            _estado.Salvar();

            if (manual)
            {
                var icone = resultado.Sucesso ? ToolTipIcon.Info : ToolTipIcon.Error;
                _icone.ShowBalloonTip(4000, "RD Intranet", resultado.Mensagem, icone);
            }

            ExecutarComandosPendentes(resultado.Comandos);
        }
        catch (Exception ex)
        {
            _estado.UltimoCheckinEm = DateTime.Now;
            _estado.UltimoCheckinSucesso = false;
            _estado.UltimaMensagem = ex.Message;
            _estado.Salvar();

            if (manual)
            {
                _icone.ShowBalloonTip(4000, "RD Intranet", $"Falha: {ex.Message}", ToolTipIcon.Error);
            }
        }
        finally
        {
            _coletando = false;
            AtualizarTooltip();
        }
    }

    private void AtualizarTooltip()
    {
        var status = _estado.UltimoCheckinEm.HasValue
            ? $"Último checkin: {_estado.UltimoCheckinEm:dd/MM HH:mm} ({(_estado.UltimoCheckinSucesso ? "OK" : "falhou")})\n" +
              $"↑ {FormatarBytes(_estado.UltimoEnvioBytes)}  ↓ {FormatarBytes(_estado.UltimoRecebimentoBytes)}\n" +
              $"Total: ↑ {FormatarBytes(_estado.TotalBytesEnviados)}  ↓ {FormatarBytes(_estado.TotalBytesRecebidos)}"
            : "RD Intranet - Agente\nAguardando primeira coleta...";

        // NotifyIcon.Text tem limite de 127 caracteres no Windows.
        _icone.Text = status.Length > 127 ? status[..127] : status;
    }

    private static string FormatarBytes(long bytes)
    {
        string[] unidades = { "B", "KB", "MB", "GB" };
        double valor = bytes;
        int i = 0;

        while (valor >= 1024 && i < unidades.Length - 1)
        {
            valor /= 1024;
            i++;
        }

        return $"{valor:0.#} {unidades[i]}";
    }

    /// <summary>
    /// Checa (no máximo 1x a cada 12h -- não a cada checkin) se há uma
    /// versão nova do .exe publicada no RD Intranet. Se houver, baixa pra
    /// uma pasta temporária e entrega a troca do arquivo pra um script
    /// auxiliar, porque um processo Windows não consegue sobrescrever o
    /// próprio .exe em execução -- o script espera este processo encerrar
    /// (Application.Exit logo em seguida), move o novo arquivo por cima
    /// do antigo e reabre. Tudo best-effort: qualquer falha aqui só
    /// significa "tenta de novo daqui a 12h", nunca derruba o agente.
    /// </summary>
    private async Task VerificarAtualizacaoAsync()
    {
        if (_estado.UltimaVerificacaoAtualizacao.HasValue &&
            (DateTime.Now - _estado.UltimaVerificacaoAtualizacao.Value) < TimeSpan.FromHours(12))
        {
            return;
        }

        _estado.UltimaVerificacaoAtualizacao = DateTime.Now;
        _estado.Salvar();

        try
        {
            var cliente = new AtualizacaoClient(_config);
            var versaoServidor = await cliente.ObterVersaoDisponivelAsync();
            if (versaoServidor == null) return;

            var versaoAtual = Assembly.GetExecutingAssembly().GetName().Version ?? new Version(0, 0, 0, 0);
            if (versaoServidor <= versaoAtual) return;

            var pastaAtualizacao = Path.Combine(Path.GetTempPath(), "RDIntranetAgenteUpdate");
            Directory.CreateDirectory(pastaAtualizacao);
            var novoExe = Path.Combine(pastaAtualizacao, "RdIntranetAgente.new.exe");

            if (!await cliente.BaixarNovaVersaoAsync(novoExe))
            {
                return;
            }

            var exeAtual = Application.ExecutablePath;
            var scriptPath = Path.Combine(pastaAtualizacao, "atualizar.bat");
            File.WriteAllText(scriptPath, ConteudoScriptAtualizacao(novoExe, exeAtual));

            Process.Start(new ProcessStartInfo
            {
                FileName = "cmd.exe",
                Arguments = $"/c \"{scriptPath}\"",
                WindowStyle = ProcessWindowStyle.Hidden,
                CreateNoWindow = true,
                UseShellExecute = false
            });

            Application.Exit();
        }
        catch
        {
            // atualizacao automatica e best-effort -- nao deve interromper o funcionamento normal
        }
    }

    /// <summary>
    /// O move só consegue substituir o .exe depois que este processo
    /// encerrar de vez (arquivo em uso) -- por isso o retry com pausas em
    /// vez de tentar uma vez só logo de cara.
    /// </summary>
    private static string ConteudoScriptAtualizacao(string origem, string destino) => $@"@echo off
setlocal
set ""ORIGEM={origem}""
set ""DESTINO={destino}""
set contador=0
:tentar
timeout /t 1 /nobreak >nul
move /y ""%ORIGEM%"" ""%DESTINO%"" >nul 2>&1
if exist ""%ORIGEM%"" (
    set /a contador+=1
    if %contador% lss 20 goto tentar
    exit /b 1
)
start """" ""%DESTINO%""
del ""%~f0""
";

    /// <summary>
    /// Reaproveita o mesmo ícone embutido no .exe (via ApplicationIcon no
    /// .csproj) pro ícone da bandeja, em vez de carregar um arquivo .ico
    /// separado -- evita depender de um arquivo extra do lado de fora do
    /// publish em arquivo único.
    /// </summary>
    private static Icon ObterIconeApp()
    {
        try
        {
            return Icon.ExtractAssociatedIcon(Application.ExecutablePath) ?? SystemIcons.Application;
        }
        catch
        {
            return SystemIcons.Application;
        }
    }

    private static void RegistrarInicioAutomatico()
    {
        try
        {
            using var chave = Registry.CurrentUser.OpenSubKey(@"Software\Microsoft\Windows\CurrentVersion\Run", writable: true);
            chave?.SetValue("RDIntranetAgente", $"\"{Application.ExecutablePath}\"");
        }
        catch
        {
            // sem permissao pra escrever no HKCU\...\Run (raro) -- o usuario
            // pode abrir manualmente, so perde o "iniciar com o Windows"
        }
    }

    private static void AbrirPastaDados()
    {
        var pasta = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "RDIntranetAgent");

        if (!Directory.Exists(pasta))
        {
            Directory.CreateDirectory(pasta);
        }

        Process.Start("explorer.exe", pasta);
    }

    private const int TempoAvisoSegundos = 300;

    /// <summary>
    /// Comandos remotos vindos junto da resposta do checkin. Desligar/
    /// reiniciar usam o shutdown.exe nativo com um tempo de aviso --
    /// mostra um alerta do sistema pro usuário, com chance de cancelar
    /// localmente (shutdown /a) antes do prazo acabar. Desinstalação é
    /// melhor esforço: MSI sai silencioso, instaladores não-MSI rodam
    /// como estão (podem abrir uma tela local, sem garantia de silêncio).
    /// </summary>
    private void ExecutarComandosPendentes(List<ComandoItem> comandos)
    {
        foreach (var comando in comandos)
        {
            try
            {
                switch (comando.Comando)
                {
                    case "desligar":
                        Executar("shutdown.exe", $"/s /t {TempoAvisoSegundos} /c \"RD Intranet: este computador será desligado remotamente pelo suporte de TI em 5 minutos. Salve seu trabalho.\"");
                        break;

                    case "reiniciar":
                        Executar("shutdown.exe", $"/r /t {TempoAvisoSegundos} /c \"RD Intranet: este computador será reiniciado remotamente pelo suporte de TI em 5 minutos. Salve seu trabalho.\"");
                        break;

                    case "desinstalar_atualizacao":
                        if (string.IsNullOrWhiteSpace(comando.Alvo)) break;
                        var kb = comando.Alvo.TrimStart('K', 'B');
                        Executar("wusa.exe", $"/uninstall /kb:{kb} /quiet /norestart");
                        break;

                    case "desinstalar_programa":
                        if (string.IsNullOrWhiteSpace(comando.Alvo)) break;
                        var match = System.Text.RegularExpressions.Regex.Match(comando.Alvo, @"\{[0-9A-Fa-f-]{36}\}");
                        if (match.Success)
                        {
                            Executar("msiexec.exe", $"/X{match.Value} /quiet /norestart");
                        }
                        else
                        {
                            Executar("cmd.exe", $"/c \"{comando.Alvo}\"");
                        }
                        break;
                }
            }
            catch
            {
                // se o comando falhar (ex: sem permissão, KB já removido),
                // não derruba o resto do checkin -- só não executa esse item
            }
        }
    }

    private static void Executar(string arquivo, string argumentos)
    {
        Process.Start(new ProcessStartInfo
        {
            FileName = arquivo,
            Arguments = argumentos,
            CreateNoWindow = true,
            UseShellExecute = false
        });
    }

    private void Encerrar()
    {
        _icone.Visible = false;
        _timer.Stop();
        Application.Exit();
    }
}
