<?php
ob_start();

use App\Components\Alert;
?>

<?= Alert::flash() ?>

<div class="mb-4 d-flex justify-content-between align-items-start">
    <div>
        <a href="<?= url('/documentos/categoria?id=' . (int)$documento['categoria_id']) ?>" class="text-decoration-none small text-muted d-block mb-1">
            <i class="bi bi-arrow-left"></i> <?= htmlspecialchars($documento['categoria_nome']) ?>
        </a>
        <h4 class="mb-1"><i class="bi bi-file-earmark me-1"></i> <?= htmlspecialchars($documento['titulo']) ?></h4>
        <span class="badge text-bg-light border">v<?= (int)$documento['versao'] ?></span>
    </div>
    <?php if ($permissao['editar'] || $permissao['excluir']): ?>
    <div class="d-flex gap-2">
        <?php if ($permissao['editar']): ?>
            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalEditarDocumento">
                <i class="bi bi-pencil"></i> Editar
            </button>
        <?php endif; ?>
        <?php if ($permissao['excluir']): ?>
            <form method="post" action="<?= url('/documentos/excluir') ?>" onsubmit="return confirm('Excluir este documento?');">
                <input type="hidden" name="id" value="<?= (int)$documento['id'] ?>">
                <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <?php if (!empty($documento['descricao'])): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h6 class="card-title mb-2">Descrição</h6>
                <p class="mb-0" style="white-space: pre-wrap;"><?= htmlspecialchars($documento['descricao']) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h6 class="card-title mb-3">Anexo</h6>
                <?php if (!empty($documento['anexo_origem'])): ?>
                    <a href="<?= url('/documentos/anexo?id=' . (int)$documento['id']) ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-<?= $documento['anexo_origem'] === 'samba' ? 'folder-symlink' : 'paperclip' ?>"></i>
                        <?= htmlspecialchars($documento['anexo_nome_original']) ?>
                        <?= $documento['anexo_origem'] === 'samba' ? '<span class="badge text-bg-light border ms-1">Samba</span>' : '' ?>
                    </a>
                <?php else: ?>
                    <span class="text-muted small">Nenhum anexo.</span>
                <?php endif; ?>

                <?php if ($permissao['editar']): ?>
                    <div class="mt-2 d-flex gap-2">
                        <button type="button" class="btn btn-link btn-sm p-0" onclick="document.getElementById('uploadAnexoDoc').click()">
                            Enviar nova versão (upload)
                        </button>
                        <span class="text-muted">&middot;</span>
                        <button type="button" class="btn btn-link btn-sm p-0" onclick="abrirSeletorSamba()">
                            Escolher do Samba
                        </button>
                    </div>
                    <form id="formUploadDoc" method="post" action="<?= url('/documentos/anexo-upload') ?>" enctype="multipart/form-data" class="d-none">
                        <input type="hidden" name="id" value="<?= (int)$documento['id'] ?>">
                        <input type="file" id="uploadAnexoDoc" name="arquivo" onchange="document.getElementById('formUploadDoc').submit()">
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($versoes)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="card-title mb-3">Histórico de versões</h6>
                <ul class="list-group list-group-flush">
                    <?php foreach ($versoes as $v): ?>
                        <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                            <span>
                                <span class="badge text-bg-light border me-2">v<?= (int)$v['versao'] ?></span>
                                <?= htmlspecialchars($v['anexo_nome_original'] ?? 'sem anexo') ?>
                            </span>
                            <span class="text-muted small">
                                <?= htmlspecialchars($v['substituido_por_nome'] ?? 'sistema') ?> &middot;
                                <?= date('d/m/Y H:i', strtotime($v['criado_em'])) ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($permissao['editar']): ?>
