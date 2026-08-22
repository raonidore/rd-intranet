<?php
ob_start();

use App\Components\Alert;
?>

<?= Alert::flash() ?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-hourglass-split me-1"></i> WhatsApp - Fila</h4>
    <small class="text-muted">Atendimentos aguardando um atendente do(s) seu(s) setor(es).</small>
</div>

<?php if (empty($fila)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-muted py-4">
            <i class="bi bi-check2-circle" style="font-size:2rem;"></i>
            <p class="mb-0 mt-2">Nenhum atendimento na fila no momento.</p>
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Contato</th>
                        <th>Número</th>
                        <th>Setor</th>
                        <th>Aguardando desde</th>
                        <th>Tempo</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fila as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['contato_nome'] ?: '(sem nome)') ?></td>
                            <td><code><?= htmlspecialchars(telefone_br($item['numero'])) ?></code></td>
                            <td><?= $item['setor_nome'] ? htmlspecialchars($item['setor_nome']) : '<span class="text-muted">-</span>' ?></td>
                            <td><?= data_br($item['aberto_em']) ?></td>
                            <td>
                                <span class="tempo-espera fw-semibold" data-aberto-em="<?= htmlspecialchars(str_replace(' ', 'T', $item['aberto_em'])) ?>">--:--</span>
                            </td>
                            <td class="text-end">
                                <form method="post" action="<?= url('/whatsapp/fila/assumir') ?>">
                                    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        <i class="bi bi-hand-index-thumb"></i> Assumir
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<script>
(function () {
    function atualizarTempos() {
        var agora = new Date();

        document.querySelectorAll('.tempo-espera').forEach(function (el) {
            var abertoEm = new Date(el.dataset.abertoEm);
            var segundos = Math.max(0, Math.floor((agora - abertoEm) / 1000));
            var horas = Math.floor(segundos / 3600);
            var minutos = Math.floor((segundos % 3600) / 60);

            el.textContent = String(horas).padStart(2, '0') + ':' + String(minutos).padStart(2, '0');
        });
    }

    atualizarTempos();
    setInterval(atualizarTempos, 1000);
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'WhatsApp - Fila';

require __DIR__ . '/../layouts/main.php';
