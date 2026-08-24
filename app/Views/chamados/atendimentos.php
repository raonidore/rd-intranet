<?php
ob_start();

use App\Components\Alert;
use App\Components\Badge;
use App\Services\ChamadoService;

$corPrioridade = ['baixa' => 'secondary', 'media' => 'primary', 'alta' => 'warning', 'urgente' => 'danger'];
$corStatus = ['fila' => 'secondary', 'em_atendimento' => 'primary', 'aguardando_cliente' => 'warning', 'resolvido' => 'success', 'fechado' => 'dark'];
?>

<?= Alert::flash() ?>

<div class="mb-4 d-flex justify-content-between align-items-start">
    <div>
        <h4 class="mb-1"><i class="bi bi-ticket-perforated me-1"></i> Chamados - Atendimentos</h4>
        <small class="text-muted">Seus chamados em andamento. Novos chamados aparecem em <a href="<?= url('/chamados/fila') ?>">Fila</a>.</small>
    </div>
    <div class="d-flex gap-2">
        <button type="button" id="btnSomChamados" class="btn btn-outline-secondary" title="Ativar som para chamados novos">
            <i class="bi bi-volume-mute"></i>
        </button>
        <a href="<?= url('/chamados/atendimentos/novo') ?>" class="btn btn-primary text-nowrap"><i class="bi bi-plus-lg"></i> Abrir Chamado</a>
    </div>
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link <?= $aba === 'andamento' ? 'active' : '' ?>" href="<?= url('/chamados/atendimentos?aba=andamento') ?>">Em andamento</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $aba === 'encerrados' ? 'active' : '' ?>" href="<?= url('/chamados/atendimentos?aba=encerrados') ?>">Encerrados</a>
    </li>
</ul>

<?php $lista = $aba === 'encerrados' ? $encerrados : $chamados; ?>

<?php if (empty($lista)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-muted py-4">
            <i class="bi bi-ticket" style="font-size:2rem;"></i>
            <p class="mb-0 mt-2"><?= $aba === 'encerrados' ? 'Nenhum chamado encerrado ainda.' : 'Você não tem nenhum chamado em andamento.' ?></p>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($lista as $item): ?>
        <a href="<?= url('/chamados/atendimentos/ver?id=' . (int)$item['id']) ?>" class="text-decoration-none text-reset">
            <div class="card border-0 shadow-sm mb-2">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div style="min-width:0">
                        <span class="font-monospace text-muted small">#<?= (int)$item['id'] ?></span>
                        <strong><?= htmlspecialchars($item['titulo']) ?></strong>
                        <?= Badge::make(htmlspecialchars(ChamadoService::STATUS[$item['status']]), $corStatus[$item['status']] ?? 'secondary') ?>
                        <?= Badge::make(htmlspecialchars(ChamadoService::PRIORIDADES[$item['prioridade']]), $corPrioridade[$item['prioridade']] ?? 'secondary') ?>
                        <div class="text-muted small">
                            <?= htmlspecialchars($item['solicitante_nome']) ?> ·
                            <?= htmlspecialchars($item['categoria_nome']) ?> ·
                            <?= htmlspecialchars($item['unidade_nome']) ?>
                        </div>
                    </div>
                    <small class="text-muted text-nowrap"><?= data_br($item['ultima_mensagem_em']) ?></small>
                </div>
            </div>
        </a>
    <?php endforeach; ?>
<?php endif; ?>

<script>
(function () {
    const CHAVE_SOM = 'chamadosSomAtivo';
    const btnSom = document.getElementById('btnSomChamados');
    const icone = btnSom.querySelector('i');

    function somAtivo() {
        return localStorage.getItem(CHAVE_SOM) === '1';
    }

    function atualizarIcone() {
        icone.className = somAtivo() ? 'bi bi-volume-up-fill' : 'bi bi-volume-mute';
        btnSom.classList.toggle('btn-primary', somAtivo());
        btnSom.classList.toggle('btn-outline-secondary', !somAtivo());
        btnSom.title = somAtivo() ? 'Som ativado -- clique pra desativar' : 'Ativar som para chamados novos';
    }

    btnSom.addEventListener('click', () => {
        const vaiLigar = !somAtivo();
        localStorage.setItem(CHAVE_SOM, vaiLigar ? '1' : '0');
        atualizarIcone();

        if (vaiLigar && typeof window.rdTocarBip === 'function') {
            window.rdTocarBip();
        }
    });

    atualizarIcone();
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Chamados - Atendimentos';

require __DIR__ . '/../layouts/main.php';
