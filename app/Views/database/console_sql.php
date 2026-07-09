<?php

ob_start();

$tabela = '';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
<style>
.CodeMirror { height: auto; min-height: 160px; border: 1px solid #ced4da; border-radius: 6px; font-size: 14px; }
.CodeMirror-focused { border-color: #86b7fe; box-shadow: 0 0 0 .25rem rgba(13,110,253,.25); }
</style>

<div class="db-layout">
    <?php require __DIR__ . '/_console_sidebar.php'; ?>

    <div class="db-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1"><i class="bi bi-code-slash me-1"></i> Console SQL</h4>
                <small class="text-muted">
                    <?= htmlspecialchars($conexao['nome']) ?> — <?= htmlspecialchars($conexao['host']) ?><?= $banco ? ' / ' . htmlspecialchars($banco) : '' ?>
                </small>
            </div>
            <a href="<?= url('/banco-dados/console?conexao=' . $conexao['id']) ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Bancos
            </a>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <form method="post" action="<?= url('/banco-dados/console/sql') ?>" id="formSql">
                    <input type="hidden" name="conexao" value="<?= (int)$conexao['id'] ?>">

                    <div class="mb-3">
                        <label class="form-label">Banco (opcional se a instrução já qualificar o banco)</label>
                        <input type="text" name="banco" class="form-control" value="<?= htmlspecialchars($banco) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Comando SQL</label>
                        <textarea name="sql" id="campoSql" class="form-control font-monospace" rows="6"><?= htmlspecialchars($sql) ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-play-fill"></i> Executar
                    </button>
                </form>
            </div>
        </div>

        <div class="modal fade" id="modalConfirmar" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-warning">
                        <h5 class="modal-title"><i class="bi bi-exclamation-triangle"></i> Confirmar execução</h5>
                    </div>
                    <div class="modal-body">
                        Este comando pode alterar ou apagar dados e não pode ser desfeito. Deseja continuar?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-danger" id="botaoConfirmarExecucao">Sim, executar</button>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($resultado !== null): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span>Resultado</span>
                    <?= $resultado['success'] ? '<span class="badge text-bg-success">OK</span>' : '<span class="badge text-bg-danger">Erro</span>' ?>
                </div>
                <div class="card-body">
                    <?php if (!$resultado['success']): ?>
                        <div class="alert alert-danger mb-0"><?= htmlspecialchars($resultado['mensagem']) ?></div>
                    <?php elseif ($resultado['tipo'] === 'afetadas'): ?>
                        <div class="alert alert-success mb-0"><?= (int)$resultado['linhas_afetadas'] ?> linha(s) afetada(s).</div>
                    <?php else: ?>
                        <div class="mb-2 text-muted"><?= (int)$resultado['total'] ?> linha(s) retornada(s).</div>
                        <div style="overflow-x:auto">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <?php foreach ($resultado['colunas'] as $col): ?>
                                            <th><?= htmlspecialchars($col) ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($resultado['linhas'] as $linha): ?>
                                        <tr>
                                            <?php foreach ($linha as $valor): ?>
                                                <td><?= $valor === null ? '<span class="text-muted">NULL</span>' : htmlspecialchars((string)$valor) ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/sql/sql.min.js"></script>
<script>
const formSql = document.getElementById('formSql');
const campoSql = document.getElementById('campoSql');
const regexSeguro = /^\s*(SELECT|SHOW|DESCRIBE|DESC|EXPLAIN)\b/i;
let confirmado = false;

const editorSql = CodeMirror.fromTextArea(campoSql, {
    mode: 'text/x-mysql',
    lineNumbers: true,
    matchBrackets: true,
    indentUnit: 4,
});

function getModalConfirmar() {
    return bootstrap.Modal.getOrCreateInstance(document.getElementById('modalConfirmar'));
}

formSql.addEventListener('submit', function (evento) {
    editorSql.save();

    if (campoSql.value.trim() === '') {
        evento.preventDefault();
        editorSql.focus();
        return;
    }

    if (confirmado) {
        return;
    }

    if (!regexSeguro.test(campoSql.value)) {
        evento.preventDefault();
        getModalConfirmar().show();
    }
});

document.getElementById('botaoConfirmarExecucao').addEventListener('click', function () {
    confirmado = true;
    editorSql.save();
    getModalConfirmar().hide();
    formSql.submit();
});
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Console SQL';

require __DIR__ . '/../layouts/main.php';
