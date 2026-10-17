<?php
ob_start();

use App\Components\Alert;

$usuariosOrdenados = $usuarios;
usort($usuariosOrdenados, fn($a, $b) => strcasecmp($a['displayName'] ?? '', $b['displayName'] ?? ''));

/** Lista de caixas de seleção de usuários (modal de novo grupo e de membros). */
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

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1"><i class="bi bi-diagram-2 me-1"></i> Microsoft Entra - Grupos</h4>
        <small class="text-muted"><a href="<?= url('/entra/dashboard') ?>"><i class="bi bi-arrow-left"></i> Dashboard</a></small>
    </div>
    <?php if ($configurado): ?>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovoGrupo">
            <i class="bi bi-plus-lg"></i> Novo grupo
        </button>
    <?php endif; ?>
</div>

<?php if (!$configurado): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-plug display-6 text-muted d-block mb-3"></i>
            <p class="text-muted mb-3">Módulo ainda não configurado.</p>
            <a href="<?= url('/entra/configuracao') ?>" class="btn btn-primary"><i class="bi bi-gear"></i> Configurar</a>
        </div>
    </div>
<?php else: ?>

    <div class="alert alert-info small">
        <i class="bi bi-info-circle"></i>
        Grupos de segurança do Entra -- o jeito de dizer <strong>quem pode entrar em quais máquinas</strong>: crie um
        grupo por unidade (ex.: <em>NTO - Acesso às Máquinas</em>), coloque as pessoas e aplique o grupo nas máquinas da
        unidade em <a href="<?= url('/entra/acesso-maquinas') ?>">Acesso às Máquinas</a>. Depois disso, incluir ou tirar
        alguém é só mudar os membros aqui -- não precisa reaplicar nas máquinas (vale no próximo login da pessoa).
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if (empty($grupos)): ?>
                <p class="text-muted p-3 mb-0">Nenhum grupo de segurança no tenant ainda.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Grupo</th>
                                <th>Membros</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($grupos as $g): ?>
                                <?php $membros = $g['membros']; ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($g['displayName']) ?></strong>
                                        <?php if (!empty($g['description'])): ?>
                                            <div class="text-muted small"><?= htmlspecialchars($g['description']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (empty($membros)): ?>
                                            <span class="text-muted small">nenhum</span>
                                        <?php else: ?>
                                            <?php foreach ($membros as $m): ?>
                                                <span class="badge text-bg-light border fw-normal" title="<?= htmlspecialchars($m['userPrincipalName'] ?? '') ?>"><?= htmlspecialchars($m['displayName'] ?? $m['userPrincipalName'] ?? '') ?></span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalMembros-<?= htmlspecialchars($g['id']) ?>">
                                            <i class="bi bi-people"></i> Membros
                                        </button>
                                        <form method="post" action="<?= url('/entra/grupos/excluir') ?>" class="d-inline"
                                              onsubmit="return confirm('Excluir o grupo <?= htmlspecialchars(addslashes($g['displayName'])) ?>? Máquinas que usam esse grupo em Acesso às Máquinas deixam de aceitar os membros dele (só administradores continuam entrando).');">
                                            <input type="hidden" name="grupo_id" value="<?= htmlspecialchars($g['id']) ?>">
                                            <input type="hidden" name="nome" value="<?= htmlspecialchars($g['displayName']) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

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
                        <p class="text-muted small">Marque quem faz parte do grupo. Quem for desmarcado sai do grupo.</p>
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
                    <div class="mb-3">
                        <label class="form-label">Nome</label>
                        <input type="text" name="nome" class="form-control" required maxlength="120" placeholder="Ex.: Prime - Acesso às Máquinas">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição <span class="text-muted small">(opcional)</span></label>
                        <input type="text" name="descricao" class="form-control" maxlength="300" placeholder="Usuários autorizados a entrar nos computadores da unidade">
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

<?php endif; ?>

<?php
$conteudo = ob_get_clean();
$titulo = 'Microsoft Entra - Grupos';

require __DIR__ . '/../layouts/main.php';
