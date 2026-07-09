<?php

use App\Components\Alert;

ob_start();

$editando = $linha !== null;
$acao = $editando ? url('/banco-dados/console/dados/editar') : url('/banco-dados/console/dados/inserir');
?>

<div class="db-layout">
    <?php require __DIR__ . '/_console_sidebar.php'; ?>

    <div class="db-content">
        <?= Alert::flash() ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1"><i class="bi <?= $editando ? 'bi-pencil' : 'bi-plus-lg' ?> me-1"></i> <?= $editando ? 'Editar registro' : 'Inserir registro' ?></h4>
                <small class="text-muted"><?= htmlspecialchars($conexao['nome']) ?> — <?= htmlspecialchars($banco) ?>.<?= htmlspecialchars($tabela) ?></small>
            </div>
            <a href="<?= url('/banco-dados/console/dados?conexao=' . $conexao['id'] . '&banco=' . urlencode($banco) . '&tabela=' . urlencode($tabela)) ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form method="post" action="<?= $acao ?>">
                    <input type="hidden" name="conexao" value="<?= (int)$conexao['id'] ?>">
                    <input type="hidden" name="banco" value="<?= htmlspecialchars($banco) ?>">
                    <input type="hidden" name="tabela" value="<?= htmlspecialchars($tabela) ?>">
                    <?php if ($editando): ?>
                        <?php foreach ($pkAntigo as $col => $valor): ?>
                            <input type="hidden" name="pk_antigo[<?= htmlspecialchars($col) ?>]" value="<?= htmlspecialchars((string)$valor) ?>">
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <div class="row g-3">
                        <?php foreach ($colunas as $col): ?>
                            <?php
                                $nome = $col['Field'];
                                $tipo = $col['Type'];
                                $nulavel = ($col['Null'] ?? 'NO') === 'YES';
                                $autoIncrement = str_contains($col['Extra'] ?? '', 'auto_increment');
                                $valorAtual = $editando ? ($linha[$nome] ?? null) : null;
                                $ehNuloAtual = $editando && $valorAtual === null;
                                $multilinhas = (bool)preg_match('/text|blob|json/i', $tipo);
                            ?>
                            <div class="col-md-6">
                                <label class="form-label">
                                    <?= htmlspecialchars($nome) ?>
                                    <?php if ($col['Key'] === 'PRI'): ?><i class="bi bi-key-fill text-warning" title="Chave primária"></i><?php endif; ?>
                                    <code class="small text-muted"><?= htmlspecialchars($tipo) ?></code>
                                </label>

                                <?php if ($multilinhas): ?>
                                    <textarea name="campo[<?= htmlspecialchars($nome) ?>]" class="form-control font-monospace campo-valor" rows="3"
                                              data-nome="<?= htmlspecialchars($nome) ?>" <?= $ehNuloAtual ? 'disabled' : '' ?>><?= htmlspecialchars((string)$valorAtual) ?></textarea>
                                <?php else: ?>
                                    <input type="text" name="campo[<?= htmlspecialchars($nome) ?>]" class="form-control font-monospace campo-valor"
                                           data-nome="<?= htmlspecialchars($nome) ?>"
                                           placeholder="<?= $autoIncrement && !$editando ? '(gerado automaticamente)' : '' ?>"
                                           value="<?= htmlspecialchars((string)$valorAtual) ?>" <?= $ehNuloAtual ? 'disabled' : '' ?>>
                                <?php endif; ?>

                                <?php if ($nulavel): ?>
                                    <div class="form-check mt-1">
                                        <input type="checkbox" class="form-check-input campo-nulo" id="nulo_<?= htmlspecialchars($nome) ?>"
                                               name="nulo[<?= htmlspecialchars($nome) ?>]" data-alvo="<?= htmlspecialchars($nome) ?>"
                                               value="1" <?= $ehNuloAtual ? 'checked' : '' ?>>
                                        <label for="nulo_<?= htmlspecialchars($nome) ?>" class="form-check-label small text-muted">NULL</label>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($col['Default'])): ?>
                                    <small class="text-muted">Padrão: <?= htmlspecialchars((string)$col['Default']) ?></small>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="<?= url('/banco-dados/console/dados?conexao=' . $conexao['id'] . '&banco=' . urlencode($banco) . '&tabela=' . urlencode($tabela)) ?>" class="btn btn-outline-secondary">
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

<script>
document.querySelectorAll('.campo-nulo').forEach(function (checkbox) {
    checkbox.addEventListener('change', function () {
        const campo = document.querySelector('.campo-valor[data-nome="' + checkbox.dataset.alvo + '"]');
        if (campo) campo.disabled = checkbox.checked;
    });
});
</script>

<?php
$conteudo = ob_get_clean();
$titulo = ($editando ? 'Editar' : 'Inserir') . ' Registro - ' . $tabela;

require __DIR__ . '/../layouts/main.php';
