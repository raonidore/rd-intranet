<?php

namespace App\Services;

use App\Repositories\PoliticaRepository;

/**
 * "Regras de Segurança" -- políticas locais de máquina (USB, Painel de
 * Controle, CMD/PowerShell, navegadores, firewall, senha forte, papel
 * de parede, IP fixo) entregues pelo nosso próprio agente Windows, sem
 * nenhuma dependência de Microsoft Entra/Intune. Cada regra é só um
 * script PowerShell (aplicar/reverter) despachado via
 * AtivoService::solicitarListagem(..., 'executar_powershell', ...) --
 * o mesmo canal (ativos_solicitacoes/heartbeat) que já devolve
 * status/resultado, usado hoje pelo Explorador de Arquivos e pelos
 * scripts do módulo Entra. Nenhuma mudança no agente compilado (.exe)
 * é necessária pra essa feature.
 */
class PoliticaService
{
    private PoliticaRepository $repository;
    private AtivoService $ativoService;

    public function __construct()
    {
        $this->repository = new PoliticaRepository();
        $this->ativoService = new AtivoService();
    }

    private const EXTENSOES_IMAGEM_VALIDAS = ['jpg', 'jpeg', 'png'];

    /** regra_id => [label, categoria, reversivel]. 'reversivel'=false = regra "só aplicar" (desmarcar só para de forçar, nunca enfraquece a máquina de propósito -- caso do Firewall e da senha forte). */
    public const CATALOGO = [
        'usb_bloqueado' => ['label' => 'Bloquear portas USB (armazenamento removível)', 'categoria' => 'Segurança', 'reversivel' => true],
        'painel_controle_bloqueado' => ['label' => 'Bloquear acesso ao Painel de Controle e Configurações', 'categoria' => 'Segurança', 'reversivel' => true],
        'cmd_bloqueado' => ['label' => 'Bloquear o Prompt de Comando (CMD)', 'categoria' => 'Segurança', 'reversivel' => true],
        'powershell_bloqueado' => ['label' => 'Bloquear o Windows PowerShell', 'categoria' => 'Segurança', 'reversivel' => true],
        'navegadores_bloqueados' => ['label' => 'Bloquear navegadores de internet (Chrome, Edge, Firefox, Opera, Brave)', 'categoria' => 'Segurança', 'reversivel' => true],
        'firewall_habilitado' => ['label' => 'Garantir que o Firewall do Windows esteja sempre ativo', 'categoria' => 'Segurança', 'reversivel' => false],
        'senha_forte' => ['label' => 'Exigir senha forte pra entrar no Windows (mínimo 8 caracteres, com complexidade)', 'categoria' => 'Segurança', 'reversivel' => false],
        'papel_parede_padrao' => ['label' => 'Aplicar o papel de parede corporativo padrão', 'categoria' => 'Customização', 'reversivel' => true],
        'ip_fixo_bloqueado' => ['label' => 'Impedir o usuário de alterar configurações de IP/rede', 'categoria' => 'Segurança', 'reversivel' => true],
    ];

    /**
     * PowerShell reaproveitado por qualquer regra que mexe na lista
     * compartilhada de programas bloqueados do Explorer
     * (DisallowRun/RestrictRun) -- lê a lista inteira, troca só a
     * própria entrada (adiciona ou remove) e regrava, pra uma regra
     * nunca apagar o que outra regra já tiver colocado ali (ex:
     * "bloquear PowerShell" e "bloquear navegadores" dividem a mesma
     * chave do registro). Grava em HKLM e HKCU -- confirmado que
     * gravar só em HKLM não tem efeito (DisallowRun/RestrictRun via
     * HKLM sozinho é ignorado pelo Explorer; a chave que realmente
     * conta é a de HKCU do usuário logado, mesma limitação já
     * documentada pra Painel de Controle/CMD).
     */
    private const FUNCAO_RESTRICT_RUN = <<<'PS1'
function Set-RdRestrictRunEntry {
    param([string]$Exe, [bool]$Bloquear)
    foreach ($hive in @('HKLM:', 'HKCU:')) {
        $chave = "$hive\SOFTWARE\Microsoft\Windows\CurrentVersion\Policies\Explorer"
        New-Item -Path $chave -Force -ErrorAction Stop | Out-Null
        $item = Get-Item -Path $chave -ErrorAction SilentlyContinue
        $existentes = @()
        if ($item) {
            foreach ($nome in $item.Property) {
                if ($nome -match '^[0-9]+$') {
                    $valor = (Get-ItemProperty -Path $chave -Name $nome -ErrorAction SilentlyContinue).$nome
                    if ($valor -and $valor -ne $Exe) { $existentes += $valor }
                    Remove-ItemProperty -Path $chave -Name $nome -ErrorAction SilentlyContinue
                }
            }
        }
        if ($Bloquear) { $existentes += $Exe }
        $i = 1
        foreach ($valor in ($existentes | Select-Object -Unique)) {
            Set-ItemProperty -Path $chave -Name "$i" -Value $valor -Type String -ErrorAction Stop
            $i++
        }
        if ($existentes.Count -gt 0) {
            Set-ItemProperty -Path $chave -Name 'RestrictRun' -Value 1 -Type DWord -ErrorAction Stop
        } else {
            Remove-ItemProperty -Path $chave -Name 'RestrictRun' -ErrorAction SilentlyContinue
        }
    }
}
PS1;

    private const REGRAS_QUE_USAM_RESTRICT_RUN = ['powershell_bloqueado', 'navegadores_bloqueados'];

