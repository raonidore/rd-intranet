<?php
ob_start();

use App\Components\Alert;

// Helper: valor atual do campo (do smb.conf lido)
function cfgVal(array $config, string $key, string $default = ''): string {
    return htmlspecialchars($config[$key] ?? $default);
}

// Converte chave smb.conf para nome de campo HTML (spaces → underscore, : → __)
function cfgName(string $key): string {
    return str_replace([' ', ':'], ['_', '__'], $key);
}
?>

<style>
.cfg-card { border:0; border-radius:14px; box-shadow:0 4px 14px rgba(0,0,0,.06); margin-bottom:1.25rem; }
.cfg-card .card-header { background:#f8fafc; border-bottom:1px solid #e9ecef; border-radius:14px 14px 0 0; padding:14px 20px; }
.cfg-group-icon { font-size:18px; }
.field-help { font-size:11px; color:#9ca3af; margin-top:3px; }
.backup-row:hover { background:#f8fafc; }
.toast-container { position:fixed; top:20px; right:20px; z-index:9999; }
</style>

<div class="toast-container">
    <div id="cfg-toast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body" id="cfg-toast-msg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<!-- Notificações PHP -->
<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-sliders me-2"></i>Configuração Global do Samba</h4>
        <small class="text-muted">Edição do arquivo <code>/etc/samba/smb.conf</code> — a configuração é validada antes de ser aplicada</small>
    </div>
    <a href="<?= url('/deploy') ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Central de Configurações
    </a>
</div>

<form method="POST" action="<?= url('/samba/configuracao/salvar') ?>" id="form-config">

<?php foreach ($grupos as $grupoKey => $grupo): ?>
<div class="cfg-card card">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi <?= htmlspecialchars($grupo['icone']) ?> cfg-group-icon text-primary"></i>
        <strong><?= htmlspecialchars($grupo['titulo']) ?></strong>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <?php foreach ($grupo['campos'] as $campo): ?>
            <?php
                $key     = $campo['key'];
                $name    = cfgName($key);
                $atual   = cfgVal($config, $key);
                $tipo    = $campo['tipo'];
                $help    = $campo['help'] ?? '';
                $opcoes  = $campo['opcoes'] ?? [];
            ?>
            <div class="col-md-6">
                <label class="form-label fw-semibold" style="font-size:13px">
                    <?= htmlspecialchars($campo['label']) ?>
                </label>
                <?php if ($tipo === 'select'): ?>
                    <select class="form-select form-select-sm" name="<?= $name ?>">
                        <?php foreach ($opcoes as $val => $label): ?>
                            <option value="<?= htmlspecialchars($val) ?>" <?= $atual === $val ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif ($tipo === 'number'): ?>
                    <input type="number" class="form-control form-control-sm"
                        name="<?= $name ?>" value="<?= $atual ?>">
                <?php else: ?>
                    <input type="text" class="form-control form-control-sm"
                        name="<?= $name ?>" value="<?= $atual ?>"
                        placeholder="<?= htmlspecialchars($key) ?>">
                <?php endif; ?>
                <?php if ($help): ?>
                    <div class="field-help"><?= htmlspecialchars($help) ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>

<div class="d-flex justify-content-between align-items-center mt-2 mb-5">
    <small class="text-muted">
        <i class="bi bi-info-circle me-1"></i>
        O arquivo atual será salvo como backup antes de qualquer alteração.
        A configuração é validada com <code>testparm</code> antes de ser aplicada.
    </small>
    <button type="submit" class="btn btn-primary" id="btn-salvar">
        <i class="bi bi-cloud-check me-2"></i>Validar e Aplicar Configuração
    </button>
</div>

</form>

<!-- Backups -->
<div class="cfg-card card">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-archive cfg-group-icon text-secondary"></i>
        <strong>Backups do smb.conf</strong>
        <span class="badge bg-secondary ms-1"><?= count($backups) ?></span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($backups)): ?>
            <div class="text-muted p-4 text-center">
                <i class="bi bi-archive display-6 d-block mb-2"></i>
                Nenhum backup encontrado.
            </div>
        <?php else: ?>
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="font-size:12px">Arquivo</th>
                    <th style="font-size:12px">Tamanho</th>
                    <th style="font-size:12px">Data</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($backups as $bkp): ?>
                <tr class="backup-row">
                    <td style="font-size:12px;font-family:monospace"><?= htmlspecialchars($bkp['nome']) ?></td>
                    <td style="font-size:12px"><?= htmlspecialchars($bkp['tamanho']) ?></td>
                    <td style="font-size:12px"><?= htmlspecialchars($bkp['data']) ?></td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-warning btn-restaurar"
                            data-arquivo="<?= htmlspecialchars($bkp['arquivo']) ?>"
                            data-nome="<?= htmlspecialchars($bkp['nome']) ?>">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Restaurar
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<script>
(function() {
    function showToast(msg, ok) {
        var el = document.getElementById('cfg-toast');
        el.className = 'toast align-items-center text-white border-0 bg-' + (ok ? 'success' : 'danger');
        document.getElementById('cfg-toast-msg').textContent = msg;
        bootstrap.Toast.getOrCreateInstance(el, {delay:5000}).show();
    }

    // Confirmação no submit
    document.getElementById('form-config').addEventListener('submit', function(e) {
        if (!confirm('Confirma a aplicação da nova configuração?\n\nUm backup será criado automaticamente.')) {
            e.preventDefault();
        }
    });

    // Restaurar backup
    document.addEventListener('click', async function(e) {
        var btn = e.target.closest('.btn-restaurar');
        if (!btn) return;
        if (!confirm('Restaurar o backup "' + btn.dataset.nome + '"?\n\nA configuração atual será salva como backup antes de restaurar.')) return;
        try {
            var fd = new FormData();
            fd.append('arquivo', btn.dataset.arquivo);
            var res  = await fetch('<?= url('/samba/configuracao/restaurar') ?>', {method:'POST', body:fd});
            var data = await res.json();
            showToast(data.message, data.success);
            if (data.success) setTimeout(function() { location.reload(); }, 1500);
        } catch(ex) { showToast('Erro ao restaurar backup.', false); }
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo   = 'Configuração Global do Samba';
require __DIR__ . '/../layouts/main.php';
