using System.Net.Http.Headers;
using System.Runtime.InteropServices;
using System.Security.Cryptography;
using System.Security.Principal;
using System.Text;
using System.Text.Json;
using System.Text.Json.Serialization;
using Microsoft.Win32;

namespace RdIntranetAgente;

public sealed class ItemAmbiente
{
    [JsonPropertyName("id")] public int Id { get; set; }
    [JsonPropertyName("tipo")] public string Tipo { get; set; } = "";
    [JsonPropertyName("nome")] public string Nome { get; set; } = "";
    [JsonPropertyName("url")] public string? Url { get; set; }
    [JsonPropertyName("caminho")] public string? Caminho { get; set; }
    [JsonPropertyName("argumentos")] public string? Argumentos { get; set; }
    [JsonPropertyName("pasta")] public string? Pasta { get; set; }
    [JsonPropertyName("letra")] public string? Letra { get; set; }
    [JsonPropertyName("unc")] public string? Unc { get; set; }
    [JsonPropertyName("usuario")] public string? Usuario { get; set; }
    [JsonPropertyName("senha")] public string? Senha { get; set; }
}

public sealed record ResultadoItemAmbiente(int Id, string Nome, bool Ok, string Mensagem);

/// <summary>
/// Ambiente padrão do portal (Ativos > Ambiente padrão): programas que iniciam
/// com o Windows, atalhos de sites e sistemas, pastas mapeadas e impressoras.
/// Duas metades:
///  - MÁQUINA (serviço, SYSTEM): vale para todos os usuários -- Run do HKLM e
///    Área de Trabalho pública;
///  - USUÁRIO (ícone na sessão): pastas mapeadas, impressoras e o que for só de
///    um grupo do Entra -- HKCU e a Área de Trabalho da pessoa.
/// Cada metade guarda no registro o que ELA criou (HKLM/HKCU\...\RDIntranet\Ambiente):
/// item excluído no portal é desfeito aqui, e nada criado à mão pelo usuário é tocado.
/// Conferido ao entrar e a cada 30 min; só registra atividade quando muda algo ou falha.
/// </summary>
public static class AmbientePadrao
{
    private const string ChaveControle = @"SOFTWARE\RDIntranet\Ambiente";
    private const string ChaveRun = @"SOFTWARE\Microsoft\Windows\CurrentVersion\Run";
    private const string PrefixoRun = "RD - ";

    private static int _aplicandoMaquina;
    /// <summary>Só o --teste-ambiente: busca os itens "de máquina" mas grava no usuário atual (testar sem administrador).</summary>
    internal static bool SimularMaquinaNoUsuario;
    private static int _aplicandoUsuario;

    public static async Task AplicarAsync(Config config, string? machineGuid, bool contextoMaquina)
    {
        if (!Entrar(contextoMaquina)) return;
        try
        {
            if (!config.EstaConfigurado || string.IsNullOrEmpty(machineGuid)) return;

            var identidade = WindowsIdentity.GetCurrent();
            var contexto = contextoMaquina ? "maquina" : "usuario";
            var itens = await BaixarAsync(config, machineGuid, contexto, identidade.User?.Value ?? "");
            if (itens == null) return; // portal fora do ar ou sem o recurso: não desfaz nada

            if (contextoMaquina && !SimularMaquinaNoUsuario) GarantirConexoesVinculadas();
            var resultados = Aplicar(itens, contextoMaquina, config);
            await EnviarResultadoAsync(config, machineGuid, contexto, contextoMaquina ? "" : identidade.Name, resultados);
        }
        catch (Exception ex)
        {
            LogAtividade.Registrar(NivelAtividade.Aviso, "AMBIENTE", "Falha ao conferir o ambiente padrão: " + ex.Message);
        }
        finally
        {
            Sair(contextoMaquina);
        }
    }

    private static bool Entrar(bool maquina) =>
        maquina ? Interlocked.Exchange(ref _aplicandoMaquina, 1) == 0 : Interlocked.Exchange(ref _aplicandoUsuario, 1) == 0;

