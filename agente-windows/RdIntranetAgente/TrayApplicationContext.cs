using System.Collections.Generic;
using System.Diagnostics;
using System.Drawing;
using System.IO;
using System.Reflection;
using System.Text;
using System.Threading.Tasks;
using System.Windows.Forms;
using Microsoft.Win32;
using RdIntranetAgente.Models;

namespace RdIntranetAgente;

public class TrayApplicationContext : ApplicationContext
{
    private readonly NotifyIcon _icone;
    private readonly System.Windows.Forms.Timer _timer;
    private readonly System.Windows.Forms.Timer _heartbeatTimer;
    private Config _config;
    private readonly AppState _estado;
    private readonly PrintListener _printListener;
    private readonly string? _machineGuid;
    private bool _coletando;
    private bool _enviandoHeartbeat;

    public TrayApplicationContext()
    {
        _config = Config.Carregar();
        _estado = AppState.Carregar();

        // Calculado uma vez só (BIOS/registro não mudam em tempo de
        // execução) -- reaproveitado em todo heartbeat, que roda a cada
        // poucos segundos e não pode pagar o custo de WMI de novo a
        // cada tick.
        try
        {
            _machineGuid = CollectorService.ObterMachineGuid();
        }
        catch
        {
            _machineGuid = null;
        }

        // Escuta local pra imprimir etiqueta sob demanda (sem esperar o
        // proximo checkin) -- sempre tenta iniciar; so imprime de verdade
        // se uma impressora estiver configurada (menu Configuracoes).
        _printListener = new PrintListener(() => _config);
        _printListener.Iniciar();

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
        _timer.Tick += async (s, e) => await ColetarEEnviarAsync(manual: false);

        _heartbeatTimer = new System.Windows.Forms.Timer { Interval = HeartbeatIntervaloEmMs() };
        _heartbeatTimer.Tick += async (s, e) => await EnviarHeartbeatAsync();

        if (!_config.EstaConfigurado)
        {
            AbrirConfiguracoes();
        }

        if (_config.EstaConfigurado)
        {
            _timer.Start();
            _heartbeatTimer.Start();
            _ = ColetarEEnviarAsync(manual: false);
        }
    }

    private int IntervaloEmMs() => Math.Max(5, _config.IntervaloMinutos) * 60 * 1000;
    private int HeartbeatIntervaloEmMs() => Math.Max(1, _config.HeartbeatSegundos) * 1000;

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
        _heartbeatTimer.Interval = HeartbeatIntervaloEmMs();

