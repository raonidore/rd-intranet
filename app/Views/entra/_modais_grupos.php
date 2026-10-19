<?php
/**
 * Janelas "Novo grupo" e "Membros" de grupo do Entra -- usadas em Entra > Grupos
 * e direto em Acesso às Máquinas (pra não ter que trocar de tela).
 * Espera: $grupos (com 'membros'), $usuarios, $voltarPara (ex.: '/entra/acesso-maquinas').
 */
$usuariosOrdenados = $usuarios;
usort($usuariosOrdenados, fn($a, $b) => strcasecmp($a['displayName'] ?? '', $b['displayName'] ?? ''));

$listaUsuarios = function (string $prefixo, array $marcados) use ($usuariosOrdenados): string {
    ob_start();
    foreach ($usuariosOrdenados as $u):
        $campo = $prefixo . '-' . $u['id'];
        ?>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="membros[]" value="<?= htmlspecialchars($u['id']) ?>" id="<?= htmlspecialchars($campo) ?>" <?= in_array($u['id'], $marcados, true) ? 'checked' : '' ?>>
            <label class="form-check-label small" for="<?= htmlspecialchars($campo) ?>">
                <?= htmlspecialchars($u['displayName'] ?? $u['userPrincipalName']) ?>
                <span class="text-muted font-monospace">(<?= htmlspecialchars($u['userPrincipalName'] ?? '') ?>)</span>
                <?php if (!($u['accountEnabled'] ?? true)): ?><span class="badge text-bg-secondary">desativado</span><?php endif; ?>
            </label>
        </div>
        <?php
    endforeach;
    return ob_get_clean();
};
?>

<?php foreach ($grupos as $g): ?>
    <div class="modal fade" id="modalMembros-<?= htmlspecialchars($g['id']) ?>" tabindex="-1">
        <div class="modal-dialog modal-dialog-scrollable">
            <form method="post" action="<?= url('/entra/grupos/membros') ?>" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-people"></i> Membros - <?= htmlspecialchars($g['displayName']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="grupo_id" value="<?= htmlspecialchars($g['id']) ?>">
                    <input type="hidden" name="nome" value="<?= htmlspecialchars($g['displayName']) ?>">
                    <input type="hidden" name="voltar" value="<?= htmlspecialchars($voltarPara) ?>">
                    <p class="text-muted small">Marque quem faz parte do grupo; quem for desmarcado sai. As máquinas onde o grupo está aplicado são atualizadas sozinhas.</p>
                    <?= $listaUsuarios('m-' . $g['id'], array_column($g['membros'], 'id')) ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar membros</button>
                </div>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<div class="modal fade" id="modalNovoGrupo" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <form method="post" action="<?= url('/entra/grupos/novo') ?>" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-lg"></i> Novo grupo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="voltar" value="<?= htmlspecialchars($voltarPara) ?>">
                <div class="mb-3">
                    <label class="form-label">Nome</label>
                    <input type="text" name="nome" class="form-control" required maxlength="120" placeholder="Ex.: NTO - Acesso às Máquinas ou TI - Administradores NTO">
                </div>
                <div class="mb-3">
                    <label class="form-label">Descrição <span class="text-muted small">(opcional)</span></label>
                    <input type="text" name="descricao" class="form-control" maxlength="300" placeholder="Quem entra / quem administra os computadores da unidade">
                </div>
                <label class="form-label">Membros</label>
                <?= $listaUsuarios('n', []) ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Criar grupo</button>
            </div>
        </form>
    </div>
</div>