    /** @return array{aplicar: ?string, reverter: ?string} PowerShell de uma regra -- null em 'reverter' = regra "só aplicar" (ver CATALOGO). */
    private function scriptsDaRegra(string $regraId): array
    {
        return match ($regraId) {
            'usb_bloqueado' => [
                'aplicar' => "Set-ItemProperty -Path 'HKLM:\\SYSTEM\\CurrentControlSet\\Services\\USBSTOR' -Name 'Start' -Value 4 -Type DWord -ErrorAction Stop",
                'reverter' => "Set-ItemProperty -Path 'HKLM:\\SYSTEM\\CurrentControlSet\\Services\\USBSTOR' -Name 'Start' -Value 3 -Type DWord -ErrorAction Stop",
            ],
            // NoControlPanel/DisableCMD: gravamos em HKLM E HKCU de propósito.
            // Confirmado ao vivo que DisableCMD só em HKLM NÃO bloqueia nada
            // -- "Prevent access to the command prompt" é uma política de
            // User Configuration de verdade, e o cmd.exe só confere
            // HKCU\...\Policies\Microsoft\Windows\System na hora de abrir.
            // HKLM sozinho não tem efeito (o valor fica só "documentando a
            // intenção", nunca é lido). Escrevendo nos dois, cobre tanto
            // esse caso quanto qualquer outro que eventualmente funcione via
            // HKLM -- mas o efeito real depende de qual usuário está logado
            // no momento em que o agente roda o script (mesma limitação já
            // documentada pra regra de IP fixo).
            'painel_controle_bloqueado' => [
                'aplicar' => "foreach (\$hive in @('HKLM:','HKCU:')) { New-Item -Path \"\$hive\\SOFTWARE\\Microsoft\\Windows\\CurrentVersion\\Policies\\Explorer\" -Force -ErrorAction Stop | Out-Null; Set-ItemProperty -Path \"\$hive\\SOFTWARE\\Microsoft\\Windows\\CurrentVersion\\Policies\\Explorer\" -Name 'NoControlPanel' -Value 1 -Type DWord -ErrorAction Stop }",
                'reverter' => "foreach (\$hive in @('HKLM:','HKCU:')) { Remove-ItemProperty -Path \"\$hive\\SOFTWARE\\Microsoft\\Windows\\CurrentVersion\\Policies\\Explorer\" -Name 'NoControlPanel' -ErrorAction SilentlyContinue }",
            ],
            'cmd_bloqueado' => [
                'aplicar' => "foreach (\$hive in @('HKLM:','HKCU:')) { New-Item -Path \"\$hive\\SOFTWARE\\Policies\\Microsoft\\Windows\\System\" -Force -ErrorAction Stop | Out-Null; Set-ItemProperty -Path \"\$hive\\SOFTWARE\\Policies\\Microsoft\\Windows\\System\" -Name 'DisableCMD' -Value 2 -Type DWord -ErrorAction Stop }",
                'reverter' => "foreach (\$hive in @('HKLM:','HKCU:')) { Remove-ItemProperty -Path \"\$hive\\SOFTWARE\\Policies\\Microsoft\\Windows\\System\" -Name 'DisableCMD' -ErrorAction SilentlyContinue }",
            ],
            'powershell_bloqueado' => [
                'aplicar' => "Set-RdRestrictRunEntry -Exe 'powershell.exe' -Bloquear \$true; Set-RdRestrictRunEntry -Exe 'powershell_ise.exe' -Bloquear \$true",
                'reverter' => "Set-RdRestrictRunEntry -Exe 'powershell.exe' -Bloquear \$false; Set-RdRestrictRunEntry -Exe 'powershell_ise.exe' -Bloquear \$false",
            ],
            'navegadores_bloqueados' => [
                'aplicar' => "foreach (\$exe in @('chrome.exe','msedge.exe','firefox.exe','opera.exe','brave.exe','iexplore.exe')) { Set-RdRestrictRunEntry -Exe \$exe -Bloquear \$true }",
                'reverter' => "foreach (\$exe in @('chrome.exe','msedge.exe','firefox.exe','opera.exe','brave.exe','iexplore.exe')) { Set-RdRestrictRunEntry -Exe \$exe -Bloquear \$false }",
            ],
            'firewall_habilitado' => [
                'aplicar' => 'Set-NetFirewallProfile -All -Enabled True -ErrorAction Stop',
                'reverter' => null,
            ],
            'senha_forte' => [
                'aplicar' => self::scriptSenhaForte(),
                'reverter' => null,
            ],
            'papel_parede_padrao' => [
                'aplicar' => $this->scriptAplicarWallpaper(),
                'reverter' => "Remove-ItemProperty -Path 'HKCU:\\Software\\Microsoft\\Windows\\CurrentVersion\\Policies\\ActiveDesktop' -Name 'NoChangingWallPaper' -ErrorAction SilentlyContinue",
            ],
            // Caminho corrigido: é 'SOFTWARE\Policies\Microsoft\Windows\Network Connections'
            // (pasta "Network Connections" só, com espaço -- não "CurrentVersion\Policies"
            // nem "Network\Connections" separados, erro da primeira versão desta regra).
            // NC_LanProperties=1 esconde o botão "Propriedades" da conexão de rede pro
            // usuário comum (o que realmente impede mudar o IP manualmente);
            // NC_LanChangeProperties=1 bloqueia até administradores/Network Config
            // Operators; NC_StdDomainUserSetLocation=1 exige elevação pra mudar o
            // perfil de rede (domínio/privada/pública). Grava em HKLM e HKCU --
            // fontes divergem sobre qual hive cada valor individual respeita, então
            // grava nos dois pra garantir.
            'ip_fixo_bloqueado' => [
                'aplicar' => "foreach (\$hive in @('HKLM:','HKCU:')) { New-Item -Path \"\$hive\\SOFTWARE\\Policies\\Microsoft\\Windows\\Network Connections\" -Force -ErrorAction Stop | Out-Null; Set-ItemProperty -Path \"\$hive\\SOFTWARE\\Policies\\Microsoft\\Windows\\Network Connections\" -Name 'NC_LanProperties' -Value 1 -Type DWord -ErrorAction Stop; Set-ItemProperty -Path \"\$hive\\SOFTWARE\\Policies\\Microsoft\\Windows\\Network Connections\" -Name 'NC_LanChangeProperties' -Value 1 -Type DWord -ErrorAction Stop; Set-ItemProperty -Path \"\$hive\\SOFTWARE\\Policies\\Microsoft\\Windows\\Network Connections\" -Name 'NC_StdDomainUserSetLocation' -Value 1 -Type DWord -ErrorAction Stop }",
                'reverter' => "foreach (\$hive in @('HKLM:','HKCU:')) { Remove-ItemProperty -Path \"\$hive\\SOFTWARE\\Policies\\Microsoft\\Windows\\Network Connections\" -Name 'NC_LanProperties' -ErrorAction SilentlyContinue; Remove-ItemProperty -Path \"\$hive\\SOFTWARE\\Policies\\Microsoft\\Windows\\Network Connections\" -Name 'NC_LanChangeProperties' -ErrorAction SilentlyContinue; Remove-ItemProperty -Path \"\$hive\\SOFTWARE\\Policies\\Microsoft\\Windows\\Network Connections\" -Name 'NC_StdDomainUserSetLocation' -ErrorAction SilentlyContinue }",
            ],
            default => ['aplicar' => null, 'reverter' => null],
        };
    }

    private static function scriptSenhaForte(): string
    {
        return <<<'PS1'
$conteudoInf = @'
[Unicode]
Unicode=yes
[System Access]
MinimumPasswordLength = 8
PasswordComplexity = 1
[Version]
signature="$CHICAGO$"
Revision=1
'@
$arquivoInf = Join-Path $env:TEMP 'rd_senha_forte.inf'
$arquivoSdb = Join-Path $env:TEMP 'rd_senha_forte.sdb'
Set-Content -Path $arquivoInf -Value $conteudoInf -Encoding Unicode -ErrorAction Stop
secedit /configure /db $arquivoSdb /cfg $arquivoInf /areas SECURITYPOLICY | Out-Null
$codigo = $LASTEXITCODE
Remove-Item -Path $arquivoInf, $arquivoSdb -ErrorAction SilentlyContinue
if ($codigo -ne 0) { throw "secedit falhou (codigo $codigo)" }
PS1;
    }

