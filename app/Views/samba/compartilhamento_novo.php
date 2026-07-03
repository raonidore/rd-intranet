<?php
ob_start();
?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0">
            <i class="bi bi-folder-plus"></i> Novo Compartilhamento
        </h5>
        <small class="text-muted">Crie uma nova pasta compartilhada no Samba.</small>
    </div>

    <div class="card-body">
        <form method="post" action="<?= url('/samba/compartilhamentos/novo') ?>">

            <div class="mb-3">
                <label class="form-label">Nome do compartilhamento</label>
                <input type="text" name="nome" class="form-control" placeholder="Ex: RH" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Descrição</label>
                <input type="text" name="descricao" class="form-control" placeholder="Ex: Compartilhamento do setor de RH">
            </div>

            <div class="mb-3">
                <label class="form-label">Grupo Linux</label>
                <input type="text" name="grupo" class="form-control" placeholder="Ex: rh" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Caminho</label>
                <input type="text" name="caminho" class="form-control"
                       value="/srv/samba/Compartilhamentos/" required>
                <small class="text-muted">Use sempre /srv/samba/Compartilhamentos/NOME</small>
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
                <i class="bi bi-save"></i> Criar Compartilhamento
            </button>

            <a href="<?= url('/samba/compartilhamentos') ?>" class="btn btn-secondary">
                Voltar
            </a>
        </form>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Novo Compartilhamento';

require __DIR__ . '/../layouts/main.php';
