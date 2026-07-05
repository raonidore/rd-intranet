<?php

use App\Components\Alert;

ob_start();

$editando = $usuario !== null;
$acao = $editando ? url('/administracao/usuarios/editar') : url('/administracao/usuarios/novo');
?>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0">
            <i class="bi bi-person-gear"></i>
            <?= $editando ? 'Editar usuário' : 'Novo usuário' ?>
        </h5>
    </div>

    <div class="card-body">
        <form method="post" action="<?= $acao ?>">
            <?php if ($editando): ?>
                <input type="hidden" name="id" value="<?= (int)$usuario['id'] ?>">
            <?php endif; ?>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Nome</label>
                    <input type="text" name="nome" class="form-control" required
                           value="<?= htmlspecialchars($usuario['nome'] ?? '') ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Login</label>
                    <input type="text" name="login" class="form-control" required
                           <?= $editando ? 'value="' . htmlspecialchars($usuario['login']) . '" disabled' : '' ?>>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Perfil</label>
                    <select name="perfil" id="perfil" class="form-select">
                        <?php foreach (['admin' => 'Administrador', 'ti' => 'TI', 'consulta' => 'Consulta'] as $valor => $rotulo): ?>
                            <option value="<?= $valor ?>" <?= ($usuario['perfil'] ?? 'ti') === $valor ? 'selected' : '' ?>>
                                <?= $rotulo ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php if (!$editando): ?>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Senha</label>
                        <input type="password" name="senha" class="form-control" minlength="8" required>
                        <small class="text-muted">Mínimo de 8 caracteres.</small>
                    </div>
                </div>
            <?php endif; ?>

            <div id="blocoModulos">
                <hr>
                <h6 class="mb-1">Módulos com acesso liberado</h6>
                <small class="text-muted d-block mb-3">
                    Vale apenas para perfis TI e Consulta. Administradores têm acesso total a todos os módulos automaticamente.
                </small>

                <div class="row">
                    <?php foreach ($modulosAgrupados as $grupo => $itens): ?>
                        <div class="col-md-4 mb-3">
                            <strong class="d-block mb-2"><?= htmlspecialchars($grupo) ?></strong>
                            <?php foreach ($itens as $chave => $label): ?>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="modulos[]"
                                           id="modulo_<?= $chave ?>" value="<?= $chave ?>"
                                           <?= in_array($chave, $modulosSelecionados, true) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="modulo_<?= $chave ?>">
                                        <?= htmlspecialchars($label) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="d-flex justify-content-between mt-3">
                <a href="<?= url('/administracao/usuarios') ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Voltar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> Salvar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function atualizarBlocoModulos() {
    const perfil = document.getElementById('perfil').value;
    document.getElementById('blocoModulos').style.display = perfil === 'admin' ? 'none' : '';
}

document.getElementById('perfil').addEventListener('change', atualizarBlocoModulos);
atualizarBlocoModulos();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = $editando ? 'Editar Usuário' : 'Novo Usuário';

require __DIR__ . '/../layouts/main.php';
