<?php
ob_start();

use App\Components\Alert;
?>

<?= Alert::flash() ?>

<div class="mb-4">
    <a href="<?= url('/projetos') ?>" class="text-decoration-none small text-muted">
        <i class="bi bi-arrow-left"></i> Voltar
    </a>
    <h4 class="mb-0 mt-1">Novo projeto</h4>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="post" action="<?= url('/projetos/novo') ?>">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Título *</label>
                    <input type="text" name="titulo" class="form-control" required maxlength="200"
                           placeholder="Ex: Assessoria de TI -- Cliente X">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Área *</label>
                    <select name="area_id" class="form-select" required>
                        <option value="">-- Selecione --</option>
                        <?php foreach ($areas as $a): ?>
                            <option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars($a['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Cliente <span class="text-muted fw-normal">(opcional)</span></label>
                    <input type="text" name="cliente" class="form-control" maxlength="150" placeholder="Se for um projeto interno, deixe em branco">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Prioridade</label>
                    <select name="prioridade" class="form-select">
                        <option value="baixa">Baixa</option>
                        <option value="media" selected>Média</option>
                        <option value="alta">Alta</option>
                        <option value="urgente">Urgente</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Início previsto</label>
                    <input type="date" name="data_inicio" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Fim previsto</label>
                    <input type="date" name="data_fim_prevista" class="form-control">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="usa_fases" id="usaFases" value="1" checked>
                        <label class="form-check-label" for="usaFases">Organizar em fases</label>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label">Descrição</label>
                    <textarea name="descricao" class="form-control" rows="4" placeholder="Contexto, escopo..."></textarea>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Criar projeto</button>
                <a href="<?= url('/projetos') ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Novo Projeto';

require __DIR__ . '/../layouts/main.php';
