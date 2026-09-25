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
                        <div class="input-group">
                            <input type="text" class="form-control font-monospace" id="campoCodigoAtivo" value="<?= htmlspecialchars($ativo['codigo_patrimonio']) ?>" disabled>
                            <button type="button" class="btn btn-outline-secondary" id="botaoAtualizarCodigo" data-id="<?= (int)$ativo['id'] ?>" title="Gerar um código novo pra unidade atual (use depois de mudar a unidade -- salve a mudança de unidade primeiro)">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                        </div>
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
                    <input type="text" name="nome" class="form-control" required value="<?= htmlspecialchars($ativo['nome'] ?? $prefillNome ?? '') ?>" placeholder="Ex: Notebook Financeiro 01">
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
                    <div class="input-group">
                        <input type="text" name="ip" id="campoIp" class="form-control" value="<?= htmlspecialchars($ativo['ip'] ?? $prefillIp ?? '') ?>" placeholder="192.168.0.10">
                        <?php if (!$editando): ?>
                            <button type="button" class="btn btn-outline-primary" id="botaoDetectarPorIp" title="Buscar esse IP nas integrações de rede (UniFi/Omada) e preencher tipo/marca/modelo sozinho">
                                <i class="bi bi-search"></i> Detectar
                            </button>
                        <?php endif; ?>
                    </div>
                    <?php if (!$editando): ?>
                        <div class="form-text" id="textoDetectarPorIp">Digite o IP e clique em "Detectar" pra preencher tipo, marca e modelo automaticamente, se o equipamento já estiver no UniFi ou Omada.</div>
                    <?php endif; ?>
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

<div class="modal fade" id="modalRegenerarCodigo" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-arrow-repeat"></i> Gerar novo código</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="regenerarCodigoAlerta"></div>
                <p class="small text-muted mb-3">Código atual: <span class="font-monospace" id="regenerarCodigoAtual"></span></p>
                <label class="form-label">Novo número</label>
                <input type="number" min="1" class="form-control" id="regenerarCodigoNumero">
                <small class="text-muted d-block mt-1">Sugestão automática já preenchida (próximo da sequência) -- edite se quiser um número específico. A sigla da empresa/unidade/tipo continua vindo do cadastro do ativo.</small>
                <div class="mt-2">
                    <small class="text-muted">Novo código ficará: <strong class="font-monospace" id="regenerarCodigoPrevia">--</strong></small>
                </div>
                <div class="alert alert-warning small mt-3 mb-0">
                    Se o equipamento já tem etiqueta impressa, ela precisa ser reimpressa e trocada -- o código antigo deixa de existir.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="botaoConfirmarRegenerarCodigo">
                    <i class="bi bi-check-lg"></i> Confirmar
                </button>
            </div>
        </div>
    </div>
</div>

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

(function () {
    const botao = document.getElementById('botaoDetectarPorIp');
    if (!botao) return;

    const tipoIdsPorSlug = <?= json_encode(array_column($tipos, 'id', 'slug')) ?>;
    const campoIp = document.getElementById('campoIp');
    const campoTipo = document.getElementById('campoTipo');
    const campoNome = document.querySelector('input[name="nome"]');
    const campoMarca = document.querySelector('input[name="marca"]');
    const campoModelo = document.querySelector('input[name="modelo"]');
    const texto = document.getElementById('textoDetectarPorIp');
    const textoOriginal = botao.innerHTML;

    botao.addEventListener('click', async function () {
        const ip = campoIp.value.trim();
        if (!ip) {
            texto.textContent = 'Digite um IP primeiro.';
            texto.classList.remove('text-success');
            texto.classList.add('text-danger');
            return;
        }

        botao.disabled = true;
        botao.innerHTML = '<i class="bi bi-hourglass-split"></i>';
        texto.classList.remove('text-danger', 'text-success');
        texto.textContent = 'Buscando nas integrações configuradas...';

        const dados = new URLSearchParams();
        dados.set('ip', ip);

        try {
            const res = await fetch(<?= json_encode(url('/ativos/detectar-por-ip')) ?>, { method: 'POST', body: dados });
            const resultado = await res.json();

            texto.textContent = resultado.message || (resultado.success ? 'Encontrado.' : 'Não encontrado.');
            texto.classList.add(resultado.success ? 'text-success' : 'text-danger');

            if (resultado.success) {
                if (campoNome && !campoNome.value.trim() && resultado.nome) {
                    campoNome.value = resultado.nome;
                }
                if (campoMarca && resultado.marca) campoMarca.value = resultado.marca;
                if (campoModelo && resultado.modelo) campoModelo.value = resultado.modelo;

                const tipoId = tipoIdsPorSlug[resultado.tipo_slug];
                if (campoTipo && tipoId) {
                    campoTipo.value = String(tipoId);
                    campoTipo.dispatchEvent(new Event('change'));
                }

                // Pré-preenche o par modelo/firmware certo pro tipo detectado, se
                // já estiver visível no bloco de Detalhes técnicos (evita repetir
                // no card de baixo o que acabou de vir pro card de cima).
                const camposPorTipo = {
                    switch: ['omada_model', 'omada_firmware'],
                    dvr_nvr: ['dvr_modelo', 'dvr_firmware'],
                };
                const [nomeCampoModelo, nomeCampoFirmware] = camposPorTipo[resultado.tipo_slug] || ['unifi_model', 'unifi_firmware'];
                const campoModeloDetalhe = document.querySelector(`input[name="${nomeCampoModelo}"]`);
                const campoFirmwareDetalhe = document.querySelector(`input[name="${nomeCampoFirmware}"]`);
                if (campoModeloDetalhe && resultado.modelo) campoModeloDetalhe.value = resultado.modelo;
                if (campoFirmwareDetalhe && resultado.firmware) campoFirmwareDetalhe.value = resultado.firmware;
            }
        } catch (e) {
            texto.textContent = 'Erro ao comunicar com o servidor.';
            texto.classList.remove('text-success');
            texto.classList.add('text-danger');
        } finally {
            botao.disabled = false;
            botao.innerHTML = textoOriginal;
        }
    });
})();

