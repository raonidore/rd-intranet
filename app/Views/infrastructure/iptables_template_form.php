<?php

use App\Components\Alert;

ob_start();
?>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi <?= htmlspecialchars($template['icone']) ?>"></i> <?= htmlspecialchars($template['nome']) ?></h5>
        <small class="text-muted"><?= htmlspecialchars($template['descricao']) ?></small>
    </div>

    <div class="card-body">
        <form method="post" action="<?= url('/infraestrutura/iptables/templates/aplicar') ?>"
              onsubmit="return confirm('Aplicar esta regra pronta? Você poderá confirmar ou reverter em seguida.');">
            <input type="hidden" name="chave" value="<?= htmlspecialchars($chave) ?>">

            <div class="row g-3 mb-3">
                <?php foreach ($template['campos'] as $campo): ?>
                    <div class="col-md-6">
                        <label class="form-label">
                            <?= htmlspecialchars($campo['label']) ?>
                            <?php if (!empty($campo['obrigatorio'])): ?><span class="text-danger">*</span><?php endif; ?>
                        </label>

                        <?php if ($campo['tipo'] === 'interface'): ?>
                            <select name="<?= $campo['nome'] ?>" class="form-select">
                                <?php if (!empty($campo['opcional_todas'])): ?>
                                    <option value="">(todas as interfaces)</option>
                                <?php endif; ?>
                                <?php foreach ($interfaces as $if): ?>
                                    <option value="<?= htmlspecialchars($if) ?>"><?= htmlspecialchars($if) ?></option>
                                <?php endforeach; ?>
                            </select>

                        <?php elseif ($campo['tipo'] === 'protocolo'): ?>
                            <select name="<?= $campo['nome'] ?>" class="form-select">
                                <option value="tcp" <?= ($campo['padrao'] ?? '') === 'tcp' ? 'selected' : '' ?>>TCP</option>
                                <option value="udp" <?= ($campo['padrao'] ?? '') === 'udp' ? 'selected' : '' ?>>UDP</option>
                            </select>

                        <?php elseif ($campo['tipo'] === 'checkbox'): ?>
                            <div class="form-check mt-2">
                                <input type="checkbox" class="form-check-input" name="<?= $campo['nome'] ?>" id="campo-<?= $campo['nome'] ?>" value="1">
                                <label class="form-check-label" for="campo-<?= $campo['nome'] ?>">
                                    <?= htmlspecialchars($campo['label']) ?>
                                </label>
                            </div>

                        <?php else: ?>
                            <input type="text" name="<?= $campo['nome'] ?>" class="form-control font-monospace"
                                   placeholder="<?= htmlspecialchars($campo['placeholder'] ?? '') ?>"
                                   value="<?= htmlspecialchars($campo['padrao'] ?? ($campo['nome'] === 'porta' ? implode(',', $sshPortas) : '')) ?>">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="alert alert-warning small mb-3">
                <i class="bi bi-shield-check"></i>
                Conexões já estabelecidas, loopback e a porta SSH atual continuam sempre liberadas — e esta alteração
                pode ser confirmada ou revertida logo em seguida.
            </div>

            <div class="d-flex justify-content-between mt-3">
                <a href="<?= url('/infraestrutura/iptables/templates') ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Voltar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> Aplicar
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Aplicar Regra Pronta - ' . $template['nome'];

require __DIR__ . '/../layouts/main.php';
