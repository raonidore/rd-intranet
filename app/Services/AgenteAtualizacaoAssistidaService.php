<?php

namespace App\Services;

/**
 * Atualização assistida pelo servidor para agentes que não conseguem se
 * atualizar sozinhos.
 *
 * Até a 1.0.32, o agente trocava o próprio .exe com um script .bat gravado
 * em UTF-8, mas o cmd lê .bat na página de código do DOS (850). Em contas
 * com acento ("Recepção AV - 1", "IMUNOQUÍMICA") o caminho do %TEMP% vira
 * lixo, o script falha na primeira linha e o agente fecha sem se trocar --
 * toda vez. A 1.0.33 corrigiu o script, mas uma máquina presa numa versão
 * antiga ainda usa o script quebrado pra tentar chegar nela.
 *
 * Aqui o servidor faz a troca por fora do script, usando dois canais que
 * todo agente tem desde a 1.0.6:
 *   1. comando "enviar_arquivo": o próprio agente (.NET) baixa o .exe novo
 *      pra uma pasta sem acento;
 *   2. solicitação "executar_powershell": abre um PowerShell separado que
 *      espera o arquivo, confere versão e assinatura, fecha o agente, troca
 *      o .exe e reabre -- PowerShell não tem o problema de página de código.
 *
 * Disparado no checkin, só pra agentes anteriores à 1.0.33 com acento no
 * usuário logado, no máximo uma tentativa a cada INTERVALO_HORAS por versão.
 */
class AgenteAtualizacaoAssistidaService
{
    /** Primeira versão cujo script de troca funciona em caminho com acento. */
    private const VERSAO_CORRIGIDA = '1.0.33';
    private const INTERVALO_HORAS = 2;
    private const PASTA_DESTINO = 'C:\\ProgramData\\RDIntranetAgenteAtualizacao';

    public function __construct(private AtivoService $ativos)
    {
    }

    public function verificar(int $ativoId, ?string $versaoAgente, ?string $usuarioLogado): void
    {
        try {
            $this->verificarOuFalhar($ativoId, (string)$versaoAgente, (string)$usuarioLogado);
        } catch (\Throwable) {
            // nunca pode derrubar o checkin -- tenta de novo no próximo
        }
    }

    public static function precisa(string $versaoAgente, string $versaoServidor, string $usuarioLogado): bool
    {
        if ($versaoAgente === '' || $versaoServidor === '') {
            return false;
        }

        return version_compare($versaoAgente, $versaoServidor, '<')
            && version_compare($versaoAgente, self::VERSAO_CORRIGIDA, '<')
            && preg_match('/[^\x20-\x7E]/', $usuarioLogado) === 1;
    }

    private function verificarOuFalhar(int $ativoId, string $versaoAgente, string $usuarioLogado): void
    {
        $versaoServidor = trim((string)ConfigService::get('ativos_agente_exe_versao', ''));
        $exe = __DIR__ . '/../../storage/uploads/agente/RdIntranetAgente.exe';

        if (!self::precisa($versaoAgente, $versaoServidor, $usuarioLogado) || !is_file($exe)) {
            return;
        }

        $chave = "agente_atualizacao_assistida_{$ativoId}";
        [$versaoTentada, $quando] = array_pad(explode('|', (string)ConfigService::get($chave, '')), 2, '0');
        if ($versaoTentada === $versaoServidor && time() - (int)$quando < self::INTERVALO_HORAS * 3600) {
            return;
        }
        ConfigService::set($chave, $versaoServidor . '|' . time());

        $pasta = __DIR__ . '/../../storage/uploads/ativos_transferencias';
        if (!is_dir($pasta)) {
            @mkdir($pasta, 0775, true);
        }
        $anexo = $pasta . '/atualizacao_assistida_' . $ativoId . '_' . time() . '.exe';
        if (!@copy($exe, $anexo)) {
            return;
        }

        $destino = self::PASTA_DESTINO . '\\RdIntranetAgente.exe';
        $envio = $this->ativos->enviarComando(
            $ativoId,
            'enviar_arquivo',
            'Atualização assistida',
            $destino,
            "RdIntranetAgente.exe {$versaoServidor}",
            $anexo
        );
        if (!($envio['success'] ?? false)) {
            @unlink($anexo);
            return;
        }

        $this->ativos->solicitarListagem($ativoId, 'executar_powershell', self::script($destino, $versaoServidor), 'Atualização assistida', false);

        AuditService::registrar('Ativos', 'Agente Windows', sprintf(
            'Atualização assistida do agente enviada para o ativo #%d: %s -> %s (usuário "%s" tem acento; o script de troca das versões anteriores à %s falha nesse caso).',
            $ativoId,
            $versaoAgente,
            $versaoServidor,
            $usuarioLogado,
            self::VERSAO_CORRIGIDA
        ));
    }

