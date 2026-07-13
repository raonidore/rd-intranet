<?php
ob_start();

use App\Components\Alert;
use App\Services\AtivoService;

$editando = $ativo !== null;
$detalhes = $ativo['detalhes'] ?? [];
$tipoAtual = $ativo['tipo'] ?? $tipoSelecionado;
?>

<?= Alert::flash() ?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-<?= $editando ? 'pencil' : 'plus-lg' ?> me-1"></i> <?= $editando ? 'Editar Ativo' : 'Novo Ativo' ?></h4>
    <small class="text-muted"><a href="<?= url('/ativos/lista') ?>"><i class="bi bi-arrow-left"></i> Voltar para a lista</a></small>
</div>

<form method="post" action="<?= url($editando ? '/ativos/editar' : '/ativos/novo') ?>">
    <?php if ($editando): ?>
        <input type="hidden" name="id" value="<?= (int)$ativo['id'] ?>">
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white"><strong>Dados gerais</strong></div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <label class="form-label">Tipo</label>
                    <?php if ($editando): ?>
                        <input type="text" class="form-control" value="<?= htmlspecialchars(AtivoService::TIPOS[$tipoAtual]['label']) ?>" disabled>
                    <?php else: ?>
                        <select name="tipo" id="campoTipo" class="form-select" required>
                            <?php foreach (AtivoService::TIPOS as $chave => $info): ?>
                                <option value="<?= $chave ?>" <?= $tipoAtual === $chave ? 'selected' : '' ?>><?= htmlspecialchars($info['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>
                <?php if ($editando): ?>
                    <div class="col-md-3">
                        <label class="form-label">Código</label>
                        <input type="text" class="form-control font-monospace" value="<?= htmlspecialchars($ativo['codigo_patrimonio']) ?>" disabled>
                    </div>
                <?php endif; ?>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <?php foreach (AtivoService::STATUS as $chave => $label): ?>
                            <option value="<?= $chave ?>" <?= ($ativo['status'] ?? 'ativo') === $chave ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-<?= $editando ? '3' : '6' ?>">
                    <label class="form-label">Nome / Identificação</label>
                    <input type="text" name="nome" class="form-control" required value="<?= htmlspecialchars($ativo['nome'] ?? '') ?>" placeholder="Ex: Notebook Financeiro 01">
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Apelido</label>
                    <input type="text" name="apelido" class="form-control" value="<?= htmlspecialchars($ativo['apelido'] ?? '') ?>" placeholder="Ex: Notebook da Ana">
                    <?php if (($ativo['origem'] ?? null) === 'agente'): ?>
                        <small class="text-muted">Diferente do Nome (que o agente sobrescreve a cada check-in com o hostname do Windows), o apelido é só seu -- fica do jeito que você definir. Aparece na etiqueta.</small>
                    <?php else: ?>
                        <small class="text-muted">Um nome informal, à sua escolha, pra facilitar a identificação. Aparece na etiqueta.</small>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <label class="form-label">Marca / Fabricante</label>
                    <input type="text" name="marca" class="form-control" value="<?= htmlspecialchars($ativo['marca'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Modelo</label>
                    <input type="text" name="modelo" class="form-control" value="<?= htmlspecialchars($ativo['modelo'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Nº de série</label>
                    <input type="text" name="numero_serie" class="form-control" value="<?= htmlspecialchars($ativo['numero_serie'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">IP</label>
                    <input type="text" name="ip" class="form-control" value="<?= htmlspecialchars($ativo['ip'] ?? '') ?>" placeholder="192.168.0.10">
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label">Setor</label>
                    <select name="setor_id" class="form-select">
                        <option value="">— Nenhum —</option>
                        <?php foreach ($setores as $s): ?>
                            <option value="<?= (int)$s['id'] ?>" <?= (int)($ativo['setor_id'] ?? 0) === (int)$s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Não achou? <a href="<?= url('/ativos/cadastros') ?>" target="_blank">Cadastre um novo setor</a>.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Localização</label>
                    <select name="localizacao_id" class="form-select">
                        <option value="">— Nenhuma —</option>
                        <?php foreach ($localizacoes as $l): ?>
                            <option value="<?= (int)$l['id'] ?>" <?= (int)($ativo['localizacao_id'] ?? 0) === (int)$l['id'] ? 'selected' : '' ?>><?= htmlspecialchars($l['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Não achou? <a href="<?= url('/ativos/cadastros') ?>" target="_blank">Cadastre uma nova localização</a>.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Responsável</label>
                    <input type="text" name="responsavel" class="form-control" value="<?= htmlspecialchars($ativo['responsavel'] ?? '') ?>">
                </div>
            </div>

            <div class="mb-0">
                <label class="form-label">Observações</label>
                <textarea name="observacoes" class="form-control" rows="2"><?= htmlspecialchars($ativo['observacoes'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3" id="cardSnmp" style="<?= in_array($tipoAtual, AtivoService::TIPOS_COM_SNMP, true) ? '' : 'display:none' ?>">
        <div class="card-header bg-white"><strong>Coleta via SNMP</strong></div>
        <div class="card-body">
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" name="snmp_habilitado" id="campoSnmpHabilitado"
                       <?= !empty($ativo['snmp_habilitado']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="campoSnmpHabilitado">Habilitar coleta automática via SNMP para este ativo</label>
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Community SNMP (opcional)</label>
                    <input type="text" name="snmp_community" class="form-control" value="<?= htmlspecialchars($ativo['snmp_community'] ?? '') ?>" placeholder="Deixe em branco para usar a padrão">
                    <div class="form-text">Só preencha se este dispositivo usa uma community diferente da padrão configurada no Dashboard de Ativos. Requer o IP preenchido acima.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white"><strong>Detalhes técnicos</strong></div>
        <div class="card-body">
            <?php foreach (AtivoService::CAMPOS_DETALHES as $tipo => $campos): ?>
                <div class="row g-3 bloco-detalhes" data-tipo="<?= $tipo ?>" style="<?= $tipoAtual === $tipo ? '' : 'display:none' ?>">
                    <?php foreach ($campos as $campo => $label): ?>
                        <div class="col-md-4">
                            <label class="form-label"><?= htmlspecialchars($label) ?></label>
                            <input type="text" name="<?= $campo ?>" class="form-control" value="<?= htmlspecialchars($detalhes[$campo] ?? '') ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
    <a href="<?= url('/ativos/lista') ?>" class="btn btn-secondary">Cancelar</a>
</form>

<script>
(function () {
    const campoTipo = document.getElementById('campoTipo');
    if (!campoTipo) return;

    const tiposComSnmp = <?= json_encode(AtivoService::TIPOS_COM_SNMP) ?>;
    const cardSnmp = document.getElementById('cardSnmp');

    function atualizarBlocos() {
        document.querySelectorAll('.bloco-detalhes').forEach(function (bloco) {
            bloco.style.display = bloco.dataset.tipo === campoTipo.value ? '' : 'none';
        });

        if (cardSnmp) {
            cardSnmp.style.display = tiposComSnmp.includes(campoTipo.value) ? '' : 'none';
        }
    }

    campoTipo.addEventListener('change', atualizarBlocos);
    atualizarBlocos();
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = $editando ? 'Editar Ativo' : 'Novo Ativo';

require __DIR__ . '/../layouts/main.php';