    private static void Sair(bool maquina)
    {
        if (maquina) Interlocked.Exchange(ref _aplicandoMaquina, 0);
        else Interlocked.Exchange(ref _aplicandoUsuario, 0);
    }

    // ------------------------------------------------------------------ aplicação

    private sealed record Criado(string Tipo, string Alvo);

    private static List<ResultadoItemAmbiente> Aplicar(List<ItemAmbiente> itens, bool maquina, Config config)
    {
        var hive = maquina && !SimularMaquinaNoUsuario ? RegistryKey.OpenBaseKey(RegistryHive.LocalMachine, RegistryView.Registry64) : Registry.CurrentUser;
        using var controle = hive.CreateSubKey(ChaveControle);
        var criadosAntes = LerCriados(controle);
        var resultados = new List<ResultadoItemAmbiente>();
        var mudancas = new List<string>();
        var desejados = new HashSet<string>();

        foreach (var item in itens)
        {
            var chave = "item" + item.Id;
            desejados.Add(chave);
            try
            {
                var ausente = ProgramaAusente(item);
                if (ausente != null)
                {
                    // Item "para todos" de um programa que esta máquina não tem: não cria atalho quebrado
                    // nem entrada de inicialização -- e desfaz se tinha criado antes (programa desinstalado).
                    if (criadosAntes.TryGetValue(chave, out var antigo))
                    {
                        Desfazer(antigo, hive);
                        controle.DeleteValue(chave, false);
                    }
                    criadosAntes.Remove(chave);
                    resultados.Add(new ResultadoItemAmbiente(item.Id, item.Nome, true, ausente));
                    continue;
                }

                var (alvo, mudou, aviso) = AplicarItem(item, maquina, config, hive);
                // Mudou de nome/letra/caminho: desfaz o que a versão anterior do item tinha criado.
                if (criadosAntes.TryGetValue(chave, out var anterior) && !string.Equals(anterior.Alvo, alvo, StringComparison.OrdinalIgnoreCase))
                {
                    Desfazer(anterior, hive);
                }
                controle.SetValue(chave, JsonSerializer.Serialize(new Criado(item.Tipo, alvo)));
                if (mudou) mudancas.Add(item.Nome);
                resultados.Add(new ResultadoItemAmbiente(item.Id, item.Nome, aviso == null, aviso ?? "ok"));
            }
            catch (Exception ex)
            {
                resultados.Add(new ResultadoItemAmbiente(item.Id, item.Nome, false, ex.Message));
            }
        }

        foreach (var (chave, criado) in criadosAntes.Where(c => !desejados.Contains(c.Key)))
        {
            try
            {
                Desfazer(criado, hive);
                controle.DeleteValue(chave, false);
                mudancas.Add("removido: " + criado.Alvo);
            }
            catch
            {
                // tenta de novo na próxima conferência
            }
        }

        var quem = maquina ? "todos os usuários" : "este usuário";
        if (mudancas.Count > 0)
        {
            LogAtividade.Registrar(NivelAtividade.Sucesso, "AMBIENTE", $"Ambiente padrão ({quem}) atualizado: {string.Join(", ", mudancas)}.");
        }
        foreach (var falha in resultados.Where(r => !r.Ok))
        {
            LogAtividade.Registrar(NivelAtividade.Aviso, "AMBIENTE", $"Ambiente padrão ({quem}) -- {falha.Nome}: {falha.Mensagem}");
        }
        return resultados;
    }