(function () {
    const botao = document.getElementById('botaoAtualizarCodigo');
    if (!botao) return;

    const modalEl = document.getElementById('modalRegenerarCodigo');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const campoAtual = document.getElementById('regenerarCodigoAtual');
    const campoNumero = document.getElementById('regenerarCodigoNumero');
    const previa = document.getElementById('regenerarCodigoPrevia');
    const alerta = document.getElementById('regenerarCodigoAlerta');
    const botaoConfirmar = document.getElementById('botaoConfirmarRegenerarCodigo');

    let prefixo = '';
    let digitos = 4;
    let numeroSugerido = 0;

    function atualizarPrevia() {
        const numero = parseInt(campoNumero.value, 10);
        previa.textContent = (prefixo && numero > 0) ? (prefixo + String(numero).padStart(digitos, '0')) : '--';
    }
    campoNumero.addEventListener('input', atualizarPrevia);

    botao.addEventListener('click', async function () {
        botao.disabled = true;
        alerta.innerHTML = '';

        try {
            const res = await fetch(<?= json_encode(url('/ativos/proximo-codigo')) ?> + '?id=' + botao.dataset.id);
            const previsao = await res.json();
            botao.disabled = false;

            if (!previsao.success) {
                alert(previsao.message || 'Não foi possível calcular o próximo código.\n\nSe você mudou a Unidade agora e ainda não salvou, salve primeiro -- o código novo usa a unidade que já está gravada.');
                return;
            }

            const ultimoTraco = previsao.codigo.lastIndexOf('-');
            prefixo = previsao.codigo.substring(0, ultimoTraco + 1);
            digitos = previsao.codigo.length - ultimoTraco - 1;
            numeroSugerido = previsao.numero;

            campoAtual.textContent = document.getElementById('campoCodigoAtivo').value;
            campoNumero.value = numeroSugerido;
            atualizarPrevia();
            modal.show();
        } catch (e) {
            botao.disabled = false;
            alert('Erro ao comunicar com o servidor.');
        }
    });

    botaoConfirmar.addEventListener('click', async function () {
        const numero = parseInt(campoNumero.value, 10);
        if (!numero || numero < 1) {
            alerta.innerHTML = '<div class="alert alert-danger small mb-3">Informe um número válido.</div>';
            return;
        }

        botaoConfirmar.disabled = true;
        alerta.innerHTML = '';

        const dados = new URLSearchParams();
        dados.set('id', botao.dataset.id);

        let endpoint;
        if (numero === numeroSugerido) {
            endpoint = <?= json_encode(url('/ativos/atualizar-codigo')) ?>;
        } else {
            endpoint = <?= json_encode(url('/ativos/ajustar-codigo')) ?>;
            dados.set('numero', numero);
        }

        try {
            const res = await fetch(endpoint, { method: 'POST', body: dados });
            const resultado = await res.json();

            if (!resultado.success) {
                alerta.innerHTML = '<div class="alert alert-danger small mb-0">' + (resultado.message || 'Falha ao atualizar o código.') + '</div>';
                botaoConfirmar.disabled = false;
                return;
            }

            document.getElementById('campoCodigoAtivo').value = resultado.codigo;
            alerta.innerHTML = '<div class="alert alert-success small mb-0">Código atualizado para "' + resultado.codigo + '".</div>';
            setTimeout(function () { modal.hide(); }, 900);
        } catch (e) {
            alerta.innerHTML = '<div class="alert alert-danger small mb-0">Erro ao comunicar com o servidor.</div>';
            botaoConfirmar.disabled = false;
        }
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = $editando ? 'Editar Ativo' : 'Novo Ativo';

require __DIR__ . '/../layouts/main.php';
