<?php
ob_start();

use App\Components\Alert;
use App\Services\SegurancaEventoService;
use App\Services\SegurancaModuloService;

$tiposEvento = SegurancaEventoService::TIPOS_AGENTE + SegurancaEventoService::TIPOS_SERVIDOR;
$modoPadrao = SegurancaModuloService::MODOS_ISOLAMENTO[$padraoSeguranca['isolamento_modo']] ?? $padraoSeguranca['isolamento_modo'];
$detectoresLigados = array_keys(array_filter(
    array_intersect_key($padraoSeguranca, SegurancaModuloService::MODULOS)
));
$numero = static fn ($v) => (int)($v ?? 0);
?>

<?= Alert::flash() ?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-shield-lock me-1"></i> Central de Segurança</h4>
        <small class="text-muted">
            Máquinas isoladas, isolamentos pendentes e detecções do módulo anti-ransomware de todas as máquinas.
        </small>
    </div>
    <div class="small text-md-end">
        <div>
            Resposta padrão: <strong><?= htmlspecialchars(strtok($modoPadrao, '--')) ?></strong>
        </div>
        <div class="text-muted">
            Detectores ligados por padrão:
            <?= $detectoresLigados
                ? htmlspecialchars(implode(', ', array_map(fn ($m) => SegurancaModuloService::MODULOS[$m], $detectoresLigados)))
                : 'nenhum' ?>
        </div>
        <?php if (\App\Services\PermissionService::temAcesso('ativos_dashboard')): ?>
            <a href="<?= url('/ativos') ?>#tabAntiRansomware" class="small">Alterar padrão</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php
        $cartoes = [
            ['Máquinas isoladas agora', count($isoladas), 'bi-ethernet', count($isoladas) > 0 ? 'danger' : 'success'],
            ['Isolamentos pendentes', $numero($resumo['isolamentos_pendentes'] ?? 0), 'bi-hourglass-split', $numero($resumo['isolamentos_pendentes'] ?? 0) > 0 ? 'danger' : 'secondary'],
            ['Críticos sem resolução', $numero($resumo['criticos_abertos'] ?? 0), 'bi-exclamation-octagon', $numero($resumo['criticos_abertos'] ?? 0) > 0 ? 'danger' : 'secondary'],
            ['Avisos sem resolução', $numero($resumo['avisos_abertos'] ?? 0), 'bi-exclamation-triangle', $numero($resumo['avisos_abertos'] ?? 0) > 0 ? 'warning' : 'secondary'],
            ['Detecções em 7 dias', $numero($resumo['deteccoes_7d'] ?? 0) . ' em ' . $numero($resumo['maquinas_7d'] ?? 0) . ' máquina(s)', 'bi-activity', 'primary'],
        ];
    ?>
    <?php foreach ($cartoes as [$rotulo, $valor, $icone, $cor]): ?>
        <div class="col-6 col-lg">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted mb-1"><i class="bi <?= $icone ?> text-<?= $cor ?>"></i> <?= htmlspecialchars($rotulo) ?></div>
                    <div class="fs-4 fw-semibold text-<?= $cor === 'secondary' ? 'body' : $cor ?>"><?= htmlspecialchars((string)$valor) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card border-0 shadow-sm mb-4 <?= $isoladas ? 'border-start border-danger border-4' : '' ?>">
    <div class="card-header bg-white"><strong><i class="bi bi-ethernet"></i> Máquinas isoladas da rede</strong></div>
    <div class="card-body p-0">
        <?php if (!$isoladas): ?>
            <p class="text-muted small p-3 mb-0">Nenhuma máquina isolada agora.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>Máquina</th><th>Isolada desde</th><th>Último sinal do agente</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($isoladas as $maquina): ?>
                            <tr>
                                <td class="small">
                                    <a href="<?= url('/ativos/ver?id=' . (int)$maquina['ativo_id'] . '#abaSeguranca') ?>"><?= htmlspecialchars($maquina['codigo_patrimonio']) ?></a>
                                    <span class="text-muted"><?= htmlspecialchars($maquina['nome']) ?></span>
                                </td>
                                <td class="small text-nowrap"><?= htmlspecialchars(data_br($maquina['isolado_em'], 'd/m/Y H:i')) ?></td>
                                <td class="small text-nowrap"><?= $maquina['ultimo_heartbeat'] ? htmlspecialchars(data_br($maquina['ultimo_heartbeat'], 'd/m/Y H:i:s')) : '—' ?></td>
                                <td class="text-end">
                                    <?php if ($podeEditarAtivo): ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary js-seguranca-acao" data-acao="remover-isolamento"
                                                data-ativo="<?= (int)$maquina['ativo_id'] ?>"
                                                data-confirmar="Remover o isolamento de <?= htmlspecialchars($maquina['codigo_patrimonio']) ?> e devolver a rede ao normal?">Remover isolamento</button>
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

