using System.Diagnostics;
using System.Reflection;
using System.Security.Cryptography.X509Certificates;
using System.Text;

namespace RdIntranetAgente;

/// <summary>
/// Atualização do agente quando ele está instalado como SERVIÇO do Windows.
///
/// Problema que resolve: o serviço roda o MESMO RdIntranetAgente.exe da
/// bandeja e mantém o arquivo em uso -- a troca feita pela bandeja falhava
/// por 90 s e a máquina ficava presa na versão antiga pra sempre.
///
/// Com serviço instalado, quem atualiza é o próprio serviço (SYSTEM, tem
/// permissão em qualquer máquina, mesmo com usuário sem administrador):
/// baixa a versão nova, confere a assinatura digital (mesmo certificado do
/// .exe atual -- o arquivo vai rodar como SYSTEM) e dispara um script que
/// para o serviço, fecha só as instâncias DESTE .exe, guarda a versão
/// anterior, troca o arquivo e religa o serviço. O serviço religado reabre
/// o agente na bandeja de quem estiver logado. Se o serviço novo não subir,
/// o script volta a versão anterior. Log em
/// C:\ProgramData\RDIntranetAgent\atualizacao\atualizar-servico.log.
/// </summary>
public static class AtualizacaoServico
{
    /// <summary>Comando personalizado do serviço (128-255) -- "verifique atualização agora", mandado pela bandeja.</summary>
    public const int ComandoVerificarAgora = 130;

    private static readonly SemaphoreSlim EmAndamento = new(1, 1);

    public static string PastaAtualizacao => Path.Combine(
        Environment.GetFolderPath(Environment.SpecialFolder.CommonApplicationData), "RDIntranetAgent", "atualizacao");

    /// <summary>true = há serviço do agente registrado nesta máquina (aí a bandeja não se atualiza sozinha).</summary>
    public static bool ServicoInstalado()
    {
        try
        {
            using var controlador = new System.ServiceProcess.ServiceController(AgenteServico.NomeServico);
            _ = controlador.Status; // lança InvalidOperationException se o serviço não existe
            return true;
        }
        catch
        {
            return false;
        }
    }

    /// <summary>Pede ao serviço pra verificar agora (bandeja: "Atualizar agora" / "Forçar coleta" do portal). false = sem permissão ou serviço parado.</summary>
    public static bool PedirVerificacaoAoServico()
    {
        try
        {
            using var controlador = new System.ServiceProcess.ServiceController(AgenteServico.NomeServico);
            if (controlador.Status != System.ServiceProcess.ServiceControllerStatus.Running) return false;
            controlador.ExecuteCommand(ComandoVerificarAgora);
            return true;
        }
        catch
        {
            return false;
        }
    }

    /// <summary>Roda dentro do serviço. Não lança: qualquer falha vai pro log de eventos e a máquina segue na versão atual.</summary>
    public static async Task VerificarEAtualizarAsync(string origem)
    {
        if (!await EmAndamento.WaitAsync(0)) return; // já tem uma verificação rodando
        try
        {
            var config = Config.CarregarParaServico();
            if (!config.EstaConfigurado) return;

            var cliente = new AtualizacaoClient(config);
            var versaoServidor = await cliente.ObterVersaoDisponivelAsync();
            var versaoAtual = Assembly.GetExecutingAssembly().GetName().Version ?? new Version(0, 0, 0, 0);
            if (versaoServidor == null || versaoServidor <= new Version(versaoAtual.Major, versaoAtual.Minor, versaoAtual.Build))
            {
                return;
            }

            Directory.CreateDirectory(PastaAtualizacao);
            var novoExe = Path.Combine(PastaAtualizacao, "RdIntranetAgente.new.exe");
            var log = Path.Combine(PastaAtualizacao, "atualizar-servico.log");
            File.Delete(novoExe);

            if (!await cliente.BaixarNovaVersaoAsync(novoExe))
            {
                AgenteServico.RegistrarEvento($"Atualização para {versaoServidor} ({origem}): falha ao baixar o executável novo.", EventLogEntryType.Warning);
                return;
            }

            var exeAtual = Environment.ProcessPath;
            if (string.IsNullOrEmpty(exeAtual))
            {
                return;
            }

            var problemaAssinatura = ConferirAssinatura(exeAtual, novoExe);
            if (problemaAssinatura != null)
            {
                File.Delete(novoExe);
                AgenteServico.RegistrarEvento($"Atualização para {versaoServidor} RECUSADA: {problemaAssinatura}", EventLogEntryType.Error);
                return;
            }

            var script = Path.Combine(PastaAtualizacao, "atualizar-servico.bat");
            File.WriteAllText(script, ConteudoScript(novoExe, exeAtual, log), new UTF8Encoding(false));

            AgenteServico.RegistrarEvento($"Atualizando de {versaoAtual.ToString(3)} para {versaoServidor} ({origem}) -- o serviço vai parar, trocar o arquivo e voltar.", EventLogEntryType.Information);

            // Filho do serviço, mas segue vivo quando o serviço parar (não fica em job object).
            Process.Start(new ProcessStartInfo
            {
                FileName = "cmd.exe",
                Arguments = $"/c \"{script}\"",
                WindowStyle = ProcessWindowStyle.Hidden,
                CreateNoWindow = true,
                UseShellExecute = false
            });
        }
        catch (Exception ex)
        {
            AgenteServico.RegistrarEvento($"Falha na verificação de atualização pelo serviço ({origem}): {ex.Message}", EventLogEntryType.Warning);
        }
        finally
        {
            EmAndamento.Release();
        }
    }

