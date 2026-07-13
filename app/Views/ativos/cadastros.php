<?php
ob_start();

use App\Components\Alert;
?>

<?= Alert::flash() ?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-tags me-1"></i> Ativos - Cadastros</h4>
    <small class="text-muted">
        <a href="<?= url('/ativos') ?>"><i class="bi bi-arrow-left"></i> Dashboard</a> ·
        Setor e Localização são escolhidos de uma lista (não texto livre) pra evitar erro ortográfico que atrapalhe relatórios depois.
    </small>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><strong>Setores</strong></div>
            <div class="card-body">
                <form method="post" action="<?= url('/ativos/cadastros/novo') ?>" class="d-flex gap-2 mb-3">
                    <input type="hidden" name="tipo" value="setor">
                    <input type="text" name="nome" class="form-control form-control-sm" placeholder="Ex: Financeiro" required>
                    <button class="btn btn-sm btn-primary text-nowrap"><i class="bi bi-plus-lg"></i> Adicionar</button>
                </form>

                <?php if (empty($setores)): ?>
                    <p class="text-muted small mb-0">Nenhum setor cadastrado ainda.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($setores as $s): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <?= htmlspecialchars($s['nome']) ?>
                                <form method="post" action="<?= url('/ativos/cadastros/excluir') ?>"
                                      onsubmit="return confirm('Excluir o setor &quot;<?= htmlspecialchars(addslashes($s['nome'])) ?>&quot;?');">
                                    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><strong>Localizações</strong></div>
            <div class="card-body">
                <form method="post" action="<?= url('/ativos/cadastros/novo') ?>" class="d-flex gap-2 mb-3">
                    <input type="hidden" name="tipo" value="localizacao">
                    <input type="text" name="nome" class="form-control form-control-sm" placeholder="Ex: Sala 2 - 2º andar" required>
                    <button class="btn btn-sm btn-primary text-nowrap"><i class="bi bi-plus-lg"></i> Adicionar</button>
                </form>

                <?php if (empty($localizacoes)): ?>
                    <p class="text-muted small mb-0">Nenhuma localização cadastrada ainda.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($localizacoes as $l): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <?= htmlspecialchars($l['nome']) ?>
                                <form method="post" action="<?= url('/ativos/cadastros/excluir') ?>"
                                      onsubmit="return confirm('Excluir a localização &quot;<?= htmlspecialchars(addslashes($l['nome'])) ?>&quot;?');">
                                    <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Ativos - Cadastros';

require __DIR__ . '/../layouts/main.php';