    /** Monta o script combinado de UM "Salvar"/lote: um try/catch por regra alterada, JSON único no final (única coisa que vai pro stdout) -- é isso que fica em ativos_solicitacoes.resultado pro polling decodificar. */
    public function gerarScriptCombinado(array $mudancas): string
    {
        $precisaRestrictRun = false;
        $blocos = [];

        foreach ($mudancas as $mudanca) {
            $regraId = $mudanca['regra_id'];
            $ativar = $mudanca['ativar'];
            $scripts = $this->scriptsDaRegra($regraId);
            $script = $ativar ? $scripts['aplicar'] : $scripts['reverter'];

            if ($script === null) {
                continue;
            }

            if (in_array($regraId, self::REGRAS_QUE_USAM_RESTRICT_RUN, true)) {
                $precisaRestrictRun = true;
            }

            $chaveJson = addslashes($regraId);
            $blocos[] = <<<PS1
try {
    {$script}
    \$resultados['{$chaveJson}'] = @{ sucesso = \$true }
} catch {
    \$resultados['{$chaveJson}'] = @{ sucesso = \$false; erro = \$_.Exception.Message }
}
PS1;
        }

        $preambulo = "\$resultados = @{}\n";
        if ($precisaRestrictRun) {
            $preambulo .= self::FUNCAO_RESTRICT_RUN . "\n";
        }

        return $preambulo . implode("\n", $blocos) . "\n(\$resultados | ConvertTo-Json -Compress)\n";
    }

    /** @return array<string, array> catálogo mesclado com o estado salvo (desejado/status/mensagem) dessa máquina -- 'desejado'=0 e status=null pra regra nunca tocada. */
    public function estadoMaquina(int $ativoId): array
    {
        $salvos = $this->repository->estadoPorAtivo($ativoId);
        $estado = [];

        foreach (self::CATALOGO as $regraId => $info) {
            $linha = $salvos[$regraId] ?? null;
            $estado[$regraId] = array_merge($info, [
                'regra_id' => $regraId,
                'desejado' => $linha ? (bool)$linha['desejado'] : false,
                'status' => $linha['status'] ?? null,
                'mensagem' => $linha['mensagem'] ?? null,
            ]);
        }

        return $estado;
    }

    /** @param string[] $regrasMarcadas ids de regra que ficaram marcadas no form (as ausentes = desmarcadas) */
    public function salvarEstadoMaquina(int $ativoId, array $regrasMarcadas, ?string $solicitadoPor): array
    {
        $ativo = $this->ativoService->buscar($ativoId);
        if (!$ativo || $ativo['origem'] !== 'agente') {
            return ['success' => false, 'message' => 'Este ativo não tem o agente Windows instalado.'];
        }

        $atual = $this->repository->estadoPorAtivo($ativoId);
        $mudancas = [];

        foreach (self::CATALOGO as $regraId => $info) {
            $desejadoNovo = in_array($regraId, $regrasMarcadas, true);
            $desejadoAtual = isset($atual[$regraId]) && (bool)$atual[$regraId]['desejado'];

            if ($desejadoNovo === $desejadoAtual) {
                continue;
            }

            if (!$desejadoNovo && !$info['reversivel']) {
                // Regra "só aplicar" sendo desmarcada -- só para de forçar
                // localmente, nunca gera um script que enfraquece a máquina.
                $this->repository->upsertEstado($ativoId, $regraId, 0, 'aplicado', 'Não é mais forçada (nenhuma mudança na máquina).', null);
                continue;
            }

            $mudancas[] = ['regra_id' => $regraId, 'ativar' => $desejadoNovo];
        }

        if (empty($mudancas)) {
            return ['success' => true, 'message' => 'Nada pra aplicar -- estado já era esse.'];
        }

        if ($regraWallpaper = $this->precisaEnviarWallpaper($mudancas)) {
            if (!$this->enviarWallpaperParaAtivo($ativoId, $solicitadoPor)) {
                return ['success' => false, 'message' => 'Não foi possível enviar a imagem do papel de parede pra essa máquina.'];
            }
        }

        $script = $this->gerarScriptCombinado($mudancas);
        $resultado = $this->ativoService->solicitarListagem($ativoId, 'executar_powershell', $script, $solicitadoPor, false);

        if (!($resultado['success'] ?? false)) {
            return $resultado;
        }

        $solicitacaoId = $resultado['id'];
        foreach ($mudancas as $mudanca) {
            $this->repository->upsertEstado($ativoId, $mudanca['regra_id'], $mudanca['ativar'] ? 1 : 0, 'pendente', null, $solicitacaoId);
        }

        AuditService::registrar('Ativos', 'Regras de Segurança', count($mudancas) . ' regra(s) alterada(s) em ' . $ativo['codigo_patrimonio'] . ' (' . $ativo['nome'] . ').');

        return ['success' => true, 'solicitacao_id' => $solicitacaoId];
    }

    private function precisaEnviarWallpaper(array $mudancas): bool
    {
        foreach ($mudancas as $mudanca) {
            if ($mudanca['regra_id'] === 'papel_parede_padrao' && $mudanca['ativar']) {
                return true;
            }
        }

        return false;
    }

    /** Confirma o resultado de uma solicitação e atualiza o status por regra -- chamado pelo polling da tela de máquina. */
    public function confirmarResultado(int $solicitacaoId, int $ativoId): array
    {
        $resultado = $this->ativoService->resultadoSolicitacao($solicitacaoId, $ativoId);

        if (!($resultado['success'] ?? false) || $resultado['status'] === 'pendente') {
            return $resultado;
        }

        $linhas = $this->repository->porSolicitacao($solicitacaoId);

        if ($resultado['status'] === 'erro') {
            foreach ($linhas as $linha) {
                $this->repository->atualizarStatus((int)$linha['id'], 'erro', $resultado['mensagem'] ?? 'Falha ao executar o script.');
            }

            return array_merge($resultado, ['estado' => $this->estadoMaquina($ativoId)]);
        }

        $porRegra = json_decode($resultado['resultado']['saida'] ?? '', true) ?: [];

        foreach ($linhas as $linha) {
            $regraId = $linha['regra_id'];
            $infoRegra = $porRegra[$regraId] ?? null;

            if ($infoRegra === null) {
                continue;
            }

            $sucesso = $infoRegra['sucesso'] ?? false;
            $this->repository->atualizarStatus((int)$linha['id'], $sucesso ? 'aplicado' : 'erro', $sucesso ? null : ($infoRegra['erro'] ?? 'Falha desconhecida.'));
        }

        return array_merge($resultado, ['estado' => $this->estadoMaquina($ativoId)]);
    }

