using System.Security.Cryptography.X509Certificates;

namespace RdIntranetAgente;

/// <summary>
/// Garante que o certificado com o qual ESTE .exe foi assinado está nas
/// lojas de confiança da máquina (LocalMachine\Root + TrustedPublisher)
/// -- sem isso, mesmo um executável assinado ainda mostra "fornecedor
/// desconhecido"/aviso do SmartScreen, porque o certificado é
/// autoassinado (não vem de uma CA pública já confiável por padrão no
/// Windows). Rodar isso uma vez (best-effort, idempotente -- checa o
/// thumbprint antes de adicionar) em toda máquina onde o agente roda
/// elevado (tela normal) ou como SYSTEM (AgenteServico) faz o
/// "fornecedor desconhecido" desaparecer sozinho, sem precisar de nenhum
/// passo manual de instalação nem distribuir o certificado à parte --
/// ele já está embutido na assinatura do próprio .exe.
///
/// Não elimina o prompt de elevação em si (Windows sempre pede
/// confirmação pra elevar, mesmo com editor verificado) -- só troca o
/// aviso amarelo "Fornecedor: Desconhecido" por um "Editor verificado:
/// RD Tecnologia" quando o prompt aparecer (ex: primeiro clique manual
/// antes do serviço/tarefa entrarem em ação).
/// </summary>
internal static class ConfiancaCertificado
{
    public static void GarantirConfianca()
    {
        try
        {
            var caminhoExe = Environment.ProcessPath;
            if (string.IsNullOrEmpty(caminhoExe) || !File.Exists(caminhoExe))
            {
                return;
            }

            using var certificado = new X509Certificate2(X509Certificate.CreateFromSignedFile(caminhoExe));

            AdicionarSeNecessario(StoreName.Root, certificado);
            AdicionarSeNecessario(StoreName.TrustedPublisher, certificado);
        }
        catch
        {
            // Build sem assinatura (dev local) ou sem permissao pra mexer
            // na loja LocalMachine -- best-effort, nao impede o agente de
            // funcionar, so continua mostrando "fornecedor desconhecido"
            // se aparecer algum prompt.
        }
    }

    private static void AdicionarSeNecessario(StoreName nome, X509Certificate2 certificado)
    {
        using var loja = new X509Store(nome, StoreLocation.LocalMachine);
        loja.Open(OpenFlags.ReadWrite);

        var jaConfia = loja.Certificates.Find(X509FindType.FindByThumbprint, certificado.Thumbprint, validOnly: false).Count > 0;
        if (!jaConfia)
        {
            loja.Add(certificado);
        }
    }
}
