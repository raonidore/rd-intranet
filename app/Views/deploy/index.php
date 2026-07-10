<?php

use App\Components\Alert;

ob_start();

$pendente = (int)($samba['alteracoes_pendentes'] ?? 0) === 1;
?>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h4>🚀 Deploy Center</h4>
        <small class="text-muted">Central de Deploy e Configurações da RD Intranet</small>
    </div>
</div>

<!-- Ferramentas rápidas -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <a href="<?= url('/samba/configuracao') ?>" class="card border-0 shadow-sm text-decoration-none h-100" style="border-radius:12px">
            <div class="card-body d-flex align-items-center gap-3">
                <i class="bi bi-sliders display-6 text-primary"></i>
                <div>
                    <div class="fw-semibold">Config. Global</div>
                    <small class="text-muted">smb.conf [global]</small>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="<?= url('/samba/compartilhamentos') ?>" class="card border-0 shadow-sm text-decoration-none h-100" style="border-radius:12px">
            <div class="card-body d-flex align-items-center gap-3">
                <i class="bi bi-folder2-open display-6 text-warning"></i>
                <div>
                    <div class="fw-semibold">Compartilhamentos</div>
                    <small class="text-muted">Shares do Samba</small>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="<?= url('/samba/usuarios') ?>" class="card border-0 shadow-sm text-decoration-none h-100" style="border-radius:12px">
            <div class="card-body d-flex align-items-center gap-3">
                <i class="bi bi-people display-6 text-success"></i>
                <div>
                    <div class="fw-semibold">Usuários Samba</div>
                    <small class="text-muted">Contas e senhas</small>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="<?= url('/samba/monitor') ?>" class="card border-0 shadow-sm text-decoration-none h-100" style="border-radius:12px">
            <div class="card-body d-flex align-items-center gap-3">
                <i class="bi bi-display display-6 text-info"></i>
                <div>
                    <div class="fw-semibold">Monitor</div>
                    <small class="text-muted">Sessões em tempo real</small>
                </div>
            </div>
        </a>
    </div>
</div>

<div class="card shadow-sm border-0">

    <div class="card-body">

        <div class="d-flex justify-content-between">

            <div>

                <h5>Samba</h5>

                <p class="mb-1">

                    Último deploy:

                    <strong>

                        <?= isset($samba['ultimo_deploy']) && $samba['ultimo_deploy'] ? date('d/m/Y H:i:s', strtotime($samba['ultimo_deploy'])) : 'Nunca' ?>

                    </strong>

                </p>

                <p class="mb-1">

                    Último backup:

                    <strong>

                        <?= isset($samba['ultimo_backup']) && $samba['ultimo_backup'] ? date('d/m/Y H:i:s', strtotime($samba['ultimo_backup'])) : '-' ?>

                    </strong>

                </p>

                <p>

                    Usuário:

                    <strong>

                        <?= $samba['ultimo_usuario'] ?? '-' ?>

                    </strong>

                </p>

            </div>

            <div class="text-end">

                <?php if($pendente): ?>

                    <span class="badge bg-warning text-dark">

                        Alterações pendentes

                    </span>

                <?php else: ?>

                    <span class="badge bg-success">

                        Produção sincronizada

                    </span>

                <?php endif; ?>

                <br><br>

                <a href="<?= url('/deploy/samba/aplicar') ?>"
                   class="btn btn-primary">

                    🚀 Aplicar Configuração

                </a>

            </div>

        </div>

    </div>

</div>

<?php if(!empty($pendencias)): ?>

<br>

<div class="card border-0 shadow-sm">

    <div class="card-header">

        <strong>

            Alterações pendentes

        </strong>

    </div>

    <table class="table table-hover mb-0">

        <thead>

        <tr>

            <th width="160">

                Tipo

            </th>

            <th width="220">

                Referência

            </th>

            <th>

                Descrição

            </th>

            <th width="170">

                Data

            </th>

        </tr>

        </thead>

        <tbody>

        <?php foreach($pendencias as $p): ?>

            <tr>

                <td>

                    <?= htmlspecialchars($p['tipo']) ?>

                </td>

                <td>

                    <?= htmlspecialchars($p['referencia']) ?>

                </td>

                <td>

                    <?= htmlspecialchars($p['descricao']) ?>

                </td>

                <td>

                    <?= date('d/m/Y H:i', strtotime($p['criado_em'])) ?>

                </td>

            </tr>

        <?php endforeach; ?>

        </tbody>

    </table>

</div>

<?php endif; ?>