    /** Aplica ou remove UMA regra em várias máquinas de uma vez (fogo-e-esquece, igual ao padrão já usado pra papel de parede/Company Portal em lote no módulo Entra). */
    public function aplicarEmLote(string $regraId, array $ativoIds, bool $ativar, ?string $solicitadoPor): array
    {
        if (!isset(self::CATALOGO[$regraId])) {
            return ['success' => false, 'message' => 'Regra inválida.'];
        }

        if (!$ativar && !self::CATALOGO[$regraId]['reversivel']) {
            return ['success' => false, 'message' => 'Essa regra não tem "desligar" -- ela só reforça, nunca enfraquece a máquina de propósito.'];
        }

        $enviados = 0;

        foreach ($ativoIds as $ativoId) {
            $ativoId = (int)$ativoId;

            if ($regraId === 'papel_parede_padrao' && $ativar && !$this->enviarWallpaperParaAtivo($ativoId, $solicitadoPor)) {
                continue;
            }

            $script = $this->gerarScriptCombinado([['regra_id' => $regraId, 'ativar' => $ativar]]);
            $resultado = $this->ativoService->solicitarListagem($ativoId, 'executar_powershell', $script, $solicitadoPor, false);

            if ($resultado['success'] ?? false) {
                $this->repository->upsertEstado($ativoId, $regraId, $ativar ? 1 : 0, 'pendente', null, $resultado['id']);
                $enviados++;
            }
        }

        AuditService::registrar('Ativos', 'Regras de Segurança', ($ativar ? 'Aplicação' : 'Remoção') . " em lote da regra \"{$regraId}\" -- {$enviados} máquina(s).");

        return ['success' => true, 'enviados' => $enviados];
    }

    /*
     |---------------------------------------------------------
     | Papel de parede corporativo -- 1 imagem só (diferente do Entra,
     | que separa área de trabalho/tela de bloqueio porque o Intune tem
     | os dois campos; aqui é um Set-ItemProperty só, então uma imagem
     | já basta). Mesmo padrão de storage do Company Portal/wallpaper do
     | Entra (arquivo fixo + metadados no ConfigService).
     |---------------------------------------------------------
     */

    private const CHAVE_WALLPAPER_NOME = 'ativos_politicas_wallpaper_nome';
    private const CHAVE_WALLPAPER_ENVIADO_EM = 'ativos_politicas_wallpaper_enviado_em';

    public static function caminhoWallpaper(): string
    {
        return __DIR__ . '/../../storage/uploads/ativos_politicas/wallpaper';
    }

    public function wallpaperConfigurado(): bool
    {
        return is_file(self::caminhoWallpaper());
    }

    public function wallpaperInfo(): ?array
    {
        if (!$this->wallpaperConfigurado()) {
            return null;
        }

        return [
            'nome' => ConfigService::get(self::CHAVE_WALLPAPER_NOME, '') ?: 'wallpaper',
            'enviado_em' => ConfigService::get(self::CHAVE_WALLPAPER_ENVIADO_EM, '') ?: '',
        ];
    }

    public function salvarWallpaper(string $caminhoTemporario, string $nomeOriginal): bool
    {
        $extensao = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
        if (!in_array($extensao, self::EXTENSOES_IMAGEM_VALIDAS, true)) {
            NotificationService::error('A imagem precisa ser .jpg, .jpeg ou .png.');
            return false;
        }

        $destino = self::caminhoWallpaper();
        $pasta = dirname($destino);
        if (!is_dir($pasta) && !@mkdir($pasta, 0777, true) && !is_dir($pasta)) {
            NotificationService::error('Não foi possível preparar a pasta de armazenamento no servidor.');
            return false;
        }

        if (!@copy($caminhoTemporario, $destino)) {
            NotificationService::error('Não foi possível salvar a imagem no servidor.');
            return false;
        }

        ConfigService::set(self::CHAVE_WALLPAPER_NOME, basename($nomeOriginal));
        ConfigService::set(self::CHAVE_WALLPAPER_ENVIADO_EM, date('Y-m-d H:i:s'));

        AuditService::registrar('Ativos', 'Regras de Segurança', 'Imagem do papel de parede corporativo enviada/atualizada.');
        NotificationService::success('Imagem salva.');

        return true;
    }

    public function removerWallpaper(): void
    {
        @unlink(self::caminhoWallpaper());
        ConfigService::set(self::CHAVE_WALLPAPER_NOME, '');
        ConfigService::set(self::CHAVE_WALLPAPER_ENVIADO_EM, '');

        AuditService::registrar('Ativos', 'Regras de Segurança', 'Imagem do papel de parede corporativo removida.');
        NotificationService::success('Imagem removida.');
    }

    /** Caminho fixo que o arquivo ocupa NA MÁQUINA depois de enviado (ProgramData -- persistente). Null se ainda não foi configurado. */
    public function caminhoRemotoWallpaper(): ?string
    {
        $info = $this->wallpaperInfo();
        if ($info === null) {
            return null;
        }

        $extensao = strtolower(pathinfo($info['nome'], PATHINFO_EXTENSION));

        return 'C:\\ProgramData\\RDIntranet\\wallpaper_corporativo.' . $extensao;
    }

    private function scriptAplicarWallpaper(): string
    {
        $caminhoRemoto = $this->caminhoRemotoWallpaper() ?? 'C:\\ProgramData\\RDIntranet\\wallpaper_corporativo';

        $template = <<<'PS1'
$caminho = '__CAMINHO__'
$limite = (Get-Date).AddSeconds(120)

while (-not (Test-Path $caminho) -and (Get-Date) -lt $limite) {
    Start-Sleep -Seconds 2
}

if (!(Test-Path $caminho)) { throw "Imagem do papel de parede ainda nao chegou nessa maquina (enviar_arquivo e mais lento que executar_powershell -- pode levar ate um checkin inteiro)." }
New-Item -Path 'HKCU:\Control Panel\Desktop' -Force -ErrorAction Stop | Out-Null
Set-ItemProperty -Path 'HKCU:\Control Panel\Desktop' -Name 'Wallpaper' -Value $caminho -ErrorAction Stop
Set-ItemProperty -Path 'HKCU:\Control Panel\Desktop' -Name 'WallpaperStyle' -Value '10' -ErrorAction Stop
Set-ItemProperty -Path 'HKCU:\Control Panel\Desktop' -Name 'TileWallpaper' -Value '0' -ErrorAction Stop

# RUNDLL32.EXE user32.dll,UpdatePerUserSystemParameters (usado antes)
# nunca foi documentado pela Microsoft como forma oficial de trocar o
# papel de parede e o Windows nem sempre honra a chamada -- a forma
# confiavel de verdade e chamar a API SystemParametersInfo direto via
# P/Invoke, que forca o Explorer a redesenhar a area de trabalho na
# hora.
Add-Type @'
using System;
using System.Runtime.InteropServices;
public class RdWallpaper {
    [DllImport("user32.dll", EntryPoint = "SystemParametersInfo", CharSet = CharSet.Auto)]
    public static extern int SystemParametersInfo(int uAction, int uParam, string lpvParam, int fuWinIni);
}
'@

$resultadoApi = [RdWallpaper]::SystemParametersInfo(20, 0, $caminho, 3)
if ($resultadoApi -eq 0) { throw "SystemParametersInfo retornou falha ao trocar o papel de parede." }

New-Item -Path 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Policies\ActiveDesktop' -Force -ErrorAction Stop | Out-Null
Set-ItemProperty -Path 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Policies\ActiveDesktop' -Name 'NoChangingWallPaper' -Value 1 -Type DWord -ErrorAction Stop
PS1;

        return str_replace('__CAMINHO__', $caminhoRemoto, $template);
    }

