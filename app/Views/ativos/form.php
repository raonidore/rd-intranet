<?php
ob_start();

use App\Components\Alert;
use App\Services\AtivoService;

$editando = $ativo !== null;
$detalhes = $ativo['detalhes'] ?? [];
$tipoIdAtual = (int)($ativo['tipo_id'] ?? $tipoIdSelecionado);
$tipoAtualNome = $ativo['tipo_nome'] ?? '';
$tipoAtualSlug = $ativo['tipo_slug'] ?? '';
$idsTiposComSnmp = array_column(array_filter($tipos, fn (array $t) => (bool)$t['snmp_elegivel']), 'id');
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
                        <input type="text" class="form-control" value="<?= htmlspecialchars($tipoAtualNome) ?>" disabled>
                    <?php else: ?>
                        <select name="tipo_id" id="campoTipo" class="form-select" required>
                            <?php foreach ($tipos as $t): ?>
                                <option value="<?= (int)$t['id'] ?>" <?= $tipoIdAtual === (int)$t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['nome']) ?></option>
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
                    <label class="form-label">Unidade</label>
                    <select name="unidade_id" class="form-select" required>
                        <?php foreach ($unidades as $u): ?>
                            <option value="<?= (int)$u['id'] ?>" <?= (int)($ativo['unidade_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
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

            <?php if ($editando && ($ativo['origem'] ?? null) === 'agente'): ?>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Identificador da máquina (machine_guid)</label>
                    <input type="text" name="machine_guid" class="form-control font-monospace" value="<?= htmlspecialchars($ativo['machine_guid'] ?? '') ?>">
                    <small class="text-muted">
                        Avançado -- normalmente não precisa mexer. O agente gera isso sozinho a partir do hardware.
                        Só corrija manualmente se a máquina foi <strong>reformatada</strong> e virou um ativo novo/
                        duplicado no inventário (aí é só copiar o novo identificador aqui pra "religar" este mesmo
                        cadastro), ou se duas máquinas colidiram no mesmo identificador por engano.
                    </small>
                </div>
            </div>
            <?php endif; ?>

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

    <div class="card border-0 shadow-sm mb-3" id="cardSnmp" style="<?= in_array($tipoIdAtual, $idsTiposComSnmp, true) ? '' : 'display:none' ?>">
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
            <?php foreach ($tipos as $t): ?>
                <?php $campos = AtivoService::CAMPOS_DETALHES[$t['slug'] ?? ''] ?? []; ?>
                <?php if (empty($campos)): continue; endif; ?>
                <div class="row g-3 bloco-detalhes" data-tipo-id="<?= (int)$t['id'] ?>" style="<?= $tipoIdAtual === (int)$t['id'] ? '' : 'display:none' ?>">
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

    const idsTiposComSnmp = <?= json_encode(array_values($idsTiposComSnmp)) ?>;
    const cardSnmp = document.getElementById('cardSnmp');

    function atualizarBlocos() {
        document.querySelectorAll('.bloco-detalhes').forEach(function (bloco) {
            bloco.style.display = bloco.dataset.tipoId === campoTipo.value ? '' : 'none';
        });

        if (cardSnmp) {
            cardSnmp.style.display = idsTiposComSnmp.includes(parseInt(campoTipo.value, 10)) ? '' : 'none';
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