    /**
     * Roda no agente e volta na hora (o agente espera no máximo 30s por
     * uma solicitação). O trabalho fica num PowerShell separado, que
     * sobrevive ao fechamento do agente.
     */
    public static function script(string $destino, string $versao): string
    {
        $destinoPs = str_replace("'", "''", $destino);
        $versaoPs = str_replace("'", "''", $versao);

        return <<<PS
\$ErrorActionPreference = 'Stop'
\$agente = Get-Process -Name 'RdIntranetAgente' -ErrorAction SilentlyContinue | Where-Object { \$_.Path -and \$_.SessionId -eq (Get-Process -Id \$PID).SessionId } | Select-Object -First 1 -ExpandProperty Path
if (-not \$agente) { throw 'Processo do agente nao encontrado nesta sessao.' }
New-Item -ItemType Directory -Force -Path (Split-Path -Parent '{$destinoPs}') | Out-Null
\$log = Join-Path (Split-Path -Parent '{$destinoPs}') 'atualizacao.log'
\$passos = @'
\$ErrorActionPreference = 'Stop'
\$novo = '{$destinoPs}'
\$exe = '__AGENTE__'
\$log = '__LOG__'
\$sessao = (Get-Process -Id \$PID).SessionId
function AgentesDaSessao { Get-Process -Name RdIntranetAgente -ErrorAction SilentlyContinue | Where-Object { \$_.SessionId -eq \$sessao } }
function Log(\$t) { Add-Content -LiteralPath \$log -Value ((Get-Date).ToString('s') + ' ' + \$t) -Encoding UTF8 }
try {
    Log "Aguardando \$novo"
    \$limite = (Get-Date).AddMinutes(10)
    while ((Get-Date) -lt \$limite) {
        if ((Test-Path -LiteralPath \$novo) -and ((Get-Item -LiteralPath \$novo).VersionInfo.ProductVersion -like '{$versaoPs}*')) { break }
        Start-Sleep -Seconds 5
    }
    Start-Sleep -Seconds 3
    \$versao = (Get-Item -LiteralPath \$novo).VersionInfo.ProductVersion
    \$assinatura = Get-AuthenticodeSignature -LiteralPath \$novo
    if (\$versao -notlike '{$versaoPs}*' -or \$assinatura.SignerCertificate.Subject -notlike '*RD Tecnologia*') { throw "Arquivo inesperado: versao=\$versao assinatura=\$(\$assinatura.SignerCertificate.Subject)" }
    Log "Trocando \$exe pela versao \$versao"
    AgentesDaSessao | Stop-Process -Force -ErrorAction SilentlyContinue
    Start-Sleep -Seconds 3
    Copy-Item -LiteralPath \$exe -Destination (\$exe + '.anterior') -Force -ErrorAction SilentlyContinue
    \$ok = \$false
    for (\$i = 0; \$i -lt 20 -and -not \$ok; \$i++) {
        try { Copy-Item -LiteralPath \$novo -Destination \$exe -Force; \$ok = \$true } catch { Start-Sleep -Seconds 2 }
    }
    if (\$ok) { Log 'Troca concluida'; Remove-Item -LiteralPath \$novo -Force -ErrorAction SilentlyContinue } else { Log 'Troca falhou -- reabrindo a versao anterior' }
} catch {
    Log ("Erro: " + \$_.Exception.Message)
} finally {
    if (-not (AgentesDaSessao)) {
        schtasks /run /tn RDIntranetAgenteAutoStart | Out-Null
        Start-Sleep -Seconds 15
        if (-not (AgentesDaSessao)) { Start-Process -FilePath \$exe }
    }
    Log 'Fim'
}
'@
\$passos = \$passos.Replace('__AGENTE__', \$agente.Replace("'", "''")).Replace('__LOG__', \$log.Replace("'", "''"))
Start-Process powershell.exe -WindowStyle Hidden -ArgumentList '-NoProfile', '-ExecutionPolicy', 'Bypass', '-EncodedCommand', ([Convert]::ToBase64String([Text.Encoding]::Unicode.GetBytes(\$passos)))
"Atualizacao assistida agendada: \$agente -> {$versaoPs}. Log em \$log"
PS;
    }
}
