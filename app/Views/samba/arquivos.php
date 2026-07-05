<?php
ob_start();

function formatBytes(int $bytes): string {
    if ($bytes === 0) return '-';
    $u = ['B','KB','MB','GB'];
    $i = (int)floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 1) . ' ' . $u[$i];
}
?>

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
                            <?php if ($f['editable']): ?>
                            <button class="btn btn-sm btn-outline-info btn-view-text"
                                data-path="<?= htmlspecialchars($f['path']) ?>"
                                data-name="<?= htmlspecialchars($f['name']) ?>"
                                title="Visualizar">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-secondary btn-edit"
                                data-path="<?= htmlspecialchars($f['path']) ?>"
                                data-name="<?= htmlspecialchars($f['name']) ?>"
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

<!-- Modal Editor -->
<div class="modal fade" id="modalEditor" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i><span id="editor-title"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-2">
                <textarea class="form-control editor-textarea" id="editor-content" spellcheck="false"></textarea>
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
            <div class="modal-body p-0" style="overflow:auto">
                <div id="texto-loading" class="text-center text-muted py-5" style="display:none">
                    <div class="spinner-border spinner-border-sm me-2"></div>Carregando...
                </div>
                <pre id="texto-content" style="margin:0;padding:16px;font-size:13px;line-height:1.6;min-height:100%;background:#fafafa;border:0;white-space:pre-wrap;word-break:break-word"></pre>
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

    const URL_RENOMEAR  = '<?= url('/samba/arquivos/renomear') ?>';

    let renomearPath = '';

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

    // ── Visualizar Texto ──────────────────────────────────────────────────
    document.addEventListener('click', async function(e) {
        var btn = e.target.closest('.btn-view-text');
        if (!btn) return;
        var path = btn.dataset.path;
        var name = btn.dataset.name;
        var preEl    = document.getElementById('texto-content');
        var loadEl   = document.getElementById('texto-loading');
        var titleEl  = document.getElementById('texto-title');
        document.getElementById('texto-download-link').href = '<?= url('/samba/arquivos/download') ?>?path=' + encodeURIComponent(path);
        document.getElementById('texto-download-link').setAttribute('download', name);
        document.getElementById('texto-edit-link').href = '#';
        document.getElementById('texto-edit-link').onclick = function() {
            bootstrap.Modal.getInstance(document.getElementById('modalTexto')).hide();
            document.querySelector('.btn-edit[data-path="' + path.replace(/"/g,'\\"') + '"]')?.click();
        };
        titleEl.textContent = name;
        preEl.textContent   = '';
        loadEl.style.display = 'block';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalTexto')).show();
        try {
            var res  = await fetch(URL_LER + '?path=' + encodeURIComponent(path));
            var data = await res.json();
            loadEl.style.display = 'none';
            preEl.textContent = data.success ? data.content : ('Erro: ' + data.message);
        } catch(ex) {
            loadEl.style.display = 'none';
            preEl.textContent = 'Erro ao carregar o arquivo.';
        }
    });

    document.getElementById('modalTexto').addEventListener('hidden.bs.modal', function() {
        document.getElementById('texto-content').textContent = '';
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
        document.getElementById('editor-title').textContent = btn.dataset.name;
        document.getElementById('editor-content').value = 'Carregando...';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditor')).show();
        try {
            var res = await fetch(URL_LER + '?path=' + encodeURIComponent(editorPath));
            var data = await res.json();
            document.getElementById('editor-content').value = data.success ? data.content : ('Erro: ' + data.message);
        } catch(e) {
            document.getElementById('editor-content').value = 'Erro ao carregar arquivo.';
        }
    });

    document.getElementById('btn-salvar-editor').addEventListener('click', async function() {
        var content = document.getElementById('editor-content').value;
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
