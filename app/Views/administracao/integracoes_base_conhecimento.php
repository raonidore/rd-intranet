<?php

use App\Components\Alert;

ob_start();
?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-journal-text me-1"></i> Base de Conhecimento -- Base central</h4>
    <small class="text-muted">
        <a href="<?= url('/administracao/integracoes') ?>"><i class="bi bi-arrow-left"></i> Integrações</a>
    </small>
</div>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm" style="max-width:640px">
    <div class="card-body">
        <p class="text-muted small mb-3">
            Onde os artigos marcados como "públicos" (em <a href="<?= url('/base-conhecimento') ?>">Base de Conhecimento</a>)
            são moderados e distribuídos entre todas as instalações do RD Intranet. Só administradores veem esta tela --
            usuários com acesso à Base de Conhecimento não precisam (nem devem) ver a chave de API daqui.
        </p>
        <form method="post" action="<?= url('/administracao/integracoes/base-conhecimento') ?>" class="row g-2 align-items-end">
            <div class="col-12">
                <label class="form-label small mb-1">URL da API central</label>
                <input type="text" name="url" class="form-control form-control-sm" value="<?= htmlspecialchars($urlCentral) ?>" placeholder="https://intranet.rd.inf.br">
            </div>
            <div class="col-12">
                <label class="form-label small mb-1">API key</label>
                <input type="text" name="api_key" class="form-control form-control-sm" value="<?= htmlspecialchars($apiKeyCentral) ?>">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-sm btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Integrações - Base de Conhecimento';

require __DIR__ . '/../layouts/main.php';
