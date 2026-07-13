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
                            <li class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-center linha-view">
                                    <span><?= htmlspecialchars($s['nome']) ?></span>
                                    <div class="d-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-secondary botao-editar-cadastro"><i class="bi bi-pencil"></i></button>
                                        <form method="post" action="<?= url('/ativos/cadastros/excluir') ?>"
                                              onsubmit="return confirm('Excluir o setor &quot;<?= htmlspecialchars(addslashes($s['nome'])) ?>&quot;?');">
                                            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </div>
                                <form method="post" action="<?= url('/ativos/cadastros/editar') ?>" class="linha-edit d-none gap-2 mt-1">
                                    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                    <input type="text" name="nome" class="form-control form-control-sm" value="<?= htmlspecialchars($s['nome']) ?>" required>
                                    <button class="btn btn-sm btn-primary text-nowrap">Salvar</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary botao-cancelar-edicao">Cancelar</button>
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
                            <li class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-center linha-view">
                                    <span><?= htmlspecialchars($l['nome']) ?></span>
                                    <div class="d-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-secondary botao-editar-cadastro"><i class="bi bi-pencil"></i></button>
                                        <form method="post" action="<?= url('/ativos/cadastros/excluir') ?>"
                                              onsubmit="return confirm('Excluir a localização &quot;<?= htmlspecialchars(addslashes($l['nome'])) ?>&quot;?');">
                                            <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </div>
                                <form method="post" action="<?= url('/ativos/cadastros/editar') ?>" class="linha-edit d-none gap-2 mt-1">
                                    <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
                                    <input type="text" name="nome" class="form-control form-control-sm" value="<?= htmlspecialchars($l['nome']) ?>" required>
                                    <button class="btn btn-sm btn-primary text-nowrap">Salvar</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary botao-cancelar-edicao">Cancelar</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    document.querySelectorAll('.botao-editar-cadastro').forEach(function (botao) {
        botao.addEventListener('click', function () {
            const li = botao.closest('li');
            li.querySelector('.linha-view').classList.add('d-none');
            const edicao = li.querySelector('.linha-edit');
            edicao.classList.remove('d-none');
            edicao.classList.add('d-flex');
            edicao.querySelector('input[name="nome"]').focus();
        });
    });

    document.querySelectorAll('.botao-cancelar-edicao').forEach(function (botao) {
        botao.addEventListener('click', function () {
            const li = botao.closest('li');
            const edicao = li.querySelector('.linha-edit');
            edicao.classList.add('d-none');
            edicao.classList.remove('d-flex');
            li.querySelector('.linha-view').classList.remove('d-none');
        });
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Ativos - Cadastros';

require __DIR__ . '/../layouts/main.php';