    /// <returns>(alvo criado, se mudou algo agora, aviso de falha ou null)</returns>
    private static (string alvo, bool mudou, string? aviso) AplicarItem(ItemAmbiente item, bool maquina, Config config, RegistryKey hive)
    {
        var desktop = Environment.GetFolderPath(maquina && !SimularMaquinaNoUsuario ? Environment.SpecialFolder.CommonDesktopDirectory : Environment.SpecialFolder.DesktopDirectory);
        switch (item.Tipo)
        {
            case "inicializar":
            {
                var caminho = item.Caminho ?? "";
                var comando = "\"" + caminho + "\"" + (string.IsNullOrWhiteSpace(item.Argumentos) ? "" : " " + item.Argumentos);
                var nomeValor = PrefixoRun + item.Nome;
                using var run = hive.CreateSubKey(ChaveRun);
                var atual = run.GetValue(nomeValor) as string;
                var mudou = !string.Equals(atual, comando, StringComparison.Ordinal);
                if (mudou) run.SetValue(nomeValor, comando);
                return ("run:" + nomeValor, mudou, null);
            }
            case "atalho_site":
            {
                var arquivo = Path.Combine(desktop, item.Nome + ".url");
                var conteudo = "[InternetShortcut]\r\nURL=" + item.Url + "\r\n";
                var mudou = !File.Exists(arquivo) || File.ReadAllText(arquivo) != conteudo;
                if (mudou) File.WriteAllText(arquivo, conteudo);
                return ("arquivo:" + arquivo, mudou, null);
            }
            case "atalho_programa":
            {
                var arquivo = Path.Combine(desktop, item.Nome + ".lnk");
                var mudou = CriarAtalho(arquivo, item.Caminho ?? "", item.Argumentos ?? "", item.Pasta ?? "");
                return ("arquivo:" + arquivo, mudou, null);
            }
            case "unidade_mapeada":
                return MapearUnidade(item, config);
            case "impressora":
                return ConectarImpressora(item);
            default:
                return ("", false, "tipo de item desconhecido por esta versão do agente");
        }
    }

    /// <summary>Texto informativo quando o item aponta para um programa local que esta máquina não tem.</summary>
    private static string? ProgramaAusente(ItemAmbiente item)
    {
        if (item.Tipo is not ("inicializar" or "atalho_programa")) return null;
        var caminho = item.Caminho ?? "";
        if (caminho.StartsWith(@"\\")) return null; // rede: o SYSTEM não enxerga, confia no caminho
        return File.Exists(caminho) ? null : "ignorado: programa não instalado nesta máquina";
    }

    private static bool CriarAtalho(string arquivo, string alvo, string argumentos, string pasta)
    {
        var tipo = Type.GetTypeFromProgID("WScript.Shell") ?? throw new InvalidOperationException("WScript.Shell indisponível");
        dynamic shell = Activator.CreateInstance(tipo)!;
        try
        {
            dynamic atalho = shell.CreateShortcut(arquivo);
            var pastaFinal = string.IsNullOrWhiteSpace(pasta) ? (Path.GetDirectoryName(alvo) ?? "") : pasta;
            if (File.Exists(arquivo)
                && string.Equals((string)atalho.TargetPath, alvo, StringComparison.OrdinalIgnoreCase)
                && (string)atalho.Arguments == argumentos
                && string.Equals((string)atalho.WorkingDirectory, pastaFinal, StringComparison.OrdinalIgnoreCase))
            {
                return false;
            }
            atalho.TargetPath = alvo;
            atalho.Arguments = argumentos;
            atalho.WorkingDirectory = pastaFinal;
            atalho.Save();
            return true;
        }
        finally
        {
            Marshal.FinalReleaseComObject(shell);
        }
    }

    private static void Desfazer(Criado criado, RegistryKey hive)
    {
        var alvo = criado.Alvo;
        if (alvo.StartsWith("run:"))
        {
            using var run = hive.OpenSubKey(ChaveRun, true);
            run?.DeleteValue(alvo[4..], false);
        }
        else if (alvo.StartsWith("arquivo:"))
        {
            var arquivo = alvo[8..];
            if (File.Exists(arquivo)) File.Delete(arquivo);
        }
        else if (alvo.StartsWith("unidade:"))
        {
            // unidade:X|\\servidor\pasta -- só desconecta se ainda for a mesma pasta que nós mapeamos.
            var partes = alvo[8..].Split('|', 2);
            if (partes.Length == 2 && string.Equals(PastaDaUnidade(partes[0]), partes[1], StringComparison.OrdinalIgnoreCase))
            {
                WNetCancelConnection2(partes[0] + ":", CONNECT_UPDATE_PROFILE, true);
            }
        }
        else if (alvo.StartsWith("impressora:"))
        {
            ExecutarOculto("rundll32.exe", $"printui.dll,PrintUIEntry /dn /q /n \"{alvo[11..]}\"");
        }
    }

