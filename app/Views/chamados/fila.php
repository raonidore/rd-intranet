<?php
ob_start();

use App\Components\Alert;
use App\Components\Badge;
use App\Services\ChamadoService;

$corPrioridade = ['baixa' => 'secondary', 'media' => 'primary', 'alta' => 'warning', 'urgente' => 'danger'];
?>

<?= Alert::flash() ?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-hourglass-split me-1"></i> Chamados - Fila</h4>
    <small class="text-muted">Chamados aguardando um atendente do(s) seu(s) setor(es).</small>
</div>

<?php if (empty($fila)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-muted py-4">
            <i class="bi bi-check2-circle" style="font-size:2rem;"></i>
            <p class="mb-0 mt-2">Nenhum chamado na fila no momento.</p>
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Título</th>
                        <th>Solicitante</th>
                        <th>Categoria</th>
                        <th>Setor</th>
                        <th>Prioridade</th>
                        <th>SLA</th>
                        <th>Aguardando há</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fila as $item): ?>
                        <tr>
                            <td class="font-monospace">#<?= htmlspecialchars($item['numero_controle'] ?? $item['id']) ?></td>
                            <td><?= htmlspecialchars($item['titulo']) ?></td>
                            <td><?= htmlspecialchars($item['solicitante_nome']) ?></td>
                            <td><?= htmlspecialchars($item['categoria_nome']) ?></td>
                            <td><?= $item['setor_nome'] ? htmlspecialchars($item['setor_nome']) : '<span class="text-muted">-</span>' ?></td>
                            <td><?= Badge::make(htmlspecialchars(ChamadoService::PRIORIDADES[$item['prioridade']]), $corPrioridade[$item['prioridade']] ?? 'secondary') ?></td>
                            <td>
                                <?php if ($item['sla_pausado_em']): ?>
                                    <span class="small text-muted"><i class="bi bi-pause-circle"></i> Pausado -- fora do expediente</span>
                                <?php elseif ($item['sla_resolucao_prazo']): ?>
                                    <span class="sla-contagem font-monospace small" data-prazo="<?= htmlspecialchars(str_replace(' ', 'T', $item['sla_resolucao_prazo'])) ?>">--</span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="tempo-espera fw-semibold" data-aberto-em="<?= htmlspecialchars(str_replace(' ', 'T', $item['aberto_em'])) ?>">--:--</span>
                            </td>
                            <td class="text-end">
                                <form method="post" action="<?= url('/chamados/fila/assumir') ?>">
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

        document.querySelectorAll('.sla-contagem').forEach(function (el) {
            var prazo = new Date(el.dataset.prazo);
            var restanteSeg = Math.floor((prazo - agora) / 1000);
            var estourado = restanteSeg < 0;
            var abs = Math.abs(restanteSeg);
            var horas = Math.floor(abs / 3600);
            var minutos = Math.floor((abs % 3600) / 60);
            var texto = (estourado ? '-' : '') + String(horas).padStart(2, '0') + ':' + String(minutos).padStart(2, '0');

            el.textContent = texto;
            el.classList.toggle('text-danger', estourado);
            el.classList.toggle('text-warning', !estourado && restanteSeg < 3600);
            el.classList.toggle('text-success', !estourado && restanteSeg >= 3600);
        });
    }

    atualizarTempos();
    setInterval(atualizarTempos, 1000);
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Chamados - Fila';

require __DIR__ . '/../layouts/main.php';