    /// <summary>
    /// null = pode instalar. Se o .exe atual é assinado, o novo precisa ser
    /// assinado pelo MESMO certificado; .exe atual sem assinatura (build de
    /// desenvolvimento) não exige nada.
    /// </summary>
    internal static string? ConferirAssinatura(string exeAtual, string exeNovo)
    {
        var certificadoAtual = Certificado(exeAtual);
        if (certificadoAtual == null) return null;

        var certificadoNovo = Certificado(exeNovo);
        if (certificadoNovo == null) return "o executável novo não tem assinatura digital.";

        return string.Equals(certificadoAtual.Thumbprint, certificadoNovo.Thumbprint, StringComparison.OrdinalIgnoreCase)
            ? null
            : $"o executável novo foi assinado por outro certificado ({certificadoNovo.Subject}).";
    }

    private static X509Certificate2? Certificado(string arquivo)
    {
        try
        {
#pragma warning disable SYSLIB0057 // leitura da assinatura Authenticode de um .exe -- não há API substituta equivalente
            return new X509Certificate2(X509Certificate.CreateFromSignedFile(arquivo));
#pragma warning restore SYSLIB0057
        }
        catch
        {
            return null;
        }
    }

    /// <summary>
    /// Para o serviço, fecha só as instâncias deste .exe (outras cópias do
    /// agente na máquina ficam em paz), guarda a anterior em .old, troca e
    /// religa. Serviço novo não subiu em 30 s = volta a .old e religa.
    /// Esperas com "ping -n" e não "timeout": rodando sem console (dentro do
    /// serviço), o timeout do Windows sai na hora sem esperar nada.
    /// </summary>
    internal static string ConteudoScript(string origem, string destino, string log) => $@"@echo off
chcp 65001 >nul
setlocal
set ""SERVICO={AgenteServico.NomeServico}""
set ""ORIGEM={origem}""
set ""DESTINO={destino}""
set ""ANTERIOR={destino}.old""
set ""LOG={log}""
echo [%date% %time%] Iniciando atualizacao pelo servico > ""%LOG%""

sc stop ""%SERVICO%"" >>""%LOG%"" 2>&1
set espera=0
:esperar_parar
ping -n 2 127.0.0.1 >nul
sc query ""%SERVICO%"" | find ""STOPPED"" >nul
if not errorlevel 1 goto parado
set /a espera+=1
if %espera% lss 60 goto esperar_parar
echo [%date% %time%] Servico nao parou em 60s -- seguindo mesmo assim >>""%LOG%""
:parado

powershell -NoProfile -ExecutionPolicy Bypass -Command ""Get-Process RdIntranetAgente -ErrorAction SilentlyContinue | Where-Object {{ $_.Path -eq $env:DESTINO }} | Stop-Process -Force"" >>""%LOG%"" 2>&1
ping -n 3 127.0.0.1 >nul

copy /y ""%DESTINO%"" ""%ANTERIOR%"" >>""%LOG%"" 2>&1
set contador=0
:trocar
move /y ""%ORIGEM%"" ""%DESTINO%"" >>""%LOG%"" 2>&1
if not exist ""%ORIGEM%"" goto trocado
set /a contador+=1
if %contador% geq 30 goto falhou_troca
ping -n 2 127.0.0.1 >nul
goto trocar

:falhou_troca
echo [%date% %time%] Nao consegui trocar o arquivo -- mantendo a versao anterior >>""%LOG%""
sc start ""%SERVICO%"" >>""%LOG%"" 2>&1
exit /b 1

:trocado
echo [%date% %time%] Arquivo trocado, religando o servico >>""%LOG%""
sc start ""%SERVICO%"" >>""%LOG%"" 2>&1
set espera=0
:esperar_subir
ping -n 2 127.0.0.1 >nul
sc query ""%SERVICO%"" | find ""RUNNING"" >nul
if not errorlevel 1 goto ok
set /a espera+=1
if %espera% lss 30 goto esperar_subir

echo [%date% %time%] Versao nova nao subiu em 30s -- voltando a anterior >>""%LOG%""
sc stop ""%SERVICO%"" >>""%LOG%"" 2>&1
ping -n 4 127.0.0.1 >nul
copy /y ""%ANTERIOR%"" ""%DESTINO%"" >>""%LOG%"" 2>&1
sc start ""%SERVICO%"" >>""%LOG%"" 2>&1
exit /b 1

:ok
echo [%date% %time%] Atualizacao concluida, servico rodando >>""%LOG%""
exit /b 0
";
}
