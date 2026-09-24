<?php

use App\Components\Alert;

ob_start();

$editando = $segredo !== null;
$acao = $editando ? url('/seguranca/cofre-senhas/editar') : url('/seguranca/cofre-senhas/novo');
?>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0">
            <i class="bi bi-key-fill"></i>
            <?= $editando ? 'Editar segredo' : 'Novo segredo' ?>
        </h5>
    </div>

    <div class="card-body">
        <form method="post" action="<?= $acao ?>">
            <?php if ($editando): ?>
                <input type="hidden" name="id" value="<?= (int)$segredo['id'] ?>">
            <?php endif; ?>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Nome</label>
                    <input type="text" name="nome" class="form-control" required
                           placeholder="Ex: Wifi do escritório, Painel do fornecedor X"
                           value="<?= htmlspecialchars($segredo['nome'] ?? '') ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Categoria</label>
                    <input type="text" name="categoria" class="form-control" list="listaCategorias"
                           placeholder="Ex: Wifi, Site, Cliente"
                           value="<?= htmlspecialchars($segredo['categoria'] ?? 'Geral') ?>">
                    <datalist id="listaCategorias">
                        <option value="Geral">
                        <option value="Wifi">
                        <option value="Site">
                        <option value="Servidor">
                        <option value="Cliente">
                    </datalist>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Login/usuário (opcional)</label>
                    <input type="text" name="usuario_login" class="form-control"
                           value="<?= htmlspecialchars($segredo['usuario_login'] ?? '') ?>">
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">URL/host (opcional)</label>
                    <input type="text" name="url_host" class="form-control"
                           placeholder="Ex: https://painel.fornecedor.com"
                           value="<?= htmlspecialchars($segredo['url_host'] ?? '') ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Observações (opcional)</label>
                    <input type="text" name="observacoes" class="form-control"
                           value="<?= htmlspecialchars($segredo['observacoes'] ?? '') ?>">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Senha<?= $editando ? ' (deixe em branco para manter a atual)' : '' ?></label>
                <input type="password" name="senha" class="form-control" autocomplete="new-password"
                       <?= $editando ? '' : 'required' ?>>
            </div>

            <div class="mb-3">
                <label class="form-label">Onde salvar</label>
                <?php if ($editando): ?>
                    <input type="text" class="form-control" disabled
                           value="<?= $cofreAtual ? htmlspecialchars($cofreAtual['nome']) : 'Pessoal (só eu)' ?>">
                    <small class="text-muted d-block">
                        Não é possível mover um segredo de cofre depois de criado -- exclua e recrie no cofre certo, se precisar.
                    </small>
                <?php else: ?>
                    <select name="cofre_id" class="form-select">
                        <option value="">Pessoal (só eu)</option>
                        <?php foreach ($cofresDisponiveis as $cofre): ?>
                            <option value="<?= (int)$cofre['id'] ?>"><?= htmlspecialchars($cofre['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted d-block">
                        Salvando num cofre de equipe, todo mundo com permissão nesse cofre vai poder ver este segredo.
                    </small>
                <?php endif; ?>
            </div>

            <div class="d-flex justify-content-between mt-3">
                <a href="<?= url('/seguranca/cofre-senhas') ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Voltar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> Salvar
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = $editando ? 'Editar Segredo' : 'Novo Segredo';

require __DIR__ . '/../layouts/main.php';