        if (_config.EstaConfigurado)
        {
            _timer.Stop();
            _timer.Start();
            _heartbeatTimer.Stop();
            _heartbeatTimer.Start();
            _ = ColetarEEnviarAsync(manual: true);
        }
    }

    private async Task EnviarHeartbeatAsync()
    {
        if (_enviandoHeartbeat || !_config.EstaConfigurado || _machineGuid == null)
        {
            return;
        }

        _enviandoHeartbeat = true;

        try
        {
            var resultado = await new HeartbeatClient(_config).EnviarAsync(_machineGuid);

            if (resultado.Sucesso)
            {
                AplicarNovaChaveApiSeNecessario(resultado.ChaveApiAtual);
            }

            if (resultado.Sucesso && resultado.ForcarCheckin && !_coletando)
            {
                // "Forçar coleta agora" clicado no PORTAL (não localmente no
                // agente) chega aqui via forcar_checkin -- é um pedido
                // explícito do admin tanto quanto um clique local, então
                // também deve pular o limite de 12h da checagem de
                // atualização (forcarVerificacaoAtualizacao), senão o botão
                // do portal nunca serve pra confirmar que o agente pegou uma
                // versão nova. manual=false continua controlando só o
                // feedback local (balão/MessageBox), que não faz sentido
                // aqui já que ninguém clicou em nada nesta máquina.
                _ = ColetarEEnviarAsync(manual: false, forcarVerificacaoAtualizacao: true);
            }

            if (resultado.Sucesso && resultado.Solicitacoes.Count > 0)
            {
                await ProcessarSolicitacoesAsync(resultado.Solicitacoes);
            }
        }
        catch
        {
            // best-effort -- tenta de novo no proximo tick, sem incomodar o usuario
        }
        finally
        {
            _enviandoHeartbeat = false;
        }
    }

    /// <summary>
    /// Adota sozinho uma chave de API nova quando o servidor manda uma
    /// diferente da que está configurada aqui (rollout automático de
    /// "Gerar nova chave" no portal, ver AtivoService::chaveParaRollout).
    /// A chave ANTIGA continua válida no servidor até ser desativada
    /// explicitamente -- então mesmo que salvar aqui falhe por algum
    /// motivo, o agente não fica sem conseguir se autenticar, só continua
    /// tentando adotar a nova a cada heartbeat/checkin seguinte.
    /// </summary>
    private void AplicarNovaChaveApiSeNecessario(string? chaveApiAtual)
    {
        if (string.IsNullOrWhiteSpace(chaveApiAtual) || chaveApiAtual == _config.ApiKey)
        {
            return;
        }

        _config.ApiKey = chaveApiAtual;
        _config.Salvar();
    }

    /// <summary>
    /// Explorador de arquivos/processos da ficha do ativo -- pedido de
    /// leitura chega no heartbeat (SolicitacaoItem), a listagem em si
    /// roda em background (Task.Run, evita travar a UI) e o resultado
    /// volta por um endpoint separado (SolicitacaoClient), sem atrasar
    /// os próximos heartbeats.
    /// </summary>
    private async Task ProcessarSolicitacoesAsync(List<SolicitacaoItem> solicitacoes)
    {
        var cliente = new SolicitacaoClient(_config);

        foreach (var solicitacao in solicitacoes)
        {
            try
            {
                switch (solicitacao.Tipo)
                {
                    case "listar_arquivos":
                        var arquivos = await Task.Run(() => ExploradorService.ListarArquivos(solicitacao.Parametro ?? ""));
                        await cliente.ResponderAsync(_machineGuid!, solicitacao.Id, arquivos);
                        break;

                    case "listar_processos":
                        var processos = await Task.Run(() => ExploradorService.ListarProcessos());
                        await cliente.ResponderAsync(_machineGuid!, solicitacao.Id, processos);
                        break;

                    case "baixar_arquivo":
                        await ProcessarBaixarArquivoAsync(cliente, solicitacao);
                        break;

                    case "executar_cmd":
                    case "executar_powershell":
                        var (saida, erro, codigo) = await Task.Run(() => solicitacao.Tipo == "executar_cmd"
                            ? ExploradorService.ExecutarCmd(solicitacao.Parametro ?? "", solicitacao.Elevado, solicitacao.UsuarioElevacao, solicitacao.SenhaElevacao)
                            : ExploradorService.ExecutarPowerShell(solicitacao.Parametro ?? "", solicitacao.Elevado, solicitacao.UsuarioElevacao, solicitacao.SenhaElevacao));

                        await cliente.ResponderAsync(_machineGuid!, solicitacao.Id, new
                        {
                            saida,
                            erro,
                            codigo_saida = codigo
                        });
                        break;
                }
            }
            catch (Exception ex)
            {
                await cliente.ResponderErroAsync(_machineGuid!, solicitacao.Id, ex.Message);
            }
        }
    }

    private const long TamanhoMaximoDownload = 200L * 1024 * 1024; // mesmo teto configurado no PHP (upload_max_filesize)

    private async Task ProcessarBaixarArquivoAsync(SolicitacaoClient cliente, SolicitacaoItem solicitacao)
    {
        var caminho = solicitacao.Parametro ?? "";
        var info = new FileInfo(caminho);

        if (!info.Exists)
        {
            await cliente.ResponderErroAsync(_machineGuid!, solicitacao.Id, "Arquivo não encontrado (pode ter sido movido/apagado).");
            return;
        }

        if (info.Length > TamanhoMaximoDownload)
        {
            await cliente.ResponderErroAsync(_machineGuid!, solicitacao.Id, $"Arquivo maior que {TamanhoMaximoDownload / 1024 / 1024}MB -- baixe por outro meio.");
            return;
        }

        var enviado = await new TransferenciaClient(_config).EnviarArquivoAsync(_machineGuid!, solicitacao.Id, caminho, info.Name);

        if (!enviado)
        {
            await cliente.ResponderErroAsync(_machineGuid!, solicitacao.Id, "Falha ao enviar o arquivo pro servidor.");
        }
    }

    private async Task ColetarEEnviarAsync(bool manual, bool forcarVerificacaoAtualizacao = false)
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
                AplicarNovaChaveApiSeNecessario(resultado.ChaveApiAtual);
            }

            _estado.Salvar();

            if (manual)
            {
                var icone = resultado.Sucesso ? ToolTipIcon.Info : ToolTipIcon.Error;
                // ShowBalloonTip lanca ArgumentException se o texto vier vazio
                // -- resultado.Mensagem vem de uma resposta HTTP externa
                // (podia ser um 500 com corpo vazio, por exemplo), entao nunca
                // confia que ela vem preenchida. Sem essa guarda, a excecao
                // era capturada pelo catch geral la embaixo e ABORTAVA o
                // resto do fluxo (comandos pendentes e checagem de
                // atualizacao nunca rodavam nesse checkin).
                var textoBalao = string.IsNullOrWhiteSpace(resultado.Mensagem) ? "Operação concluída." : resultado.Mensagem;
                _icone.ShowBalloonTip(4000, "RD Intranet", textoBalao, icone);
            }

            await ExecutarComandosPendentesAsync(resultado.Comandos);

            if (resultado.Sucesso)
            {
                // "manual" (clique local) OU forcarVerificacaoAtualizacao
                // (pedido explícito vindo do portal) força a checagem na
                // hora (ignora o limite de 12h) -- os dois são um pedido
                // explícito de alguém, só que um é local e o outro remoto.
                await VerificarAtualizacaoAsync(forcar: manual || forcarVerificacaoAtualizacao);
            }
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
    /// Checa se há uma versão nova do .exe publicada no RD Intranet --
    /// no máximo 1x a cada 12h só no ciclo periódico automático (timer),
    /// mas na hora quando é um pedido explícito de alguém -- clique manual
    /// ("Coletar agora"/duplo clique no ícone) OU "Forçar coleta agora"
    /// vindo do portal -- pra dar um jeito de confirmar a atualização sem
    /// esperar até 12h ao testar/diagnosticar. Se
    /// houver versão nova, baixa pra uma pasta temporária e entrega a
    /// troca do arquivo pra um script auxiliar, porque um processo
    /// Windows não consegue sobrescrever o próprio .exe em execução -- o
    /// script espera este processo encerrar (Application.Exit logo em
    /// seguida), move o novo arquivo por cima do antigo e reabre. Tudo
    /// best-effort: qualquer falha aqui só significa "tenta de novo no
    /// próximo gatilho", nunca derruba o agente.
    /// </summary>
    private async Task VerificarAtualizacaoAsync(bool forcar = false)
    {
        if (!forcar && _estado.UltimaVerificacaoAtualizacao.HasValue &&
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

            _icone.ShowBalloonTip(4000, "RD Intranet", $"Atualizando para a versão {versaoServidor}...", ToolTipIcon.Info);

            var exeAtual = Application.ExecutablePath;
            var scriptPath = Path.Combine(pastaAtualizacao, "atualizar.bat");
            var logPath = Path.Combine(pastaAtualizacao, "atualizar.log");
            File.WriteAllText(scriptPath, ConteudoScriptAtualizacao(novoExe, exeAtual, logPath));

            Process.Start(new ProcessStartInfo
            {
                FileName = "cmd.exe",
                Arguments = $"/c \"{scriptPath}\"",
                WindowStyle = ProcessWindowStyle.Hidden,
                CreateNoWindow = true,
                UseShellExecute = false
            });

            Encerrar();
        }
        catch
        {
            // atualizacao automatica e best-effort -- nao deve interromper o funcionamento normal
        }
    }

    /// <summary>
    /// O move só consegue substituir o .exe depois que este processo
    /// encerrar de vez (arquivo em uso) -- por isso o retry com pausas em
    /// vez de tentar uma vez só logo de cara. Registra cada tentativa num
    /// log (pasta %TEMP%\RDIntranetAgenteUpdate\atualizar.log) porque essa
    /// troca acontece sem ninguém olhando -- se travar (antivírus
    /// escaneando o novo .exe, arquivo ainda em uso, permissão), precisa
    /// dar pra diagnosticar sem depender de acesso remoto à máquina.
    /// Sempre reabre algum .exe no fim (o novo se a troca deu certo, o
    /// antigo intacto se não deu) -- nunca deixa o agente sumir da
    /// bandeja só porque a troca falhou.
    /// </summary>
    private static string ConteudoScriptAtualizacao(string origem, string destino, string log) => $@"@echo off
