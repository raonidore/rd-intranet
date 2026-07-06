<?php
ob_start();

use App\Components\Alert;
?>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-signpost-split me-1"></i> Rotas</h4>
        <small class="text-muted">Tabela de roteamento atual. Só rotas criadas por aqui podem ser testadas/excluídas.</small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('/infraestrutura/rede') ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Interfaces
        </a>
        <a href="<?= url('/infraestrutura/rede/rotas/novo') ?>" class="btn btn-sm btn-primary">
            <i class="bi bi-plus-lg"></i> Nova Rota
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($rotas)): ?>
            <div class="text-center text-muted py-4">
                <i class="bi bi-signpost-split display-6"></i>
                <p class="mt-2">Nenhuma rota encontrada</p>
            </div>
        <?php else: ?>
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Destino</th>
                    <th>Via</th>
                    <th>Interface</th>
                    <th>Origem</th>
                    <th></th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rotas as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['destino']) ?></td>
                        <td><?= htmlspecialchars($r['via']) ?></td>
                        <td><?= htmlspecialchars($r['dev']) ?></td>
                        <td><?= htmlspecialchars($r['src']) ?></td>
                        <td>
                            <?= $r['gerenciada']
                                ? '<span class="badge text-bg-primary">RD Intranet</span>'
                                : '<span class="badge text-bg-secondary">Sistema</span>' ?>
                        </td>
                        <td class="text-end">
                            <?php if ($r['gerenciada'] && $r['via'] !== '-'): ?>
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-primary btn-testar" data-via="<?= htmlspecialchars($r['via']) ?>" title="Testar (ping no gateway)">
                                        <i class="bi bi-broadcast"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger btn-excluir" data-destino="<?= htmlspecialchars($r['destino']) ?>" title="Excluir">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="modalTeste" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Teste de conectividade</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <pre class="bg-dark text-light p-3 rounded mb-0" id="modal-teste-output" style="white-space:pre-wrap">Executando...</pre>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const TESTAR_URL = '<?= url('/infraestrutura/rede/rotas/testar') ?>';
    const EXCLUIR_URL = '<?= url('/infraestrutura/rede/rotas/excluir') ?>';
    const modalTeste = new bootstrap.Modal(document.getElementById('modalTeste'));

    document.querySelectorAll('.btn-testar').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            document.getElementById('modal-teste-output').textContent = 'Executando...';
            modalTeste.show();
            try {
                const fd = new FormData();
                fd.append('via', btn.dataset.via);
                const res = await fetch(TESTAR_URL, { method: 'POST', body: fd });
                const data = await res.json();
                document.getElementById('modal-teste-output').textContent = data.output;
            } catch (e) {
                document.getElementById('modal-teste-output').textContent = 'Erro ao testar.';
            }
        });
    });

    document.querySelectorAll('.btn-excluir').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            if (!confirm('Excluir a rota para ' + btn.dataset.destino + '?')) return;
            try {
                const fd = new FormData();
                fd.append('destino', btn.dataset.destino);
                const res = await fetch(EXCLUIR_URL, { method: 'POST', body: fd });
                const data = await res.json();
                alert(data.message);
                if (data.success) location.reload();
            } catch (e) {
                alert('Erro ao excluir.');
            }
        });
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Infraestrutura - Rotas';

require __DIR__ . '/../layouts/main.php';
