<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Isolamento de rede do módulo anti-ransomware. Não tem canal próprio: usa
 * a mesma solicitação "executar_powershell" elevada que o portal já usa
 * (entregue no heartbeat, em segundos). O script guarda o estado original
 * do firewall antes de mexer, pra que "Remover isolamento" devolva a
 * máquina exatamente como estava, e sempre libera o executável do próprio
 * agente -- a máquina isolada continua falando com o portal, senão não
 * haveria como desfazer remotamente.
 */
class SegurancaIsolamentoService
{
    private const GRUPO_REGRAS = 'RD Intranet - Isolamento';
    private const SOLICITANTE = 'Anti-ransomware';

    private PDO $pdo;
    private SegurancaModuloService $modulos;
    private SegurancaEventoService $eventos;

    public function __construct()
    {
        $this->pdo = Database::connection();
        $this->modulos = new SegurancaModuloService();
        $this->eventos = new SegurancaEventoService();
    }

    /** Decide o que fazer com um evento crítico recém-chegado. Devolve o texto usado na notificação. */
    public function reagirAEvento(int $ativoId, int $eventoId): string
    {
        $efetivo = $this->modulos->efetivo($ativoId);

        if (!empty($efetivo['isolado_em'])) {
            return 'a máquina já estava isolada da rede.';
        }

        switch ($efetivo['isolamento_modo']) {
            case 'automatico':
                $resultado = $this->isolar($ativoId, "automático (evento #{$eventoId})", $eventoId);
                return $resultado['success'] ? 'rede isolada automaticamente.' : 'falha ao isolar: ' . $resultado['message'];

            case 'confirmacao':
                $minutos = (int)$efetivo['confirmacao_minutos'];
                $this->eventos->atualizarAcao($eventoId, 'isolamento_pendente', $minutos);
                return "a rede será isolada sozinha em {$minutos} min se ninguém cancelar na ficha do ativo.";

            case 'alerta':
                return 'somente alerta -- nada foi isolado. Se confirmar o ataque, isole pela ficha do ativo.';

            default:
                return 'isolamento desligado para esta máquina.';
        }
    }

    /**
     * Chamado a cada heartbeat da máquina: aplica isolamentos em modo
     * "confirmação" cujo prazo venceu sem ninguém cancelar. Consulta leve e
     * indexada por ativo -- o heartbeat roda a cada poucos segundos.
     */
    public function processarPendentes(int $ativoId): void
    {
        $stmt = $this->pdo->prepare(
            "SELECT id FROM ativos_eventos_seguranca
             WHERE ativo_id = ? AND isolamento_pendente_ate IS NOT NULL AND isolamento_pendente_ate <= NOW()
               AND resolvido_em IS NULL
             ORDER BY id LIMIT 1"
        );
        $stmt->execute([$ativoId]);
        $eventoId = $stmt->fetchColumn();

        if ($eventoId === false) {
            return;
        }

        $this->eventos->atualizarAcao((int)$eventoId, 'isolamento_pendente', null);
        $this->isolar($ativoId, "prazo de confirmação vencido (evento #{$eventoId})", (int)$eventoId);
    }

    /** @return array{success: bool, message: string} */
    public function isolar(int $ativoId, string $motivo, ?int $eventoId = null, ?string $usuario = null): array
    {
        $efetivo = $this->modulos->efetivo($ativoId);
        if (!empty($efetivo['isolado_em'])) {
            return ['success' => false, 'message' => 'A máquina já está isolada.'];
        }

        $resultado = (new AtivoService())->solicitarListagem($ativoId, 'executar_powershell', self::scriptIsolar(), $usuario ?? self::SOLICITANTE, true);
        if (!$resultado['success']) {
            return ['success' => false, 'message' => $resultado['message'] ?? 'Não foi possível enfileirar o isolamento.'];
        }

        $this->modulos->marcarIsolado($ativoId, true);
        if ($eventoId !== null) {
            $this->eventos->atualizarAcao($eventoId, 'rede_isolada');
        }
        $this->eventos->registrarDoServidor($ativoId, 'NETWORK_ISOLATION_APPLIED', 'Rede isolada -- motivo: ' . $motivo, [
            'motivo' => $motivo,
            'evento_origem' => $eventoId,
            'solicitacao_id' => $resultado['id'] ?? null,
            'por' => $usuario ?? self::SOLICITANTE,
        ]);

        return ['success' => true, 'message' => 'Isolamento enviado para a máquina (aplicado no próximo heartbeat, em segundos).'];
    }

    /** @return array{success: bool, message: string} */
    public function removerIsolamento(int $ativoId, string $usuario): array
    {
        $resultado = (new AtivoService())->solicitarListagem($ativoId, 'executar_powershell', self::scriptRemover(), $usuario, true);
        if (!$resultado['success']) {
            return ['success' => false, 'message' => $resultado['message'] ?? 'Não foi possível enfileirar a remoção.'];
        }

        $this->modulos->marcarIsolado($ativoId, false);
        $this->eventos->registrarDoServidor($ativoId, 'NETWORK_ISOLATION_REMOVED', "Isolamento removido por {$usuario}", [
            'por' => $usuario,
            'solicitacao_id' => $resultado['id'] ?? null,
        ]);

        return ['success' => true, 'message' => 'Remoção do isolamento enviada para a máquina.'];
    }