    /** Copia a imagem já salva no servidor pra máquina (enviar_arquivo), igual ao padrão do wallpaper/Company Portal do módulo Entra. */
    private function enviarWallpaperParaAtivo(int $ativoId, ?string $solicitadoPor): bool
    {
        if (!$this->wallpaperConfigurado()) {
            NotificationService::error('Envie a imagem do papel de parede corporativo antes de aplicar essa regra.');
            return false;
        }

        $pastaTransferencias = __DIR__ . '/../../storage/uploads/ativos_transferencias';
        if (!is_dir($pastaTransferencias) && !@mkdir($pastaTransferencias, 0777, true) && !is_dir($pastaTransferencias)) {
            return false;
        }

        $caminhoRemoto = $this->caminhoRemotoWallpaper();
        $nomeArquivo = basename(str_replace('\\', '/', $caminhoRemoto));
        $copiaTemp = $pastaTransferencias . '/enviar_' . uniqid('', true) . '_' . $nomeArquivo;

        if (!@copy(self::caminhoWallpaper(), $copiaTemp)) {
            return false;
        }

        $resultado = $this->ativoService->enviarComando($ativoId, 'enviar_arquivo', $solicitadoPor, $caminhoRemoto, $nomeArquivo, $copiaTemp);

        if (!($resultado['success'] ?? false)) {
            @unlink($copiaTemp);
            return false;
        }

        return true;
    }

    /** Máquinas elegíveis pro picker (mesmo filtro do módulo Entra: só computador, com agente instalado e numa versão que já suporta executar_powershell). */
    public function maquinasElegiveis(): array
    {
        return array_values(array_filter(
            $this->ativoService->listar(['tipo' => 'computador']),
            fn($a) => $a['origem'] === 'agente' && ($a['agente_versao'] ?? '') !== 'ps1'
        ));
    }

    /*
     |---------------------------------------------------------
     | Fase 2: recursos de rede (impressora/unidade) por setor --
     | ativos.setor_id já existe (é o "departamento" da máquina), só
     | precisava de um catálogo de que impressora/unidade cada setor usa.
     | Diferente do catálogo de regras (não é liga/desliga por máquina):
     | é uma AÇÃO ("aplicar mapeamentos do setor desta máquina") que lê o
     | setor da máquina na hora e gera o script -- por isso não usa
     | ativos_politicas_estado, é fogo-e-esquece igual o "Executar
     | comando" já existente (mesmo canal genérico de executar_powershell).
     |---------------------------------------------------------
     */

    public const TIPOS_RECURSO_SETOR = ['impressora' => 'Impressora', 'unidade_rede' => 'Unidade de rede'];

    public function listarRecursosSetor(): array
    {
        return $this->repository->listarRecursosSetor();
    }

    public function criarRecursoSetor(int $setorId, string $tipo, string $nomeExibicao, ?string $letraUnidade, string $caminhoUnc): array
    {
        if (!isset(self::TIPOS_RECURSO_SETOR[$tipo])) {
            return ['success' => false, 'message' => 'Tipo de recurso inválido.'];
        }

        if ($nomeExibicao === '' || $caminhoUnc === '') {
            return ['success' => false, 'message' => 'Preencha o nome e o caminho de rede (\\\\servidor\\compartilhamento).'];
        }

        // Nome e caminho entram sem escape nenhum no script PowerShell gerado
        // (ver scriptMapearRecursosSetor) -- aspas simples/duplas quebrariam a
        // string do PowerShell, e $/backtick disparariam interpolação/escape
        // dentro dela. Em vez de tentar escapar certo em cada contexto (net
        // use usa aspas duplas, Add-Printer usa simples), é mais
        // simples e seguro só recusar esses caracteres aqui.
        if (preg_match('/[\'"$`]/', $nomeExibicao . $caminhoUnc)) {
            return ['success' => false, 'message' => 'Nome e caminho de rede não podem conter aspas, $ ou acento grave.'];
        }

        if ($tipo === 'unidade_rede' && !preg_match('/^[A-Za-z]$/', (string)$letraUnidade)) {
            return ['success' => false, 'message' => 'Informe uma letra de unidade válida (A-Z).'];
        }

        $this->repository->criarRecursoSetor($setorId, $tipo, $nomeExibicao, $tipo === 'unidade_rede' ? strtoupper($letraUnidade) : null, $caminhoUnc);

        AuditService::registrar('Ativos', 'Regras de Segurança', "Recurso de setor criado: {$nomeExibicao} ({$caminhoUnc}).");
        NotificationService::success('Recurso adicionado.');

        return ['success' => true];
    }

    public function excluirRecursoSetor(int $id): void
    {
        $this->repository->excluirRecursoSetor($id);
        AuditService::registrar('Ativos', 'Regras de Segurança', 'Recurso de setor removido.');
        NotificationService::success('Recurso removido.');
    }

    /** Script pra mapear TODOS os recursos do setor de uma vez -- null se o setor não tiver nenhum recurso cadastrado. Idempotente por natureza (net use sobrescreve, Add-Printer não duplica pela mesma conexão). */
    public function scriptMapearRecursosSetor(?int $setorId): ?string
    {
        if ($setorId === null) {
            return null;
        }

        $recursos = $this->repository->recursosPorSetor($setorId);
        if (empty($recursos)) {
            return null;
        }

        $resultados = [];
        foreach ($recursos as $r) {
            // Sem escape aqui de propósito: nome/caminho já são validados em
            // criarRecursoSetor() pra nunca conter aspas (o único caractere
            // que quebraria a string do PowerShell nos dois formatos abaixo).
            $chave = $r['nome_exibicao'];
            $caminho = $r['caminho_unc'];

            if ($r['tipo'] === 'unidade_rede') {
                $letra = $r['letra_unidade'];
                $comando = "net use {$letra}: \"{$caminho}\" /persistent:yes";
            } else {
                $comando = "if (Get-Printer -Name '{$caminho}' -ErrorAction SilentlyContinue) { } else { Add-Printer -ConnectionName '{$caminho}' -ErrorAction Stop }";
            }

            $resultados[] = <<<PS1
try {
    {$comando}
    Write-Output "OK: {$chave}"
} catch {
    Write-Output "ERRO ({$chave}): \$(\$_.Exception.Message)"
}
PS1;
        }

        return implode("\n", $resultados);
    }

