<?php
ob_start();

use App\Components\Alert;
use App\Components\Badge;

$rotuloAcao = ['renomeado' => 'Renomeado/Movido', 'excluido' => 'Excluído', 'gravado' => 'Conteúdo gravado', 'pasta_criada' => 'Pasta criada', 'arquivo_criado' => 'Arquivo criado'];
$corAcao = ['renomeado' => 'primary', 'excluido' => 'danger', 'gravado' => 'success', 'pasta_criada' => 'info', 'arquivo_criado' => 'success'];
$iconeAcao = ['renomeado' => 'bi-arrow-left-right', 'excluido' => 'bi-trash3', 'gravado' => 'bi-pencil-square', 'pasta_criada' => 'bi-folder-plus', 'arquivo_criado' => 'bi-file-earmark-plus'];
?>

<!-- CodeMirror -- visualizador/editor de texto, mesma lib usada em Samba > Arquivos -->
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

<div class="toast-container">
    <div id="aud-toast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body" id="aud-toast-msg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?= Alert::flash() ?>

<div class="mb-4 d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h4 class="mb-1"><i class="bi bi-eye me-1"></i> Auditoria de Arquivos</h4>
        <small class="text-muted">Quem renomeou, excluiu ou gravou conteúdo nos compartilhamentos Samba.</small>
    </div>
    <?php if ($ativa): ?>
        <form method="post" action="<?= url('/samba/auditoria/desativar') ?>" onsubmit="return confirm('Desativar a auditoria de arquivos? Nenhum registro novo será gravado até ligar de novo -- o histórico já coletado continua salvo.');">
            <button type="submit" class="btn btn-outline-danger">Desativar auditoria</button>
        </form>
    <?php else: ?>
        <form method="post" action="<?= url('/samba/auditoria/ativar') ?>">
            <button type="submit" class="btn btn-primary"><i class="bi bi-play-fill"></i> Ativar auditoria</button>
        </form>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <strong><i class="bi bi-question-circle"></i> Como funciona</strong>
        <ul class="small text-muted mb-0 mt-2 ps-3">
            <li>Registra, por usuário e por compartilhamento: <strong>criar pasta</strong>, <strong>criar arquivo</strong>, <strong>renomear/mover</strong>, <strong>excluir</strong> e <strong>gravar conteúdo</strong> (modificar um arquivo já existente, inclusive copiar de outro lugar da rede).</li>
            <li>Copiar arquivos pela rede (Explorer, "Copiar e Colar") costuma usar um atalho do próprio Windows/Samba que não informa o nome do arquivo -- aparece como "Conteúdo gravado" com uma observação em vez do caminho.</li>
            <li>
                Como a <a href="<?= url('/samba/lixeira') ?>">Lixeira Administrativa</a> fica sempre ativa, uma
                exclusão de usuário nunca some de verdade na hora -- ela move o arquivo pra dentro de
                <code>.recycle/</code>, e é esse movimento que aparece aqui como "Excluído".
            </li>
            <li>Não registra leitura/abertura de arquivo (só o que muda algo), pra não lotar a tela com ruído.</li>
            <li>O histórico completo fica gravado em <code>/var/log/samba/audit.log</code> no servidor (rotacionado conforme a retenção configurada abaixo); esta tela mostra as últimas 5.000 entradas.</li>
            <li>Isso é um arquivo <strong>diferente</strong> do <code>log file</code> (<code>/var/log/samba/%m.log</code>) que aparece em <a href="<?= url('/samba/configuracao') ?>">Samba &gt; Configuração</a> -- aquele é o log geral de conexão/protocolo do Samba (um arquivo por máquina que conecta), não registra quem apagou ou renomeou um arquivo. <strong>Pra investigar quem apagou/moveu/gravou um arquivo, o arquivo certo é o desta tela.</strong></li>
            <li>Quando o arquivo referenciado ainda existe no compartilhamento (ou seja, ação diferente de "Excluído"), a coluna <strong>Ações</strong> deixa visualizar/editar (texto) ou visualizar (imagem/PDF/vídeo) sem sair desta tela.</li>
        </ul>
    </div>
</div>

<?php if (!$ativa): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-eye-slash" style="font-size:2rem;"></i>
            <p class="mb-0 mt-2">Auditoria desativada -- clique em "Ativar auditoria" acima para começar a registrar.</p>
        </div>
    </div>
