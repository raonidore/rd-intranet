<?php
ob_start();

use App\Components\Alert;
?>

<?= Alert::flash() ?>

<div class="mb-4 d-flex justify-content-between align-items-start">
    <div>
        <a href="<?= url('/projetos') ?>" class="text-decoration-none small text-muted d-block mb-1">
            <i class="bi bi-arrow-left"></i> Projetos
        </a>
        <h4 class="mb-1"><i class="bi bi-diagram-3 me-1"></i> Áreas</h4>
        <small class="text-muted">Departamento (TI, Comercial, Financeiro...) -- cadastro novo é só isso, uma linha aqui.</small>
    </div>
    <button type="button" class="btn btn-primary text-nowrap" data-bs-toggle="modal" data-bs-target="#modalNovaArea">
        <i class="bi bi-plus-lg"></i> Nova área
    </button>
</div>

<?php foreach ($areas as $area): ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div class="d-flex align-items-center gap-2">
                    <input type="text" class="form-control form-control-sm campo-nome-area" value="<?= htmlspecialchars($area['nome']) ?>" style="width:220px">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input campo-ativo-area" type="checkbox" <?= $area['ativo'] ? 'checked' : '' ?>>
                        <label class="form-check-label small text-muted">Ativa</label>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm btn-salvar-area" data-id="<?= (int)$area['id'] ?>">
                        <i class="bi bi-save"></i>
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm btn-excluir-area" data-id="<?= (int)$area['id'] ?>">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-md-6">
                    <h6 class="small text-uppercase text-muted mb-2">Gestores da área</h6>
                    <ul class="list-group list-group-flush mb-2">
                        <?php if (empty($area['gestores'])): ?>
                            <li class="list-group-item px-0 text-muted small">Nenhum gestor -- só admin do módulo gerencia essa área por enquanto.</li>
                        <?php endif; ?>
                        <?php foreach ($area['gestores'] as $gestor): ?>
                            <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                <span><?= htmlspecialchars($gestor['nome']) ?> <span class="text-muted small">(<?= htmlspecialchars($gestor['email'] ?? '') ?>)</span></span>
                                <form method="post" action="<?= url('/projetos/areas/gestor-remover') ?>" onsubmit="return confirm('Remover esse gestor?');">
                                    <input type="hidden" name="gestor_id" value="<?= (int)$gestor['id'] ?>">
                                    <button type="submit" class="btn btn-link btn-sm text-danger p-0"><i class="bi bi-x-lg"></i></button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="position-relative">
                        <input type="text" class="form-control form-control-sm campo-buscar-gestor" autocomplete="off" placeholder="Buscar usuário pra adicionar como gestor..." data-area-id="<?= (int)$area['id'] ?>">
                        <div class="list-group position-absolute w-100 shadow-sm d-none lista-gestores-sugeridos" style="z-index:10; max-height:220px; overflow-y:auto"></div>
                    </div>
                </div>

                <div class="col-md-6">
                    <h6 class="small text-uppercase text-muted mb-2">Modo TV -- links de exibição</h6>
                    <ul class="list-group list-group-flush mb-2">
                        <?php if (empty($area['paineis_tv'])): ?>
                            <li class="list-group-item px-0 text-muted small">Nenhum link gerado ainda.</li>
                        <?php endif; ?>
                        <?php foreach ($area['paineis_tv'] as $painel): ?>
                            <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                <span class="small text-muted">Gerado em <?= date('d/m/Y H:i', strtotime($painel['criado_em'])) ?></span>
                                <form method="post" action="<?= url('/projetos/tv/revogar') ?>" onsubmit="return confirm('Revogar esse link? A TV que estiver usando ele para de funcionar.');">
                                    <input type="hidden" name="id" value="<?= (int)$painel['id'] ?>">
                                    <button type="submit" class="btn btn-link btn-sm text-danger p-0">Revogar</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <form method="post" action="<?= url('/projetos/tv/gerar-link') ?>">
                        <input type="hidden" name="area_id" value="<?= (int)$area['id'] ?>">
                        <button type="submit" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-tv"></i> Gerar novo link de exibição
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<!-- Modal nova área -->
<div class="modal fade" id="modalNovaArea" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="<?= url('/projetos/areas/criar') ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Nova área</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Nome *</label>
                    <input type="text" name="nome" class="form-control" required maxlength="100" placeholder="Ex: Comercial, Financeiro, Contábil...">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Cadastrar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.btn-salvar-area').forEach(function (botao) {
    botao.addEventListener('click', async function () {
        const linha = botao.closest('.card-body');
        const nome = linha.querySelector('.campo-nome-area').value;
        const ativo = linha.querySelector('.campo-ativo-area').checked ? '1' : '';

        await fetch(<?= json_encode(url('/projetos/areas/atualizar')) ?>, {
            method: 'POST',
            body: new URLSearchParams({ id: botao.dataset.id, nome: nome, ativo: ativo }),
        });
        location.reload();
    });
});

document.querySelectorAll('.btn-excluir-area').forEach(function (botao) {
    botao.addEventListener('click', async function () {
        if (!confirm('Excluir esta área?')) return;

        await fetch(<?= json_encode(url('/projetos/areas/excluir')) ?>, {
            method: 'POST',
            body: new URLSearchParams({ id: botao.dataset.id }),
        });
        location.reload();
    });
});

document.querySelectorAll('.campo-buscar-gestor').forEach(function (campo) {
    const lista = campo.closest('.position-relative').querySelector('.lista-gestores-sugeridos');
    let timer = null;

    campo.addEventListener('input', function () {
        clearTimeout(timer);
        const termo = campo.value.trim();
        if (termo.length < 2) {
            lista.classList.add('d-none');
            return;
        }
        timer = setTimeout(async () => {
            const res = await fetch(<?= json_encode(url('/projetos/areas/usuarios-buscar')) ?> + '?q=' + encodeURIComponent(termo));
            const dados = await res.json();
            lista.innerHTML = '';
            if (!dados.success || !dados.usuarios.length) { lista.classList.add('d-none'); return; }

            dados.usuarios.forEach(u => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'list-group-item list-group-item-action';
                item.textContent = u.nome + (u.email ? ' (' + u.email + ')' : '');
                item.onclick = async () => {
                    await fetch(<?= json_encode(url('/projetos/areas/gestor-adicionar')) ?>, {
                        method: 'POST',
                        body: new URLSearchParams({ area_id: campo.dataset.areaId, usuario_id: u.id }),
                    });
                    location.reload();
                };
                lista.appendChild(item);
            });
            lista.classList.remove('d-none');
        }, 300);
    });

    document.addEventListener('click', function (e) {
        if (!lista.contains(e.target) && e.target !== campo) {
            lista.classList.add('d-none');
        }
    });
});
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Áreas de Projetos';

require __DIR__ . '/../layouts/main.php';