    /*
     |---------------------------------------------------------
     | Fase 3: instalar software remotamente -- catálogo de pacotes
     | (.exe/.msi) + push do arquivo (enviar_arquivo) seguido de
     | instalação silenciosa (executar_powershell), mesmo encadeamento
     | já usado pelo pacote de provisionamento (.ppkg) do módulo Entra.
     |---------------------------------------------------------
     */

    private const EXTENSOES_INSTALADOR_VALIDAS = ['exe', 'msi'];

    private static function pastaPacotesSoftware(): string
    {
        return __DIR__ . '/../../storage/uploads/ativos_pacotes_software';
    }

    public function listarPacotesSoftware(): array
    {
        return $this->repository->listarPacotesSoftware();
    }

    public function criarPacoteSoftware(string $nome, string $caminhoTemporario, string $nomeOriginal, ?string $argumentos, ?string $criadoPor): array
    {
        $extensao = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
        if (!in_array($extensao, self::EXTENSOES_INSTALADOR_VALIDAS, true)) {
            return ['success' => false, 'message' => 'O instalador precisa ser .exe ou .msi.'];
        }

        if ($nome === '') {
            return ['success' => false, 'message' => 'Informe um nome pro pacote.'];
        }

        $pasta = self::pastaPacotesSoftware();
        if (!is_dir($pasta) && !@mkdir($pasta, 0777, true) && !is_dir($pasta)) {
            return ['success' => false, 'message' => 'Não foi possível preparar a pasta de armazenamento no servidor.'];
        }

        $nomeArmazenado = uniqid('pacote_', true) . '.' . $extensao;
        $destino = $pasta . '/' . $nomeArmazenado;

        if (!@copy($caminhoTemporario, $destino)) {
            return ['success' => false, 'message' => 'Não foi possível salvar o instalador no servidor.'];
        }

        $this->repository->criarPacoteSoftware($nome, basename($nomeOriginal), $destino, $argumentos !== '' ? $argumentos : null, $criadoPor);

        AuditService::registrar('Ativos', 'Regras de Segurança', "Pacote de software \"{$nome}\" enviado.");
        NotificationService::success('Pacote adicionado.');

        return ['success' => true];
    }

    public function excluirPacoteSoftware(int $id): void
    {
        $pacote = $this->repository->buscarPacoteSoftware($id);
        if ($pacote !== null) {
            @unlink($pacote['arquivo_caminho']);
        }

        $this->repository->excluirPacoteSoftware($id);
        AuditService::registrar('Ativos', 'Regras de Segurança', 'Pacote de software removido.');
        NotificationService::success('Pacote removido.');
    }

    /** Instala UM pacote em várias máquinas (fogo-e-esquece, igual wallpaper/Company Portal -- confira o resultado no histórico de cada ativo). */
    public function instalarPacoteEmLote(int $pacoteId, array $ativoIds, ?string $solicitadoPor): array
    {
        $pacote = $this->repository->buscarPacoteSoftware($pacoteId);
        if ($pacote === null) {
            return ['success' => false, 'message' => 'Pacote não encontrado.'];
        }

        if (!is_file($pacote['arquivo_caminho'])) {
            return ['success' => false, 'message' => 'O arquivo do pacote não está mais no servidor -- envie de novo.'];
        }

        $pastaTransferencias = __DIR__ . '/../../storage/uploads/ativos_transferencias';
        if (!is_dir($pastaTransferencias) && !@mkdir($pastaTransferencias, 0777, true) && !is_dir($pastaTransferencias)) {
            return ['success' => false, 'message' => 'Não foi possível preparar a pasta de transferência.'];
        }

        $extensao = strtolower(pathinfo($pacote['arquivo_caminho'], PATHINFO_EXTENSION));
        $nomeRemoto = 'pacote_' . $pacoteId . '.' . $extensao;
        $destinoRemoto = 'C:\\Windows\\Temp\\RDIntranetInstall\\' . $nomeRemoto;

        $enviados = 0;

        foreach ($ativoIds as $ativoId) {
            $ativoId = (int)$ativoId;
            $copiaTemp = $pastaTransferencias . '/enviar_' . uniqid('', true) . '_' . $nomeRemoto;

            if (!@copy($pacote['arquivo_caminho'], $copiaTemp)) {
                continue;
            }

            $resultadoArquivo = $this->ativoService->enviarComando($ativoId, 'enviar_arquivo', $solicitadoPor, $destinoRemoto, $nomeRemoto, $copiaTemp);
            if (!($resultadoArquivo['success'] ?? false)) {
                @unlink($copiaTemp);
                continue;
            }

            $script = $this->scriptInstalarPacote($destinoRemoto, $extensao, $pacote['argumentos_silenciosos'] ?? '');
            $resultado = $this->ativoService->solicitarListagem($ativoId, 'executar_powershell', $script, $solicitadoPor, false);

            if ($resultado['success'] ?? false) {
                $enviados++;
            }
        }

        AuditService::registrar('Ativos', 'Regras de Segurança', "Instalação de \"{$pacote['nome']}\" solicitada em {$enviados} máquina(s).");

        return ['success' => true, 'enviados' => $enviados];
    }

    /** Espera o arquivo chegar (enviar_arquivo é assíncrono, sem confirmação) e instala silenciosamente -- msiexec pra .msi, execução direta com os argumentos configurados pra .exe. */
    private function scriptInstalarPacote(string $destinoRemoto, string $extensao, string $argumentos): string
    {
        $template = <<<'PS1'
$destino = '__DESTINO__'
$limite = (Get-Date).AddSeconds(120)

while (-not (Test-Path $destino) -and (Get-Date) -lt $limite) {
    Start-Sleep -Seconds 2
}

if (-not (Test-Path $destino)) {
    Write-Output "ERRO: instalador nao chegou a tempo em $destino."
    exit 1
}

try {
    $processo = __COMANDO_INSTALACAO__
    if ($processo.ExitCode -ne 0) { throw "instalador saiu com codigo $($processo.ExitCode)" }
    Write-Output "Instalacao concluida (codigo de saida $($processo.ExitCode))."
} catch {
    Write-Output "ERRO ao instalar: $($_.Exception.Message)"
    exit 1
} finally {
    Remove-Item $destino -ErrorAction SilentlyContinue
}
PS1;

        // -PassThru devolve o objeto do processo (com .ExitCode) -- sem ele,
        // Start-Process não dá pra saber se o instalador realmente terminou
        // com sucesso ou só se conseguiu ABRIR o processo ($LASTEXITCODE não
        // reflete Start-Process, só comandos nativos chamados direto).
        $argumentosSeguros = $argumentos !== '' ? $argumentos : ($extensao === 'msi' ? '/quiet /norestart' : '/S');

        $comando = $extensao === 'msi'
            ? "Start-Process -FilePath msiexec.exe -ArgumentList '/i', \"`\"\$destino`\"\", {$this->argumentosComoLista($argumentosSeguros)} -Wait -PassThru"
            : "Start-Process -FilePath \$destino -ArgumentList {$this->argumentosComoLista($argumentosSeguros)} -Wait -PassThru";

        return str_replace(['__DESTINO__', '__COMANDO_INSTALACAO__'], [$destinoRemoto, $comando], $template);
    }

