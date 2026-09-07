<?php

use App\Components\Alert;

ob_start();
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-1"><i class="bi bi-journal-check"></i> GPOs (Políticas de Grupo)</h5>
            <small class="text-muted">
                Criar/organizar/vincular pela UI. O <strong>conteúdo</strong> da política (as configurações em si)
                precisa ser editado pelo GPMC a partir de uma estação Windows -- não há como reimplementar isso aqui.
            </small>
        </div>
        <a href="<?= url('/samba/dominio/gpos/novo') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Nova GPO
        </a>
    </div>
</div>

<?= Alert::flash() ?>

<?php if (!empty($aclcheck) && $aclcheck['success'] === false): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-1"></i>
        <strong>Verificação de ACL do SYSVOL encontrou problema</strong> (causa comum de "GPO existe mas não aplica"):
        <pre class="mb-0 mt-2 small"><?= htmlspecialchars($aclcheck['message'] ?? '') ?></pre>
    </div>
<?php elseif (!empty($aclcheck)): ?>
    <div class="alert alert-success small"><i class="bi bi-check-circle me-1"></i> SYSVOL: <?= htmlspecialchars($aclcheck['message'] ?? 'ok') ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>GUID</th>
                    <th>Versão</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($gpos)): ?>
                    <tr><td colspan="4" class="text-center text-muted py-4">Nenhuma GPO encontrada.</td></tr>
                <?php endif; ?>
                <?php foreach ($gpos as $g): ?>
                    <?php $idColapso = 'vincular-' . preg_replace('/[^a-zA-Z0-9]/', '', $g['guid']); ?>
                    <tr>
                        <td><?= htmlspecialchars($g['nome']) ?></td>
                        <td class="font-monospace small"><?= htmlspecialchars($g['guid']) ?></td>
                        <td><?= htmlspecialchars($g['versao']) ?></td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#<?= $idColapso ?>">
                                <i class="bi bi-link-45deg"></i> Vincular
                            </button>
                            <a href="<?= url('/samba/dominio/gpos/excluir?guid=' . urlencode($g['guid'])) ?>"
                               class="btn btn-sm btn-outline-danger" title="Excluir"
                               onclick="return confirm('Excluir a GPO \'<?= htmlspecialchars($g['nome'], ENT_QUOTES) ?>\'?')">
                                <i class="bi bi-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <tr class="collapse" id="<?= $idColapso ?>">
                        <td colspan="4" class="bg-light">
                            <form class="d-flex gap-2 align-items-end py-2" method="post" action="<?= url('/samba/dominio/gpos/vincular') ?>">
                                <input type="hidden" name="guid" value="<?= htmlspecialchars($g['guid']) ?>">
                                <div class="flex-grow-1">
                                    <label class="form-label small mb-1">Vincular a</label>
                                    <select name="container_dn" class="form-select form-select-sm" required>
                                        <?php if ($dominioDn !== ''): ?>
                                            <option value="<?= htmlspecialchars($dominioDn) ?>">Domínio inteiro</option>
                                        <?php endif; ?>
                                        <?php foreach ($ous as $ou): ?>
                                            <option value="<?= htmlspecialchars($ou) ?>"><?= htmlspecialchars($ou) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-sm btn-primary">Vincular</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Samba - Domínio - GPOs';
require __DIR__ . '/../../layouts/main.php';