<!-- ── Configurações: Extensões do Gerenciador de Arquivos ─────────── -->
<div class="card shadow-sm border-0 mt-4">
    <div class="card-header bg-white d-flex align-items-center gap-2">
        <i class="bi bi-file-earmark-code text-primary"></i>
        <strong>Gerenciador de Arquivos — Extensões permitidas</strong>
    </div>
    <div class="card-body">
        <div class="row g-4">

            <div class="col-md-6">
                <label class="form-label">
                    <i class="bi bi-eye me-1 text-info"></i>
                    <strong>Visualização</strong>
                    <span class="text-muted ms-1" style="font-size:12px">— botão olho (somente leitura)</span>
                </label>
                <div class="ext-tags-box d-flex flex-wrap gap-1 p-2 border rounded mb-2" id="tags-visualizar" style="min-height:42px">
                    <?php foreach ($extVisualizar as $ext): ?>
                    <span class="badge bg-info ext-tag d-inline-flex align-items-center gap-1" data-ext="<?= htmlspecialchars($ext) ?>">
                        .<?= htmlspecialchars($ext) ?>
                        <button type="button" class="btn-close btn-close-white" style="font-size:9px"></button>
                    </span>
                    <?php endforeach; ?>
                </div>
                <div class="input-group input-group-sm">
                    <span class="input-group-text">.</span>
                    <input type="text" class="form-control" id="input-vis" placeholder="ex: csv" maxlength="10">
                    <button class="btn btn-outline-info" id="btn-add-vis"><i class="bi bi-plus"></i></button>
                </div>
            </div>

            <div class="col-md-6">
                <label class="form-label">
                    <i class="bi bi-pencil me-1 text-secondary"></i>
                    <strong>Edição</strong>
                    <span class="text-muted ms-1" style="font-size:12px">— botão lápis (leitura e escrita)</span>
                </label>
                <div class="ext-tags-box d-flex flex-wrap gap-1 p-2 border rounded mb-2" id="tags-editar" style="min-height:42px">
                    <?php foreach ($extEditar as $ext): ?>
                    <span class="badge bg-secondary ext-tag d-inline-flex align-items-center gap-1" data-ext="<?= htmlspecialchars($ext) ?>">
                        .<?= htmlspecialchars($ext) ?>
                        <button type="button" class="btn-close btn-close-white" style="font-size:9px"></button>
                    </span>
                    <?php endforeach; ?>
                </div>
                <div class="input-group input-group-sm">
                    <span class="input-group-text">.</span>
                    <input type="text" class="form-control" id="input-edit" placeholder="ex: log" maxlength="10">
                    <button class="btn btn-outline-secondary" id="btn-add-edit"><i class="bi bi-plus"></i></button>
                </div>
            </div>

        </div>
        <div class="mt-3 d-flex justify-content-between align-items-center">
            <small class="text-muted">
                <i class="bi bi-info-circle me-1"></i>
                Alterações aplicam-se imediatamente ao Gerenciador de Arquivos.
            </small>
            <button class="btn btn-primary btn-sm" id="btn-salvar-ext">
                <i class="bi bi-floppy me-1"></i>Salvar configurações
            </button>
        </div>
        <div id="ext-result" class="mt-2"></div>
    </div>
</div>

<script>
(function() {
    const SAVE_URL = '<?= url('/deploy/configuracoes') ?>';

    function makeBadge(ext, group) {
        const span = document.createElement('span');
        const color = group === 'visualizar' ? 'info' : 'secondary';
        span.className = 'badge bg-' + color + ' ext-tag d-inline-flex align-items-center gap-1';
        span.dataset.ext = ext;
        span.innerHTML = '.' + ext + ' <button type="button" class="btn-close btn-close-white" style="font-size:9px"></button>';
        span.querySelector('.btn-close').addEventListener('click', function() { span.remove(); });
        return span;
    }

    function addTag(boxId, inputId, group) {
        var input = document.getElementById(inputId);
        var ext   = input.value.toLowerCase().trim().replace(/^\.+/, '').replace(/[^a-z0-9]/g, '');
        if (!ext) return;
        var box = document.getElementById(boxId);
        if (box.querySelector('[data-ext="' + ext + '"]')) { input.value = ''; return; }
        box.appendChild(makeBadge(ext, group));
        input.value = '';
        input.focus();
    }

    document.getElementById('btn-add-vis').addEventListener('click', function() { addTag('tags-visualizar', 'input-vis', 'visualizar'); });
    document.getElementById('btn-add-edit').addEventListener('click', function() { addTag('tags-editar', 'input-edit', 'editar'); });
    document.getElementById('input-vis').addEventListener('keydown', function(e) { if (e.key === 'Enter') { e.preventDefault(); addTag('tags-visualizar', 'input-vis', 'visualizar'); } });
    document.getElementById('input-edit').addEventListener('keydown', function(e) { if (e.key === 'Enter') { e.preventDefault(); addTag('tags-editar', 'input-edit', 'editar'); } });

    document.querySelectorAll('#tags-visualizar .btn-close, #tags-editar .btn-close').forEach(function(btn) {
        btn.addEventListener('click', function() { btn.closest('.ext-tag').remove(); });
    });

    document.getElementById('btn-salvar-ext').addEventListener('click', async function() {
        var vis  = [...document.querySelectorAll('#tags-visualizar .ext-tag')].map(function(t) { return t.dataset.ext; }).join(',');
        var edit = [...document.querySelectorAll('#tags-editar .ext-tag')].map(function(t) { return t.dataset.ext; }).join(',');
        try {
            var fd = new FormData();
            fd.append('ext_visualizar', vis);
            fd.append('ext_editar', edit);
            var res  = await fetch(SAVE_URL, { method: 'POST', body: fd });
            var data = await res.json();
            var resultEl = document.getElementById('ext-result');
            resultEl.innerHTML = '<div class="alert alert-' + (data.success ? 'success' : 'danger') + ' py-2 mb-0">' + data.message + '</div>';
            setTimeout(function() { resultEl.innerHTML = ''; }, 3000);
        } catch(e) {
            document.getElementById('ext-result').innerHTML = '<div class="alert alert-danger py-2 mb-0">Erro ao salvar.</div>';
        }
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Deploy Center';
require __DIR__.'/../layouts/main.php';