    /** Transforma "argumento1 argumento2" (separado por espaço, como o admin digita) numa lista PowerShell de strings -- evita que Start-Process quebre argumentos com espaço junto sem querer. */
    private function argumentosComoLista(string $argumentos): string
    {
        $partes = preg_split('/\s+/', trim($argumentos)) ?: [];
        $partesEscapadas = array_map(fn($p) => "'" . str_replace("'", "''", $p) . "'", $partes);

        return implode(', ', $partesEscapadas);
    }

    /*
     |---------------------------------------------------------
     | Fase 4: script de login personalizado -- um .ps1 só (slot único,
     | igual Company Portal), entregue via enviar_arquivo e registrado
     | pra rodar em TODO logon via Scheduled Task. Reaproveita o mesmo
     | truque de XML com <GroupId>S-1-5-32-545</GroupId> (grupo "Users")
     | que o próprio agente já usa pra se auto-iniciar em qualquer
     | usuário -- só que aqui quem gera o XML é o PHP, dentro do script
     | PowerShell que o agente executa (não precisa de nenhuma mudança
     | no .exe compilado).
     |---------------------------------------------------------
     */

    private const NOME_TAREFA_LOGIN_SCRIPT = 'RDIntranetLoginScript';
    private const CHAVE_LOGIN_SCRIPT_NOME = 'ativos_politicas_login_script_nome';
    private const CHAVE_LOGIN_SCRIPT_ENVIADO_EM = 'ativos_politicas_login_script_enviado_em';

    public static function caminhoLoginScript(): string
    {
        return __DIR__ . '/../../storage/uploads/ativos_politicas/login_script.ps1';
    }

    public function loginScriptConfigurado(): bool
    {
        return is_file(self::caminhoLoginScript());
    }

    public function loginScriptInfo(): ?array
    {
        if (!$this->loginScriptConfigurado()) {
            return null;
        }

        return [
            'nome' => ConfigService::get(self::CHAVE_LOGIN_SCRIPT_NOME, '') ?: 'login_script.ps1',
            'enviado_em' => ConfigService::get(self::CHAVE_LOGIN_SCRIPT_ENVIADO_EM, '') ?: '',
        ];
    }

    public function salvarLoginScript(string $caminhoTemporario, string $nomeOriginal): bool
    {
        $extensao = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
        if ($extensao !== 'ps1') {
            NotificationService::error('O script de login precisa ser um arquivo .ps1.');
            return false;
        }

        $destino = self::caminhoLoginScript();
        $pasta = dirname($destino);
        if (!is_dir($pasta) && !@mkdir($pasta, 0777, true) && !is_dir($pasta)) {
            NotificationService::error('Não foi possível preparar a pasta de armazenamento no servidor.');
            return false;
        }

        if (!@copy($caminhoTemporario, $destino)) {
            NotificationService::error('Não foi possível salvar o script no servidor.');
            return false;
        }

        ConfigService::set(self::CHAVE_LOGIN_SCRIPT_NOME, basename($nomeOriginal));
        ConfigService::set(self::CHAVE_LOGIN_SCRIPT_ENVIADO_EM, date('Y-m-d H:i:s'));

        AuditService::registrar('Ativos', 'Regras de Segurança', 'Script de login enviado/atualizado.');
        NotificationService::success('Script salvo.');

        return true;
    }

    public function removerLoginScript(): void
    {
        @unlink(self::caminhoLoginScript());
        ConfigService::set(self::CHAVE_LOGIN_SCRIPT_NOME, '');
        ConfigService::set(self::CHAVE_LOGIN_SCRIPT_ENVIADO_EM, '');

        AuditService::registrar('Ativos', 'Regras de Segurança', 'Script de login removido do servidor (as tarefas já registradas em máquinas continuam até serem removidas individualmente).');
        NotificationService::success('Script removido do servidor.');
    }

    private function caminhoRemotoLoginScript(): string
    {
        return 'C:\\ProgramData\\RDIntranet\\login_script.ps1';
    }

    /** Instala (envia o arquivo + registra a tarefa) em N máquinas -- fogo-e-esquece. */
    public function instalarLoginScriptEmLote(array $ativoIds, ?string $solicitadoPor): array
    {
        if (!$this->loginScriptConfigurado()) {
            return ['success' => false, 'message' => 'Envie o script de login antes.'];
        }

        $pastaTransferencias = __DIR__ . '/../../storage/uploads/ativos_transferencias';
        if (!is_dir($pastaTransferencias) && !@mkdir($pastaTransferencias, 0777, true) && !is_dir($pastaTransferencias)) {
            return ['success' => false, 'message' => 'Não foi possível preparar a pasta de transferência.'];
        }

        $destinoRemoto = $this->caminhoRemotoLoginScript();
        $nomeRemoto = basename(str_replace('\\', '/', $destinoRemoto));
        $enviados = 0;

        foreach ($ativoIds as $ativoId) {
            $ativoId = (int)$ativoId;
            $copiaTemp = $pastaTransferencias . '/enviar_' . uniqid('', true) . '_' . $nomeRemoto;

            if (!@copy(self::caminhoLoginScript(), $copiaTemp)) {
                continue;
            }

            $resultadoArquivo = $this->ativoService->enviarComando($ativoId, 'enviar_arquivo', $solicitadoPor, $destinoRemoto, $nomeRemoto, $copiaTemp);
            if (!($resultadoArquivo['success'] ?? false)) {
                @unlink($copiaTemp);
                continue;
            }

            $resultado = $this->ativoService->solicitarListagem($ativoId, 'executar_powershell', $this->scriptRegistrarLoginScript(), $solicitadoPor, false);
            if ($resultado['success'] ?? false) {
                $enviados++;
            }
        }

        AuditService::registrar('Ativos', 'Regras de Segurança', "Script de login instalado em {$enviados} máquina(s).");

        return ['success' => true, 'enviados' => $enviados];
    }