<!-- Modal editar documento -->
<div class="modal fade" id="modalEditarDocumento" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="<?= url('/documentos/editar') ?>">
                <input type="hidden" name="id" value="<?= (int)$documento['id'] ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Editar documento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Título *</label>
                        <input type="text" name="titulo" class="form-control" required maxlength="200"
                               value="<?= htmlspecialchars($documento['titulo']) ?>">
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Descrição</label>
                        <textarea name="descricao" class="form-control" rows="3"><?= htmlspecialchars($documento['descricao'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal seletor de arquivo do Samba -->
<div class="modal fade" id="modalSeletorSamba" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" action="<?= url('/documentos/anexo-samba') ?>" id="formAnexoSamba">
                <input type="hidden" name="id" value="<?= (int)$documento['id'] ?>">
                <input type="hidden" name="compartilhamento_id" id="sambaCompartilhamentoId">
                <input type="hidden" name="subcaminho" id="sambaSubcaminho">
                <input type="hidden" name="nome_arquivo" id="sambaNomeArquivo">
                <div class="modal-header">
                    <h5 class="modal-title">Escolher arquivo do Samba</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <nav aria-label="breadcrumb" class="mb-2">
                        <ol class="breadcrumb mb-0 small" id="sambaBreadcrumb"></ol>
                    </nav>
                    <div id="sambaListaItens" class="list-group" style="max-height: 350px; overflow-y: auto;">
                        <div class="text-center text-muted py-4" id="sambaCarregando">
                            <div class="spinner-border spinner-border-sm"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="sambaBtnAnexar" disabled>Anexar arquivo selecionado</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let sambaEstado = { compartilhamentoId: null, compartilhamentoNome: null, subcaminho: '', arquivoSelecionado: null };

function abrirSeletorSamba() {
    document.getElementById('sambaBtnAnexar').disabled = true;
    sambaEstado = { compartilhamentoId: null, compartilhamentoNome: null, subcaminho: '', arquivoSelecionado: null };

    new bootstrap.Modal(document.getElementById('modalSeletorSamba')).show();
    carregarCompartilhamentos();
}

function carregarCompartilhamentos() {
    const lista = document.getElementById('sambaListaItens');
    lista.innerHTML = '<div class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm"></div></div>';
    document.getElementById('sambaBreadcrumb').innerHTML = '';

    fetch('<?= url('/documentos/samba-compartilhamentos') ?>')
        .then(r => r.json())
        .then(dados => {
            if (!dados.success || !dados.compartilhamentos.length) {
                lista.innerHTML = '<div class="text-center text-muted py-4">Nenhum compartilhamento disponível pra você. Peça acesso ao administrador.</div>';
                return;
            }
            lista.innerHTML = '';
            dados.compartilhamentos.forEach(c => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'list-group-item list-group-item-action';
                item.innerHTML = '<i class="bi bi-hdd-network me-2"></i>' + escapeHtml(c.nome);
                item.onclick = () => {
                    sambaEstado.compartilhamentoId = c.id;
                    sambaEstado.compartilhamentoNome = c.nome;
                    sambaEstado.subcaminho = '';
                    navegarSamba();
                };
                lista.appendChild(item);
            });
        });
}

function navegarSamba() {
    const lista = document.getElementById('sambaListaItens');
    lista.innerHTML = '<div class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm"></div></div>';
    document.getElementById('sambaBtnAnexar').disabled = true;
    sambaEstado.arquivoSelecionado = null;
    atualizarBreadcrumb();

    const params = new URLSearchParams({
        compartilhamento_id: sambaEstado.compartilhamentoId,
        subcaminho: sambaEstado.subcaminho,
    });

    fetch('<?= url('/documentos/samba-listar') ?>?' + params.toString())
        .then(r => r.json())
        .then(dados => {
            if (!dados.success) {
                lista.innerHTML = '<div class="text-center text-muted py-4">' + escapeHtml(dados.message || 'Erro ao listar.') + '</div>';
                return;
            }
            lista.innerHTML = '';
            if (!dados.itens.length) {
                lista.innerHTML = '<div class="text-center text-muted py-4">Pasta vazia.</div>';
            }
            dados.itens.forEach(item => {
                const el = document.createElement('button');
                el.type = 'button';
                el.className = 'list-group-item list-group-item-action d-flex align-items-center';
                const isDir = item.type === 'dir' || item.type === 'directory' || item.type === 'folder';
                el.innerHTML = '<i class="bi bi-' + (isDir ? 'folder-fill text-warning' : 'file-earmark') + ' me-2"></i>' + escapeHtml(item.name);
                el.onclick = () => {
                    if (isDir) {
                        sambaEstado.subcaminho = sambaEstado.subcaminho ? sambaEstado.subcaminho + '/' + item.name : item.name;
                        navegarSamba();
                    } else {
                        document.querySelectorAll('#sambaListaItens .list-group-item').forEach(n => n.classList.remove('active'));
                        el.classList.add('active');
                        sambaEstado.arquivoSelecionado = item.name;
                        document.getElementById('sambaBtnAnexar').disabled = false;
                    }
                };
                lista.appendChild(el);
            });
        });
}

function atualizarBreadcrumb() {
    const bc = document.getElementById('sambaBreadcrumb');
    bc.innerHTML = '';

    const raiz = document.createElement('li');
    raiz.className = 'breadcrumb-item';
    const raizLink = document.createElement('a');
    raizLink.href = '#';
    raizLink.textContent = sambaEstado.compartilhamentoNome;
    raizLink.onclick = (e) => { e.preventDefault(); sambaEstado.subcaminho = ''; navegarSamba(); };
    raiz.appendChild(raizLink);
    bc.appendChild(raiz);

    if (!sambaEstado.subcaminho) return;

    const partes = sambaEstado.subcaminho.split('/');
    let acumulado = '';
    partes.forEach((parte, i) => {
        acumulado = acumulado ? acumulado + '/' + parte : parte;
        const caminhoAteAqui = acumulado;
        const li = document.createElement('li');
        li.className = 'breadcrumb-item';
        if (i === partes.length - 1) {
            li.classList.add('active');
            li.textContent = parte;
        } else {
            const a = document.createElement('a');
            a.href = '#';
            a.textContent = parte;
            a.onclick = (e) => { e.preventDefault(); sambaEstado.subcaminho = caminhoAteAqui; navegarSamba(); };
            li.appendChild(a);
        }
        bc.appendChild(li);
    });
}

document.getElementById('formAnexoSamba').addEventListener('submit', function () {
    document.getElementById('sambaCompartilhamentoId').value = sambaEstado.compartilhamentoId;
    document.getElementById('sambaSubcaminho').value = sambaEstado.subcaminho;
    document.getElementById('sambaNomeArquivo').value = sambaEstado.arquivoSelecionado;
});

function escapeHtml(texto) {
    const div = document.createElement('div');
    div.textContent = texto;
    return div.innerHTML;
}
</script>
<?php endif; ?>

<?php
$conteudo = ob_get_clean();
$titulo = $documento['titulo'];

require __DIR__ . '/../layouts/main.php';