<?php if ($pendentes): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white"><strong><i class="bi bi-exclamation-octagon"></i> Aguardando análise</strong>
        <small class="text-muted ms-1">críticos e avisos ainda não marcados como resolvidos</small></div>
    <div class="card-body p-0">
        <?php
            $eventosTabela = $pendentes;
            $mostrarMaquina = true;
            $podeEditarTabela = $podeEditarAtivo;
            require __DIR__ . '/_eventos_seguranca.php';
        ?>
    </div>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <form method="get" action="<?= url('/ativos/seguranca') ?>" class="row g-2 align-items-center">
            <div class="col-12 col-lg-auto me-auto"><strong><i class="bi bi-clock-history"></i> Histórico</strong></div>
            <div class="col-6 col-lg-auto">
                <select name="dias" class="form-select form-select-sm" aria-label="Período">
                    <?php foreach ([1 => 'Últimas 24h', 7 => 'Últimos 7 dias', 30 => 'Últimos 30 dias', 90 => 'Últimos 90 dias', 365 => 'Último ano'] as $dias => $rotulo): ?>
                        <option value="<?= $dias ?>" <?= (int)$filtros['dias'] === $dias ? 'selected' : '' ?>><?= $rotulo ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-lg-auto">
                <select name="severidade" class="form-select form-select-sm" aria-label="Severidade">
                    <option value="">Todas as severidades</option>
                    <?php foreach (['CRITICAL' => 'Crítico', 'WARNING' => 'Aviso', 'INFO' => 'Informativo'] as $sev => $rotulo): ?>
                        <option value="<?= $sev ?>" <?= $filtros['severidade'] === $sev ? 'selected' : '' ?>><?= $rotulo ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-8 col-lg-auto">
                <select name="tipo" class="form-select form-select-sm" aria-label="Tipo">
                    <option value="">Todos os tipos</option>
                    <?php foreach ($tiposEvento as $tipo => $rotulo): ?>
                        <option value="<?= $tipo ?>" <?= $filtros['tipo'] === $tipo ? 'selected' : '' ?>><?= htmlspecialchars($rotulo) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-4 col-lg-auto">
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" name="abertos" value="1" id="filtroAbertos" <?= $filtros['abertos'] ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="filtroAbertos">Só abertos</label>
                </div>
            </div>
            <div class="col-12 col-lg-auto"><button class="btn btn-sm btn-outline-primary w-100">Filtrar</button></div>
        </form>
    </div>
    <div class="card-body p-0">
        <?php
            $eventosTabela = $eventos;
            $mostrarMaquina = true;
            $podeEditarTabela = $podeEditarAtivo;
            require __DIR__ . '/_eventos_seguranca.php';
        ?>
    </div>
</div>

<script>
(function () {
    const rotas = {
        'remover-isolamento': <?= json_encode(url('/ativos/seguranca/remover-isolamento')) ?>,
        'cancelar-isolamento': <?= json_encode(url('/ativos/seguranca/cancelar-isolamento')) ?>,
        'resolver-evento': <?= json_encode(url('/ativos/seguranca/resolver-evento')) ?>,
    };

    document.querySelectorAll('.js-seguranca-acao').forEach(function (botao) {
        botao.addEventListener('click', async function () {
            if (botao.dataset.confirmar && !confirm(botao.dataset.confirmar)) {
                return;
            }

            const corpo = new URLSearchParams({ id: botao.dataset.ativo });
            if (botao.dataset.evento) {
                corpo.append('evento_id', botao.dataset.evento);
            }

            botao.disabled = true;
            try {
                const res = await fetch(rotas[botao.dataset.acao], { method: 'POST', body: corpo });
                const dados = await res.json();
                if (!dados.success) {
                    alert(dados.message || 'Não foi possível concluir a ação.');
                    botao.disabled = false;
                    return;
                }
                if (dados.message) {
                    alert(dados.message);
                }
                location.reload();
            } catch (e) {
                alert('Falha de comunicação com o servidor.');
                botao.disabled = false;
            }
        });
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Ativos - Central de Segurança';

require __DIR__ . '/../layouts/main.php';
