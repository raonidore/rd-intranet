<?php
/** Freio da atualização automática do agente -- incluído no Dashboard de Ativos e na Central de Segurança. Espera $agenteAtualizacaoAutomatica, $agenteVersaoDisponivel, $podeEditarFreio, $voltarFreio. */
?>
<div class="border rounded-3 p-2 px-3 d-flex flex-wrap align-items-center justify-content-between gap-2 <?= $agenteAtualizacaoAutomatica ? '' : 'border-warning' ?>">
    <div class="small">
        <?php if ($agenteAtualizacaoAutomatica): ?>
            <span class="badge text-bg-success">Ligada</span> <strong>Atualização automática do agente</strong>
            <div class="text-muted">Versão nova publicada pela RD chega sozinha em até 30 min; as máquinas se atualizam no próximo contato com o servidor.</div>
        <?php else: ?>
            <span class="badge text-bg-warning">Desligada</span> <strong>Atualização automática do agente</strong>
            <div class="text-muted">Este servidor fica na versão atual (regras do anti-ransomware continuam chegando). Quando quiser aplicar, use "Buscar atualização agora".</div>
            <?php if ($agenteVersaoDisponivel !== ''): ?>
                <div class="text-warning-emphasis"><i class="bi bi-info-circle"></i> Versão <strong><?= htmlspecialchars($agenteVersaoDisponivel) ?></strong> publicada pela RD e ainda não aplicada neste servidor.</div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php if ($podeEditarFreio): ?>
        <form method="post" action="<?= url('/ativos/agente/atualizacao-automatica') ?>" class="m-0"
              onsubmit="return <?= $agenteAtualizacaoAutomatica ? "confirm('Desligar a atualização automática do agente neste servidor? Ele fica na versão atual até alguém clicar em Buscar atualização agora.')" : 'true' ?>;">
            <input type="hidden" name="ligada" value="<?= $agenteAtualizacaoAutomatica ? '0' : '1' ?>">
            <input type="hidden" name="voltar" value="<?= htmlspecialchars($voltarFreio) ?>">
            <button class="btn btn-sm <?= $agenteAtualizacaoAutomatica ? 'btn-outline-warning' : 'btn-outline-success' ?>">
                <i class="bi <?= $agenteAtualizacaoAutomatica ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i> <?= $agenteAtualizacaoAutomatica ? 'Desligar (frear)' : 'Ligar' ?>
            </button>
        </form>
    <?php endif; ?>
</div>
