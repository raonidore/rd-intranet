<?php

use App\Components\Alert;

ob_start();

$editando = $coluna !== null;
$acao = $editando ? url('/banco-dados/console/estrutura/coluna/editar') : url('/banco-dados/console/estrutura/coluna/nova');
?>

<div class="db-layout">
    <?php require __DIR__ . '/_console_sidebar.php'; ?>

    <div class="db-content">
        <?= Alert::flash() ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1"><i class="bi <?= $editando ? 'bi-pencil' : 'bi-plus-lg' ?> me-1"></i> <?= $editando ? 'Editar coluna' : 'Nova coluna' ?></h4>
                <small class="text-muted"><?= htmlspecialchars($conexao['nome']) ?> — <?= htmlspecialchars($banco) ?>.<?= htmlspecialchars($tabela) ?></small>
            </div>
            <a href="<?= url('/banco-dados/console/estrutura?conexao=' . $conexao['id'] . '&banco=' . urlencode($banco) . '&tabela=' . urlencode($tabela)) ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>

        <div class="alert alert-warning small">
            <i class="bi bi-exclamation-triangle"></i>
            Isso altera a estrutura da tabela (<code>ALTER TABLE</code>) — em tabelas grandes pode demorar e travar
            escritas durante a operação. Confira antes de salvar.
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form method="post" action="<?= $acao ?>"
                      onsubmit="return confirm('<?= $editando ? 'Alterar esta coluna?' : 'Adicionar esta coluna?' ?>');">
                    <input type="hidden" name="conexao" value="<?= (int)$conexao['id'] ?>">
                    <input type="hidden" name="banco" value="<?= htmlspecialchars($banco) ?>">
                    <input type="hidden" name="tabela" value="<?= htmlspecialchars($tabela) ?>">
                    <?php if ($editando): ?>
                        <input type="hidden" name="nome_antigo" value="<?= htmlspecialchars($coluna['Field']) ?>">
                    <?php endif; ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nome da coluna</label>
                            <input type="text" name="nome" class="form-control font-monospace" required
                                   pattern="[A-Za-z0-9_]+" title="Letras, números e sublinhado"
                                   value="<?= htmlspecialchars($coluna['Field'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tipo</label>
                            <input type="text" name="tipo" class="form-control font-monospace" required
                                   placeholder="VARCHAR(100), INT(11), DECIMAL(10,2)..."
                                   value="<?= htmlspecialchars($coluna['Type'] ?? '') ?>">
                        </div>

                        <div class="col-md-4">
                            <div class="form-check mt-4">
                                <input type="checkbox" class="form-check-input" name="nulo" id="nulo"
                                       <?= ($coluna['Null'] ?? 'NO') === 'YES' ? 'checked' : '' ?>>
                                <label for="nulo" class="form-check-label">Permite NULL</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check mt-4">
                                <input type="checkbox" class="form-check-input" name="auto_increment" id="auto_increment"
                                       <?= str_contains($coluna['Extra'] ?? '', 'auto_increment') ? 'checked' : '' ?>>
                                <label for="auto_increment" class="form-check-label">Auto Increment</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Valor padrão</label>
                            <input type="text" name="padrao" class="form-control font-monospace"
                                   placeholder="ex: 0, '', CURRENT_TIMESTAMP"
                                   value="<?= htmlspecialchars((string)($coluna['Default'] ?? '')) ?>">
                        </div>

                        <?php if (!$editando): ?>
                            <div class="col-md-6">
                                <label class="form-label">Posição (opcional)</label>
                                <select name="apos" class="form-select">
                                    <option value="">No final da tabela</option>
                                    <?php foreach ($colunasExistentes as $c): ?>
                                        <option value="<?= htmlspecialchars($c['Field']) ?>">Depois de <?= htmlspecialchars($c['Field']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="<?= url('/banco-dados/console/estrutura?conexao=' . $conexao['id'] . '&banco=' . urlencode($banco) . '&tabela=' . urlencode($tabela)) ?>" class="btn btn-outline-secondary">
                            Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Salvar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = ($editando ? 'Editar' : 'Nova') . ' Coluna - ' . $tabela;

require __DIR__ . '/../layouts/main.php';
