<?php
ob_start();
?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-folder-plus"></i> Novo Compartilhamento</h5>
        <small class="text-muted">Crie uma nova pasta compartilhada no Samba.</small>
    </div>

    <div class="card-body">
        <form method="post" action="<?= url('/samba/compartilhamentos/novo') ?>">

            <div class="mb-3">
                <label class="form-label">Nome do compartilhamento</label>
                <input type="text" id="nome" name="nome" class="form-control" placeholder="Ex: RH" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Descrição</label>
                <input type="text" name="descricao" class="form-control" placeholder="Ex: Compartilhamento do setor de RH">
            </div>

            <div class="mb-3">
                <label class="form-label">Grupo Linux</label>
                <input type="text" id="grupo" name="grupo" class="form-control" list="grupos-existentes" placeholder="Ex: rh" required>
                <datalist id="grupos-existentes">
                    <?php foreach ($grupos as $grupo): ?>
                        <option value="<?= htmlspecialchars($grupo) ?>">
                    <?php endforeach; ?>
                </datalist>
                <small class="text-muted">Se escolher um grupo já existente, o compartilhamento passa a ser acessado por quem já está nele. Se digitar um nome novo, o grupo é criado automaticamente.</small>
            </div>

            <div class="mb-3">
                <label class="form-label">Caminho</label>
                <input type="text" id="caminho" name="caminho" class="form-control" required>
                <small class="text-muted">Gerado automaticamente em /srv/samba/Compartilhamentos/NOME</small>
            </div>

            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="lixeira" checked>
                <label class="form-check-label">Habilitar lixeira</label>
            </div>

            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="bloqueio_extensoes" checked>
                <label class="form-check-label">Bloquear extensões perigosas</label>
            </div>

            <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" name="somente_leitura">
                <label class="form-check-label">Somente leitura</label>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-rocket"></i> Criar e aplicar no Samba
            </button>

            <a href="<?= url('/samba/compartilhamentos') ?>" class="btn btn-secondary">Voltar</a>
        </form>
    </div>
</div>

<script>
function normalizarGrupo(texto) {
    return texto
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '')
        .trim();
}

function normalizarPasta(texto) {
    return texto
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .replace(/[^A-Za-z0-9_-]+/g, '')
        .trim();
}

document.getElementById('nome').addEventListener('input', function () {
    const nome = this.value;
    const grupo = normalizarGrupo(nome);
    const pasta = normalizarPasta(nome);

    document.getElementById('grupo').value = grupo;
    document.getElementById('caminho').value = '/srv/samba/Compartilhamentos/' + pasta;
});
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Novo Compartilhamento';

require __DIR__ . '/../layouts/main.php';
