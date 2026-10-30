<?php ob_start(); ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-shield-lock"></i> Segurança do Compartilhamento</h5>
        <small class="text-muted"><?= htmlspecialchars($compartilhamento['nome']) ?></small>
    </div>

    <div class="card-body">
        <form method="post" action="<?= url('/samba/compartilhamentos/seguranca') ?>">
            <input type="hidden" name="id" value="<?= htmlspecialchars($compartilhamento['id']) ?>">

            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="somente_leitura" <?= (int)$compartilhamento['somente_leitura'] ? 'checked' : '' ?>>
                <label class="form-check-label">Somente leitura</label>
            </div>

            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="lixeira" <?= (int)$compartilhamento['lixeira'] ? 'checked' : '' ?>>
                <label class="form-check-label">Habilitar lixeira</label>
            </div>

            <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" name="bloqueio_extensoes" <?= (int)$compartilhamento['bloqueio_extensoes'] ? 'checked' : '' ?>>
                <label class="form-check-label">Bloquear extensões perigosas</label>
            </div>

            <button class="btn btn-primary"><i class="bi bi-save"></i> Salvar Segurança</button>
            <a href="<?= url('/samba/compartilhamentos') ?>" class="btn btn-secondary">Voltar</a>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white">
        <h6 class="mb-0"><i class="bi bi-folder-check text-success"></i> Exceções por pasta</h6>
        <small class="text-muted">
            Libera extensões bloqueadas numa pasta específica. O Samba não aceita bloqueio diferente por subpasta,
            então a pasta é publicada como um compartilhamento próprio (mesmas permissões deste) — acesse ou mapeie
            a unidade por <code>\\servidor\nome-da-exceção</code>. Pelo caminho normal os arquivos continuam bloqueados.
        </small>
    </div>

    <div class="card-body">
        <?php if (!empty($excecoes)): ?>
        <table class="table table-sm align-middle mb-4">
            <thead>
                <tr><th>Pasta</th><th>Acesso por</th><th>Extensões liberadas</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($excecoes as $e): ?>
                <tr>
                    <td><i class="bi bi-folder2 text-warning"></i> <?= htmlspecialchars($e['pasta']) ?></td>
                    <td><code>\\servidor\<?= htmlspecialchars($e['nome_compartilhamento']) ?></code></td>
                    <td>
                        <?php foreach (explode(',', $e['extensoes']) as $ext): ?>
                            <span class="badge bg-success">.<?= htmlspecialchars($ext) ?></span>
                        <?php endforeach; ?>
                    </td>
                    <td class="text-end">
                        <form method="post" action="<?= url('/samba/compartilhamentos/excecoes/excluir') ?>"
                              onsubmit="return confirm('Remover a exceção da pasta <?= htmlspecialchars(addslashes($e['pasta']), ENT_QUOTES) ?>?')">
                            <input type="hidden" name="id" value="<?= (int)$compartilhamento['id'] ?>">
                            <input type="hidden" name="excecao_id" value="<?= (int)$e['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" title="Remover exceção"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <?php if (!(int)$compartilhamento['bloqueio_extensoes']): ?>
            <div class="alert alert-info py-2 small">
                O bloqueio de extensões está desligado neste compartilhamento — nada está sendo bloqueado, então exceções não são necessárias.
            </div>
        <?php endif; ?>

        <form method="post" action="<?= url('/samba/compartilhamentos/excecoes/criar') ?>">
            <input type="hidden" name="id" value="<?= (int)$compartilhamento['id'] ?>">

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Pasta</label>
                    <div class="input-group">
                        <span class="input-group-text"><?= htmlspecialchars(rtrim($compartilhamento['caminho'], '/')) ?>/</span>
                        <input type="text" name="pasta" id="excecao-pasta" class="form-control" placeholder="ex: arbackup" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Nome do compartilhamento da exceção</label>
                    <input type="text" name="nome_compartilhamento" id="excecao-nome" class="form-control"
                           placeholder="<?= htmlspecialchars($compartilhamento['nome']) ?>-pasta" maxlength="80" pattern="[A-Za-z0-9_\-]+">
                    <div class="form-text">Em branco = <?= htmlspecialchars($compartilhamento['nome']) ?>-nome-da-pasta.</div>
                </div>
            </div>

            <label class="form-label mt-3">Extensões liberadas nesta pasta</label>
            <div class="d-flex flex-wrap gap-2 p-2 border rounded">
                <?php foreach ($extensoesPerigosas as $ext): ?>
                <div class="form-check form-check-inline m-0">
                    <input class="form-check-input" type="checkbox" name="extensoes[]" value="<?= htmlspecialchars($ext) ?>" id="ext-<?= htmlspecialchars($ext) ?>">
                    <label class="form-check-label" for="ext-<?= htmlspecialchars($ext) ?>">.<?= htmlspecialchars($ext) ?></label>
                </div>
                <?php endforeach; ?>
            </div>

            <button class="btn btn-success mt-3"><i class="bi bi-plus-circle"></i> Criar exceção</button>
            <small class="text-muted ms-2">Depois, clique em "Aplicar Configuração" no Deploy Center.</small>
        </form>
    </div>
</div>

<script>
(function () {
    const pasta = document.getElementById('excecao-pasta');
    const nome = document.getElementById('excecao-nome');
    const base = <?= json_encode($compartilhamento['nome']) ?>;
    pasta.addEventListener('input', function () {
        const ultima = pasta.value.replace(/\\/g, '/').replace(/\/+$/, '').split('/').pop().replace(/[^A-Za-z0-9_-]/g, '');
        nome.placeholder = base + '-' + (ultima || 'pasta');
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Segurança do Compartilhamento';
require __DIR__ . '/../layouts/main.php';
