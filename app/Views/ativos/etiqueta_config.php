<?php
ob_start();

use App\Components\Alert;
?>

<style>
.rd-etiqueta-preview {
    border: 1px solid #999;
    display: flex;
    align-items: center;
    gap: 2mm;
    box-sizing: border-box;
    background: #fff;
}
.rd-etiqueta-qr {
    flex-shrink: 0;
    border: 1px dashed #ccc;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 6mm;
    color: #999;
}
.rd-etiqueta-texto {
    min-width: 0;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    width: 100%;
}
.rd-etiqueta-linha {
    font-weight: 700;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.rd-etiqueta-espaco {
    margin-top: 2mm;
    font-weight: 400;
    color: #666;
}
</style>

<?= Alert::flash() ?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-qr-code me-1"></i> Configurações de Etiqueta</h4>
    <small class="text-muted">
        <a href="<?= url('/ativos') ?>"><i class="bi bi-arrow-left"></i> Dashboard</a> ·
        Define tamanho, DPI e campos usados na impressão em impressoras térmicas Zebra
        (TLP2844, GC420t, ZD420t) via o agente Windows.
    </small>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><strong>Configuração</strong></div>
            <div class="card-body">
                <form method="post" action="<?= url('/ativos/etiqueta-config/salvar') ?>" id="formEtiqueta">
                    <div class="row g-3 mb-3">
                        <div class="col-sm-4">
                            <label class="form-label">Largura (mm)</label>
                            <input type="number" step="0.1" name="largura_mm" id="campoLargura" class="form-control" value="<?= htmlspecialchars((string)$config['largura_mm']) ?>">
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label">Altura (mm)</label>
                            <input type="number" step="0.1" name="altura_mm" id="campoAltura" class="form-control" value="<?= htmlspecialchars((string)$config['altura_mm']) ?>">
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label">DPI da impressora</label>
                            <select name="dpi" id="campoDpi" class="form-select">
                                <?php foreach ([152, 203, 300, 600] as $dpiOpcao): ?>
                                    <option value="<?= $dpiOpcao ?>" <?= (int)$config['dpi'] === $dpiOpcao ? 'selected' : '' ?>><?= $dpiOpcao ?> dpi</option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">203dpi é o padrão dos 3 modelos citados -- confira na etiqueta de identificação da própria impressora se não tiver certeza.</small>
                        </div>
                    </div>

                    <label class="form-label">Campos exibidos na etiqueta</label>
                    <div class="mb-3">
                        <?php foreach ($camposDisponiveis as $chave => $label): ?>
                            <div class="d-flex align-items-center justify-content-between border-bottom py-2">
                                <div class="form-check mb-0">
                                    <input type="checkbox" name="campos[]" value="<?= $chave ?>" class="form-check-input campo-etiqueta" id="campo_<?= $chave ?>"
                                           <?= in_array($chave, $config['campos'], true) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="campo_<?= $chave ?>"><?= htmlspecialchars($label) ?></label>
                                </div>
                                <?php if (isset($config['fontes'][$chave])): ?>
                                    <div class="input-group input-group-sm" style="width:110px">
                                        <input type="number" step="0.1" min="1.5" max="12" name="fontes[<?= $chave ?>]" class="form-control campo-fonte" value="<?= htmlspecialchars((string)$config['fontes'][$chave]) ?>">
                                        <span class="input-group-text">mm</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar configuração</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>Pré-visualização</strong>
                <small class="text-muted" id="tamanhoPreview"></small>
            </div>
            <div class="card-body d-flex flex-column align-items-center">
                <div id="previewContainer">
                    <?= $previewHtml ?>
                </div>
                <p class="text-muted small mt-3 mb-0 text-center">
                    Escala real (em mm). Se o conteúdo aparecer cortado aqui, também vai sair cortado na
                    impressão -- desmarque algum campo ou aumente o tamanho da etiqueta.
                </p>
            </div>
        </div>

        <div class="alert alert-light border small mt-3 mb-0">
            <i class="bi bi-info-circle"></i>
            Esta tela só define e mostra como a etiqueta vai ficar. Pra imprimir de verdade numa
            Zebra, use o botão <strong>"Imprimir etiqueta (Zebra)"</strong> na ficha do ativo (ou
            "Imprimir na Zebra" na tela de etiqueta) -- precisa do agente Windows atualizado e
            com a impressora selecionada em Configurações.
        </div>
    </div>
</div>

<script>
(function () {
    const largura = document.getElementById('campoLargura');
    const altura = document.getElementById('campoAltura');
    const dpi = document.getElementById('campoDpi');
    const checkboxes = document.querySelectorAll('.campo-etiqueta');
    const fontes = document.querySelectorAll('.campo-fonte');
    const preview = document.getElementById('previewContainer');
    const tamanho = document.getElementById('tamanhoPreview');

    function atualizarTamanho() {
        tamanho.textContent = largura.value + ' x ' + altura.value + ' mm';
    }

    let atualizando = false;
    let pendente = false;

    async function atualizarPreview() {
        if (atualizando) { pendente = true; return; }
        atualizando = true;
        atualizarTamanho();

        const dados = new URLSearchParams();
        dados.set('largura_mm', largura.value);
        dados.set('altura_mm', altura.value);
        dados.set('dpi', dpi.value);
        checkboxes.forEach(function (c) { if (c.checked) dados.append('campos[]', c.value); });
        fontes.forEach(function (f) { dados.set('fontes[' + f.name.match(/\[(.+)\]/)[1] + ']', f.value); });

        try {
            const res = await fetch(<?= json_encode(url('/ativos/etiqueta-config/preview')) ?>, { method: 'POST', body: dados });
            const resultado = await res.json();
            if (resultado.success) preview.innerHTML = resultado.html;
        } catch (e) {
            // preview e so cosmetico -- se falhar, mantem o ultimo estado sem travar o formulario
        } finally {
            atualizando = false;
            if (pendente) { pendente = false; atualizarPreview(); }
        }
    }

    [largura, altura, dpi].forEach(function (campo) {
        campo.addEventListener('input', atualizarPreview);
    });
    checkboxes.forEach(function (c) { c.addEventListener('change', atualizarPreview); });
    fontes.forEach(function (f) { f.addEventListener('input', atualizarPreview); });

    atualizarTamanho();
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Ativos - Configurações de Etiqueta';

require __DIR__ . '/../layouts/main.php';
