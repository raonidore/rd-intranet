<?php
ob_start();

use App\Components\Alert;
use App\Components\Badge;
use App\Services\AtivoService;

$statusCores = [
    'ativo' => 'success',
    'manutencao' => 'warning',
    'estoque' => 'secondary',
    'baixado' => 'danger',
];

$detalhes = $ativo['detalhes'] ?? [];
$camposTipo = AtivoService::CAMPOS_DETALHES[$ativo['tipo']] ?? [];
?>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <small class="text-muted"><a href="<?= url('/ativos/lista') ?>"><i class="bi bi-arrow-left"></i> Lista de Ativos</a></small>
        <h4 class="mb-1 mt-1">
            <i class="bi <?= AtivoService::TIPOS[$ativo['tipo']]['icone'] ?> me-1"></i>
            <?= htmlspecialchars($ativo['nome']) ?>
        </h4>
        <span class="font-monospace text-muted"><?= htmlspecialchars($ativo['codigo_patrimonio']) ?></span>
        <?= Badge::make(htmlspecialchars(AtivoService::STATUS[$ativo['status']] ?? $ativo['status']), $statusCores[$ativo['status']] ?? 'secondary') ?>
        <?php if ($ativo['origem'] === 'agente'): ?>
            <?= Badge::make($estaLigada ? '<i class="bi bi-circle-fill" style="font-size:8px"></i> Ligada' : 'Offline', $estaLigada ? 'success' : 'secondary') ?>
            <?php if ($estaLigada && $uptime): ?>
                <span class="text-muted small">uptime: <?= htmlspecialchars($uptime) ?></span>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-2">
        <?php if (!empty($ativo['snmp_habilitado']) && !empty($ativo['ip'])): ?>
            <button type="button" class="btn btn-outline-secondary" id="botaoColetarSnmp" data-id="<?= (int)$ativo['id'] ?>">
                <i class="bi bi-arrow-repeat"></i> Coletar via SNMP
            </button>
        <?php endif; ?>
        <?php if ($ativo['origem'] === 'agente'): ?>
            <div class="btn-group">
                <button type="button" class="btn btn-outline-warning botao-comando" data-id="<?= (int)$ativo['id'] ?>" data-comando="reiniciar">
                    <i class="bi bi-arrow-clockwise"></i> Reiniciar
                </button>
                <button type="button" class="btn btn-outline-danger botao-comando" data-id="<?= (int)$ativo['id'] ?>" data-comando="desligar">
                    <i class="bi bi-power"></i> Desligar
                </button>
            </div>
        <?php endif; ?>
        <a href="<?= url('/ativos/etiqueta?id=' . $ativo['id']) ?>" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-qr-code"></i> Etiqueta</a>
        <a href="<?= url('/ativos/editar?id=' . $ativo['id']) ?>" class="btn btn-primary"><i class="bi bi-pencil"></i> Editar</a>
        <a href="<?= url('/ativos/excluir?id=' . $ativo['id']) ?>" class="btn btn-outline-danger"><i class="bi bi-trash"></i></a>
    </div>
</div>

