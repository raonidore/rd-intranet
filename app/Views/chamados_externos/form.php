<?php
ob_start();

use App\Components\Alert;
?>

<?= Alert::flash() ?>

<div class="mb-4">
    <a href="<?= url('/chamados-externos') ?>" class="text-decoration-none small text-muted">
        <i class="bi bi-arrow-left"></i> Voltar
    </a>
    <h4 class="mb-0 mt-1">Novo chamado externo</h4>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="post" action="<?= url('/chamados-externos/novo') ?>">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Título *</label>
                    <input type="text" name="titulo" class="form-control" required maxlength="200"
                           placeholder="Ex: Instabilidade no link de internet">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Fornecedor *</label>
                    <select name="fornecedor_id" class="form-select" required>
                        <option value="">-- Selecione --</option>
                        <?php foreach ($fornecedores as $f): ?>
                            <option value="<?= (int)$f['id'] ?>"><?= htmlspecialchars($f['nome_fantasia']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Não achou? <a href="<?= url('/fornecedores/novo') ?>" target="_blank">Cadastre um novo fornecedor</a>.</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Categoria</label>
                    <select name="categoria_id" class="form-select">
                        <option value="">-- Selecione --</option>
                        <?php foreach ($categorias as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
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
                    <label class="form-label">Protocolo do fornecedor</label>
                    <input type="text" name="protocolo_fornecedor" class="form-control" maxlength="100"
                           placeholder="Se já tiver um número de protocolo">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Ativo relacionado (opcional)</label>
                    <input type="text" id="buscaAtivo" class="form-control" autocomplete="off"
                           placeholder="Buscar por patrimônio ou nome..."
                           value="<?= $ativoPreSelecionado ? htmlspecialchars($ativoPreSelecionado['codigo_patrimonio'] . ' - ' . $ativoPreSelecionado['nome']) : '' ?>">
                    <input type="hidden" name="ativo_id" id="ativoId" value="<?= $ativoPreSelecionado ? (int)$ativoPreSelecionado['id'] : '' ?>">
                    <div id="resultadosAtivo" class="list-group position-absolute" style="z-index:10; max-width: 350px;"></div>
                </div>

                <div class="col-12">
                    <label class="form-label">Descrição</label>
                    <textarea name="descricao" class="form-control" rows="4" placeholder="O que está acontecendo..."></textarea>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Abrir chamado</button>
                <a href="<?= url('/chamados-externos') ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const campoBusca = document.getElementById('buscaAtivo');
    const campoId = document.getElementById('ativoId');
    const resultados = document.getElementById('resultadosAtivo');
    let timeoutBusca = null;

    campoBusca.addEventListener('input', function () {
        campoId.value = '';
        clearTimeout(timeoutBusca);
        const termo = campoBusca.value.trim();

        if (termo.length < 2) {
            resultados.innerHTML = '';
            return;
        }

        timeoutBusca = setTimeout(async () => {
            const res = await fetch(<?= json_encode(url('/chamados-externos/ativos-api')) ?> + '?termo=' + encodeURIComponent(termo));
            const dados = await res.json();
            resultados.innerHTML = '';

            if (!dados.success || !dados.ativos.length) return;

            dados.ativos.forEach(a => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'list-group-item list-group-item-action';
                item.textContent = a.label;
                item.onclick = () => {
                    campoBusca.value = a.label;
                    campoId.value = a.id;
                    resultados.innerHTML = '';
                };
                resultados.appendChild(item);
            });
        }, 300);
    });

    document.addEventListener('click', function (e) {
        if (!resultados.contains(e.target) && e.target !== campoBusca) {
            resultados.innerHTML = '';
        }
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Novo Chamado Externo';

require __DIR__ . '/../layouts/main.php';
