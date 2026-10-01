<?php
/**
 * Botões de download dos instaladores do MeshAgent (acesso remoto) -- usado
 * no Dashboard de Ativos (aba Agente Windows) e em Acesso Remoto, pra equipe
 * baixar tudo de que uma máquina nova precisa num lugar só.
 * Espera: $arquiteturasMeshAgente, $meshAgentesDisponiveis.
 */
?>
<div class="d-flex flex-wrap gap-2">
    <?php foreach ($arquiteturasMeshAgente as $chave => $label): ?>
        <?php if ($meshAgentesDisponiveis[$chave] ?? false): ?>
            <a href="<?= url('/ativos/acesso-remoto/mesh-agente?arquitetura=' . $chave) ?>" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-download"></i> <?= htmlspecialchars($label) ?>
            </a>
        <?php else: ?>
            <span class="btn btn-sm btn-outline-secondary disabled"><?= htmlspecialchars($label) ?> -- não enviado</span>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
