using System.Diagnostics;
using System.Runtime.InteropServices;
using System.Security.Cryptography;
using System.ServiceProcess;
using System.Text.Json;
using System.Text.Json.Serialization;

namespace RdIntranetAgente;

public sealed class MeshAgentInstallerService
{
    private const long TamanhoMinimoInstaller = 500_000;
    private const long TamanhoMaximoInstaller = 40L * 1024 * 1024;
    private static readonly SemaphoreSlim TravaInstalacao = new(1, 1);

    private sealed class RespostaDownload
    {
        [JsonPropertyName("success")]
        public bool Success { get; set; }

        [JsonPropertyName("message")]
        public string? Message { get; set; }
    }

    private readonly Config _config;
    private readonly string _machineGuid;

    public MeshAgentInstallerService(Config config, string machineGuid)
    {
        _config = config;
        _machineGuid = machineGuid;
    }

    public async Task<object> InstalarAsync()
    {
        var arquitetura = RuntimeInformation.OSArchitecture == Architecture.Arm64 ? "arm64" : "x64";

        if (MeshAgentInstalado(out var servicoExistente))
        {
            LogAtividade.Registrar(NivelAtividade.Info, "ACESSO REMOTO", $"MeshAgent já está instalado ({servicoExistente}). Instalação ignorada.");
            return new
            {
                instalado = false,
                ja_instalado = true,
                arquitetura,
                mensagem = "O MeshAgent já está instalado nesta máquina."
            };
        }

        if (!await TravaInstalacao.WaitAsync(0))
            throw new InvalidOperationException("Uma instalação do MeshAgent já está em andamento.");

        string? arquivoTemporario = null;
        try
        {
            LogAtividade.Registrar(NivelAtividade.Info, "ACESSO REMOTO", $"Solicitando instalador MeshAgent para Windows {arquitetura}.");
            var binario = await BaixarInstallerAsync(arquitetura);
            var pasta = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "RDIntranetAgent", "MeshInstall");
            Directory.CreateDirectory(pasta);
            arquivoTemporario = Path.Combine(pasta, $"MeshAgent-{Guid.NewGuid():N}.exe");
            await File.WriteAllBytesAsync(arquivoTemporario, binario);

            using var processo = new Process
            {
                StartInfo = new ProcessStartInfo
                {
                    FileName = arquivoTemporario,
                    UseShellExecute = false,
                    CreateNoWindow = true,
                    RedirectStandardOutput = true,
                    RedirectStandardError = true
                }
            };
            processo.StartInfo.ArgumentList.Add("-fullinstall");
            processo.Start();
            var saida = processo.StandardOutput.ReadToEndAsync();
            var erro = processo.StandardError.ReadToEndAsync();
            using var timeout = new CancellationTokenSource(TimeSpan.FromSeconds(90));
            try
            {
                await processo.WaitForExitAsync(timeout.Token);
            }
            catch (OperationCanceledException)
            {
                try { processo.Kill(entireProcessTree: true); } catch { }
                await processo.WaitForExitAsync();
                throw new System.TimeoutException("O instalador do MeshAgent excedeu 90 segundos.");
            }

            var servicoInstalado = await AguardarServicoAsync(TimeSpan.FromSeconds(20));
            if (!servicoInstalado)
            {
                var detalhe = string.Join(" ", new[] { (await erro).Trim(), (await saida).Trim() }.Where(s => s.Length > 0));
                if (detalhe.Length > 240) detalhe = detalhe[..240];
                throw new InvalidOperationException(
                    processo.ExitCode == 0
                        ? "O instalador terminou, mas o serviço Mesh Agent não apareceu no Windows."
                        : $"O instalador MeshAgent terminou com código {processo.ExitCode}. {detalhe}".Trim());
            }

            LogAtividade.Registrar(NivelAtividade.Sucesso, "ACESSO REMOTO", $"MeshAgent instalado e serviço confirmado para Windows {arquitetura}.");
            return new
            {
                instalado = true,
                ja_instalado = false,
                arquitetura,
                servico = "Mesh Agent",
                mensagem = "MeshAgent instalado e serviço do Windows iniciado. Aguardando conexão com o MeshCentral."
            };
        }
        catch (Exception ex)
        {
            LogAtividade.Registrar(NivelAtividade.Erro, "ACESSO REMOTO", $"Falha na instalação do MeshAgent: {ex.Message}");
            throw;
        }
        finally
        {
            if (arquivoTemporario != null)
            {
                try { File.Delete(arquivoTemporario); } catch { }
            }
            TravaInstalacao.Release();
        }
    }

    private async Task<byte[]> BaixarInstallerAsync(string arquitetura)
    {
        var handler = new HttpClientHandler
        {
            ServerCertificateCustomValidationCallback = (mensagem, certificado, cadeia, erros) => true
        };
        using var cliente = new HttpClient(handler) { Timeout = TimeSpan.FromSeconds(30) };
        cliente.DefaultRequestHeaders.Add("X-RD-Agente-Chave", _config.ApiKey);
        var payload = JsonSerializer.Serialize(new { machine_guid = _machineGuid, arquitetura });
        using var conteudo = new StringContent(payload, System.Text.Encoding.UTF8, "application/json");
        using var resposta = await cliente.PostAsync(_config.ServerUrl.TrimEnd('/') + "/api/ativos/agente/meshagent", conteudo);
        if (!resposta.IsSuccessStatusCode)
        {
            var jsonErro = await resposta.Content.ReadAsStringAsync();
            var erro = JsonSerializer.Deserialize<RespostaDownload>(jsonErro);
            throw new InvalidOperationException(erro?.Message ?? $"O servidor recusou o instalador MeshAgent (HTTP {(int)resposta.StatusCode}).");
        }

        if (resposta.Content.Headers.ContentLength is long tamanho &&
            (tamanho < TamanhoMinimoInstaller || tamanho > TamanhoMaximoInstaller))
            throw new InvalidDataException("O tamanho do instalador MeshAgent não é válido.");

        var bytes = await resposta.Content.ReadAsByteArrayAsync();
        if (bytes.LongLength < TamanhoMinimoInstaller || bytes.LongLength > TamanhoMaximoInstaller)
            throw new InvalidDataException("O tamanho do instalador MeshAgent não é válido.");

        var hashEsperado = resposta.Headers.TryGetValues("X-MeshAgent-SHA256", out var valores)
            ? valores.FirstOrDefault()
            : null;
        if (string.IsNullOrWhiteSpace(hashEsperado))
            throw new InvalidDataException("O servidor não informou o hash do instalador MeshAgent.");

        var hashAtual = Convert.ToHexString(SHA256.HashData(bytes));
        if (!CryptographicOperations.FixedTimeEquals(
                System.Text.Encoding.ASCII.GetBytes(hashAtual),
                System.Text.Encoding.ASCII.GetBytes(hashEsperado.Trim().ToUpperInvariant())))
            throw new InvalidDataException("A verificação SHA-256 do instalador MeshAgent falhou.");

        return bytes;
    }

    private static bool MeshAgentInstalado(out string nomeServico)
    {
        try
        {
            foreach (var servico in ServiceController.GetServices())
            {
                using (servico)
                {
                    var identificacao = servico.ServiceName + " " + servico.DisplayName;
                    if (identificacao.Contains("Mesh Agent", StringComparison.OrdinalIgnoreCase))
                    {
                        nomeServico = servico.ServiceName;
                        return true;
                    }
                }
            }
        }
        catch (InvalidOperationException)
        {
            // O Service Control Manager pode não responder durante o boot; a validação após instalar continua obrigatória.
        }

        nomeServico = "";
        return false;
    }

    private static async Task<bool> AguardarServicoAsync(TimeSpan limite)
    {
        var inicio = DateTime.UtcNow;
        while (DateTime.UtcNow - inicio < limite)
        {
            if (MeshAgentInstalado(out _)) return true;
            await Task.Delay(TimeSpan.FromSeconds(2));
        }
        return MeshAgentInstalado(out _);
    }
}