    // ------------------------------------------------------------------ pastas mapeadas

    private static (string alvo, bool mudou, string? aviso) MapearUnidade(ItemAmbiente item, Config config)
    {
        var letra = (item.Letra ?? "").Trim().TrimEnd(':').ToUpperInvariant();
        var unc = (item.Unc ?? "").TrimEnd('\\');
        var alvo = $"unidade:{letra}|{unc}";
        if (letra.Length != 1 || !unc.StartsWith(@"\\")) return (alvo, false, "letra ou caminho inválido");

        var servidor = unc.TrimStart('\\').Split('\\')[0];
        var senha = string.IsNullOrEmpty(item.Senha) ? null : Decifrar(item.Senha, config.ApiKey);
        if (!string.IsNullOrWhiteSpace(item.Usuario) && senha != null)
        {
            // No Gerenciador de Credenciais da pessoa: o Windows reconecta a unidade sozinho no próximo login.
            GravarCredencial(servidor, item.Usuario!, senha);
        }

        var atual = PastaDaUnidade(letra);
        if (string.Equals(atual, unc, StringComparison.OrdinalIgnoreCase) && Directory.Exists(letra + @":\"))
        {
            return (alvo, false, null);
        }
        if (atual != null && !string.Equals(atual, unc, StringComparison.OrdinalIgnoreCase) && !CriadoPorNos(letra))
        {
            return (alvo, false, $"a letra {letra}: já está em uso por {atual}");
        }
        if (atual != null)
        {
            WNetCancelConnection2(letra + ":", CONNECT_UPDATE_PROFILE, true);
        }

        var recurso = new NETRESOURCE { dwType = RESOURCETYPE_DISK, lpLocalName = letra + ":", lpRemoteName = unc };
        var erro = WNetAddConnection2(ref recurso, null, null, CONNECT_UPDATE_PROFILE);
        if (erro == 1219)
        {
            return (alvo, false, $"o Windows já tem uma conexão com {servidor} usando outro usuário -- saia e entre de novo no Windows");
        }
        return erro == 0 ? (alvo, true, null) : (alvo, false, $"não foi possível mapear {letra}: ({new System.ComponentModel.Win32Exception(erro).Message})");
    }

    private static bool CriadoPorNos(string letra)
    {
        using var controle = Registry.CurrentUser.OpenSubKey(ChaveControle);
        return LerCriados(controle).Values.Any(c => c.Alvo.StartsWith($"unidade:{letra}|", StringComparison.OrdinalIgnoreCase));
    }

    /// <summary>Pasta de rede da letra no perfil (HKCU\Network), ou null se a letra não está mapeada.</summary>
    private static string? PastaDaUnidade(string letra)
    {
        using var chave = Registry.CurrentUser.OpenSubKey(@"Network\" + letra);
        return (chave?.GetValue("RemotePath") as string)?.TrimEnd('\\');
    }

    /// <summary>
    /// O ícone de quem é administrador roda elevado, e unidade mapeada por um processo
    /// elevado não aparece no Explorer (que roda sem elevação) até o próximo login.
    /// EnableLinkedConnections faz o Windows mostrar nos dois lados (vale após reiniciar).
    /// </summary>
    private static void GarantirConexoesVinculadas()
    {
        try
        {
            using var chave = RegistryKey.OpenBaseKey(RegistryHive.LocalMachine, RegistryView.Registry64)
                .CreateSubKey(@"SOFTWARE\Microsoft\Windows\CurrentVersion\Policies\System");
            if (chave.GetValue("EnableLinkedConnections") is not int v || v != 1)
            {
                chave.SetValue("EnableLinkedConnections", 1, RegistryValueKind.DWord);
            }
        }
        catch
        {
            // sem isso a unidade só aparece no próximo login -- não impede o resto
        }
    }

    // ------------------------------------------------------------------ impressoras

    private static (string alvo, bool mudou, string? aviso) ConectarImpressora(ItemAmbiente item)
    {
        var fila = (item.Unc ?? "").Trim();
        var alvo = "impressora:" + fila;
        var partes = fila.TrimStart('\\').Split('\\');
        if (partes.Length != 2) return (alvo, false, "impressora inválida");

        using (var conexoes = Registry.CurrentUser.OpenSubKey(@"Printers\Connections"))
        {
            if (conexoes?.GetSubKeyNames().Any(n => string.Equals(n, $",,{partes[0]},{partes[1]}", StringComparison.OrdinalIgnoreCase)) == true)
            {
                return (alvo, false, null);
            }
        }
        var codigo = ExecutarOculto("rundll32.exe", $"printui.dll,PrintUIEntry /in /q /n \"{fila}\"");
        using var depois = Registry.CurrentUser.OpenSubKey(@"Printers\Connections");
        var conectou = depois?.GetSubKeyNames().Any(n => string.Equals(n, $",,{partes[0]},{partes[1]}", StringComparison.OrdinalIgnoreCase)) == true;
        return conectou ? (alvo, true, null) : (alvo, false, $"não foi possível conectar a impressora (servidor fora do ar ou sem permissão; código {codigo})");
    }

    private static int ExecutarOculto(string arquivo, string argumentos)
    {
        using var p = System.Diagnostics.Process.Start(new System.Diagnostics.ProcessStartInfo(arquivo, argumentos)
        {
            CreateNoWindow = true,
            UseShellExecute = false,
            WindowStyle = System.Diagnostics.ProcessWindowStyle.Hidden,
        });
        if (p == null) return -1;
        p.WaitForExit(60_000);
        return p.HasExited ? p.ExitCode : -2;
    }

    // ------------------------------------------------------------------ controle

    private static Dictionary<string, Criado> LerCriados(RegistryKey? controle)
    {
        var lista = new Dictionary<string, Criado>(StringComparer.OrdinalIgnoreCase);
        foreach (var nome in controle?.GetValueNames() ?? Array.Empty<string>())
        {
            try
            {
                var criado = JsonSerializer.Deserialize<Criado>(controle!.GetValue(nome) as string ?? "");
                if (criado != null && !string.IsNullOrEmpty(criado.Alvo)) lista[nome] = criado;
            }
            catch
            {
                // valor estranho: ignora
            }
        }
        return lista;
    }

    // ------------------------------------------------------------------ portal

    private static HttpClient NovoCliente(Config config)
    {
        var handler = new HttpClientHandler { ServerCertificateCustomValidationCallback = (msg, cert, chain, erros) => true };
        var cliente = new HttpClient(handler) { Timeout = TimeSpan.FromSeconds(30) };
        cliente.DefaultRequestHeaders.Add("X-RD-Agente-Chave", config.ApiKey);
        return cliente;
    }

    private sealed class RespostaAmbiente
    {
        [JsonPropertyName("success")] public bool Success { get; set; }
        [JsonPropertyName("itens")] public List<ItemAmbiente>? Itens { get; set; }
    }

    private static async Task<List<ItemAmbiente>?> BaixarAsync(Config config, string guid, string contexto, string sid)
    {
        using var cliente = NovoCliente(config);
        var corpo = new StringContent(JsonSerializer.Serialize(new { machine_guid = guid, contexto, sid }), Encoding.UTF8);
        corpo.Headers.ContentType = new MediaTypeHeaderValue("application/json") { CharSet = "utf-8" };
        try
        {
            var resposta = await cliente.PostAsync(config.ServerUrl.TrimEnd('/') + "/api/ativos/ambiente", corpo);
            if (!resposta.IsSuccessStatusCode) return null;
            var dados = JsonSerializer.Deserialize<RespostaAmbiente>(await resposta.Content.ReadAsStringAsync());
            return dados is { Success: true } ? dados.Itens ?? new() : null;
        }
        catch
        {
            return null;
        }
    }

    private static async Task EnviarResultadoAsync(Config config, string guid, string contexto, string usuario, List<ResultadoItemAmbiente> itens)
    {
        using var cliente = NovoCliente(config);
        var json = JsonSerializer.Serialize(new
        {
            machine_guid = guid,
            contexto,
            usuario,
            itens = itens.Select(i => new { id = i.Id, nome = i.Nome, ok = i.Ok, mensagem = i.Mensagem }),
        });
        var corpo = new StringContent(json, Encoding.UTF8);
        corpo.Headers.ContentType = new MediaTypeHeaderValue("application/json") { CharSet = "utf-8" };
        try
        {
            await cliente.PostAsync(config.ServerUrl.TrimEnd('/') + "/api/ativos/ambiente/resultado", corpo);
        }
        catch
        {
            // a próxima conferência manda de novo
        }
    }

    /// <summary>Senha da pasta: AES-256-GCM com chave derivada da chave de API (ver AmbientePadraoService::cifrarParaAgente).</summary>
    private static string? Decifrar(string base64, string chaveApi)
    {
        try
        {
            var bruto = Convert.FromBase64String(base64);
            if (bruto.Length < 12 + 16) return null;
            var chave = SHA256.HashData(Encoding.UTF8.GetBytes("rd-ambiente|" + chaveApi));
            var nonce = bruto.AsSpan(0, 12);
            var tag = bruto.AsSpan(bruto.Length - 16);
            var cifrado = bruto.AsSpan(12, bruto.Length - 28);
            var texto = new byte[cifrado.Length];
            using var aes = new AesGcm(chave, 16);
            aes.Decrypt(nonce, cifrado, tag, texto);
            return Encoding.UTF8.GetString(texto);
        }
        catch
        {
            return null;
        }
    }

    // ------------------------------------------------------------------ Win32

    private const int RESOURCETYPE_DISK = 1;
    private const int CONNECT_UPDATE_PROFILE = 1;
    private const int CRED_TYPE_DOMAIN_PASSWORD = 2;
    private const int CRED_PERSIST_LOCAL_MACHINE = 2;

    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
    private struct NETRESOURCE
    {
        public int dwScope;
        public int dwType;
        public int dwDisplayType;
        public int dwUsage;
        public string? lpLocalName;
        public string? lpRemoteName;
        public string? lpComment;
        public string? lpProvider;
    }

    [DllImport("mpr.dll", CharSet = CharSet.Unicode)]
    private static extern int WNetAddConnection2(ref NETRESOURCE recurso, string? senha, string? usuario, int flags);

    [DllImport("mpr.dll", CharSet = CharSet.Unicode)]
    private static extern int WNetCancelConnection2(string nome, int flags, bool forcar);

    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
    private struct CREDENTIAL
    {
        public int Flags;
        public int Type;
        public string TargetName;
        public string? Comment;
        public System.Runtime.InteropServices.ComTypes.FILETIME LastWritten;
        public int CredentialBlobSize;
        public IntPtr CredentialBlob;
        public int Persist;
        public int AttributeCount;
        public IntPtr Attributes;
        public string? TargetAlias;
        public string UserName;
    }

    [DllImport("advapi32.dll", CharSet = CharSet.Unicode, SetLastError = true, EntryPoint = "CredWriteW")]
    private static extern bool CredWrite(ref CREDENTIAL credencial, int flags);

    private static void GravarCredencial(string servidor, string usuario, string senha)
    {
        var bytes = Encoding.Unicode.GetBytes(senha);
        var ponteiro = Marshal.AllocHGlobal(bytes.Length);
        try
        {
            Marshal.Copy(bytes, 0, ponteiro, bytes.Length);
            var credencial = new CREDENTIAL
            {
                Type = CRED_TYPE_DOMAIN_PASSWORD,
                TargetName = servidor,
                Comment = "RD.Intranet - Ambiente padrão",
                CredentialBlobSize = bytes.Length,
                CredentialBlob = ponteiro,
                Persist = CRED_PERSIST_LOCAL_MACHINE,
                UserName = usuario,
            };
            CredWrite(ref credencial, 0);
        }
        finally
        {
            for (var i = 0; i < bytes.Length; i++) Marshal.WriteByte(ponteiro, i, 0);
            Marshal.FreeHGlobal(ponteiro);
            Array.Clear(bytes);
        }
    }
}
