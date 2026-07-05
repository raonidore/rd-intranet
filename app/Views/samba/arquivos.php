<?php
ob_start();

function formatBytes(int $bytes): string {
    if ($bytes === 0) return '-';
    $u = ['B','KB','MB','GB'];
    $i = (int)floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 1) . ' ' . $u[$i];
}
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/monokai.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/xml/xml.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/javascript/javascript.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/css/css.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/clike/clike.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/php/php.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/htmlmixed/htmlmixed.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/python/python.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/sql/sql.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/shell/shell.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/markdown/markdown.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/properties/properties.min.js"></script>

<style>
.fm-card { border:0; border-radius:14px; box-shadow:0 4px 14px rgba(0,0,0,.06); }
.fm-breadcrumb { background:#f1f5f9; border-radius:8px; padding:8px 14px; }
.fm-breadcrumb .breadcrumb { margin:0; }
.fm-row:hover { background:#f8fafc; cursor:pointer; }
.fm-name { font-weight:500; }
.fm-actions .btn { font-size:11px; padding:2px 8px; }
.drop-zone { border:2px dashed #cbd5e1; border-radius:10px; padding:30px; text-align:center; transition:.2s; }
.drop-zone.drag-over { border-color:#2563eb; background:#eff6ff; }
.editor-textarea { font-family:monospace; font-size:13px; min-height:400px; resize:vertical; }
.toast-container { position:fixed; top:20px; right:20px; z-index:9999; }
</style>

<div class="toast-container">
    <div id="fm-toast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body" id="fm-toast-msg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<!-- Cabeçalho -->
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-1"><i class="bi bi-folder2-open me-2"></i>Arquivos dos Compartilhamentos</h4>
        <nav class="fm-breadcrumb mt-2">
            <ol class="breadcrumb">
                <?php foreach ($breadcrumb as $i => $crumb): ?>
                    <?php if ($i < count($breadcrumb) - 1): ?>
                        <li class="breadcrumb-item">
                            <a href="<?= url('/samba/arquivos?path=' . urlencode($crumb['path'])) ?>">
                                <?= htmlspecialchars($crumb['name']) ?>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="breadcrumb-item active"><?= htmlspecialchars($crumb['name']) ?></li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (!empty($pathAtual)): ?>
            <a href="<?= url('/samba/arquivos?path=' . urlencode(dirname($pathAtual) === '.' ? '' : dirname($pathAtual))) ?>"
               class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-up me-1"></i>Subir
            </a>
        <?php endif; ?>
        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalUpload">
            <i class="bi bi-upload me-1"></i>Upload
        </button>
        <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalNovaPasta">
            <i class="bi bi-folder-plus me-1"></i>Nova pasta
        </button>
        <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#modalNovoArquivo">
            <i class="bi bi-file-earmark-plus me-1"></i>Novo arquivo
        </button>
    </div>
</div>

<?php if (!empty($erro)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
<?php endif; ?>

<!-- Tabela de arquivos -->
<div class="fm-card card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="fm-table">
            <thead>
                <tr>
                    <th style="width:40%">Nome</th>
                    <th>Tamanho</th>
                    <th>Modificado em</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($arquivos)): ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted py-5">
                            <i class="bi bi-folder2 display-6 d-block mb-2"></i>
                            Pasta vazia
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($arquivos as $f): ?>
                <tr class="fm-row" data-type="<?= $f['type'] ?>" data-path="<?= htmlspecialchars($f['path']) ?>">
                    <td>
                        <i class="bi <?= $f['icon'] ?> me-2"></i>
                        <?php if ($f['type'] === 'dir'): ?>
                            <a href="<?= url('/samba/arquivos?path=' . urlencode($f['path'])) ?>"
                               class="fm-name text-decoration-none text-dark">
                                <?= htmlspecialchars($f['name']) ?>
                            </a>
                        <?php else: ?>
                            <span class="fm-name"><?= htmlspecialchars($f['name']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted" style="font-size:13px">
                        <?= htmlspecialchars(formatBytes($f['size'])) ?>
                    </td>
                    <td class="text-muted" style="font-size:13px"><?= htmlspecialchars($f['modified']) ?></td>
                    <td class="text-end fm-actions">
                        <?php if ($f['type'] === 'file'): ?>
                            <?php if ($f['ext'] === 'pdf'): ?>
                            <button class="btn btn-sm btn-outline-danger btn-view-pdf"
                                data-path="<?= htmlspecialchars($f['path']) ?>"
                                data-name="<?= htmlspecialchars($f['name']) ?>"
                                title="Visualizar PDF">
                                <i class="bi bi-eye"></i>
                            </button>
                            <?php endif; ?>
                            <a href="<?= url('/samba/arquivos/download?path=' . urlencode($f['path'])) ?>"
                               class="btn btn-sm btn-outline-primary" title="Download">
                                <i class="bi bi-download"></i>
                            </a>
                            <?php if ($f['viewable'] ?? false): ?>
                            <button class="btn btn-sm btn-outline-info btn-view-text"
                                data-path="<?= htmlspecialchars($f['path']) ?>"
                                data-name="<?= htmlspecialchars($f['name']) ?>"
                                data-ext="<?= htmlspecialchars($f['ext']) ?>"
                                title="Visualizar">
                                <i class="bi bi-eye"></i>
                            </button>
                            <?php endif; ?>
                            <?php if ($f['editable'] ?? false): ?>
                            <button class="btn btn-sm btn-outline-secondary btn-edit"
                                data-path="<?= htmlspecialchars($f['path']) ?>"
                                data-name="<?= htmlspecialchars($f['name']) ?>"
                                data-ext="<?= htmlspecialchars($f['ext']) ?>"
                                title="Editar">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php endif; ?>
                        <?php endif; ?>
                        <button class="btn btn-sm btn-outline-secondary btn-renomear"
                            data-path="<?= htmlspecialchars($f['path']) ?>"
                            data-name="<?= htmlspecialchars($f['name']) ?>"
                            title="Renomear">
                            <i class="bi bi-pencil-square"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary btn-copiar"
                            data-path="<?= htmlspecialchars($f['path']) ?>"
                            data-name="<?= htmlspecialchars($f['name']) ?>"
                            title="Copiar para...">
                            <i class="bi bi-copy"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary btn-mover"
                            data-path="<?= htmlspecialchars($f['path']) ?>"
                            data-name="<?= htmlspecialchars($f['name']) ?>"
                            title="Mover para...">
                            <i class="bi bi-arrows-move"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger btn-excluir"
                            data-path="<?= htmlspecialchars($f['path']) ?>"
                            data-name="<?= htmlspecialchars($f['name']) ?>"
                            data-type="<?= $f['type'] ?>"
                            title="Excluir">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Upload -->
<div class="modal fade" id="modalUpload" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-upload me-2"></i>Upload de arquivo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="drop-zone" id="drop-zone">
                    <i class="bi bi-cloud-upload display-5 text-muted d-block mb-2"></i>
                    <p class="text-muted mb-2">Arraste arquivos aqui ou</p>
                    <label class="btn btn-primary btn-sm">
                        Selecionar arquivo
                        <input type="file" id="upload-input" multiple hidden>
                    </label>
                </div>
                <div id="upload-progress" class="mt-3" style="display:none">
                    <div class="d-flex justify-content-between mb-1">
                        <small id="upload-filename"></small>
                        <small id="upload-pct">0%</small>
                    </div>
                    <div class="progress" style="height:6px">
                        <div class="progress-bar" id="upload-bar" style="width:0%"></div>
                    </div>
                </div>
                <div id="upload-result" class="mt-2"></div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nova Pasta -->
<div class="modal fade" id="modalNovaPasta" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-folder-plus me-2"></i>Nova pasta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="text" class="form-control" id="nova-pasta-nome" placeholder="Nome da pasta" autofocus>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success btn-sm" id="btn-criar-pasta">
                    <i class="bi bi-check me-1"></i>Criar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Novo Arquivo -->
<div class="modal fade" id="modalNovoArquivo" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" style="max-width:88vw">
        <div class="modal-content" style="height:85vh">
            <div class="modal-header py-2">
                <h5 class="modal-title"><i class="bi bi-file-earmark-plus me-2"></i>Novo arquivo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0 d-flex flex-column" style="overflow:hidden">
                <div class="p-2 border-bottom bg-light d-flex align-items-center gap-2">
                    <span class="text-muted" style="font-size:13px;white-space:nowrap">Nome do arquivo:</span>
                    <input type="text" class="form-control form-control-sm" id="novo-arquivo-nome"
                        placeholder="ex: relatorio.txt ou config.php" style="max-width:320px">
                    <small class="text-muted" id="novo-arquivo-ext-hint"></small>
                </div>
                <div style="flex:1;overflow:hidden">
                    <textarea id="novo-arquivo-content" style="display:none"></textarea>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-salvar-novo-arquivo">
                    <i class="bi bi-floppy me-1"></i>Criar e salvar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Novo Arquivo -->
<!-- Modal Editor -->
<div class="modal fade" id="modalEditor" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i><span id="editor-title"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" style="overflow:hidden">
                <textarea id="editor-content" style="display:none"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-salvar-editor">
                    <i class="bi bi-floppy me-1"></i>Salvar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Copiar / Mover -->
<div class="modal fade" id="modalCopiarMover" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cm-titulo"><i class="bi bi-copy me-2"></i>Copiar para...</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-2" style="font-size:13px">
                    <span id="cm-acao-label">Copiando</span>:
                    <strong id="cm-src-nome"></strong>
                </p>
                <nav aria-label="breadcrumb" class="mb-2">
                    <ol class="breadcrumb mb-0" id="cm-breadcrumb" style="font-size:13px"></ol>
                </nav>
                <div id="cm-folder-list" style="max-height:280px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:8px"></div>
                <div class="mt-2 p-2 bg-light rounded d-flex align-items-center gap-2" style="font-size:12px">
                    <i class="bi bi-folder-fill text-warning"></i>
                    Destino: <strong id="cm-dest-label">Compartilhamentos</strong>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-confirmar-cm">
                    <i class="bi bi-check me-1"></i><span id="cm-btn-label">Copiar aqui</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Renomear -->
<div class="modal fade" id="modalRenomear" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Renomear</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label text-muted" style="font-size:12px" id="renomear-label"></label>
                <input type="text" class="form-control" id="renomear-input" placeholder="Novo nome">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-confirmar-renomear">
                    <i class="bi bi-check me-1"></i>Renomear
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Visualizador Texto -->
<div class="modal fade" id="modalTexto" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" style="max-width:88vw">
        <div class="modal-content" style="height:85vh">
            <div class="modal-header py-2">
                <h6 class="modal-title mb-0">
                    <i class="bi bi-file-earmark-text me-2 text-secondary"></i>
                    <span id="texto-title"></span>
                </h6>
                <div class="d-flex gap-2 align-items-center ms-auto me-2">
                    <a id="texto-download-link" href="#" class="btn btn-sm btn-outline-primary" download>
                        <i class="bi bi-download me-1"></i>Download
                    </a>
                    <a id="texto-edit-link" href="#" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-pencil me-1"></i>Editar
                    </a>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" style="overflow:hidden">
                <div id="texto-loading" class="text-center text-muted py-5" style="display:none">
                    <div class="spinner-border spinner-border-sm me-2"></div>Carregando...
                </div>
                <div id="texto-cm-viewer"></div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Visualizador PDF -->
<div class="modal fade" id="modalPdf" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" style="max-width:90vw">
        <div class="modal-content" style="height:90vh">
            <div class="modal-header py-2">
                <h6 class="modal-title mb-0">
                    <i class="bi bi-file-earmark-pdf text-danger me-2"></i>
                    <span id="pdf-title"></span>
                </h6>
                <div class="d-flex gap-2 align-items-center ms-auto me-2">
                    <a id="pdf-download-link" href="#" class="btn btn-sm btn-outline-primary" download>
                        <i class="bi bi-download me-1"></i>Download
                    </a>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" style="flex:1;overflow:hidden">
                <iframe id="pdf-frame" src="" style="width:100%;height:100%;border:0;display:block"></iframe>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const PATH_ATUAL = '<?= addslashes($pathAtual) ?>';
    const URL_UPLOAD = '<?= url('/samba/arquivos/upload') ?>';
    const URL_EXCLUIR = '<?= url('/samba/arquivos/excluir') ?>';
    const URL_PASTA = '<?= url('/samba/arquivos/pasta') ?>';
    const URL_LER = '<?= url('/samba/arquivos/ler') ?>';
    const URL_SALVAR = '<?= url('/samba/arquivos/salvar') ?>';

    let editorPath = '';

    function showToast(msg, ok) {
        var el = document.getElementById('fm-toast');
        el.className = 'toast align-items-center text-white border-0 bg-' + (ok ? 'success' : 'danger');
        document.getElementById('fm-toast-msg').textContent = msg;
        bootstrap.Toast.getOrCreateInstance(el, {delay: 4000}).show();
    }

    // ── Upload ──────────────────────────────────────────────────────────
    async function uploadFile(file) {
        var progress = document.getElementById('upload-progress');
        var bar = document.getElementById('upload-bar');
        var pct = document.getElementById('upload-pct');
        var result = document.getElementById('upload-result');

        document.getElementById('upload-filename').textContent = file.name;
        progress.style.display = 'block';
        result.innerHTML = '';

        return new Promise(function(resolve) {
            var fd = new FormData();
            fd.append('arquivo', file);
            fd.append('path', PATH_ATUAL);
            var xhr = new XMLHttpRequest();
            xhr.open('POST', URL_UPLOAD);
            xhr.upload.onprogress = function(e) {
                if (e.lengthComputable) {
                    var p = Math.round(e.loaded / e.total * 100);
                    bar.style.width = p + '%';
                    pct.textContent = p + '%';
                }
            };
            xhr.onload = function() {
                try {
                    var data = JSON.parse(xhr.responseText);
                    if (data.success) {
                        result.innerHTML = '<div class="alert alert-success py-1 mb-0">' + data.message + '</div>';
                        showToast(data.message, true);
                        setTimeout(function() { location.reload(); }, 1200);
                    } else {
                        result.innerHTML = '<div class="alert alert-danger py-1 mb-0">' + data.message + '</div>';
                    }
                } catch(e) {
                    result.innerHTML = '<div class="alert alert-danger py-1 mb-0">Erro inesperado.</div>';
                }
                resolve();
            };
            xhr.onerror = function() {
                result.innerHTML = '<div class="alert alert-danger py-1 mb-0">Erro de rede.</div>';
                resolve();
            };
            xhr.send(fd);
        });
    }

    document.getElementById('upload-input').addEventListener('change', async function() {
        for (var f of this.files) await uploadFile(f);
    });

    var dropZone = document.getElementById('drop-zone');
    dropZone.addEventListener('dragover', function(e) { e.preventDefault(); dropZone.classList.add('drag-over'); });
    dropZone.addEventListener('dragleave', function() { dropZone.classList.remove('drag-over'); });
    dropZone.addEventListener('drop', async function(e) {
        e.preventDefault();
        dropZone.classList.remove('drag-over');
        for (var f of e.dataTransfer.files) await uploadFile(f);
    });

    // ── Excluir ─────────────────────────────────────────────────────────
    document.addEventListener('click', async function(e) {
        var btn = e.target.closest('.btn-excluir');
        if (!btn) return;
        var name = btn.dataset.name;
        var type = btn.dataset.type;
        var label = type === 'dir' ? 'pasta "' + name + '" e todo seu conteúdo' : 'arquivo "' + name + '"';
        if (!confirm('Confirma a exclusão de ' + label + '?')) return;
        try {
            var fd = new FormData(); fd.append('path', btn.dataset.path);
            var res = await fetch(URL_EXCLUIR, {method:'POST', body:fd});
            var data = await res.json();
            showToast(data.message, data.success);
            if (data.success) {
                btn.closest('tr').remove();
                if (!document.querySelector('#fm-table tbody tr')) {
                    document.querySelector('#fm-table tbody').innerHTML =
                        '<tr><td colspan="4" class="text-center text-muted py-5"><i class="bi bi-folder2 display-6 d-block mb-2"></i>Pasta vazia</td></tr>';
                }
            }
        } catch(e) { showToast('Erro ao comunicar com o servidor.', false); }
    });

    // ── Nova pasta ───────────────────────────────────────────────────────
    document.getElementById('btn-criar-pasta').addEventListener('click', async function() {
        var nome = document.getElementById('nova-pasta-nome').value.trim();
        if (!nome) return;
        try {
            var fd = new FormData(); fd.append('path', PATH_ATUAL); fd.append('nome', nome);
            var res = await fetch(URL_PASTA, {method:'POST', body:fd});
            var data = await res.json();
            showToast(data.message, data.success);
            if (data.success) setTimeout(function() { location.reload(); }, 800);
        } catch(e) { showToast('Erro ao criar pasta.', false); }
    });

    document.getElementById('nova-pasta-nome').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') document.getElementById('btn-criar-pasta').click();
    });

    // ── Novo arquivo ─────────────────────────────────────────────────────
    const URL_CRIAR = '<?= url('/samba/arquivos/criar') ?>';
    var novoCm = null;

    document.getElementById('modalNovoArquivo').addEventListener('shown.bs.modal', function() {
        if (!novoCm) {
            novoCm = CodeMirror.fromTextArea(document.getElementById('novo-arquivo-content'), {
                theme: 'monokai', lineNumbers: true, lineWrapping: true,
            });
            novoCm.setSize('100%', 'calc(85vh - 110px)');
        }
        novoCm.setOption('mode', 'text/plain');
        novoCm.refresh();
        novoCm.focus();
        document.getElementById('novo-arquivo-nome').focus();
    });

    document.getElementById('modalNovoArquivo').addEventListener('hidden.bs.modal', function() {
        document.getElementById('novo-arquivo-nome').value = '';
        document.getElementById('novo-arquivo-ext-hint').textContent = '';
        if (novoCm) novoCm.setValue('');
    });

    document.getElementById('novo-arquivo-nome').addEventListener('input', function() {
        var ext = this.value.split('.').pop().toLowerCase();
        var mode = getCmMode(ext);
        if (novoCm) novoCm.setOption('mode', mode);
        var hint = document.getElementById('novo-arquivo-ext-hint');
        hint.textContent = (mode !== 'text/plain' && ext !== this.value) ? '(' + mode + ')' : '';
    });

    document.getElementById('btn-salvar-novo-arquivo').addEventListener('click', async function() {
        var nome = document.getElementById('novo-arquivo-nome').value.trim();
        if (!nome) { showToast('Informe o nome do arquivo.', false); return; }
        var content = novoCm ? novoCm.getValue() : '';
        try {
            var fd = new FormData();
            fd.append('path', PATH_ATUAL);
            fd.append('nome', nome);
            fd.append('content', content);
            var res  = await fetch(URL_CRIAR, { method: 'POST', body: fd });
            var data = await res.json();
            showToast(data.message, data.success);
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('modalNovoArquivo')).hide();
                setTimeout(function() { location.reload(); }, 600);
            }
        } catch(e) { showToast('Erro ao criar arquivo.', false); }
    });

    const URL_RENOMEAR  = '<?= url('/samba/arquivos/renomear') ?>';

    let renomearPath = '';

    // ── Copiar / Mover ───────────────────────────────────────────────────
    const URL_LISTAR_DIRS = '<?= url('/samba/arquivos/listar-dirs') ?>';
    const URL_COPIAR      = '<?= url('/samba/arquivos/copiar') ?>';
    const URL_MOVER       = '<?= url('/samba/arquivos/mover') ?>';

    var cmAction  = '';   // 'copiar' | 'mover'
    var cmSrcPath = '';
    var cmPickerPath = '';

    function cmBreadcrumb(path) {
        var bc = document.getElementById('cm-breadcrumb');
        bc.innerHTML = '';
        var crumbs = [{ name: 'Compartilhamentos', path: '' }];
        if (path) {
            var acc = '';
            path.split('/').forEach(function(p) {
                if (!p) return;
                acc = acc ? acc + '/' + p : p;
                crumbs.push({ name: p, path: acc });
            });
        }
        crumbs.forEach(function(c, i) {
            var li = document.createElement('li');
            li.className = 'breadcrumb-item' + (i === crumbs.length - 1 ? ' active' : '');
            if (i < crumbs.length - 1) {
                li.innerHTML = '<a href="#">' + esc(c.name) + '</a>';
                li.querySelector('a').addEventListener('click', function(e) { e.preventDefault(); cmCarregarPasta(c.path); });
            } else {
                li.textContent = c.name;
            }
            bc.appendChild(li);
        });
        document.getElementById('cm-dest-label').textContent = path ? path.split('/').pop() : 'Compartilhamentos';
    }

    async function cmCarregarPasta(path) {
        cmPickerPath = path;
        cmBreadcrumb(path);

        var listEl = document.getElementById('cm-folder-list');
        listEl.innerHTML = '<div class="text-center text-muted py-3"><div class="spinner-border spinner-border-sm me-2"></div>Carregando...</div>';

        try {
            var res  = await fetch(URL_LISTAR_DIRS + '?path=' + encodeURIComponent(path));
            var data = await res.json();

            if (data.error) {
                listEl.innerHTML = '<div class="text-danger p-3 small"><i class="bi bi-exclamation-circle me-1"></i>' + esc(data.error) + '</div>';
                return;
            }

            listEl.innerHTML = '';

            // Botão "Subir" se não estiver na raiz
            if (path) {
                var parent = path.indexOf('/') !== -1 ? path.substring(0, path.lastIndexOf('/')) : '';
                var upItem = document.createElement('div');
                upItem.className = 'p-2 border-bottom d-flex align-items-center gap-2';
                upItem.style.cssText = 'cursor:pointer;transition:background .15s;color:#6b7280';
                upItem.innerHTML = '<i class="bi bi-arrow-up-circle text-secondary"></i><span>.. Subir</span>';
                upItem.addEventListener('mouseenter', function() { upItem.style.background = '#f1f5f9'; });
                upItem.addEventListener('mouseleave', function() { upItem.style.background = ''; });
                upItem.addEventListener('click', function() { cmCarregarPasta(parent); });
                listEl.appendChild(upItem);
            }

            if (!data.dirs || !data.dirs.length) {
                var emptyEl = document.createElement('div');
                emptyEl.className = 'text-muted p-3 small';
                emptyEl.innerHTML = '<i class="bi bi-folder2 me-1"></i>Nenhuma subpasta aqui';
                listEl.appendChild(emptyEl);
                return;
            }

            data.dirs.forEach(function(d) {
                var item = document.createElement('div');
                item.className = 'p-2 border-bottom d-flex align-items-center gap-2';
                item.style.cssText = 'cursor:pointer;transition:background .15s';
                item.innerHTML = '<i class="bi bi-folder-fill text-warning"></i><span class="flex-grow-1">' + esc(d.name) + '</span><i class="bi bi-chevron-right text-muted" style="font-size:11px"></i>';
                item.addEventListener('mouseenter', function() { item.style.background = '#f8fafc'; });
                item.addEventListener('mouseleave', function() { item.style.background = ''; });
                item.addEventListener('click', function() { cmCarregarPasta(d.path); });
                listEl.appendChild(item);
            });
        } catch(ex) {
            listEl.innerHTML = '<div class="text-danger p-3 small"><i class="bi bi-exclamation-circle me-1"></i>Erro ao carregar pastas.</div>';
        }
    }

    function cmAbrir(action, path, name) {
        cmAction  = action;
        cmSrcPath = path;
        document.getElementById('cm-titulo').innerHTML =
            (action === 'copiar' ? '<i class="bi bi-copy me-2"></i>Copiar para...' : '<i class="bi bi-arrows-move me-2"></i>Mover para...');
        document.getElementById('cm-acao-label').textContent = action === 'copiar' ? 'Copiando' : 'Movendo';
        document.getElementById('cm-src-nome').textContent = name;
        document.getElementById('cm-btn-label').textContent = action === 'copiar' ? 'Copiar aqui' : 'Mover aqui';
        var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCopiarMover'));
        modal.show();
    }

    document.getElementById('modalCopiarMover').addEventListener('shown.bs.modal', function() {
        cmCarregarPasta('');
    });

    document.addEventListener('click', function(e) {
        var bc = e.target.closest('.btn-copiar');
        if (bc) { cmAbrir('copiar', bc.dataset.path, bc.dataset.name); return; }
        var bm = e.target.closest('.btn-mover');
        if (bm) { cmAbrir('mover', bm.dataset.path, bm.dataset.name); }
    });

    document.getElementById('btn-confirmar-cm').addEventListener('click', async function() {
        var url = cmAction === 'copiar' ? URL_COPIAR : URL_MOVER;
        try {
            var fd = new FormData();
            fd.append('src',      cmSrcPath);
            fd.append('dest_dir', cmPickerPath);
            var res  = await fetch(url, { method: 'POST', body: fd });
            var data = await res.json();
            showToast(data.message, data.success);
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('modalCopiarMover')).hide();
                if (cmAction === 'mover') setTimeout(function() { location.reload(); }, 700);
            }
        } catch(e) { showToast('Erro ao executar operação.', false); }
    });

    // ── Renomear ─────────────────────────────────────────────────────────
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.btn-renomear');
        if (!btn) return;
        renomearPath = btn.dataset.path;
        document.getElementById('renomear-label').textContent = btn.dataset.name;
        var input = document.getElementById('renomear-input');
        input.value = btn.dataset.name;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalRenomear')).show();
        setTimeout(function() { input.select(); }, 300);
    });

    document.getElementById('btn-confirmar-renomear').addEventListener('click', async function() {
        var novoNome = document.getElementById('renomear-input').value.trim();
        if (!novoNome) return;
        try {
            var fd = new FormData();
            fd.append('path', renomearPath);
            fd.append('nome', novoNome);
            var res  = await fetch(URL_RENOMEAR, { method: 'POST', body: fd });
            var data = await res.json();
            showToast(data.message, data.success);
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('modalRenomear')).hide();
                setTimeout(function() { location.reload(); }, 600);
            }
        } catch(e) { showToast('Erro ao renomear.', false); }
    });

    document.getElementById('renomear-input').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') document.getElementById('btn-confirmar-renomear').click();
    });

    document.getElementById('modalRenomear').addEventListener('hidden.bs.modal', function() {
        document.getElementById('renomear-input').value = '';
    });

    // ── CodeMirror helpers ───────────────────────────────────────────────
    function getCmMode(ext) {
        var modes = {
            'js':'javascript','json':'application/json',
            'php':'application/x-httpd-php',
            'py':'python',
            'sql':'text/x-sql',
            'sh':'shell','conf':'shell','cfg':'shell',
            'xml':'xml',
            'html':'htmlmixed',
            'css':'css',
            'md':'markdown',
            'ini':'text/x-properties','properties':'text/x-properties',
        };
        return modes[ext] || 'text/plain';
    }

    var editorCm  = null;
    var viewerCm  = null;
    var editorExt = '';

    // ── Visualizar Texto ──────────────────────────────────────────────────
    document.addEventListener('click', async function(e) {
        var btn = e.target.closest('.btn-view-text');
        if (!btn) return;
        var path     = btn.dataset.path;
        var name     = btn.dataset.name;
        var ext      = (btn.dataset.ext || name.split('.').pop()).toLowerCase();
        var loadEl   = document.getElementById('texto-loading');
        var titleEl  = document.getElementById('texto-title');
        document.getElementById('texto-download-link').href = '<?= url('/samba/arquivos/download') ?>?path=' + encodeURIComponent(path);
        document.getElementById('texto-download-link').setAttribute('download', name);
        document.getElementById('texto-edit-link').onclick = function() {
            bootstrap.Modal.getInstance(document.getElementById('modalTexto')).hide();
            setTimeout(function() { document.querySelector('.btn-edit[data-path="' + path.replace(/"/g,'\\"') + '"]')?.click(); }, 300);
        };
        titleEl.textContent = name;
        loadEl.style.display = 'block';
        var container = document.getElementById('texto-cm-viewer');
        container.innerHTML = '';
        if (viewerCm) { try { viewerCm.toTextArea(); } catch(x){} viewerCm = null; }
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalTexto')).show();
        try {
            var res  = await fetch(URL_LER + '?path=' + encodeURIComponent(path));
            var data = await res.json();
            loadEl.style.display = 'none';
            var content = data.success ? data.content : ('Erro: ' + data.message);
            viewerCm = CodeMirror(container, {
                value: content, mode: getCmMode(ext),
                theme: 'monokai', lineNumbers: true,
                readOnly: true, lineWrapping: true,
                autofocus: false,
            });
            viewerCm.setSize('100%', 'calc(85vh - 90px)');
            setTimeout(function() { viewerCm.refresh(); }, 50);
        } catch(ex) {
            loadEl.style.display = 'none';
            container.textContent = 'Erro ao carregar o arquivo.';
        }
    });

    document.getElementById('modalTexto').addEventListener('hidden.bs.modal', function() {
        if (viewerCm) { try { viewerCm.setValue(''); } catch(x){} }
    });

    document.getElementById('modalTexto').addEventListener('shown.bs.modal', function() {
        if (viewerCm) { viewerCm.refresh(); }
    });

    // ── Visualizar PDF ───────────────────────────────────────────────────
    const URL_VISUALIZAR = '<?= url('/samba/arquivos/visualizar') ?>';

    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.btn-view-pdf');
        if (!btn) return;
        var path = btn.dataset.path;
        var name = btn.dataset.name;
        var url  = URL_VISUALIZAR + '?path=' + encodeURIComponent(path);
        document.getElementById('pdf-title').textContent = name;
        document.getElementById('pdf-frame').src = url;
        document.getElementById('pdf-download-link').href = '<?= url('/samba/arquivos/download') ?>?path=' + encodeURIComponent(path);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalPdf')).show();
    });

    document.getElementById('modalPdf').addEventListener('hidden.bs.modal', function() {
        document.getElementById('pdf-frame').src = '';
    });

    // ── Editor ───────────────────────────────────────────────────────────
    document.addEventListener('click', async function(e) {
        var btn = e.target.closest('.btn-edit');
        if (!btn) return;
        editorPath = btn.dataset.path;
        editorExt  = (btn.dataset.ext || btn.dataset.name.split('.').pop()).toLowerCase();
        document.getElementById('editor-title').textContent = btn.dataset.name;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditor')).show();
        try {
            var res  = await fetch(URL_LER + '?path=' + encodeURIComponent(editorPath));
            var data = await res.json();
            var content = data.success ? data.content : ('Erro: ' + data.message);
            if (editorCm) {
                editorCm.setValue(content);
                editorCm.setOption('mode', getCmMode(editorExt));
                editorCm.clearHistory();
                editorCm.refresh();
            } else {
                document.getElementById('editor-content').value = content;
            }
        } catch(ex) {
            var fallback = 'Erro ao carregar arquivo.';
            if (editorCm) editorCm.setValue(fallback);
            else document.getElementById('editor-content').value = fallback;
        }
    });

    document.getElementById('modalEditor').addEventListener('shown.bs.modal', function() {
        if (!editorCm) {
            editorCm = CodeMirror.fromTextArea(document.getElementById('editor-content'), {
                theme: 'monokai', lineNumbers: true, lineWrapping: true,
            });
            editorCm.setSize('100%', 'calc(85vh - 130px)');
        }
        editorCm.setOption('mode', getCmMode(editorExt));
        editorCm.refresh();
        editorCm.focus();
    });

    document.getElementById('btn-salvar-editor').addEventListener('click', async function() {
        var content = editorCm ? editorCm.getValue() : document.getElementById('editor-content').value;
        try {
            var fd = new FormData(); fd.append('path', editorPath); fd.append('content', content);
            var res = await fetch(URL_SALVAR, {method:'POST', body:fd});
            var data = await res.json();
            showToast(data.message, data.success);
            if (data.success) bootstrap.Modal.getInstance(document.getElementById('modalEditor')).hide();
        } catch(e) { showToast('Erro ao salvar arquivo.', false); }
    });

    // Reset modais
    document.getElementById('modalUpload').addEventListener('hidden.bs.modal', function() {
        document.getElementById('upload-progress').style.display = 'none';
        document.getElementById('upload-result').innerHTML = '';
        document.getElementById('upload-bar').style.width = '0%';
        document.getElementById('upload-pct').textContent = '0%';
        document.getElementById('upload-input').value = '';
    });
    document.getElementById('modalNovaPasta').addEventListener('hidden.bs.modal', function() {
        document.getElementById('nova-pasta-nome').value = '';
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Arquivos - Samba';
require __DIR__ . '/../layouts/main.php';