setlocal
set ""ORIGEM={origem}""
set ""DESTINO={destino}""
set ""LOG={log}""
echo [%date% %time%] Iniciando troca de versao > ""%LOG%""
set contador=0
:tentar
timeout /t 1 /nobreak >nul
move /y ""%ORIGEM%"" ""%DESTINO%"" >>""%LOG%"" 2>&1
if exist ""%ORIGEM%"" (
    set /a contador+=1
    echo [%date% %time%] Tentativa %contador% -- arquivo ainda em uso >>""%LOG%""
    if %contador% lss 30 goto tentar
    echo [%date% %time%] Desisti apos 30 tentativas -- reabrindo versao anterior >>""%LOG%""
    start """" ""%DESTINO%""
    del ""%~f0""
    exit /b 1
)
echo [%date% %time%] Troca concluida, reabrindo versao nova >>""%LOG%""
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

    /// <summary>
    /// Registra o início automático via Agendador de Tarefas, não mais
    /// HKCU\...\Run -- uma entrada em Run sempre inicia sem elevação
    /// (nível Médio), mesmo numa conta admin, e o agente agora PRECISA
    /// estar elevado (ver Program.cs) pra "comando com elevação"
    /// funcionar sem credencial extra por máquina.
    ///
    /// Usa uma definição de tarefa em XML (não os parâmetros simples do
    /// schtasks /create) porque precisa disparar pra QUALQUER usuário
    /// que logar na máquina, não só quem rodou o primeiro início
    /// elevado -- `schtasks /create /sc onlogon` sem `/ru` vincula a
    /// tarefa à conta que está criando ela (bug real, confirmado ao
    /// vivo: funcionava só pra quem instalou, não pra outras contas do
    /// Entra que logavam depois). O XML usa &lt;GroupId&gt; com o SID
    /// universal do grupo local "Users" (S-1-5-32-545, todo mundo que
    /// consegue logar interativamente, seja conta local ou do Entra) em
    /// vez de &lt;UserId&gt; de uma conta específica -- é a forma
    /// oficialmente suportada de disparar "pra qualquer usuário",
    /// equivalente a escolher "Any user" na caixa de diálogo do
    /// Agendador de Tarefas (não exposto como flag simples no schtasks.exe).
    /// </summary>
    private static void RegistrarInicioAutomatico()
    {
        RemoverEntradaRunAntiga();

        try
        {
            const string nomeTarefa = "RDIntranetAgenteAutoStart";
            var caminhoXml = Path.Combine(Path.GetTempPath(), $"{nomeTarefa}.xml");
            var xml =
                "<?xml version=\"1.0\" encoding=\"UTF-16\"?>\r\n" +
                "<Task version=\"1.2\" xmlns=\"http://schemas.microsoft.com/windows/2004/02/mit/task\">\r\n" +
                "  <Triggers>\r\n" +
                "    <LogonTrigger>\r\n" +
                "      <Enabled>true</Enabled>\r\n" +
                "    </LogonTrigger>\r\n" +
                "  </Triggers>\r\n" +
                "  <Principals>\r\n" +
                "    <Principal id=\"Author\">\r\n" +
                "      <GroupId>S-1-5-32-545</GroupId>\r\n" +
                "      <RunLevel>HighestAvailable</RunLevel>\r\n" +
                "    </Principal>\r\n" +
                "  </Principals>\r\n" +
                "  <Settings>\r\n" +
                "    <MultipleInstancesPolicy>IgnoreNew</MultipleInstancesPolicy>\r\n" +
                "    <DisallowStartIfOnBatteries>false</DisallowStartIfOnBatteries>\r\n" +
                "    <StopIfGoingOnBatteries>false</StopIfGoingOnBatteries>\r\n" +
                "    <AllowHardTerminate>true</AllowHardTerminate>\r\n" +
                "    <StartWhenAvailable>false</StartWhenAvailable>\r\n" +
                "    <RunOnlyIfNetworkAvailable>false</RunOnlyIfNetworkAvailable>\r\n" +
                "    <Enabled>true</Enabled>\r\n" +
                "    <Hidden>false</Hidden>\r\n" +
                "    <ExecutionTimeLimit>PT0S</ExecutionTimeLimit>\r\n" +
                "  </Settings>\r\n" +
                "  <Actions Context=\"Author\">\r\n" +
                "    <Exec>\r\n" +
                $"      <Command>\"{Application.ExecutablePath}\"</Command>\r\n" +
                "    </Exec>\r\n" +
                "  </Actions>\r\n" +
                "</Task>\r\n";

            File.WriteAllText(caminhoXml, xml, Encoding.Unicode);

            try
            {
                var argumentos = $"/create /tn \"{nomeTarefa}\" /xml \"{caminhoXml}\" /f";

                using var processo = new Process
                {
                    StartInfo = new ProcessStartInfo
                    {
                        FileName = "schtasks.exe",
                        Arguments = argumentos,
                        UseShellExecute = false,
                        CreateNoWindow = true,
                        RedirectStandardOutput = true,
                        RedirectStandardError = true
                    }
                };
                processo.Start();
                processo.WaitForExit(15000);
            }
            finally
            {
                try { File.Delete(caminhoXml); } catch { }
            }
        }
        catch
        {
            // Melhor esforço -- se falhar, só perde o "iniciar com o
            // Windows" nessa máquina, não impede o agente de continuar
            // rodando na sessão atual.
        }
    }

    /// <summary>Migra instalações antigas que ainda têm a entrada em HKCU\...\Run (versão anterior, sem elevação) -- remove pra não iniciar duas vezes.</summary>
    private static void RemoverEntradaRunAntiga()
    {
        try
        {
            using var chave = Registry.CurrentUser.OpenSubKey(@"Software\Microsoft\Windows\CurrentVersion\Run", writable: true);
            chave?.DeleteValue("RDIntranetAgente", throwOnMissingValue: false);
        }
        catch
        {
            // melhor esforço
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
    private async Task ExecutarComandosPendentesAsync(List<ComandoItem> comandos)
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

                    case "executar_arquivo":
                        if (string.IsNullOrWhiteSpace(comando.Alvo)) break;
                        // ShellExecute (nao Executar()) de proposito -- abre o
                        // arquivo do jeito que o Windows abriria com duplo
                        // clique (associacao de tipo), nao so processos .exe.
                        Process.Start(new ProcessStartInfo
                        {
                            FileName = comando.Alvo,
                            UseShellExecute = true
                        });
                        break;

                    case "encerrar_processo":
                        if (!int.TryParse(comando.Alvo, out var pid)) break;
                        using (var processo = Process.GetProcessById(pid))
                        {
                            processo.Kill();
                        }
                        break;

                    case "renomear_arquivo":
                        if (string.IsNullOrWhiteSpace(comando.Alvo) || string.IsNullOrWhiteSpace(comando.AlvoLabel)) break;
                        RenomearArquivoOuPasta(comando.Alvo, comando.AlvoLabel);
                        break;

                    case "enviar_arquivo":
                        if (string.IsNullOrWhiteSpace(comando.Alvo)) break;
                        await new TransferenciaClient(_config).BaixarAnexoComandoAsync(_machineGuid ?? "", comando.Id, comando.Alvo);
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

    /// <summary>
    /// Renomeia arquivo OU pasta -- comando.Alvo é o caminho completo
    /// atual, comando.AlvoLabel é só o NOME novo (sem caminho); o
    /// destino final é montado juntando a pasta de comando.Alvo com esse
    /// nome novo.
    /// </summary>
    private static void RenomearArquivoOuPasta(string caminhoAtual, string nomeNovo)
    {
        var pasta = Path.GetDirectoryName(caminhoAtual);
        if (string.IsNullOrEmpty(pasta)) return;

        var destino = Path.Combine(pasta, nomeNovo);

        if (Directory.Exists(caminhoAtual))
        {
            Directory.Move(caminhoAtual, destino);
        }
        else if (File.Exists(caminhoAtual))
        {
            File.Move(caminhoAtual, destino);
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
        _heartbeatTimer.Stop();
        _printListener.Dispose();
        Application.Exit();
    }
}