<?php else: ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <strong><i class="bi bi-clock-history"></i> Retenção do histórico</strong>
            <p class="small text-muted mt-1 mb-3">Por quanto tempo o <code>audit.log</code> completo (não só as 5.000 entradas mostradas abaixo) fica guardado no servidor antes de ser descartado. Um arquivo apagado/renomeado só pode ser rastreado enquanto o dia dele ainda estiver dentro desse prazo.</p>
            <?php if (!$retencao || empty($retencao['success'])): ?>
                <div class="text-danger small"><i class="bi bi-exclamation-triangle"></i> Não foi possível ler a configuração de retenção no servidor<?= !empty($retencao['message']) ? ': ' . htmlspecialchars($retencao['message']) : '.' ?></div>
            <?php else: ?>
                <?php
                    $tamanhoMb = round(($retencao['tamanho_bytes'] ?? 0) / 1048576, 1);
                    $maisAntiga = $retencao['data_mais_antiga'] ?? '';
                ?>
                <div class="row g-3 mb-3">
                    <div class="col-sm-4">
                        <div class="text-muted small">Guardando há</div>
                        <div class="fw-semibold"><?= (int) ($retencao['dias'] ?? 0) ?> dias</div>
                    </div>
                    <div class="col-sm-4">
                        <div class="text-muted small">Espaço ocupado hoje</div>
                        <div class="fw-semibold"><?= number_format($tamanhoMb, 1, ',', '.') ?> MB</div>
                    </div>
                    <div class="col-sm-4">
                        <div class="text-muted small">Registro mais antigo em disco</div>
                        <div class="fw-semibold"><?= $maisAntiga ? htmlspecialchars(data_br($maisAntiga, 'd/m/Y')) : '<span class="text-muted">ainda não rotacionou</span>' ?></div>
                    </div>
                </div>
                <form method="post" action="<?= url('/samba/auditoria/retencao') ?>" class="d-flex align-items-end gap-2">
                    <div>
                        <label class="form-label small text-muted mb-1">Guardar por quantos dias</label>
                        <input type="number" name="dias" min="1" max="3650" class="form-control form-control-sm" style="width:110px" value="<?= (int) ($retencao['dias'] ?? 30) ?>" required>
                    </div>
                    <button type="submit" class="btn btn-outline-primary btn-sm">Salvar retenção</button>
                </form>
                <p class="small text-muted mt-2 mb-0">Referência: <?= number_format($tamanhoMb, 1, ',', '.') ?> MB acumulados em <?= (int) ($retencao['dias'] ?? 0) ?> dias -- use essa média pra estimar quanto espaço um prazo maior vai ocupar (ex: dobrar os dias tende a dobrar o espaço).</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Usuário</label>
                    <input type="text" name="usuario" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros['usuario']) ?>" placeholder="login do usuário">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Compartilhamento</label>
                    <select name="compartilhamento" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($compartilhamentos as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>" <?= $filtros['compartilhamento'] === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Ação</label>
                    <select name="acao" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        <?php foreach ($rotuloAcao as $valor => $label): ?>
                            <option value="<?= $valor ?>" <?= $filtros['acao'] === $valor ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Arquivo (busca)</label>
                    <input type="text" name="busca" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros['busca']) ?>" placeholder="parte do nome/caminho">
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-outline-secondary btn-sm w-100"><i class="bi bi-search"></i></button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if (empty($registros)): ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-inbox" style="font-size:2rem;"></i>
                    <p class="mb-0 mt-2">Nenhum registro encontrado<?= array_filter($filtros) ? ' com esses filtros' : ' ainda' ?>.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x:auto">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Data/Hora</th>
                                <th>Usuário</th>
                                <th>Máquina</th>
                                <th>Compartilhamento</th>
                                <th>Ação</th>
                                <th>Arquivo</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registros as $r): ?>
                                <tr>
                                    <td class="small text-nowrap"><?= htmlspecialchars(data_br($r['data_hora'], 'd/m/Y H:i:s')) ?></td>
                                    <td class="small"><?= htmlspecialchars($r['usuario']) ?></td>
                                    <td class="small text-muted">
                                        <?= htmlspecialchars($r['maquina']) ?>
                                        <?php if (!empty($r['ativo_id'])): ?>
                                            <a href="<?= url('/ativos/ver?id=' . (int) $r['ativo_id']) ?>" class="text-decoration-none" title="Abrir Ativo cadastrado">
                                                <?= Badge::make('<i class="bi bi-box-seam"></i> ' . htmlspecialchars($r['ativo_codigo']), 'secondary') ?>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small"><?= htmlspecialchars($r['compartilhamento']) ?></td>
                                    <td>
                                        <?= Badge::make('<i class="bi ' . $iconeAcao[$r['acao']] . '"></i> ' . $rotuloAcao[$r['acao']], $corAcao[$r['acao']]) ?>
                                    </td>
                                    <td class="small font-monospace" style="word-break:break-all">
                                        <?= htmlspecialchars($r['arquivo']) ?>
                                        <?php if ($r['arquivo_destino']): ?>
                                            <i class="bi bi-arrow-right text-muted mx-1"></i><?= htmlspecialchars($r['arquivo_destino']) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <?php if ($r['acao'] === 'pasta_criada' && !empty($r['rel'])): ?>
                                            <a href="<?= url('/samba/arquivos?path=' . urlencode($r['rel'])) ?>" class="btn btn-sm btn-outline-secondary" title="Abrir pasta">
                                                <i class="bi bi-folder2-open"></i>
                                            </a>
                                        <?php elseif (!empty($r['rel'])): ?>
                                            <?php if (!empty($r['isPdf'])): ?>
                                            <button class="btn btn-sm btn-outline-danger btn-view-pdf"
                                                data-path="<?= htmlspecialchars($r['rel']) ?>"
                                                data-name="<?= htmlspecialchars(basename($r['rel'])) ?>"
                                                title="Visualizar PDF">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <?php endif; ?>
                                            <?php if (!empty($r['isImage'])): ?>
                                            <button class="btn btn-sm btn-outline-info btn-view-image"
                                                data-path="<?= htmlspecialchars($r['rel']) ?>"
                                                data-name="<?= htmlspecialchars(basename($r['rel'])) ?>"
                                                title="Visualizar imagem">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <?php endif; ?>
                                            <?php if (!empty($r['isVideo'])): ?>
                                            <button class="btn btn-sm btn-outline-dark btn-view-video"
                                                data-path="<?= htmlspecialchars($r['rel']) ?>"
                                                data-name="<?= htmlspecialchars(basename($r['rel'])) ?>"
                                                title="Visualizar vídeo">
                                                <i class="bi bi-play-circle"></i>
                                            </button>
                                            <?php endif; ?>
                                            <?php if (!empty($r['viewable'])): ?>
                                            <button class="btn btn-sm btn-outline-info btn-view-text"
                                                data-path="<?= htmlspecialchars($r['rel']) ?>"
                                                data-name="<?= htmlspecialchars(basename($r['rel'])) ?>"
                                                data-ext="<?= htmlspecialchars($r['ext']) ?>"
                                                title="Visualizar">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <?php endif; ?>
                                            <?php if (!empty($r['editable'])): ?>
                                            <button class="btn btn-sm btn-outline-secondary btn-edit"
                                                data-path="<?= htmlspecialchars($r['rel']) ?>"
                                                data-name="<?= htmlspecialchars(basename($r['rel'])) ?>"
                                                data-ext="<?= htmlspecialchars($r['ext']) ?>"
                                                title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <?php endif; ?>
                                            <a href="<?= url('/samba/arquivos/download?path=' . urlencode($r['rel'])) ?>"
                                               class="btn btn-sm btn-outline-primary" title="Download">
                                                <i class="bi bi-download"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php endif; ?>

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

