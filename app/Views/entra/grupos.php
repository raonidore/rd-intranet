<?php
ob_start();

use App\Components\Alert;

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
            <a href="<?= url('/administracao/integracoes/entra') ?>" class="btn btn-primary"><i class="bi bi-gear"></i> Configurar</a>
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

    <?php $voltarPara = '/entra/grupos'; require __DIR__ . '/_modais_grupos.php'; ?>

<?php endif; ?>

<?php
$conteudo = ob_get_clean();
$titulo = 'Microsoft Entra - Grupos';

require __DIR__ . '/../layouts/main.php';