    /** Desregistra a tarefa (e apaga o .ps1) em N máquinas -- fogo-e-esquece. */
    public function removerLoginScriptEmLote(array $ativoIds, ?string $solicitadoPor): array
    {
        $script = $this->scriptRemoverLoginScript();
        $enviados = 0;

        foreach ($ativoIds as $ativoId) {
            $resultado = $this->ativoService->solicitarListagem((int)$ativoId, 'executar_powershell', $script, $solicitadoPor, false);
            if ($resultado['success'] ?? false) {
                $enviados++;
            }
        }

        AuditService::registrar('Ativos', 'Regras de Segurança', "Script de login removido de {$enviados} máquina(s).");

        return ['success' => true, 'enviados' => $enviados];
    }

    /** Espera o .ps1 chegar (enviar_arquivo é assíncrono) e registra a Scheduled Task de logon -- mesmo XML que o agente usa pra se auto-iniciar, gerado aqui em PHP. */
    private function scriptRegistrarLoginScript(): string
    {
        $template = <<<'PS1'
$destino = '__DESTINO__'
$limite = (Get-Date).AddSeconds(60)

while (-not (Test-Path $destino) -and (Get-Date) -lt $limite) {
    Start-Sleep -Seconds 2
}

if (-not (Test-Path $destino)) {
    Write-Output "ERRO: script de login nao chegou a tempo em $destino."
    exit 1
}

$xml = @'
<?xml version="1.0" encoding="UTF-16"?>
<Task version="1.2" xmlns="http://schemas.microsoft.com/windows/2004/02/mit/task">
  <Triggers>
    <LogonTrigger>
      <Enabled>true</Enabled>
    </LogonTrigger>
  </Triggers>
  <Principals>
    <Principal id="Author">
      <GroupId>S-1-5-32-545</GroupId>
      <RunLevel>HighestAvailable</RunLevel>
    </Principal>
  </Principals>
  <Settings>
    <MultipleInstancesPolicy>IgnoreNew</MultipleInstancesPolicy>
    <DisallowStartIfOnBatteries>false</DisallowStartIfOnBatteries>
    <StopIfGoingOnBatteries>false</StopIfGoingOnBatteries>
    <AllowHardTerminate>true</AllowHardTerminate>
    <StartWhenAvailable>false</StartWhenAvailable>
    <RunOnlyIfNetworkAvailable>false</RunOnlyIfNetworkAvailable>
    <Enabled>true</Enabled>
    <Hidden>false</Hidden>
    <ExecutionTimeLimit>PT0S</ExecutionTimeLimit>
  </Settings>
  <Actions Context="Author">
    <Exec>
      <Command>powershell.exe</Command>
      <Arguments>-ExecutionPolicy Bypass -File "__DESTINO__"</Arguments>
    </Exec>
  </Actions>
</Task>
'@

$caminhoXml = Join-Path $env:TEMP '__NOMETAREFA__.xml'
Set-Content -Path $caminhoXml -Value $xml -Encoding Unicode -ErrorAction Stop

try {
    schtasks /create /tn "__NOMETAREFA__" /xml $caminhoXml /f | Out-Null
    if ($LASTEXITCODE -ne 0) { throw "schtasks retornou codigo $LASTEXITCODE" }
    Write-Output "Script de login registrado com sucesso."
} catch {
    Write-Output "ERRO ao registrar tarefa: $($_.Exception.Message)"
    exit 1
} finally {
    Remove-Item $caminhoXml -ErrorAction SilentlyContinue
}
PS1;

        return str_replace(['__DESTINO__', '__NOMETAREFA__'], [$this->caminhoRemotoLoginScript(), self::NOME_TAREFA_LOGIN_SCRIPT], $template);
    }

    private function scriptRemoverLoginScript(): string
    {
        $template = <<<'PS1'
schtasks /delete /tn "__NOMETAREFA__" /f 2>&1 | Out-Null
Remove-Item -Path '__DESTINO__' -ErrorAction SilentlyContinue
Write-Output "Script de login removido."
PS1;

        return str_replace(['__NOMETAREFA__', '__DESTINO__'], [self::NOME_TAREFA_LOGIN_SCRIPT, $this->caminhoRemotoLoginScript()], $template);
    }

    /**
     * Fase 5: ação avulsa "Aplicar atualizações do Windows" -- sem
     * estado nenhum pra guardar, só dispara e mostra o resultado (igual
     * o botão "Aplicar mapeamentos do setor").
     *
     * Não usa UsoClient.exe: é uma ferramenta não documentada oficialmente
     * pela Microsoft, e o switch "ScanInstallWait" parou de funcionar de
     * verdade em builds do Windows 10 a partir de ~2020 (só finge que
     * disparou). Em vez disso, fala direto com a Windows Update Agent API
     * (o motor de verdade por trás da tela de Configurações), via os
     * objetos COM Microsoft.Update.Session/Searcher/Downloader/Installer
     * -- nativo do Windows, não precisa instalar nenhum módulo extra
     * (diferente do PSWindowsUpdate, que exigiria internet/PSGallery na
     * máquina do cliente).
     */
    public function scriptAplicarAtualizacoesWindows(): string
    {
        return <<<'PS1'
try {
    $sessao = New-Object -ComObject Microsoft.Update.Session
    $busca = $sessao.CreateUpdateSearcher()
    $resultado = $busca.Search("IsInstalled=0 and Type='Software' and IsHidden=0")

    if ($resultado.Updates.Count -eq 0) {
        Write-Output "Nenhuma atualizacao pendente."
    } else {
        $paraBaixar = New-Object -ComObject Microsoft.Update.UpdateColl
        foreach ($item in $resultado.Updates) { $paraBaixar.Add($item) | Out-Null }

        $baixador = $sessao.CreateUpdateDownloader()
        $baixador.Updates = $paraBaixar
        $baixador.Download() | Out-Null

        $paraInstalar = New-Object -ComObject Microsoft.Update.UpdateColl
        foreach ($item in $resultado.Updates) {
            if ($item.IsDownloaded) { $paraInstalar.Add($item) | Out-Null }
        }

        if ($paraInstalar.Count -eq 0) {
            Write-Output "Encontrei $($resultado.Updates.Count) atualizacao(oes), mas nenhuma baixou com sucesso."
        } else {
            $instalador = $sessao.CreateUpdateInstaller()
            $instalador.Updates = $paraInstalar
            $resultadoInstalacao = $instalador.Install()

            # ResultCode: 2=sucesso, 3=sucesso com falhas parciais, 4=falha, 5=cancelado
            Write-Output "Atualizacoes instaladas: $($paraInstalar.Count) de $($resultado.Updates.Count) encontrada(s). Codigo do resultado: $($resultadoInstalacao.ResultCode). Reinicio necessario: $($resultadoInstalacao.RebootRequired)."
        }
    }
} catch {
    Write-Output "ERRO ao aplicar atualizacoes: $($_.Exception.Message)"
    exit 1
}
PS1;
    }
}