<!-- Modal Visualizador Imagem -->
<div class="modal fade" id="modalImagem" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title mb-0">
                    <i class="bi bi-file-earmark-image text-info me-2"></i>
                    <span id="imagem-title"></span>
                </h6>
                <div class="d-flex gap-2 align-items-center ms-auto me-2">
                    <a id="imagem-download-link" href="#" class="btn btn-sm btn-outline-primary" download>
                        <i class="bi bi-download me-1"></i>Download
                    </a>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-3" style="background:#111;">
                <img id="imagem-preview" src="" style="max-width:100%;max-height:75vh;">
            </div>
        </div>
    </div>
</div>

<!-- Modal Visualizador Vídeo -->
<div class="modal fade" id="modalVideo" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title mb-0">
                    <i class="bi bi-file-earmark-play text-dark me-2"></i>
                    <span id="video-title"></span>
                </h6>
                <div class="d-flex gap-2 align-items-center ms-auto me-2">
                    <a id="video-download-link" href="#" class="btn btn-sm btn-outline-primary" download>
                        <i class="bi bi-download me-1"></i>Download
                    </a>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-3" style="background:#111;">
                <video id="video-preview" src="" controls style="max-width:100%;max-height:75vh;">
                    Seu navegador não consegue reproduzir este formato de vídeo -- use o botão Download acima.
                </video>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const URL_LER = '<?= url('/samba/arquivos/ler') ?>';
    const URL_SALVAR = '<?= url('/samba/arquivos/salvar') ?>';
    const URL_VISUALIZAR = '<?= url('/samba/arquivos/visualizar') ?>';
    const URL_DOWNLOAD = '<?= url('/samba/arquivos/download') ?>';

    let editorPath = '';
    let editorExt = '';
    let editorCm = null;
    let viewerCm = null;

    function showToast(msg, ok) {
        var el = document.getElementById('aud-toast');
        el.className = 'toast align-items-center text-white border-0 bg-' + (ok ? 'success' : 'danger');
        document.getElementById('aud-toast-msg').textContent = msg;
        bootstrap.Toast.getOrCreateInstance(el, {delay: 4000}).show();
    }

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

    // ── Visualizar Texto ────────────────────────────────────────────────
    document.addEventListener('click', async function(e) {
        var btn = e.target.closest('.btn-view-text');
        if (!btn) return;
        var path    = btn.dataset.path;
        var name    = btn.dataset.name;
        var ext     = (btn.dataset.ext || name.split('.').pop()).toLowerCase();
        var loadEl  = document.getElementById('texto-loading');
        var titleEl = document.getElementById('texto-title');
        document.getElementById('texto-download-link').href = URL_DOWNLOAD + '?path=' + encodeURIComponent(path);
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

    // ── Editor ──────────────────────────────────────────────────────────
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

    // ── Visualizar PDF ──────────────────────────────────────────────────
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.btn-view-pdf');
        if (!btn) return;
        var path = btn.dataset.path;
        var name = btn.dataset.name;
        var url  = URL_VISUALIZAR + '?path=' + encodeURIComponent(path);
        document.getElementById('pdf-title').textContent = name;
        document.getElementById('pdf-frame').src = url;
        document.getElementById('pdf-download-link').href = URL_DOWNLOAD + '?path=' + encodeURIComponent(path);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalPdf')).show();
    });

    document.getElementById('modalPdf').addEventListener('hidden.bs.modal', function() {
        document.getElementById('pdf-frame').src = '';
    });

    // ── Visualizar Imagem ───────────────────────────────────────────────
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.btn-view-image');
        if (!btn) return;
        var path = btn.dataset.path;
        var name = btn.dataset.name;
        var url  = URL_VISUALIZAR + '?path=' + encodeURIComponent(path);
        document.getElementById('imagem-title').textContent = name;
        document.getElementById('imagem-preview').src = url;
        document.getElementById('imagem-download-link').href = URL_DOWNLOAD + '?path=' + encodeURIComponent(path);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalImagem')).show();
    });

    document.getElementById('modalImagem').addEventListener('hidden.bs.modal', function() {
        document.getElementById('imagem-preview').src = '';
    });

    // ── Visualizar Vídeo ────────────────────────────────────────────────
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.btn-view-video');
        if (!btn) return;
        var path = btn.dataset.path;
        var name = btn.dataset.name;
        var url  = URL_VISUALIZAR + '?path=' + encodeURIComponent(path);
        document.getElementById('video-title').textContent = name;
        document.getElementById('video-preview').src = url;
        document.getElementById('video-download-link').href = URL_DOWNLOAD + '?path=' + encodeURIComponent(path);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalVideo')).show();
    });

    document.getElementById('modalVideo').addEventListener('hidden.bs.modal', function() {
        var video = document.getElementById('video-preview');
        video.pause();
        video.removeAttribute('src');
        video.load();
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Samba - Auditoria de Arquivos';

require __DIR__ . '/../layouts/main.php';