    /** @return array{success: bool, message: string} */
    public function cancelarPendente(int $eventoId, int $ativoId, string $usuario): array
    {
        $evento = $this->eventos->buscar($eventoId);
        if (!$evento || (int)$evento['ativo_id'] !== $ativoId || empty($evento['isolamento_pendente_ate'])) {
            return ['success' => false, 'message' => 'Não há isolamento pendente para esse evento.'];
        }

        $this->eventos->atualizarAcao($eventoId, 'isolamento_cancelado');
        $this->eventos->registrarDoServidor($ativoId, 'NETWORK_ISOLATION_CANCELLED', "Isolamento pendente do evento #{$eventoId} cancelado por {$usuario}", [
            'evento_origem' => $eventoId,
            'por' => $usuario,
        ]);

        return ['success' => true, 'message' => 'Isolamento cancelado.'];
    }

    /**
     * Bloqueia entrada e saída em todos os perfis, liberando só o
     * executável do agente (+ DNS/DHCP, sem os quais a máquina perde o
     * endereço ou não resolve o nome do portal). Regras de saída "permitir"
     * já existentes são desativadas -- senão continuariam passando por cima
     * do bloqueio padrão -- exceto as de Rede Principal (Core Networking,
     * grupo @FirewallAPI.dll,-25000, igual em qualquer idioma do Windows).
     * Idempotente: rodar duas vezes não sobrescreve o estado original salvo.
     */
    public static function scriptIsolar(): string
    {
        $grupo = self::GRUPO_REGRAS;

        return <<<PS
\$ErrorActionPreference = 'Stop'
\$dir = Join-Path \$env:ProgramData 'RDIntranetAgent'
New-Item -ItemType Directory -Force -Path \$dir | Out-Null
\$arq = Join-Path \$dir 'isolamento_estado.json'
\$agente = Get-Process -Name 'RdIntranetAgente' -ErrorAction SilentlyContinue | Where-Object { \$_.Path } | Select-Object -First 1 -ExpandProperty Path
if (-not \$agente) { throw 'Processo do agente nao encontrado -- isolamento abortado para nao cortar a comunicacao com o portal.' }
if (-not (Test-Path \$arq)) {
    \$perfis = @(Get-NetFirewallProfile | ForEach-Object { [pscustomobject]@{ Name = [string]\$_.Name; Enabled = [string]\$_.Enabled; DefaultInboundAction = [string]\$_.DefaultInboundAction; DefaultOutboundAction = [string]\$_.DefaultOutboundAction; AllowInboundRules = [string]\$_.AllowInboundRules } })
    \$desativadas = @(Get-NetFirewallRule -Direction Outbound -Action Allow -Enabled True -ErrorAction SilentlyContinue | Where-Object { \$_.Group -ne '@FirewallAPI.dll,-25000' -and \$_.Group -ne '{$grupo}' } | ForEach-Object { [string]\$_.Name })
    [pscustomobject]@{ perfis = \$perfis; regras_desativadas = \$desativadas; aplicado_em = (Get-Date).ToString('s') } | ConvertTo-Json -Depth 4 | Set-Content -Path \$arq -Encoding UTF8
}
\$estado = Get-Content -Path \$arq -Raw | ConvertFrom-Json
Get-NetFirewallRule -Group '{$grupo}' -ErrorAction SilentlyContinue | Remove-NetFirewallRule
New-NetFirewallRule -DisplayName '{$grupo} (agente)' -Group '{$grupo}' -Direction Outbound -Action Allow -Program \$agente -Profile Any | Out-Null
New-NetFirewallRule -DisplayName '{$grupo} (DNS)' -Group '{$grupo}' -Direction Outbound -Action Allow -Protocol UDP -RemotePort 53 -Profile Any | Out-Null
New-NetFirewallRule -DisplayName '{$grupo} (DHCP)' -Group '{$grupo}' -Direction Outbound -Action Allow -Protocol UDP -LocalPort 68 -RemotePort 67 -Profile Any | Out-Null
\$regras = @(\$estado.regras_desativadas | Where-Object { \$_ })
if (\$regras.Count -gt 0) { Disable-NetFirewallRule -Name \$regras -ErrorAction SilentlyContinue }
Set-NetFirewallProfile -All -Enabled True -DefaultInboundAction Block -DefaultOutboundAction Block -AllowInboundRules False
"Rede isolada. Liberado apenas o agente (\$agente), DNS e DHCP. Regras de saida desativadas: " + @(\$estado.regras_desativadas).Count
PS;
    }

    /** Desfaz exatamente o que scriptIsolar() mudou, a partir do estado salvo. */
    public static function scriptRemover(): string
    {
        $grupo = self::GRUPO_REGRAS;

        return <<<PS
\$ErrorActionPreference = 'Stop'
\$arq = Join-Path (Join-Path \$env:ProgramData 'RDIntranetAgent') 'isolamento_estado.json'
Get-NetFirewallRule -Group '{$grupo}' -ErrorAction SilentlyContinue | Remove-NetFirewallRule
if (Test-Path \$arq) {
    \$estado = Get-Content -Path \$arq -Raw | ConvertFrom-Json
    foreach (\$p in @(\$estado.perfis)) {
        Set-NetFirewallProfile -Name \$p.Name -Enabled \$p.Enabled -DefaultInboundAction \$p.DefaultInboundAction -DefaultOutboundAction \$p.DefaultOutboundAction -AllowInboundRules \$p.AllowInboundRules
    }
    \$regras = @(\$estado.regras_desativadas | Where-Object { \$_ })
    if (\$regras.Count -gt 0) { Enable-NetFirewallRule -Name \$regras -ErrorAction SilentlyContinue }
    Remove-Item -Path \$arq -Force
    "Isolamento removido. Firewall restaurado ao estado de " + \$estado.aplicado_em
} else {
    Set-NetFirewallProfile -All -DefaultOutboundAction Allow -AllowInboundRules True
    "Estado original nao encontrado -- saida liberada e regras de entrada reabilitadas nos padroes do Windows."
}
PS;
    }
}
