<?php

use App\Components\Alert;
use App\Components\Badge;

ob_start();

$formatarBytes = function (int $bytes): string {
    if ($bytes <= 0) return '0 B';
    $unidades = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = (int)floor(log($bytes, 1024));
    $i = min($i, count($unidades) - 1);
    return round($bytes / (1024 ** $i), 1) . ' ' . $unidades[$i];
};

$statusBadge = fn(string $s) => match ($s) {
    'concluido' => Badge::make('Concluído', 'success'),
    'erro' => Badge::make('Erro', 'danger'),
    'executando' => Badge::make('Executando', 'info'),
    default => Badge::make($s, 'secondary'),
};
?>

<style>
/* mesmo "cron builder" visual de infraestrutura/cron/novo e backup/configuracao.php */
.cron-builder-tabs { display: inline-flex; gap: 4px; background: #f1f3f5; border-radius: 10px; padding: 4px; }
.cron-tab {
    border: 0; background: transparent; padding: 6px 16px; border-radius: 8px;
    font-size: 14px; color: #495057; cursor: pointer; transition: all .15s ease;
}
.cron-tab.active { background: #fff; color: #0d6efd; box-shadow: 0 1px 3px rgba(0,0,0,.12); font-weight: 600; }
.cron-dias { display: flex; gap: 6px; flex-wrap: wrap; }
.cron-dia-btn { position: relative; cursor: pointer; user-select: none; }
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
.passo-restauracao { display: flex; align-items: center; gap: 8px; padding: 6px 0; font-size: 14px; }
.passo-restauracao .bi-circle { color: #adb5bd; }
</style>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-shield-lock me-1"></i> Configurações -- Backup e Restauração</h4>
        <small class="text-muted">Backup total do sistema (banco de dados + arquivos críticos), para reconstruir o servidor em caso de desastre.</small>
    </div>
</div>

<div class="alert alert-warning d-flex gap-2 mb-4">
    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
    <div>
        <strong>Este backup cobre a configuração inteira do sistema</strong> -- usuários e compartilhamentos Samba,
        VPN, Backup em Nuvem, firewall, Cloudflare Tunnel, e-mail e mais -- não os arquivos dos compartilhamentos
        em si (isso é o módulo <a href="<?= url('/backup/configuracao') ?>">Backup em Nuvem</a>).
        O pacote é cifrado com uma senha que <strong>você escolhe e nunca fica salva em lugar nenhum</strong> --
        se perder essa senha, o backup se torna inútil. Guarde-a num lugar seguro (ex: um gerenciador de senhas).
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <strong><i class="bi bi-download"></i> Gerar backup agora</strong>
            </div>
            <div class="card-body">
                <form id="formGerar">
                    <div class="mb-3">
                        <label class="form-label">Senha de criptografia</label>
                        <input type="password" class="form-control" id="campoSenhaGerar" required minlength="8" autocomplete="new-password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirme a senha</label>
                        <input type="password" class="form-control" id="campoSenhaGerarConfirmar" required minlength="8" autocomplete="new-password">
                    </div>
                    <div class="alert alert-danger d-none" id="gerarErro"></div>
                    <button type="submit" class="btn btn-primary" id="botaoGerar">
                        <i class="bi bi-play-fill"></i> Gerar backup agora
                    </button>
                </form>

                <div id="painelProgressoGerar" class="mt-3 d-none">
                    <div class="progress" style="height: 22px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" id="barraGerar" style="width:0%">0%</div>
                    </div>
                    <div class="small text-muted mt-2" id="textoProgressoGerar"></div>
                    <div class="alert alert-success mt-2 d-none" id="gerarSucesso"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-clock-history"></i> Agendamento automático</strong>
                <?= $agendamento ? Badge::make('Ativo', 'success') : Badge::make('Inativo', 'secondary') ?>
            </div>
            <div class="card-body">
                <?php if ($agendamento): ?>
                    <p class="mb-2"><i class="bi bi-check-circle text-success"></i> <code><?= htmlspecialchars($agendamento['expressao']) ?></code></p>
                    <p class="small text-muted">Roda automaticamente com a senha dedicada de backups agendados (guardada cifrada no servidor).</p>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="botaoEditarAgendamento">
                        <i class="bi bi-pencil"></i> Editar
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger" id="botaoRemoverAgendamento">
                        <i class="bi bi-x-lg"></i> Remover agendamento
                    </button>
                <?php else: ?>
                    <p class="small text-muted">Sem agendamento -- os backups precisam ser gerados manualmente.</p>
                    <button type="button" class="btn btn-sm btn-primary" id="botaoEditarAgendamento">
                        <i class="bi bi-plus-lg"></i> Agendar
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white">
        <strong><i class="bi bi-clock-history"></i> Histórico</strong>
    </div>
    <div class="card-body p-0">
        <?php if (empty($historico)): ?>
            <div class="text-center text-muted py-5">Nenhum backup de configuração gerado ainda.</div>
        <?php else: ?>
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Tipo</th>
                        <th>Status</th>
                        <th>Tamanho</th>
                        <th>Nuvem</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historico as $h): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($h['iniciado_em'])) ?></td>
                            <td><?= $h['tipo'] === 'agendado' ? 'Agendado' : 'Manual' ?></td>
                            <td><?= $statusBadge($h['status']) ?></td>
                            <td><?= $h['tamanho_bytes'] ? $formatarBytes((int)$h['tamanho_bytes']) : '--' ?></td>
                            <td>
                                <?php if ($h['status'] === 'concluido'): ?>
                                    <?= $h['enviado_nuvem'] ? '<i class="bi bi-cloud-check text-success" title="Enviado para a nuvem"></i>' : '<i class="bi bi-cloud-slash text-muted" title="Só local"></i>' ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($h['status'] === 'concluido'): ?>
                                    <a href="<?= url('/administracao/configuracoes/download?id=' . (int)$h['id']) ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-download"></i> Baixar
                                    </a>
                                <?php elseif ($h['status'] === 'erro'): ?>
                                    <span class="text-danger small" title="<?= htmlspecialchars($h['mensagem_erro'] ?? '') ?>">
                                        <i class="bi bi-exclamation-circle"></i> Falhou
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div class="card border-danger shadow-sm mb-4">
    <div class="card-header bg-danger-subtle text-danger-emphasis">
        <strong><i class="bi bi-exclamation-triangle-fill"></i> Restaurar (ação destrutiva)</strong>
    </div>
    <div class="card-body">
        <div class="alert alert-danger">
            Restaurar <strong>substitui TODO o banco de dados e os arquivos críticos do sistema</strong> pelos do
            backup enviado. Use isto só num servidor recém-reinstalado (após um desastre), nunca num servidor em
            produção com dados que você quer manter -- não há como desfazer.
        </div>
        <form id="formRestaurar">
            <div class="mb-3">
                <label class="form-label">Arquivo de backup (.tar.enc)</label>
                <input type="file" class="form-control" id="campoArquivoRestaurar" accept=".enc" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Senha de criptografia</label>
                <input type="password" class="form-control" id="campoSenhaRestaurar" required autocomplete="new-password">
            </div>
            <div class="mb-3">
                <label class="form-label">Digite <code>RESTAURAR</code> para confirmar</label>
                <input type="text" class="form-control" id="campoConfirmarRestaurar" required autocomplete="off">
            </div>
            <div class="alert alert-danger d-none" id="restaurarErro"></div>
            <button type="submit" class="btn btn-danger" id="botaoRestaurar">
                <i class="bi bi-arrow-counterclockwise"></i> Restaurar
            </button>
        </form>

        <div id="painelProgressoRestaurar" class="mt-3 d-none">
            <div class="progress" style="height: 22px;">
                <div class="progress-bar progress-bar-striped progress-bar-animated bg-danger" id="barraRestaurar" style="width:0%">0%</div>
            </div>
            <div class="small text-muted mt-2" id="textoProgressoRestaurar"></div>
            <div id="passosRestaurar" class="mt-3 d-none"></div>
            <div class="alert alert-success mt-2 d-none" id="restaurarSucesso">
                Restauração concluída. <a href="<?= url('/login') ?>">Faça login novamente</a> com as credenciais do backup restaurado.
            </div>
        </div>
    </div>
</div>

<!-- Modal agendar -->
<div class="modal fade" id="modalAgendar" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Agendar backup automático de configuração</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Senha de backups agendados</label>
                    <input type="password" class="form-control" id="agendarSenha" autocomplete="new-password"
                           placeholder="<?= $senhaAgendadaConfigurada ? '•••••••• (deixe em branco pra manter)' : '' ?>">
                    <small class="text-muted d-block mt-1">
                        Fica guardada cifrada no servidor (mesmo cofre que já protege a senha SMTP e as credenciais de nuvem) --
                        precisa de uma senha própria porque backups manuais nunca guardam a sua.
                    </small>
                </div>

                <div class="cron-builder-tabs mb-3" role="tablist">
                    <button type="button" class="cron-tab active" data-alvo="visual">
                        <i class="bi bi-mouse2"></i> Visual
                    </button>
                    <button type="button" class="cron-tab" data-alvo="manual">
                        <i class="bi bi-code-slash"></i> Manual
                    </button>
                </div>

                <div id="agendarPainelVisual" class="cron-painel">
                    <select id="agendarFrequencia" class="form-select mb-3">
                        <option value="diario" selected>Todo dia, num horário</option>
                        <option value="semanal">Toda semana, em dias específicos</option>
                        <option value="mensal">Todo mês, num dia específico</option>
                    </select>

                    <div data-painel="diario" class="cron-subpainel row g-2 align-items-center">
                        <div class="col-auto">Às</div>
                        <div class="col-auto">
                            <input type="time" id="agendarHorarioDiario" class="form-control" value="04:00">
                        </div>
                    </div>

                    <div data-painel="semanal" class="cron-subpainel" style="display:none">
                        <div class="cron-dias mb-2">
                            <?php foreach (['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'] as $i => $dia): ?>
                                <label class="cron-dia-btn">
                                    <input type="checkbox" class="cron-dia-semana" value="<?= $i ?>" <?= $i === 0 ? 'checked' : '' ?>>
                                    <span><?= $dia ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="row g-2 align-items-center">
                            <div class="col-auto">Às</div>
                            <div class="col-auto">
                                <input type="time" id="agendarHorarioSemanal" class="form-control" value="04:00">
                            </div>
                        </div>
                    </div>

                    <div data-painel="mensal" class="cron-subpainel row g-2 align-items-center" style="display:none">
                        <div class="col-auto">No dia</div>
                        <div class="col-auto">
                            <input type="number" id="agendarDiaMes" class="form-control" style="width:90px" min="1" max="31" value="1">
                        </div>
                        <div class="col-auto">de cada mês, às</div>
                        <div class="col-auto">
                            <input type="time" id="agendarHorarioMensal" class="form-control" value="04:00">
                        </div>
                    </div>

                    <div class="cron-preview mt-3">
                        <i class="bi bi-info-circle"></i> <span id="agendarPreviewTexto">Executa todo dia às 04:00.</span>
                    </div>
                </div>

                <div id="agendarPainelManual" class="cron-painel" style="display:none">
                    <input type="text" class="form-control font-monospace" id="agendarManualInput" placeholder="0 4 * * *">
                    <small class="text-muted d-block mt-1">
                        5 campos (min hora dia mês dia-semana) ou um atalho como <code>@daily</code>.
                    </small>
                </div>

                <input type="hidden" id="campoExpressaoCron" value="0 4 * * *">
                <div class="alert alert-danger d-none mt-3" id="agendarErro"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="botaoConfirmarAgendar">Salvar agendamento</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const URLS = {
        gerar: <?= json_encode(url('/administracao/configuracoes/gerar')) ?>,
        gerarStatus: <?= json_encode(url('/administracao/configuracoes/gerar-status')) ?>,
        agendarSalvar: <?= json_encode(url('/administracao/configuracoes/agendar-salvar')) ?>,
        agendarExcluir: <?= json_encode(url('/administracao/configuracoes/agendar-excluir')) ?>,
        restaurarUpload: <?= json_encode(url('/administracao/configuracoes/restaurar-upload')) ?>,
        restaurarStatus: <?= json_encode(url('/administracao/configuracoes/restaurar-status')) ?>,
        restaurarFinalizar: <?= json_encode(url('/administracao/configuracoes/restaurar-finalizar')) ?>,
    };

    function modal(id) {
        return bootstrap.Modal.getOrCreateInstance(document.getElementById(id));
    }

    function formatarBytes(bytes) {
        if (!bytes || bytes <= 0) return '0 B';
        const unidades = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), unidades.length - 1);
        return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + unidades[i];
    }

    // --- Gerar backup agora ---
    const formGerar = document.getElementById('formGerar');
    const gerarErro = document.getElementById('gerarErro');
    const painelGerar = document.getElementById('painelProgressoGerar');
    const barraGerar = document.getElementById('barraGerar');
    const textoGerar = document.getElementById('textoProgressoGerar');
    const gerarSucesso = document.getElementById('gerarSucesso');

    function atualizarBarraGerar(pct) {
        const p = Math.max(0, Math.min(100, pct || 0));
        barraGerar.style.width = p + '%';
        barraGerar.textContent = p + '%';
    }

    function consultarStatusGerar(execucaoId) {
        const intervalo = setInterval(async function () {
            try {
                const res = await fetch(URLS.gerarStatus + '?execucao_id=' + encodeURIComponent(execucaoId));
                const dados = await res.json();

                if (dados.status === 'rodando') {
                    atualizarBarraGerar(dados.percentual);
                    textoGerar.textContent = dados.mensagem || 'Gerando...';
                    return;
                }

                if (dados.status === 'concluido') {
                    clearInterval(intervalo);
                    atualizarBarraGerar(100);
                    barraGerar.classList.remove('progress-bar-animated');
                    textoGerar.classList.add('d-none');
                    gerarSucesso.textContent = 'Backup gerado: ' + (dados.arquivo || '') + ' (' + formatarBytes(dados.tamanho_bytes) + ')' +
                        (dados.enviado_nuvem ? ' -- enviado também para a nuvem.' : '.');
                    gerarSucesso.classList.remove('d-none');
                    setTimeout(function () { location.reload(); }, 2500);
                    return;
                }

                if (dados.status === 'erro') {
                    clearInterval(intervalo);
                    barraGerar.classList.remove('progress-bar-animated');
                    textoGerar.classList.add('d-none');
                    gerarErro.textContent = dados.mensagem || 'Erro ao gerar o backup.';
                    gerarErro.classList.remove('d-none');
                }
            } catch (e) {
                // falha de rede pontual -- tenta de novo no proximo tick
            }
        }, 2000);
    }

    formGerar.addEventListener('submit', async function (e) {
        e.preventDefault();
        gerarErro.classList.add('d-none');

        const senha = document.getElementById('campoSenhaGerar').value;
        const confirmar = document.getElementById('campoSenhaGerarConfirmar').value;

        if (senha !== confirmar) {
            gerarErro.textContent = 'As senhas não conferem.';
            gerarErro.classList.remove('d-none');
            return;
        }

        const botao = document.getElementById('botaoGerar');
        botao.disabled = true;
        try {
            const dados = new URLSearchParams();
            dados.set('senha', senha);

            const res = await fetch(URLS.gerar, { method: 'POST', body: dados });
            const resposta = await res.json();

            if (!resposta.success) {
                gerarErro.textContent = resposta.message;
                gerarErro.classList.remove('d-none');
                return;
            }

            painelGerar.classList.remove('d-none');
            atualizarBarraGerar(0);
            consultarStatusGerar(resposta.execucao_id);
            formGerar.reset();
        } catch (e) {
            gerarErro.textContent = 'Erro de rede ao iniciar o backup.';
            gerarErro.classList.remove('d-none');
        } finally {
            botao.disabled = false;
        }
    });

    // --- Agendamento (construtor visual de cron, mesmo mecanismo de backup/configuracao.php) ---
    const DIAS_NOME = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];
    const expressaoFinal = document.getElementById('campoExpressaoCron');
    const frequencia = document.getElementById('agendarFrequencia');
    const manualInput = document.getElementById('agendarManualInput');
    const preview = document.getElementById('agendarPreviewTexto');
    const abas = document.querySelectorAll('#modalAgendar .cron-tab');
    const painelVisual = document.getElementById('agendarPainelVisual');
    const painelManual = document.getElementById('agendarPainelManual');

    function pad(n) { return String(n).padStart(2, '0'); }

    function horaMinuto(inputId) {
        const [h, m] = (document.getElementById(inputId).value || '00:00').split(':');
        return { h: parseInt(h, 10) || 0, m: parseInt(m, 10) || 0 };
    }

    function diasSelecionados() {
        return Array.from(document.querySelectorAll('#modalAgendar .cron-dia-semana:checked')).map(function (c) { return parseInt(c.value, 10); });
    }

    function construirExpressao() {
        switch (frequencia.value) {
            case 'diario': {
                const { h, m } = horaMinuto('agendarHorarioDiario');
                return { expr: `${m} ${h} * * *`, texto: `Executa todo dia às ${pad(h)}:${pad(m)}.` };
            }
            case 'semanal': {
                const { h, m } = horaMinuto('agendarHorarioSemanal');
                const dias = diasSelecionados();
                if (dias.length === 0) {
                    return { expr: `${m} ${h} * * *`, texto: 'Selecione ao menos um dia da semana.', invalido: true };
                }
                const nomes = dias.slice().sort().map(function (d) { return DIAS_NOME[d]; }).join(', ');
                return { expr: `${m} ${h} * * ${dias.join(',')}`, texto: `Executa às ${pad(h)}:${pad(m)}, toda(s): ${nomes}.` };
            }
            case 'mensal': {
                const { h, m } = horaMinuto('agendarHorarioMensal');
                const dia = Math.min(31, Math.max(1, parseInt(document.getElementById('agendarDiaMes').value, 10) || 1));
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

    frequencia.addEventListener('change', function () {
        document.querySelectorAll('#modalAgendar [data-painel]').forEach(function (p) { p.style.display = 'none'; });
        const alvo = document.querySelector('#modalAgendar [data-painel="' + frequencia.value + '"]');
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

    atualizarPreview();

    document.getElementById('botaoEditarAgendamento')?.addEventListener('click', function () {
        document.getElementById('agendarErro').classList.add('d-none');
        document.getElementById('agendarSenha').value = '';
        <?php if ($agendamento): ?>
        (function preencher(expressaoAtual) {
            const campos = (expressaoAtual || '').trim().split(/\s+/);
            const ehNumero = (v) => /^\d+$/.test(v);
            if (campos.length !== 5) return;
            const [min, hora, dom, mes, dow] = campos;
            if (ehNumero(min) && ehNumero(hora) && dom === '*' && mes === '*' && dow === '*') {
                frequencia.value = 'diario';
                document.getElementById('agendarHorarioDiario').value = pad(hora) + ':' + pad(min);
            } else if (ehNumero(min) && ehNumero(hora) && dom === '*' && mes === '*' && /^[0-6](,[0-6])*$/.test(dow)) {
                frequencia.value = 'semanal';
                document.getElementById('agendarHorarioSemanal').value = pad(hora) + ':' + pad(min);
                document.querySelectorAll('#modalAgendar .cron-dia-semana').forEach((c) => c.checked = false);
                dow.split(',').forEach((d) => {
                    const cb = document.querySelector('#modalAgendar .cron-dia-semana[value="' + d + '"]');
                    if (cb) cb.checked = true;
                });
            } else if (ehNumero(min) && ehNumero(hora) && ehNumero(dom) && mes === '*' && dow === '*') {
                frequencia.value = 'mensal';
                document.getElementById('agendarHorarioMensal').value = pad(hora) + ':' + pad(min);
                document.getElementById('agendarDiaMes').value = dom;
            }
            frequencia.dispatchEvent(new Event('change'));
        })(<?= json_encode($agendamento['expressao'] ?? '') ?>);
        <?php endif; ?>
        modal('modalAgendar').show();
    });

    document.getElementById('botaoConfirmarAgendar').addEventListener('click', async function () {
        const erroBox = document.getElementById('agendarErro');
        erroBox.classList.add('d-none');

        const dados = new URLSearchParams();
        dados.set('expressao', expressaoFinal.value);
        dados.set('senha_agendada', document.getElementById('agendarSenha').value);

        const res = await fetch(URLS.agendarSalvar, { method: 'POST', body: dados });
        const resposta = await res.json();

        if (!resposta.success) {
            erroBox.textContent = resposta.message;
            erroBox.classList.remove('d-none');
            return;
        }

        location.reload();
    });

    document.getElementById('botaoRemoverAgendamento')?.addEventListener('click', async function () {
        if (!confirm('Remover o agendamento de backup de configuração?')) return;
        await fetch(URLS.agendarExcluir, { method: 'POST' });
        location.reload();
    });

    // --- Restaurar ---
    const formRestaurar = document.getElementById('formRestaurar');
    const restaurarErro = document.getElementById('restaurarErro');
    const painelRestaurar = document.getElementById('painelProgressoRestaurar');
    const barraRestaurar = document.getElementById('barraRestaurar');
    const textoRestaurar = document.getElementById('textoProgressoRestaurar');
    const passosRestaurar = document.getElementById('passosRestaurar');
    const restaurarSucesso = document.getElementById('restaurarSucesso');

    const NOMES_PASSO = {
        migrations: 'Aplicar migrations pendentes',
        samba: 'Regenerar configuração do Samba',
        cron: 'Regenerar agendamentos (cron)',
        firewall: 'Reaplicar regras de firewall',
        rclone: 'Regenerar configuração do rclone (Backup em Nuvem)',
        cloudflare_tunnel: 'Reconectar Cloudflare Tunnel',
    };

    function atualizarBarraRestaurar(pct) {
        const p = Math.max(0, Math.min(100, pct || 0));
        barraRestaurar.style.width = p + '%';
        barraRestaurar.textContent = p + '%';
    }

    async function finalizarRestauracao(execucaoId) {
        textoRestaurar.textContent = 'Regenerando configurações a partir do banco restaurado...';
        const dados = new URLSearchParams();
        dados.set('execucao_id', execucaoId);

        const res = await fetch(URLS.restaurarFinalizar, { method: 'POST', body: dados });
        const resposta = await res.json();

        barraRestaurar.classList.remove('progress-bar-animated');
        textoRestaurar.classList.add('d-none');

        passosRestaurar.classList.remove('d-none');
        passosRestaurar.innerHTML = Object.keys(resposta.passos || {}).map(function (chave) {
            const p = resposta.passos[chave];
            const icone = p.success ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger';
            return '<div class="passo-restauracao"><i class="bi ' + icone + '"></i> ' +
                (NOMES_PASSO[chave] || chave) + (p.success ? '' : ': ' + (p.message || '')) + '</div>';
        }).join('');

        restaurarSucesso.classList.remove('d-none');
    }

    function consultarStatusRestaurar(execucaoId) {
        const intervalo = setInterval(async function () {
            try {
                const res = await fetch(URLS.restaurarStatus + '?execucao_id=' + encodeURIComponent(execucaoId));
                const dados = await res.json();

                if (dados.status === 'rodando') {
                    atualizarBarraRestaurar(dados.percentual);
                    textoRestaurar.textContent = dados.mensagem || 'Restaurando...';
                    return;
                }

                if (dados.status === 'concluido') {
                    clearInterval(intervalo);
                    atualizarBarraRestaurar(100);
                    finalizarRestauracao(execucaoId);
                    return;
                }

                if (dados.status === 'erro') {
                    clearInterval(intervalo);
                    barraRestaurar.classList.remove('progress-bar-animated');
                    textoRestaurar.classList.add('d-none');
                    restaurarErro.textContent = dados.mensagem || 'Erro ao restaurar.';
                    restaurarErro.classList.remove('d-none');
                }
            } catch (e) {
                // falha de rede pontual -- tenta de novo no proximo tick
            }
        }, 2000);
    }

    formRestaurar.addEventListener('submit', async function (e) {
        e.preventDefault();
        restaurarErro.classList.add('d-none');

        const arquivo = document.getElementById('campoArquivoRestaurar').files[0];
        if (!arquivo) return;

        if (!confirm('Tem certeza? Isso vai SUBSTITUIR todo o banco de dados e arquivos críticos deste servidor pelos do backup.')) {
            return;
        }

        const botao = document.getElementById('botaoRestaurar');
        botao.disabled = true;
        try {
            const dados = new FormData();
            dados.set('arquivo', arquivo);
            dados.set('senha', document.getElementById('campoSenhaRestaurar').value);
            dados.set('confirmacao', document.getElementById('campoConfirmarRestaurar').value);

            const res = await fetch(URLS.restaurarUpload, { method: 'POST', body: dados });
            const resposta = await res.json();

            if (!resposta.success) {
                restaurarErro.textContent = resposta.message;
                restaurarErro.classList.remove('d-none');
                return;
            }

            painelRestaurar.classList.remove('d-none');
            atualizarBarraRestaurar(0);
            consultarStatusRestaurar(resposta.execucao_id);
        } catch (e) {
            restaurarErro.textContent = 'Erro de rede ao enviar o arquivo.';
            restaurarErro.classList.remove('d-none');
        } finally {
            botao.disabled = false;
        }
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Configurações -- Backup e Restauração';

require __DIR__ . '/../layouts/main.php';