<?php if (!empty($ativo['ultimo_checkin'])): ?>
    <div class="alert alert-light border small mb-4">
        <i class="bi bi-clock-history"></i> Última atualização automática: <?= htmlspecialchars(data_br($ativo['ultimo_checkin'])) ?>
        via <?= $ativo['origem'] === 'agente' ? 'agente Windows' : ($ativo['origem'] === 'snmp' ? 'SNMP' : 'manual') ?>.
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white"><strong>Dados gerais</strong></div>
            <div class="card-body">
                <div class="stat-mini-row d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Marca / Fabricante</span><span><?= htmlspecialchars($ativo['marca'] ?? '—') ?></span>
                </div>
                <div class="stat-mini-row d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Modelo</span><span><?= htmlspecialchars($ativo['modelo'] ?? '—') ?></span>
                </div>
                <div class="stat-mini-row d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Nº de série</span><span><?= htmlspecialchars($ativo['numero_serie'] ?? '—') ?></span>
                </div>
                <div class="stat-mini-row d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">IP</span><span><?= htmlspecialchars($ativo['ip'] ?? '—') ?></span>
                </div>
                <div class="stat-mini-row d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Setor</span><span><?= htmlspecialchars($ativo['setor_nome'] ?? '—') ?></span>
                </div>
                <div class="stat-mini-row d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Localização</span><span><?= htmlspecialchars($ativo['localizacao_nome'] ?? '—') ?></span>
                </div>
                <div class="stat-mini-row d-flex justify-content-between py-2">
                    <span class="text-muted">Responsável</span><span><?= htmlspecialchars($ativo['responsavel'] ?? '—') ?></span>
                </div>
                <?php if (!empty($ativo['observacoes'])): ?>
                    <hr>
                    <div class="text-muted small mb-1">Observações</div>
                    <p class="mb-0"><?= nl2br(htmlspecialchars($ativo['observacoes'])) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white"><strong>Detalhes técnicos</strong></div>
            <div class="card-body">
                <?php if (empty($camposTipo) || empty($detalhes)): ?>
                    <p class="text-muted mb-0">Nenhum detalhe técnico preenchido ainda.</p>
                <?php else: ?>
                    <?php foreach ($camposTipo as $campo => $label): ?>
                        <?php if (!empty($detalhes[$campo])): ?>
                            <div class="d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted"><?= htmlspecialchars($label) ?></span>
                                <span><?= htmlspecialchars($detalhes[$campo]) ?></span>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><strong>Programas instalados</strong></div>
            <div class="card-body p-0">
                <?php if (empty($programas)): ?>
                    <p class="text-muted p-3 mb-0">Nenhum programa coletado ainda. Isso é preenchido automaticamente pelo agente Windows quando instalado neste ativo.</p>
                <?php else: ?>
                    <table class="table table-sm mb-0">
                        <tbody>
                            <?php foreach ($programas as $p): ?>
                                <tr>
                                    <td><?= htmlspecialchars($p['nome']) ?></td>
                                    <td class="text-muted small text-end"><?= htmlspecialchars($p['versao'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><strong>Alertas</strong></div>
            <div class="card-body p-0">
                <?php if (empty($alertas)): ?>
                    <p class="text-muted p-3 mb-0">Nenhum alerta coletado ainda. Isso é preenchido automaticamente pelo agente Windows quando instalado neste ativo.</p>
                <?php else: ?>
                    <table class="table table-sm mb-0">
                        <tbody>
                            <?php foreach ($alertas as $al): ?>
                                <tr>
                                    <td><?= Badge::make(htmlspecialchars($al['nivel']), $al['nivel'] === 'erro' ? 'danger' : ($al['nivel'] === 'aviso' ? 'warning' : 'secondary')) ?></td>
                                    <td class="small"><?= htmlspecialchars($al['mensagem']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($ativo['origem'] === 'agente'): ?>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><strong>Comandos remotos</strong></div>
                <div class="card-body p-0">
                    <?php if (empty($comandos)): ?>
                        <p class="text-muted p-3 mb-0">Nenhum comando enviado ainda.</p>
                    <?php else: ?>
                        <table class="table table-sm mb-0">
                            <tbody>
                                <?php foreach ($comandos as $c): ?>
                                    <tr>
                                        <td class="text-capitalize"><?= htmlspecialchars($c['comando']) ?></td>
                                        <td><?= Badge::make($c['status'] === 'entregue' ? 'Entregue' : 'Pendente', $c['status'] === 'entregue' ? 'success' : 'secondary') ?></td>
                                        <td class="text-muted small text-end"><?= htmlspecialchars(data_br($c['criado_em'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
(function () {
    const botao = document.getElementById('botaoColetarSnmp');
    if (!botao) return;

    botao.addEventListener('click', async function () {
        botao.disabled = true;
        botao.innerHTML = '<i class="bi bi-hourglass-split"></i> Coletando...';

        const dados = new URLSearchParams();
        dados.set('id', botao.dataset.id);

        try {
            const res = await fetch(<?= json_encode(url('/ativos/coletar-snmp')) ?>, { method: 'POST', body: dados });
            const resultado = await res.json();
            alert(resultado.message || (resultado.success ? 'Coletado.' : 'Falha ao coletar.'));
            if (resultado.success) location.reload();
        } catch (e) {
            alert('Erro ao comunicar com o servidor.');
        } finally {
            botao.disabled = false;
            botao.innerHTML = '<i class="bi bi-arrow-repeat"></i> Coletar via SNMP';
        }
    });
})();

(function () {
    document.querySelectorAll('.botao-comando').forEach(function (botao) {
        botao.addEventListener('click', async function () {
            const comando = botao.dataset.comando;
            const label = comando === 'desligar' ? 'DESLIGAR' : 'REINICIAR';

            const confirmado = confirm(
                'Tem certeza que quer ' + label + ' esta máquina remotamente?\n\n' +
                'O usuário vai receber um aviso do Windows com alguns minutos de contagem ' +
                'regressiva antes de acontecer (dá tempo de salvar o trabalho ou cancelar ' +
                'localmente). Pode levar até 30 minutos pra chegar até lá, dependendo de ' +
                'quando o agente fizer a próxima coleta.'
            );

            if (!confirmado) return;

            botao.disabled = true;

            const dados = new URLSearchParams();
            dados.set('id', botao.dataset.id);
            dados.set('comando', comando);

            try {
                const res = await fetch(<?= json_encode(url('/ativos/comando')) ?>, { method: 'POST', body: dados });
                const resultado = await res.json();
                alert(resultado.message || (resultado.success ? 'Comando enviado.' : 'Falha ao enviar comando.'));
                if (resultado.success) location.reload();
            } catch (e) {
                alert('Erro ao comunicar com o servidor.');
            } finally {
                botao.disabled = false;
            }
        });
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Ativo - ' . $ativo['nome'];

require __DIR__ . '/../layouts/main.php';
