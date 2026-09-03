<?php
ob_start();

use App\Components\Alert;
use App\Services\ChamadoExternoService;

$statusClasses = [
    'aberto' => 'text-bg-primary',
    'aguardando_fornecedor' => 'text-bg-warning',
    'em_andamento' => 'text-bg-info',
    'resolvido' => 'text-bg-success',
    'fechado' => 'text-bg-secondary',
];
?>

<?= Alert::flash() ?>

<div class="mb-4 d-flex justify-content-between align-items-start">
    <div>
        <a href="<?= url('/chamados-externos') ?>" class="text-decoration-none small text-muted d-block mb-1">
            <i class="bi bi-arrow-left"></i> Chamados Externos
        </a>
        <h4 class="mb-1">
            <span class="font-monospace text-muted"><?= htmlspecialchars('#' . ($chamado['numero_controle'] ?? $chamado['id'])) ?></span>
            <?= htmlspecialchars($chamado['titulo']) ?>
        </h4>
        <span class="badge <?= $statusClasses[$chamado['status']] ?? '' ?>"><?= ChamadoExternoService::statusLabel($chamado['status']) ?></span>
        <span class="badge text-bg-light border"><?= ucfirst($chamado['prioridade']) ?></span>
        <?php if (!empty($chamado['ativo_id'])): ?>
            <a href="<?= url('/ativos/ver?id=' . (int)$chamado['ativo_id']) ?>" class="badge text-bg-light border text-decoration-none">
                <i class="bi bi-cpu"></i> <?= htmlspecialchars($chamado['ativo_patrimonio'] ?? $chamado['ativo_nome']) ?>
            </a>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalEditar">
            <i class="bi bi-pencil"></i> Editar
        </button>
        <form method="post" action="<?= url('/chamados-externos/excluir') ?>" onsubmit="return confirm('Excluir este chamado externo?');">
            <input type="hidden" name="id" value="<?= (int)$chamado['id'] ?>">
            <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
        </form>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-4"><span class="text-muted small">Fornecedor</span><br><?= htmlspecialchars($chamado['fornecedor_nome']) ?></div>
                    <div class="col-md-4"><span class="text-muted small">Categoria</span><br><?= htmlspecialchars($chamado['categoria_nome'] ?? '-') ?></div>
                    <div class="col-md-4"><span class="text-muted small">Protocolo do fornecedor</span><br><?= htmlspecialchars($chamado['protocolo_fornecedor'] ?? '-') ?></div>
                </div>
                <?php if (!empty($chamado['descricao'])): ?>
                    <p class="mb-0" style="white-space: pre-wrap;"><?= htmlspecialchars($chamado['descricao']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h6 class="card-title mb-3"><i class="bi bi-clock-history"></i> Timeline</h6>
                <ul class="list-unstyled mb-3">
                    <?php foreach ($timeline as $t): ?>
                        <li class="mb-3 pb-3 border-bottom">
                            <?php if ($t['tipo'] === 'sistema'): ?>
                                <div class="small text-muted">
                                    <i class="bi bi-gear"></i> <?= htmlspecialchars($t['conteudo']) ?>
                                    &middot; <?= htmlspecialchars($t['usuario_nome'] ?? 'sistema') ?> &middot; <?= date('d/m/Y H:i', strtotime($t['criado_em'])) ?>
                                </div>
                            <?php else: ?>
                                <div>
                                    <strong><?= htmlspecialchars($t['usuario_nome'] ?? 'Usuário') ?></strong>
                                    <span class="text-muted small">&middot; <?= date('d/m/Y H:i', strtotime($t['criado_em'])) ?></span>
                                    <p class="mb-0 mt-1" style="white-space: pre-wrap;"><?= htmlspecialchars($t['conteudo']) ?></p>
                                </div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <form method="post" action="<?= url('/chamados-externos/comentar') ?>">
                    <input type="hidden" name="id" value="<?= (int)$chamado['id'] ?>">
                    <div class="input-group">
                        <textarea name="conteudo" class="form-control" rows="2" placeholder="Adicionar nota..." required></textarea>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i></button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="card-title mb-3"><i class="bi bi-paperclip"></i> Anexos</h6>

                <?php if (empty($anexos)): ?>
                    <p class="text-muted small mb-3">Nenhum anexo ainda.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush mb-3">
                        <?php foreach ($anexos as $anexo): ?>
                            <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                <a href="<?= url('/chamados-externos/anexo?anexo_id=' . (int)$anexo['id']) ?>" target="_blank" class="text-decoration-none">
                                    <i class="bi bi-<?= $anexo['anexo_origem'] === 'samba' ? 'folder-symlink' : 'paperclip' ?>"></i>
                                    <?= htmlspecialchars($anexo['anexo_nome_original']) ?>
                                    <?= $anexo['anexo_origem'] === 'samba' ? '<span class="badge text-bg-light border ms-1">Samba</span>' : '' ?>
                                </a>
                                <form method="post" action="<?= url('/chamados-externos/anexo-excluir') ?>" onsubmit="return confirm('Remover este anexo?');">
                                    <input type="hidden" name="id" value="<?= (int)$chamado['id'] ?>">
                                    <input type="hidden" name="anexo_id" value="<?= (int)$anexo['id'] ?>">
                                    <button type="submit" class="btn btn-link btn-sm text-danger p-0"><i class="bi bi-trash"></i></button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-link btn-sm p-0" onclick="document.getElementById('uploadAnexoChExt').click()">
                        Enviar arquivo
                    </button>
                    <span class="text-muted">&middot;</span>
                    <button type="button" class="btn btn-link btn-sm p-0" onclick="abrirSeletorSamba()">
                        Escolher do Samba
                    </button>
                </div>
                <form id="formUploadChExt" method="post" action="<?= url('/chamados-externos/anexo-upload') ?>" enctype="multipart/form-data" class="d-none">
                    <input type="hidden" name="id" value="<?= (int)$chamado['id'] ?>">
                    <input type="file" id="uploadAnexoChExt" name="arquivo" onchange="document.getElementById('formUploadChExt').submit()">
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h6 class="card-title mb-3">Status</h6>
                <form method="post" action="<?= url('/chamados-externos/status') ?>">
                    <input type="hidden" name="id" value="<?= (int)$chamado['id'] ?>">
                    <select name="status" class="form-select mb-2" onchange="this.form.submit()">
                        <?php foreach (ChamadoExternoService::statusLabelTodos() as $valor => $label): ?>
                            <option value="<?= $valor ?>" <?= $chamado['status'] === $valor ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <ul class="list-unstyled small text-muted mb-0">
                    <li>Aberto em: <?= date('d/m/Y H:i', strtotime($chamado['aberto_em'])) ?></li>
                    <?php if (!empty($chamado['resolvido_em'])): ?><li>Resolvido em: <?= date('d/m/Y H:i', strtotime($chamado['resolvido_em'])) ?></li><?php endif; ?>
                    <?php if (!empty($chamado['fechado_em'])): ?><li>Fechado em: <?= date('d/m/Y H:i', strtotime($chamado['fechado_em'])) ?></li><?php endif; ?>
                </ul>
            </div>
        </div>

        <?php if (!empty($chamado['fornecedor_canal_abertura']) || !empty($chamado['fornecedor_telefone']) || !empty($chamado['fornecedor_email'])): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="card-title mb-2"><i class="bi bi-headset"></i> Contato do fornecedor</h6>
                <?php if (!empty($chamado['fornecedor_telefone'])): ?><p class="small mb-1"><i class="bi bi-telephone text-muted"></i> <?= htmlspecialchars($chamado['fornecedor_telefone']) ?></p><?php endif; ?>
                <?php if (!empty($chamado['fornecedor_email'])): ?><p class="small mb-1"><i class="bi bi-envelope text-muted"></i> <?= htmlspecialchars($chamado['fornecedor_email']) ?></p><?php endif; ?>
                <?php if (!empty($chamado['fornecedor_canal_abertura'])): ?><p class="small text-muted mb-0" style="white-space: pre-wrap;"><?= htmlspecialchars($chamado['fornecedor_canal_abertura']) ?></p><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal editar -->
<div class="modal fade" id="modalEditar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="<?= url('/chamados-externos/editar') ?>">
                <input type="hidden" name="id" value="<?= (int)$chamado['id'] ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Editar chamado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Título *</label>
                            <input type="text" name="titulo" class="form-control" required maxlength="200" value="<?= htmlspecialchars($chamado['titulo']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Categoria</label>
                            <select name="categoria_id" class="form-select">
                                <option value="">-- Selecione --</option>
                                <?php foreach ($categorias as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>" <?= (int)($chamado['categoria_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Prioridade</label>
                            <select name="prioridade" class="form-select">
                                <?php foreach (['baixa' => 'Baixa', 'media' => 'Média', 'alta' => 'Alta', 'urgente' => 'Urgente'] as $valor => $label): ?>
                                    <option value="<?= $valor ?>" <?= $chamado['prioridade'] === $valor ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Protocolo do fornecedor</label>
                            <input type="text" name="protocolo_fornecedor" class="form-control" maxlength="100" value="<?= htmlspecialchars($chamado['protocolo_fornecedor'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descrição</label>
                            <textarea name="descricao" class="form-control" rows="4"><?= htmlspecialchars($chamado['descricao'] ?? '') ?></textarea>
                        </div>
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
            <form method="post" action="<?= url('/chamados-externos/anexo-samba') ?>" id="formAnexoSamba">
                <input type="hidden" name="id" value="<?= (int)$chamado['id'] ?>">
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
                        <div class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm"></div></div>
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

    fetch('<?= url('/chamados-externos/samba-compartilhamentos') ?>')
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

    fetch('<?= url('/chamados-externos/samba-listar') ?>?' + params.toString())
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

<?php
$conteudo = ob_get_clean();
$titulo = $chamado['titulo'];

require __DIR__ . '/../layouts/main.php';
