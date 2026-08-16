<?php

use App\Components\Alert;
use App\Services\ModuloCatalogo;

ob_start();

$editando = $usuario !== null;
$acao = $editando ? url('/administracao/usuarios/editar') : url('/administracao/usuarios/novo');
$perfilAtual = $usuario['perfil'] ?? 'ti';
?>

<style>
.uf-hero {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    border-radius: 16px;
    padding: 28px 32px;
    color: #fff;
    margin-bottom: 22px;
    display: flex;
    align-items: center;
    gap: 18px;
}
.uf-hero .uf-icone {
    width: 56px; height: 56px; border-radius: 14px;
    background: rgba(255,255,255,.12);
    display: flex; align-items: center; justify-content: center;
    font-size: 26px; flex-shrink: 0;
}
.uf-hero small { color: #94a3b8; }
.uf-card { border: 0; border-radius: 14px; box-shadow: 0 4px 14px rgba(0,0,0,.06); }
.uf-card .card-header { background: #f8fafc; border-bottom: 1px solid #eef1f5; border-radius: 14px 14px 0 0; padding: 14px 20px; font-weight: 600; }
.uf-grupo-card { border: 1px solid #eef1f5; border-radius: 12px; padding: 14px 16px; height: 100%; background: #fff; transition: border-color .15s ease; }
.uf-grupo-card:has(input:checked) { border-color: #93c5fd; background: #f8fbff; }
.uf-grupo-titulo { display: flex; align-items: center; gap: 8px; font-weight: 600; font-size: 14px; margin-bottom: 10px; }
.uf-grupo-titulo .bi { color: #2563eb; font-size: 16px; }
.uf-grupo-marcar-todos { font-size: 11px; color: #6b7280; cursor: pointer; text-decoration: underline; margin-left: auto; font-weight: 400; }
.uf-perfil-opcao { cursor: pointer; }
.uf-perfil-opcao input { display: none; }
.uf-perfil-opcao .uf-perfil-caixa {
    border: 2px solid #e5e7eb; border-radius: 12px; padding: 14px; text-align: center; transition: all .15s ease;
}
.uf-perfil-opcao input:checked + .uf-perfil-caixa {
    border-color: #2563eb; background: #eff6ff; box-shadow: 0 0 0 3px rgba(37,99,235,.12);
}
.uf-perfil-opcao .bi { font-size: 22px; display: block; margin-bottom: 4px; color: #64748b; }
.uf-perfil-opcao input:checked + .uf-perfil-caixa .bi { color: #2563eb; }
</style>

<?= Alert::flash() ?>

<div class="uf-hero">
    <div class="uf-icone"><i class="bi bi-person-gear"></i></div>
    <div>
        <h4 class="mb-0"><?= $editando ? 'Editar usuário' : 'Novo usuário' ?></h4>
        <small><?= $editando ? 'Ajuste os dados e os módulos liberados para ' . htmlspecialchars($usuario['nome']) : 'Cadastre um novo acesso ao sistema e escolha o que ele pode ver' ?></small>
    </div>
</div>

<form method="post" action="<?= $acao ?>">
    <?php if ($editando): ?>
        <input type="hidden" name="id" value="<?= (int)$usuario['id'] ?>">
    <?php endif; ?>

    <div class="card uf-card mb-3">
        <div class="card-header"><i class="bi bi-person-vcard me-1"></i> Dados básicos</div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label small text-muted">Nome</label>
                    <input type="text" name="nome" class="form-control" required
                           value="<?= htmlspecialchars($usuario['nome'] ?? '') ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label small text-muted">Login</label>
                    <input type="text" name="login" class="form-control" required
                           <?= $editando ? 'value="' . htmlspecialchars($usuario['login']) . '" disabled' : '' ?>>
                </div>

                <?php if (!$editando): ?>
                    <div class="col-md-3">
                        <label class="form-label small text-muted">Senha</label>
                        <input type="password" name="senha" class="form-control" minlength="8" required>
                    </div>
                <?php endif; ?>
            </div>

            <label class="form-label small text-muted d-block">Perfil</label>
            <div class="row g-2">
                <?php foreach (['admin' => ['Administrador', 'bi-star-fill'], 'ti' => ['TI', 'bi-tools'], 'consulta' => ['Consulta', 'bi-eye']] as $valor => [$rotulo, $icone]): ?>
                    <div class="col-4">
                        <label class="uf-perfil-opcao d-block">
                            <input type="radio" name="perfil" id="perfil_<?= $valor ?>" value="<?= $valor ?>" <?= $perfilAtual === $valor ? 'checked' : '' ?>>
                            <div class="uf-perfil-caixa">
                                <i class="bi <?= $icone ?>"></i>
                                <?= $rotulo ?>
                            </div>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div id="blocoModulos">
        <div class="card uf-card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-grid-3x3-gap-fill me-1"></i> Módulos com acesso liberado</span>
                <small class="text-muted fw-normal">Só vale para TI e Consulta -- administrador já tem acesso total</small>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php foreach ($modulosAgrupados as $grupo => $itens): ?>
                        <div class="col-md-4">
                            <div class="uf-grupo-card">
                                <div class="uf-grupo-titulo">
                                    <i class="bi <?= ModuloCatalogo::iconeDoGrupo($grupo) ?>"></i>
                                    <?= htmlspecialchars($grupo) ?>
                                    <span class="uf-grupo-marcar-todos" data-grupo="grupo-<?= md5($grupo) ?>">marcar todos</span>
                                </div>
                                <?php foreach ($itens as $chave => $label): ?>
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input grupo-<?= md5($grupo) ?>" name="modulos[]"
                                               id="modulo_<?= $chave ?>" value="<?= $chave ?>"
                                               <?= in_array($chave, $modulosSelecionados, true) ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="modulo_<?= $chave ?>">
                                            <?= htmlspecialchars($label) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between">
        <a href="<?= url('/administracao/usuarios') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
        <button type="submit" class="btn btn-primary px-4">
            <i class="bi bi-check-lg"></i> Salvar
        </button>
    </div>
</form>

<script>
function atualizarBlocoModulos() {
    const perfil = document.querySelector('input[name="perfil"]:checked')?.value;
    document.getElementById('blocoModulos').style.display = perfil === 'admin' ? 'none' : '';
}

document.querySelectorAll('input[name="perfil"]').forEach(function (radio) {
    radio.addEventListener('change', atualizarBlocoModulos);
});
atualizarBlocoModulos();

document.querySelectorAll('.uf-grupo-marcar-todos').forEach(function (link) {
    link.addEventListener('click', function () {
        const caixas = document.querySelectorAll('.' + this.dataset.grupo);
        const marcarTudo = ![...caixas].every(c => c.checked);
        caixas.forEach(c => c.checked = marcarTudo);
        this.textContent = marcarTudo ? 'desmarcar todos' : 'marcar todos';
    });
});
</script>

<?php
$conteudo = ob_get_clean();
$titulo = $editando ? 'Editar Usuário' : 'Novo Usuário';

require __DIR__ . '/../layouts/main.php';
