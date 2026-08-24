<?php
ob_start();

use App\Components\Alert;
use App\Services\ChamadoService;
?>

<?= Alert::flash() ?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-plus-circle me-1"></i> Abrir Chamado</h4>
    <small class="text-muted"><a href="<?= url('/chamados/atendimentos') ?>"><i class="bi bi-arrow-left"></i> Voltar</a></small>
</div>

<form method="post" action="<?= url('/chamados/atendimentos/novo') ?>">
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white"><strong>O chamado</strong></div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-8">
                    <label class="form-label">Título</label>
                    <input type="text" name="titulo" class="form-control" required maxlength="200" placeholder="Ex: Impressora não imprime -- 2º andar financeiro">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Prioridade</label>
                    <select name="prioridade" class="form-select">
                        <?php foreach (ChamadoService::PRIORIDADES as $chave => $label): ?>
                            <option value="<?= $chave ?>" <?= $chave === 'media' ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label">Categoria</label>
                    <select name="categoria_id" class="form-select" required>
                        <option value="">— Selecione —</option>
                        <?php foreach ($categorias as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Setor responsável <span class="text-muted">(opcional)</span></label>
                    <select name="setor_id" class="form-select">
                        <option value="">— Usar o padrão da categoria —</option>
                        <?php foreach ($setores as $s): ?>
                            <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Unidade</label>
                    <select name="unidade_id" class="form-select" required>
                        <?php foreach ($unidades as $u): ?>
                            <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mb-0">
                <label class="form-label">Descrição</label>
                <textarea name="descricao" class="form-control" rows="4" required placeholder="Descreva o problema com o máximo de detalhe possível."></textarea>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white"><strong>Solicitante</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Nome</label>
                    <input type="text" name="solicitante_nome" class="form-control" required maxlength="150">
                </div>
                <div class="col-md-4">
                    <label class="form-label">E-mail</label>
                    <input type="email" name="solicitante_email" class="form-control" maxlength="150">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Telefone</label>
                    <input type="text" name="solicitante_telefone" class="form-control" maxlength="30" placeholder="(83) 99104-3598">
                </div>
            </div>
            <div class="form-text mt-2">Informe pelo menos um contato (e-mail ou telefone) -- é por ele que o solicitante recebe as atualizações do chamado.</div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> Abrir chamado</button>
    <a href="<?= url('/chamados/atendimentos') ?>" class="btn btn-secondary">Cancelar</a>
</form>

<?php
$conteudo = ob_get_clean();
$titulo = 'Abrir Chamado';

require __DIR__ . '/../layouts/main.php';
