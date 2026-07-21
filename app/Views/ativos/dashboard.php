<?php
ob_start();

use App\Components\Alert;
use App\Services\AtivoService;

$statusCores = [
    'ativo' => 'success',
    'manutencao' => 'warning',
    'estoque' => 'secondary',
    'baixado' => 'danger',
];
?>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1"><i class="bi bi-boxes me-1"></i> Ativos de TI</h4>
        <small class="text-muted">Controle do parque de computadores, monitores, impressoras, switches e servidores.</small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('/ativos/lista') ?>" class="btn btn-outline-secondary"><i class="bi bi-list-ul"></i> Ver lista</a>
        <a href="<?= url('/ativos/novo') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Novo Ativo</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php foreach (AtivoService::TIPOS as $chave => $info): ?>
        <div class="col-md-4" style="flex:1 1 200px">
            <a href="<?= url('/ativos/lista?tipo=' . $chave) ?>" class="card border-0 shadow-sm text-decoration-none h-100">
                <div class="card-body text-center">
                    <i class="bi <?= $info['icone'] ?> display-6 text-primary"></i>
                    <div class="fs-3 fw-bold mt-2"><?= (int)($por_tipo[$chave] ?? 0) ?></div>
                    <div class="text-muted small"><?= htmlspecialchars($info['label']) ?></div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white"><strong>Por status</strong></div>
            <div class="card-body">
                <?php if (empty($por_status)): ?>
                    <p class="text-muted mb-0">Nenhum ativo cadastrado ainda.</p>
                <?php else: ?>
                    <?php foreach (AtivoService::STATUS as $chave => $label): ?>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <span><span class="badge text-bg-<?= $statusCores[$chave] ?>">&nbsp;</span> <?= htmlspecialchars($label) ?></span>
                            <strong><?= (int)($por_status[$chave] ?? 0) ?></strong>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><strong>Agente Windows</strong></div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Instale nos computadores/servidores Windows pra receber automaticamente hardware,
                    programas instalados e alertas do Visualizador de Eventos.
                </p>
                <div class="mb-3">
                    <label class="form-label small mb-1">Chave de API do agente</label>
                    <div class="input-group input-group-sm" style="max-width:520px">
                        <input type="text" class="form-control font-monospace" value="<?= htmlspecialchars($chaveAgente) ?>" readonly id="campoChaveAgente">
                        <button class="btn btn-outline-secondary" type="button" id="botaoCopiarChave" title="Copiar"><i class="bi bi-clipboard"></i></button>
                    </div>
                </div>
                <a href="<?= url('/ativos/agente/script') ?>" class="btn btn-sm btn-primary"><i class="bi bi-download"></i> Baixar script do agente (.ps1)</a>
                <?php if ($agenteExeDisponivel): ?>
                    <a href="<?= url('/ativos/agente/exe') ?>" class="btn btn-sm btn-primary"><i class="bi bi-download"></i> Baixar agente (.exe) -- v<?= htmlspecialchars($versaoAgenteExe) ?></a>
                <?php endif; ?>
                <?php if ($dotnetRuntimeDisponivel): ?>
                    <a href="<?= url('/ativos/agente/dotnet') ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-download"></i> Baixar .NET Desktop Runtime -- <?= htmlspecialchars($dotnetRuntimeLabel) ?></a>
                <?php endif; ?>
                <button type="button" class="btn btn-sm btn-outline-danger" id="botaoAbrirRegenerar" data-bs-toggle="collapse" data-bs-target="#painelRegenerarChave">
                    <i class="bi bi-arrow-repeat"></i> Gerar nova chave
                </button>
                <?php if (!$dotnetRuntimeDisponivel): ?>
                    <p class="text-muted small mt-2 mb-0">
                        <i class="bi bi-info-circle"></i> O agente <code>.exe</code> framework-dependent (menor)
                        precisa do <strong>.NET 8 Desktop Runtime</strong> instalado na máquina pra rodar. Envie o
                        instalador no card "Atualizar agente (.exe)" pra disponibilizar o download aqui também.
                    </p>
                <?php endif; ?>

                <div class="collapse mt-3" id="painelRegenerarChave">
                    <div class="border rounded p-3 bg-light">
                        <p class="small mb-2">
                            <i class="bi bi-info-circle"></i> A chave <strong>atual continua válida</strong> depois de
                            gerar uma nova -- nada quebra na hora. Só desativar uma chave explicitamente (na tabela de
                            histórico abaixo) derruba os agentes que ainda estiverem usando ela.
                        </p>
                        <form method="post" action="<?= url('/ativos/agente/regenerar-chave') ?>" id="formRegenerarChave">
                            <div class="form-check mb-2">
                                <input type="checkbox" class="form-check-input" name="notificar_agentes" value="1" id="campoNotificarAgentes" checked>
                                <label class="form-check-label small" for="campoNotificarAgentes">
                                    Enviar automaticamente pros agentes já conectados (recomendado -- eles adotam a
                                    chave nova sozinhos em segundos, via o próprio heartbeat). Desmarcado: só quem
                                    baixar o script/exe a partir de agora sai com essa chave; os já instalados
                                    continuam na chave anterior até você decidir notificar ou reinstalar manualmente.
                                </label>
                            </div>
                            <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-arrow-repeat"></i> Confirmar geração</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#painelRegenerarChave">Cancelar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>Histórico de chaves de API</strong>
                <span class="text-muted small"><?= count($historicoChaves) ?> chave(s)</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Chave</th>
                                <th>Gerada por</th>
                                <th>Quando</th>
                                <th>Status</th>
                                <th class="text-end">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historicoChaves as $c): ?>
                                <tr>
                                    <td class="font-monospace small"><?= htmlspecialchars(substr($c['chave'], 0, 8)) ?>...<?= htmlspecialchars(substr($c['chave'], -4)) ?></td>
                                    <td class="small"><?= htmlspecialchars($c['gerada_por'] ?? '—') ?></td>
                                    <td class="text-muted small"><?= htmlspecialchars(data_br($c['criada_em'])) ?></td>
                                    <td class="small">
                                        <?php if (!$c['ativa']): ?>
                                            <span class="badge text-bg-secondary">Desativada</span>
                                            <?php if (!empty($c['desativada_por'])): ?>
                                                <div class="text-muted" style="font-size:10px">por <?= htmlspecialchars($c['desativada_por']) ?> em <?= htmlspecialchars(data_br($c['desativada_em'])) ?></div>
                                            <?php endif; ?>
                                        <?php elseif ($c['eh_atual']): ?>
                                            <span class="badge text-bg-success">Atual</span>
                                        <?php else: ?>
                                            <span class="badge text-bg-info">Ativa</span>
                                            <?php if ($c['ativos_usando'] > 0): ?>
                                                <div class="text-muted" style="font-size:10px"><?= (int)$c['ativos_usando'] ?> ativo(s) usando</div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($c['ativa']): ?>
                                            <form method="post" action="<?= url('/ativos/agente/desativar-chave') ?>" class="d-inline formDesativarChave"
                                                  data-ativos-usando="<?= (int)$c['ativos_usando'] ?>">
                                                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-slash-circle"></i> Desativar</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><strong>Atualizar agente (.exe)</strong></div>
            <div class="card-body">
                <p class="text-muted small mb-2">
                    <?php if ($agenteExeDisponivel): ?>
                        Versão atual disponível: <strong>v<?= htmlspecialchars($versaoAgenteExe) ?></strong>.
                    <?php else: ?>
                        Nenhuma versão enviada ainda -- os agentes .exe já instalados não se autoatualizam até o primeiro envio.
                    <?php endif; ?>
                    Envie aqui um novo <code>.exe</code> publicado (veja o README em <code>agente-windows/</code>) junto do
                    número de versão (o mesmo do <code>&lt;Version&gt;</code> no <code>.csproj</code>) -- os agentes já
                    instalados detectam a versão nova sozinhos e se atualizam no próximo check-in, sem precisar
                    reinstalar máquina por máquina.
                </p>
                <form action="<?= url('/ativos/agente/exe/upload') ?>" enctype="multipart/form-data" class="row g-2 align-items-end" id="formUploadAgente">
                    <div class="col-auto">
                        <label class="form-label small mb-0">Versão</label>
                        <input type="text" name="versao" class="form-control form-control-sm" style="width:110px" placeholder="1.0.1" pattern="\d+\.\d+\.\d+" required>
                    </div>
                    <div class="col-auto">
                        <label class="form-label small mb-0">Arquivo (.exe)</label>
                        <input type="file" name="arquivo" accept=".exe" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-sm btn-outline-primary" id="botaoUploadAgente"><i class="bi bi-upload"></i> Enviar</button>
                    </div>
                </form>
                <div class="progress mt-2 d-none" id="progressoUploadAgente" style="height:20px">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width:0%">0%</div>
                </div>

                <p class="text-muted small mb-1 mt-2">
                    Roda o sistema em mais de um servidor? Publique o <code>.exe</code> em
                    <code>agente-windows/dist/</code> no repositório git (veja o passo a passo no README em
                    <code>agente-windows/</code>) e use o botão abaixo em cada servidor pra buscar a versão
                    publicada, sem precisar repetir o upload manual.
                </p>
                <form action="<?= url('/ativos/agente/exe/baixar-git') ?>" method="post" class="d-inline">
                    <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-git"></i> Baixar do repositório</button>
                </form>

                <hr>

                <p class="text-muted small mb-2">
                    <?php if ($dotnetRuntimeDisponivel): ?>
                        .NET Desktop Runtime disponível: <strong><?= htmlspecialchars($dotnetRuntimeLabel) ?></strong>.
                    <?php else: ?>
                        Nenhum .NET Desktop Runtime enviado ainda.
                    <?php endif; ?>
                    Baixe o instalador em
                    <a href="https://dotnet.microsoft.com/download/dotnet/8.0" target="_blank">dotnet.microsoft.com</a>
                    (".NET Desktop Runtime", <em>não</em> o SDK) e envie aqui -- fica disponível pra baixar direto
                    do card "Agente Windows" acima, sem precisar ir buscar no site da Microsoft em cada máquina nova.
                </p>
                <form action="<?= url('/ativos/agente/dotnet/upload') ?>" enctype="multipart/form-data" class="row g-2 align-items-end" id="formUploadDotnet">
                    <div class="col-auto">
                        <label class="form-label small mb-0">Rótulo</label>
                        <input type="text" name="label" class="form-control form-control-sm" style="width:170px" placeholder="8.0.11 (win-x64)" required>
                    </div>
                    <div class="col-auto">
                        <label class="form-label small mb-0">Arquivo (.exe)</label>
                        <input type="file" name="arquivo" accept=".exe" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-sm btn-outline-primary" id="botaoUploadDotnet"><i class="bi bi-upload"></i> Enviar</button>
                    </div>
                </form>
                <div class="progress mt-2 d-none" id="progressoUploadDotnet" style="height:20px">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width:0%">0%</div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><strong>Comunicação com Agentes</strong></div>
            <div class="card-body">
                <p class="text-muted small mb-2">
                    <i class="bi bi-broadcast"></i> <strong>Heartbeat</strong> -- ping bem leve que só confirma
                    "estou ligado", em tempo quase real. É o que decide o badge Ligado/Desligado na lista e na
                    ficha do ativo (considerado "Desligado" depois de 3x esse valor sem receber um ping, mínimo 5s).
                </p>
                <form method="post" action="<?= url('/ativos/heartbeat/salvar') ?>" class="row g-2 align-items-end mb-3">
                    <div class="col-auto">
                        <label class="form-label small mb-0">Intervalo (segundos)</label>
                        <input type="number" name="segundos" class="form-control form-control-sm" style="width:100px"
                               min="1" max="60" value="<?= (int)$heartbeatIntervalo ?>">
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-sm btn-outline-secondary">Salvar</button>
                    </div>
                </form>
                <p class="text-muted small mb-2">
                    <i class="bi bi-hdd-stack"></i> <strong>Coleta completa</strong> -- hardware, programas
                    instalados e alertas do Visualizador de Eventos. Mais pesada, por isso roda num intervalo
                    maior. Gravado automaticamente no script <code>.ps1</code> baixado a partir de agora --
                    agentes já instalados mantêm o intervalo com que foram configurados até serem reinstalados.
                    Pra forçar uma coleta fora do ciclo, use o botão "Forçar coleta agora" na ficha do ativo.
                </p>
                <form method="post" action="<?= url('/ativos/comunicacao/salvar') ?>" class="row g-2 align-items-end">
                    <div class="col-auto">
                        <label class="form-label small mb-0">Intervalo (minutos)</label>
                        <input type="number" name="minutos" class="form-control form-control-sm" style="width:100px"
                               min="5" max="240" value="<?= (int)$intervaloComunicacao ?>">
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-sm btn-outline-secondary">Salvar</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><strong>Coleta via SNMP</strong></div>
            <div class="card-body">
                <form method="post" action="<?= url('/ativos/snmp/config') ?>" class="row g-2 align-items-end mb-3">
                    <div class="col-auto">
                        <label class="form-label small mb-0">Community padrão</label>
                        <input type="text" name="comunidade" class="form-control form-control-sm" value="<?= htmlspecialchars($comunidadePadrao) ?>" style="width:160px">
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-sm btn-outline-secondary">Salvar</button>
                    </div>
                </form>
                <div class="d-flex justify-content-between align-items-center small text-muted">
                    <span><i class="bi bi-info-circle"></i> Coleta periódica (a cada 30 min) dos ativos com SNMP habilitado.</span>
                    <?php if ($coletaSnmpAtiva): ?>
                        <span class="text-success"><i class="bi bi-check-circle"></i> Ativa</span>
                    <?php else: ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="botaoAtivarColetaSnmp">Ativar coleta periódica</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>Cadastrados recentemente</strong>
                <span class="text-muted small">Total: <?= (int)$total ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentes)): ?>
                    <p class="text-muted p-3 mb-0">Nenhum ativo cadastrado ainda. <a href="<?= url('/ativos/novo') ?>">Cadastre o primeiro</a>.</p>
                <?php else: ?>
                    <table class="table table-hover align-middle mb-0">
                        <tbody>
                            <?php foreach ($recentes as $a): ?>
                                <tr>
                                    <td class="font-monospace small"><?= htmlspecialchars($a['codigo_patrimonio']) ?></td>
                                    <td><?= htmlspecialchars($a['nome']) ?></td>
                                    <td class="text-muted small"><?= htmlspecialchars(AtivoService::TIPOS[$a['tipo']]['label']) ?></td>
                                    <td class="text-end">
                                        <a href="<?= url('/ativos/ver?id=' . $a['id']) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const botao = document.getElementById('botaoAtivarColetaSnmp');
    if (botao) {
        botao.addEventListener('click', async function () {
            botao.disabled = true;
            try {
                const res = await fetch(<?= json_encode(url('/ativos/snmp/ativar-coleta')) ?>, { method: 'POST' });
                const dados = await res.json();
                alert(dados.message || (dados.success ? 'Ativado.' : 'Falha ao ativar.'));
                location.reload();
            } catch (e) {
                botao.disabled = false;
            }
        });
    }

    const botaoCopiar = document.getElementById('botaoCopiarChave');
    if (botaoCopiar) {
        botaoCopiar.addEventListener('click', async function () {
            const campo = document.getElementById('campoChaveAgente');
            let copiou = false;

            // navigator.clipboard exige contexto seguro (HTTPS/localhost) --
            // em HTTP puro o objeto nem existe, e sem esse fallback o botão
            // falhava calado (sem nenhum feedback de erro).
            if (window.isSecureContext && navigator.clipboard) {
                try {
                    await navigator.clipboard.writeText(campo.value);
                    copiou = true;
                } catch (e) {
                    copiou = false;
                }
            }

            if (!copiou) {
                campo.removeAttribute('readonly');
                campo.select();
                campo.setSelectionRange(0, 99999);
                try {
                    copiou = document.execCommand('copy');
                } catch (e) {
                    copiou = false;
                }
                campo.setAttribute('readonly', 'readonly');
                window.getSelection().removeAllRanges();
            }

            botaoCopiar.innerHTML = copiou ? '<i class="bi bi-check-lg"></i>' : '<i class="bi bi-x-lg"></i>';
            setTimeout(function () { botaoCopiar.innerHTML = '<i class="bi bi-clipboard"></i>'; }, 1500);
        });
    }

    const formRegenerar = document.getElementById('formRegenerarChave');
    if (formRegenerar) {
        formRegenerar.addEventListener('submit', function (e) {
            const notificar = document.getElementById('campoNotificarAgentes').checked;
            const mensagem = notificar
                ? 'Gerar uma chave nova? A chave atual continua funcionando -- a nova vai ser enviada automaticamente pros agentes já conectados nos próximos segundos.'
                : 'Gerar uma chave nova SEM notificar os agentes já conectados? Eles continuam na chave atual até você decidir notificar (ou reinstalar manualmente) -- só instalações novas já saem com a chave nova.';

            if (!confirm(mensagem)) {
                e.preventDefault();
            }
        });
    }

    document.querySelectorAll('.formDesativarChave').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            const ativosUsando = parseInt(form.dataset.ativosUsando || '0', 10);
            const mensagem = ativosUsando > 0
                ? `Desativar essa chave? Pelo menos ${ativosUsando} ativo(s) autenticaram com ela da última vez que se conectaram -- se ainda não adotaram uma chave mais nova, vão PARAR de conseguir se comunicar com o servidor até serem reinstalados. Essa ação não pode ser desfeita.`
                : 'Desativar essa chave? Nenhum ativo conhecido usou ela recentemente, mas a ação não pode ser desfeita -- qualquer agente ainda configurado com ela vai parar de conseguir se comunicar. Continuar?';

            if (!confirm(mensagem)) {
                e.preventDefault();
            }
        });
    });

    function configurarUploadComProgresso(idForm, idBotao, idProgresso) {
        const form = document.getElementById(idForm);
        if (!form) return;

        const botao = document.getElementById(idBotao);
        const progresso = document.getElementById(idProgresso);
        const barra = progresso.querySelector('.progress-bar');

        form.addEventListener('submit', function (e) {
            e.preventDefault();

            botao.disabled = true;
            progresso.classList.remove('d-none');
            barra.style.width = '0%';
            barra.textContent = '0%';

            const xhr = new XMLHttpRequest();
            xhr.open('POST', form.action, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

            xhr.upload.addEventListener('progress', function (evento) {
                if (!evento.lengthComputable) return;
                const pct = Math.round((evento.loaded / evento.total) * 100);
                barra.style.width = pct + '%';
                barra.textContent = pct + '%';
            });

            xhr.addEventListener('load', function () {
                // O envio chegou ao servidor -- recarrega pra mostrar a
                // mensagem de resultado (Alert::flash) e o status
                // atualizado. Falha de rede antes de chegar lá cai no
                // listener de 'error' abaixo, sem recarregar.
                window.location.href = <?= json_encode(url('/ativos')) ?>;
            });

            xhr.addEventListener('error', function () {
                botao.disabled = false;
                progresso.classList.add('d-none');
                alert('Falha de rede ao enviar o arquivo. Tente novamente.');
            });

            xhr.send(new FormData(form));
        });
    }

    configurarUploadComProgresso('formUploadAgente', 'botaoUploadAgente', 'progressoUploadAgente');
    configurarUploadComProgresso('formUploadDotnet', 'botaoUploadDotnet', 'progressoUploadDotnet');
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Ativos de TI - Dashboard';

require __DIR__ . '/../layouts/main.php';
