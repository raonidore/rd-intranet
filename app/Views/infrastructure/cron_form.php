<?php

use App\Components\Alert;

ob_start();

$editando = $job !== null;
$acao = $editando ? url('/infraestrutura/cron/editar') : url('/infraestrutura/cron/novo');
?>

<style>
.cron-builder-tabs { display: inline-flex; gap: 4px; background: #f1f3f5; border-radius: 10px; padding: 4px; }
.cron-tab {
    border: 0; background: transparent; padding: 6px 16px; border-radius: 8px;
    font-size: 14px; color: #495057; cursor: pointer; transition: all .15s ease;
}
.cron-tab.active { background: #fff; color: #0d6efd; box-shadow: 0 1px 3px rgba(0,0,0,.12); font-weight: 600; }
.cron-dias { display: flex; gap: 6px; flex-wrap: wrap; }
.cron-dia-btn {
    position: relative; cursor: pointer; user-select: none;
}
.cron-dia-btn input { position: absolute; opacity: 0; width: 0; height: 0; }
.cron-dia-btn span {
    display: inline-flex; align-items: center; justify-content: center;
    width: 44px; height: 38px; border-radius: 8px; border: 1px solid #ced4da;
    font-size: 13px; color: #495057; transition: all .15s ease;
}
.cron-dia-btn input:checked + span { background: #0d6efd; border-color: #0d6efd; color: #fff; font-weight: 600; }
.cron-preview {
    background: #eef6ff; border: 1px solid #cfe4fd; border-radius: 8px;
    padding: 10px 14px; font-size: 14px; color: #0b5ed7;
}
</style>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0">
            <i class="bi bi-clock-history"></i>
            <?= $editando ? 'Editar job de cron' : 'Novo job de cron' ?>
        </h5>
    </div>

    <div class="card-body">
        <form method="post" action="<?= $acao ?>">
            <?php if ($editando): ?>
                <input type="hidden" name="id" value="<?= (int)$job['id'] ?>">
            <?php endif; ?>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Nome</label>
                    <input type="text" name="nome" class="form-control" required
                           placeholder="Ex: Backup diário do banco"
                           value="<?= htmlspecialchars($job['nome'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Descrição (opcional)</label>
                    <input type="text" name="descricao" class="form-control"
                           value="<?= htmlspecialchars($job['descricao'] ?? '') ?>">
                </div>
            </div>

            <div class="cron-builder mb-3">
                <label class="form-label d-block">Agendamento</label>

                <div class="cron-builder-tabs mb-3" role="tablist">
                    <button type="button" class="cron-tab active" data-alvo="visual">
                        <i class="bi bi-mouse2"></i> Visual
                    </button>
                    <button type="button" class="cron-tab" data-alvo="manual">
                        <i class="bi bi-code-slash"></i> Manual
                    </button>
                </div>

                <div id="cronPainelVisual" class="cron-painel">
                    <select id="cronFrequencia" class="form-select mb-3">
                        <option value="minuto">A cada minuto</option>
                        <option value="n-minutos" selected>A cada X minutos</option>
                        <option value="hora">A cada hora, num minuto fixo</option>
                        <option value="diario">Todo dia, num horário</option>
                        <option value="semanal">Toda semana, em dias específicos</option>
                        <option value="mensal">Todo mês, num dia específico</option>
                    </select>

                    <div data-painel="n-minutos" class="cron-subpainel row g-2 align-items-center">
                        <div class="col-auto">A cada</div>
                        <div class="col-auto">
                            <input type="number" id="cronNMinutos" class="form-control" style="width:90px" min="2" max="59" value="5">
                        </div>
                        <div class="col-auto">minutos</div>
                    </div>

                    <div data-painel="hora" class="cron-subpainel row g-2 align-items-center" style="display:none">
                        <div class="col-auto">No minuto</div>
                        <div class="col-auto">
                            <input type="number" id="cronMinutoHora" class="form-control" style="width:90px" min="0" max="59" value="0">
                        </div>
                        <div class="col-auto">de cada hora</div>
                    </div>

                    <div data-painel="diario" class="cron-subpainel row g-2 align-items-center" style="display:none">
                        <div class="col-auto">Às</div>
                        <div class="col-auto">
                            <input type="time" id="cronHorarioDiario" class="form-control" value="00:00">
                        </div>
                    </div>

                    <div data-painel="semanal" class="cron-subpainel" style="display:none">
                        <div class="cron-dias mb-2">
                            <?php foreach (['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'] as $i => $dia): ?>
                                <label class="cron-dia-btn">
                                    <input type="checkbox" class="cron-dia-semana" value="<?= $i ?>" <?= $i >= 1 && $i <= 5 ? 'checked' : '' ?>>
                                    <span><?= $dia ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="row g-2 align-items-center">
                            <div class="col-auto">Às</div>
                            <div class="col-auto">
                                <input type="time" id="cronHorarioSemanal" class="form-control" value="00:00">
                            </div>
                        </div>
                    </div>

                    <div data-painel="mensal" class="cron-subpainel row g-2 align-items-center" style="display:none">
                        <div class="col-auto">No dia</div>
                        <div class="col-auto">
                            <input type="number" id="cronDiaMes" class="form-control" style="width:90px" min="1" max="31" value="1">
                        </div>
                        <div class="col-auto">de cada mês, às</div>
                        <div class="col-auto">
                            <input type="time" id="cronHorarioMensal" class="form-control" value="00:00">
                        </div>
                    </div>

                    <div class="cron-preview mt-3">
                        <i class="bi bi-info-circle"></i> <span id="cronPreviewTexto">Executa a cada 5 minutos</span>
                    </div>
                </div>

                <div id="cronPainelManual" class="cron-painel" style="display:none">
                    <input type="text" class="form-control font-monospace" id="cronManualInput"
                           placeholder="*/5 * * * *">
                    <small class="text-muted d-block mt-1">
                        5 campos (min hora dia mês dia-semana) ou um atalho como <code>@daily</code>.
                    </small>
                </div>

                <input type="hidden" name="expressao" id="expressaoFinal" value="<?= htmlspecialchars($job['expressao'] ?? '*/5 * * * *') ?>">
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label">Usuário de execução</label>
                    <input type="text" name="usuario_execucao" class="form-control font-monospace" required
                           placeholder="root"
                           value="<?= htmlspecialchars($job['usuario_execucao'] ?? 'root') ?>">
                    <small class="text-muted">Precisa existir no sistema (ex: root, www-data).</small>
                </div>

                <div class="col-md-8">
                    <label class="form-label">Comando</label>
                    <input type="text" name="comando" class="form-control font-monospace" required
                           placeholder="/usr/bin/php /var/www/rd.intranet/scripts/system/exemplo.php"
                           value="<?= htmlspecialchars($job['comando'] ?? '') ?>">
                </div>
            </div>

            <div class="form-check mb-3">
                <input type="checkbox" name="ativo" id="ativo" class="form-check-input"
                       <?= (!$editando || (int)($job['ativo'] ?? 1) === 1) ? 'checked' : '' ?>>
                <label for="ativo" class="form-check-label">Ativo (entra no cron do sistema)</label>
            </div>

            <div class="alert alert-info small mb-3">
                <i class="bi bi-info-circle"></i>
                A saída do job (quando rodar pelo agendamento) fica disponível em "Ver log" na listagem.
            </div>

            <div class="d-flex justify-content-between mt-3">
                <a href="<?= url('/infraestrutura/cron') ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Voltar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> Salvar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const DIAS_NOME = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];

    const expressaoFinal = document.getElementById('expressaoFinal');
    const frequencia = document.getElementById('cronFrequencia');
    const manualInput = document.getElementById('cronManualInput');
    const preview = document.getElementById('cronPreviewTexto');
    const abas = document.querySelectorAll('.cron-tab');
    const painelVisual = document.getElementById('cronPainelVisual');
    const painelManual = document.getElementById('cronPainelManual');

    function pad(n) { return String(n).padStart(2, '0'); }

    function horaMinuto(inputId) {
        const [h, m] = (document.getElementById(inputId).value || '00:00').split(':');
        return { h: parseInt(h, 10) || 0, m: parseInt(m, 10) || 0 };
    }

    function diasSelecionados() {
        return Array.from(document.querySelectorAll('.cron-dia-semana:checked')).map(function (c) { return parseInt(c.value, 10); });
    }

    function construirExpressao() {
        switch (frequencia.value) {
            case 'minuto':
                return { expr: '* * * * *', texto: 'Executa todo minuto.' };

            case 'n-minutos': {
                const n = Math.min(59, Math.max(2, parseInt(document.getElementById('cronNMinutos').value, 10) || 5));
                return { expr: `*/${n} * * * *`, texto: `Executa a cada ${n} minutos.` };
            }

            case 'hora': {
                const m = Math.min(59, Math.max(0, parseInt(document.getElementById('cronMinutoHora').value, 10) || 0));
                return { expr: `${m} * * * *`, texto: `Executa a cada hora, no minuto ${pad(m)}.` };
            }

            case 'diario': {
                const { h, m } = horaMinuto('cronHorarioDiario');
                return { expr: `${m} ${h} * * *`, texto: `Executa todo dia às ${pad(h)}:${pad(m)}.` };
            }

            case 'semanal': {
                const { h, m } = horaMinuto('cronHorarioSemanal');
                const dias = diasSelecionados();
                if (dias.length === 0) {
                    return { expr: `${m} ${h} * * *`, texto: `Selecione ao menos um dia da semana.`, invalido: true };
                }
                const nomes = dias.slice().sort().map(function (d) { return DIAS_NOME[d]; }).join(', ');
                return { expr: `${m} ${h} * * ${dias.join(',')}`, texto: `Executa às ${pad(h)}:${pad(m)}, toda(s): ${nomes}.` };
            }

            case 'mensal': {
                const { h, m } = horaMinuto('cronHorarioMensal');
                const dia = Math.min(31, Math.max(1, parseInt(document.getElementById('cronDiaMes').value, 10) || 1));
                return { expr: `${m} ${h} ${dia} * *`, texto: `Executa todo mês no dia ${dia}, às ${pad(h)}:${pad(m)}.` };
            }
        }
    }

    function atualizarPreview() {
        const resultado = construirExpressao();
        expressaoFinal.value = resultado.expr;
        preview.textContent = resultado.texto + '  →  ' + resultado.expr;
        preview.closest('.cron-preview').classList.toggle('border-danger', !!resultado.invalido);
    }

    document.querySelectorAll('[data-painel]').forEach(function (painel) { painel.style.display = 'none'; });
    document.querySelector('[data-painel="' + frequencia.value + '"]').style.display = '';

    frequencia.addEventListener('change', function () {
        document.querySelectorAll('[data-painel]').forEach(function (painel) { painel.style.display = 'none'; });
        const alvo = document.querySelector('[data-painel="' + frequencia.value + '"]');
        if (alvo) alvo.style.display = '';
        atualizarPreview();
    });

    painelVisual.addEventListener('input', atualizarPreview);
    painelVisual.addEventListener('change', atualizarPreview);

    manualInput.addEventListener('input', function () {
        expressaoFinal.value = manualInput.value;
    });

    abas.forEach(function (aba) {
        aba.addEventListener('click', function () {
            abas.forEach(function (a) { a.classList.remove('active'); });
            aba.classList.add('active');

            if (aba.dataset.alvo === 'manual') {
                painelVisual.style.display = 'none';
                painelManual.style.display = '';
                manualInput.value = expressaoFinal.value;
            } else {
                painelManual.style.display = 'none';
                painelVisual.style.display = '';
                atualizarPreview();
            }
        });
    });

    // Ao editar um job existente, tenta reconhecer a expressao atual num dos
    // modos visuais (mesmas formas que o proprio construtor gera); se nao
    // bater com nenhum padrao conhecido, abre direto no modo Manual com o
    // valor original intacto -- nunca tenta "adivinhar" e alterar o que o
    // usuario configurou por fora da tela.
    (function reconhecerExpressaoAtual() {
        const atual = expressaoFinal.value.trim();
        const campos = atual.split(/\s+/);

        if (atual === '' || atual === '*/5 * * * *') {
            return;
        }

        if (campos.length !== 5 || campos.some(function (c) { return c === ''; })) {
            abrirManual(atual);
            return;
        }

        const [min, hora, dom, mes, dow] = campos;
        const ehNumero = function (v) { return /^\d+$/.test(v); };

        if (min === '*' && hora === '*' && dom === '*' && mes === '*' && dow === '*') {
            frequencia.value = 'minuto';
        } else if (/^\*\/\d+$/.test(min) && hora === '*' && dom === '*' && mes === '*' && dow === '*') {
            frequencia.value = 'n-minutos';
            document.getElementById('cronNMinutos').value = min.slice(2);
        } else if (ehNumero(min) && hora === '*' && dom === '*' && mes === '*' && dow === '*') {
            frequencia.value = 'hora';
            document.getElementById('cronMinutoHora').value = min;
        } else if (ehNumero(min) && ehNumero(hora) && dom === '*' && mes === '*' && dow === '*') {
            frequencia.value = 'diario';
            document.getElementById('cronHorarioDiario').value = pad(hora) + ':' + pad(min);
        } else if (ehNumero(min) && ehNumero(hora) && dom === '*' && mes === '*' && /^[0-6](,[0-6])*$/.test(dow)) {
            frequencia.value = 'semanal';
            document.getElementById('cronHorarioSemanal').value = pad(hora) + ':' + pad(min);
            document.querySelectorAll('.cron-dia-semana').forEach(function (c) { c.checked = false; });
            dow.split(',').forEach(function (d) {
                const cb = document.querySelector('.cron-dia-semana[value="' + d + '"]');
                if (cb) cb.checked = true;
            });
        } else if (ehNumero(min) && ehNumero(hora) && ehNumero(dom) && mes === '*' && dow === '*') {
            frequencia.value = 'mensal';
            document.getElementById('cronHorarioMensal').value = pad(hora) + ':' + pad(min);
            document.getElementById('cronDiaMes').value = dom;
        } else {
            abrirManual(atual);
            return;
        }

        frequencia.dispatchEvent(new Event('change'));
    })();

    function abrirManual(valor) {
        document.querySelector('.cron-tab[data-alvo="manual"]').click();
        manualInput.value = valor;
        expressaoFinal.value = valor;
    }

    atualizarPreview();
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = $editando ? 'Editar Job de Cron' : 'Novo Job de Cron';

require __DIR__ . '/../layouts/main.php';
