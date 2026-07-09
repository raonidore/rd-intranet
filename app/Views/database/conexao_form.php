<?php

use App\Components\Alert;

ob_start();

$editando = $conexao !== null;
$acao = $editando ? url('/banco-dados/conexoes/editar') : url('/banco-dados/conexoes/novo');
?>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0">
            <i class="bi bi-database-gear"></i>
            <?= $editando ? 'Editar conexão' : 'Nova conexão' ?>
        </h5>
    </div>

    <div class="card-body">
        <form method="post" action="<?= $acao ?>">
            <?php if ($editando): ?>
                <input type="hidden" name="id" value="<?= (int)$conexao['id'] ?>">
            <?php endif; ?>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Nome</label>
                    <input type="text" name="nome" class="form-control" required
                           placeholder="Ex: Cliente XPTO - Produção"
                           value="<?= htmlspecialchars($conexao['nome'] ?? '') ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Host</label>
                    <input type="text" name="host" class="form-control" required
                           placeholder="Ex: 192.168.1.10 ou db.cliente.com"
                           value="<?= htmlspecialchars($conexao['host'] ?? '') ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label">Porta</label>
                    <input type="number" name="porta" class="form-control" min="1" max="65535"
                           value="<?= (int)($conexao['porta'] ?? 3306) ?>">
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label">Usuário</label>
                    <input type="text" name="usuario" class="form-control" required
                           value="<?= htmlspecialchars($conexao['usuario'] ?? '') ?>">
                </div>

                <?php if (!$editando): ?>
                    <div class="col-md-4">
                        <label class="form-label">Senha</label>
                        <input type="password" name="senha" class="form-control" required>
                    </div>
                <?php endif; ?>

                <div class="col-md-4">
                    <label class="form-label">Banco padrão (opcional)</label>
                    <input type="text" name="banco_padrao" class="form-control"
                           placeholder="Ex: producao"
                           value="<?= htmlspecialchars($conexao['banco_padrao'] ?? '') ?>">
                </div>
            </div>

            <?php if ($editando): ?>
                <small class="text-muted d-block mb-3">
                    Pra trocar a senha desta conexão, use "Redefinir credencial" na listagem.
                </small>
            <?php endif; ?>

            <div class="d-flex justify-content-between mt-3">
                <a href="<?= url('/banco-dados/conexoes') ?>" class="btn btn-outline-secondary">
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
$titulo = $editando ? 'Editar Conexão' : 'Nova Conexão';

require __DIR__ . '/../layouts/main.php';
