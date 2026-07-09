<?php

use App\Components\Alert;
use App\Components\Badge;

ob_start();

$total = count($itens);
$instalados = count(array_filter($itens, fn($i) => $i['instalado'] === true));
$faltandoObrigatorio = count(array_filter($itens, fn($i) => $i['instalado'] === false && $i['obrigatorio']));
?>

<style>
.fw-card { border: 0; border-radius: 14px; box-shadow: 0 4px 14px rgba(0,0,0,.06); margin-bottom: 1.25rem; }
.fw-card .card-header { background: #f8fafc; border-bottom: 1px solid #e9ecef; border-radius: 14px 14px 0 0; padding: 14px 20px; }
</style>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-clipboard2-check me-1"></i> Checklist de Dependências</h4>
        <small class="text-muted">Ferramentas do sistema operacional que a RD Intranet usa para funcionar completamente.</small>
    </div>
</div>

<?php if ($faltandoObrigatorio > 0): ?>
    <div class="alert alert-warning">
        <strong><i class="bi bi-exclamation-triangle"></i> <?= $faltandoObrigatorio ?> item(ns) obrigatório(s) faltando.</strong>
        Alguns recursos podem não funcionar corretamente até instalar.
    </div>
<?php else: ?>
    <div class="alert alert-success">
        <i class="bi bi-check-circle"></i> Todos os itens obrigatórios estão instalados (<?= $instalados ?>/<?= $total ?> no total).
    </div>
<?php endif; ?>

<div class="card fw-card">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Ferramenta</th>
                    <th>Para que serve</th>
                    <th>Usado em</th>
                    <th>Importância</th>
                    <th>Status</th>
                    <th class="text-end">Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($itens as $item): ?>
                    <tr data-chave="<?= htmlspecialchars($item['chave']) ?>">
                        <td class="font-monospace"><?= htmlspecialchars($item['nome']) ?></td>
                        <td class="small text-muted"><?= htmlspecialchars($item['descricao']) ?></td>
                        <td class="small"><?= htmlspecialchars($item['usado_em']) ?></td>
                        <td><?= $item['obrigatorio'] ? Badge::make('Obrigatório', 'danger') : Badge::make('Opcional', 'secondary') ?></td>
                        <td class="celula-status">
                            <?= $item['instalado'] ? Badge::make('Instalado', 'success') : Badge::make('Não instalado', 'warning') ?>
                        </td>
                        <td class="text-end celula-acao">
                            <?php if (!$item['instalado']): ?>
                                <button type="button" class="btn btn-sm btn-primary botao-instalar" data-chave="<?= htmlspecialchars($item['chave']) ?>">
                                    <i class="bi bi-download"></i> Instalar
                                </button>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalInstalar" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Instalando</h5>
            </div>
            <div class="modal-body" id="modalInstalarCorpo">
                <div class="text-center text-muted py-3"><i class="bi bi-hourglass-split"></i> Instalando, pode levar até um minuto...</div>
            </div>
            <div class="modal-footer" id="modalInstalarRodape" style="display:none">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const INSTALAR_URL = <?= json_encode(url('/infraestrutura/dependencias/instalar')) ?>;

    document.querySelectorAll('.botao-instalar').forEach(function (botao) {
        botao.addEventListener('click', async function () {
            const chave = botao.dataset.chave;
            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalInstalar'));
            const corpo = document.getElementById('modalInstalarCorpo');
            const rodape = document.getElementById('modalInstalarRodape');

            corpo.innerHTML = '<div class="text-center text-muted py-3"><i class="bi bi-hourglass-split"></i> Instalando ' + chave + ', pode levar até um minuto...</div>';
            rodape.style.display = 'none';
            modal.show();
            botao.disabled = true;

            try {
                const res = await fetch(INSTALAR_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'chave=' + encodeURIComponent(chave)
                });
                const dados = await res.json();

                const cor = dados.success ? 'success' : 'danger';
                const icone = dados.success ? 'check-circle' : 'x-circle';
                let html = '<div class="alert alert-' + cor + '"><i class="bi bi-' + icone + '"></i> ' + dados.message + '</div>';
                if (dados.saida_completa) {
                    html += '<pre class="bg-dark text-light p-3 rounded mb-0" style="max-height:300px; overflow:auto; font-size:12px;">' +
                        dados.saida_completa.replace(/</g, '&lt;') + '</pre>';
                }
                corpo.innerHTML = html;
                rodape.style.display = '';

                if (dados.success) {
                    const linha = document.querySelector('tr[data-chave="' + chave + '"]');
                    if (linha) {
                        linha.querySelector('.celula-status').innerHTML = '<span class="badge text-bg-success">Instalado</span>';
                        linha.querySelector('.celula-acao').innerHTML = '<span class="text-muted small">—</span>';
                    }
                }
            } catch (e) {
                corpo.innerHTML = '<div class="alert alert-danger mb-0">Erro ao comunicar com o servidor.</div>';
                rodape.style.display = '';
            } finally {
                botao.disabled = false;
            }
        });
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Infraestrutura - Checklist de Dependências';

require __DIR__ . '/../layouts/main.php';
