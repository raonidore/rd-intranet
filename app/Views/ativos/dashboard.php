<?php
ob_start();

use App\Components\Alert;
use App\Services\AtivoService;
use App\Services\PermissionService;

$statusCores = [
    'ativo' => 'success',
    'manutencao' => 'warning',
    'estoque' => 'secondary',
    'baixado' => 'danger',
];
?>

<style>
.config-panel-header {
    display: flex;
    align-items: center;
    gap: 12px;
    background: linear-gradient(135deg, #0d1117, #161b22);
    border-radius: 12px;
    padding: 14px 18px;
    margin-bottom: 12px;
}
.config-panel-header > i {
    font-size: 1.5rem;
    color: #58a6ff;
}
.config-panel-title { color: #fff; font-weight: 700; font-size: 1.02rem; }
.config-panel-subtitle { color: #8b949e; font-size: .78rem; }
.config-tabs { border-bottom: none; gap: 6px; flex-wrap: wrap; margin-bottom: 14px; }
.config-tabs .nav-link {
    color: #495057;
    border: 1px solid #dee2e6;
    border-radius: 999px;
    padding: 6px 14px;
    font-size: .82rem;
    font-weight: 600;
    background: #fff;
    transition: all .15s ease;
}
.config-tabs .nav-link i { margin-right: 4px; }
.config-tabs .nav-link:hover { color: #0d1117; border-color: #58a6ff; }
.config-tabs .nav-link.active { color: #fff; background: #0d1117; border-color: #0d1117; }

.integration-tile {
    border-radius: 12px;
    background: linear-gradient(160deg, #161b22, #0d1117);
    border: 1px solid #30363d;
    padding: 16px;
    height: 100%;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.integration-tile .integration-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}
.integration-tile .integration-name { color: #fff; font-weight: 700; font-size: .92rem; }
.integration-tile .integration-desc { color: #8b949e; font-size: .78rem; line-height: 1.4; flex-grow: 1; margin-bottom: 0; }
.integration-status { display: inline-flex; align-items: center; gap: 6px; font-size: .74rem; font-weight: 600; color: #8b949e; }
.integration-status .status-dot { width: 8px; height: 8px; border-radius: 50%; background: #6e7681; display: inline-block; }
.integration-status.is-ativa { color: #3fb950; }
.integration-status.is-ativa .status-dot { background: #3fb950; animation: pulse-dot 1.8s infinite; }
@keyframes pulse-dot {
    0% { box-shadow: 0 0 0 0 rgba(63,185,80,.55); }
    70% { box-shadow: 0 0 0 6px rgba(63,185,80,0); }
    100% { box-shadow: 0 0 0 0 rgba(63,185,80,0); }
}
.integration-tile a { color: #58a6ff; text-decoration: none; }
.integration-tile a:hover { text-decoration: underline; }
.integration-tile .form-label { color: #8b949e; }
.integration-tile .form-control-sm { background: #0d1117; border-color: #30363d; color: #c9d1d9; }
.integration-tile .form-control-sm:focus { background: #0d1117; color: #fff; box-shadow: none; }

.config-toggle { cursor: pointer; display: flex; justify-content: space-between; align-items: center; padding: 4px 0; }
.config-toggle .bi-chevron-down { transition: transform .2s ease; color: #adb5bd; }
.config-toggle[aria-expanded="true"] .bi-chevron-down { transform: rotate(180deg); }
</style>

<?= Alert::flash() ?>

<?php if (!empty($duplicatas)): ?>
<div class="alert alert-warning border-0 shadow-sm mb-4">
    <div class="d-flex align-items-start gap-2">
        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
        <div class="flex-grow-1">
            <strong><?= count($duplicatas) ?> possível<?= count($duplicatas) > 1 ? 'eis' : '' ?> duplicata<?= count($duplicatas) > 1 ? 's' : '' ?> de ativo detectada<?= count($duplicatas) > 1 ? 's' : '' ?></strong>
            <div class="small text-muted mb-2">
                Máquinas com o mesmo nome cadastradas mais de uma vez, com identificador diferente -- geralmente o agente perdeu a identidade estável dela (comum em VMs sem BIOS/SMBIOS customizado pelo hypervisor). Confira e decida manualmente qual manter.
            </div>
            <?php foreach ($duplicatas as $nome => $itens): ?>
                <div class="mb-2">
                    <div class="fw-semibold"><?= htmlspecialchars($nome) ?></div>
                    <ul class="mb-0 small">
                        <?php foreach ($itens as $item): ?>
                            <li>
                                <a href="<?= url('/ativos/ver?id=' . (int)$item['id']) ?>"><?= htmlspecialchars($item['codigo_patrimonio']) ?></a>
                                -- agente v<?= htmlspecialchars($item['agente_versao'] ?: '?') ?>,
                                IP <?= htmlspecialchars($item['ip'] ?: '-') ?>,
                                cadastrado em <?= htmlspecialchars(data_br($item['criado_em'])) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

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
    <?php foreach ($tipos as $t): ?>
        <div class="col-md-4" style="flex:1 1 200px">
            <a href="<?= url('/ativos/lista?tipo_id=' . (int)$t['id']) ?>" class="card border-0 shadow-sm text-decoration-none h-100">
                <div class="card-body text-center">
                    <i class="bi <?= htmlspecialchars($t['icone']) ?> display-6 text-primary"></i>
                    <div class="fs-3 fw-bold mt-2"><?= (int)($por_tipo[$t['id']] ?? 0) ?></div>
                    <div class="text-muted small"><?= htmlspecialchars($t['nome']) ?></div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 align-items-start">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
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

        <div class="card border-0 shadow-sm mt-3">
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
                                    <td class="text-muted small"><?= htmlspecialchars($a['tipo_nome']) ?></td>
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

        <?php if (PermissionService::temAcesso('ativos_politicas')): ?>
            <a href="<?= url('/ativos/politicas') ?>" class="card border-0 shadow-sm text-decoration-none mt-3" style="background:linear-gradient(135deg,#0d1117,#161b22)">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <i class="bi bi-shield-lock-fill" style="font-size:2rem; color:#58a6ff"></i>
                    <div>
                        <div class="fw-bold" style="color:#fff">Regras de Segurança</div>
                        <div class="small" style="color:#8b949e">Políticas locais via agente -- USB, CMD, firewall, papel de parede e mais, sem depender do Intune.</div>
                    </div>
                    <i class="bi bi-chevron-right ms-auto" style="color:#58a6ff"></i>
                </div>
            </a>
        <?php endif; ?>
    </div>

    <div class="col-lg-7">
        <div class="config-panel-header">
            <i class="bi bi-sliders"></i>
            <div>
                <div class="config-panel-title">Configurações da coleta automática</div>
                <div class="config-panel-subtitle">Agente Windows, integrações de rede e frequência de comunicação</div>
            </div>
        </div>

        <ul class="nav config-tabs" id="abasConfig" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabAgente" type="button" role="tab">
                    <i class="bi bi-laptop"></i> Agente Windows
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabIntegracoes" type="button" role="tab">
                    <i class="bi bi-hdd-network"></i> Integrações de rede
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabComunicacao" type="button" role="tab">
                    <i class="bi bi-broadcast"></i> Comunicação
                </button>
            </li>
        </ul>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
            <div class="tab-content">
                <div class="tab-pane fade show active" id="tabAgente" role="tabpanel">
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
                    <div class="d-flex flex-wrap gap-2 mb-2">
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
                    </div>
                    <?php if (!$dotnetRuntimeDisponivel): ?>
                        <p class="text-muted small mb-0">
                            <i class="bi bi-info-circle"></i> O agente <code>.exe</code> framework-dependent (menor)
                            precisa do <strong>.NET 8 Desktop Runtime</strong> instalado na máquina pra rodar. Envie o
                            instalador em "Atualizar agente e runtime" abaixo pra disponibilizar o download aqui também.
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

                    <hr class="my-3">

                    <div class="config-toggle text-secondary" data-bs-toggle="collapse" data-bs-target="#painelHistoricoChaves" role="button" aria-expanded="false">
                        <span class="small fw-semibold"><i class="bi bi-clock-history"></i> Histórico de chaves de API <span class="text-muted fw-normal">(<?= count($historicoChaves) ?>)</span></span>
                        <i class="bi bi-chevron-down"></i>
                    </div>
                    <div class="collapse" id="painelHistoricoChaves">
                        <div class="table-responsive mt-2">
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

                    <hr class="my-3">

                    <div class="config-toggle text-secondary" data-bs-toggle="collapse" data-bs-target="#painelAtualizarAgente" role="button" aria-expanded="false">
                        <span class="small fw-semibold"><i class="bi bi-upload"></i> Atualizar agente e runtime</span>
                        <i class="bi bi-chevron-down"></i>
                    </div>
                    <div class="collapse" id="painelAtualizarAgente">
                        <div class="pt-2">
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
                                da aba "Agente Windows" acima, sem precisar ir buscar no site da Microsoft em cada máquina nova.
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
                </div>

                <div class="tab-pane fade" id="tabIntegracoes" role="tabpanel">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="integration-tile">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="integration-icon" style="background:rgba(163,113,247,.18); color:#a371f7"><i class="bi bi-diagram-3"></i></div>
                                    <div>
                                        <div class="integration-name">SNMP</div>
                                        <div class="integration-status <?= $coletaSnmpAtiva ? 'is-ativa' : '' ?>">
                                            <span class="status-dot"></span> <?= $coletaSnmpAtiva ? 'Ativa' : 'Inativa' ?>
                                        </div>
                                    </div>
                                </div>
                                <p class="integration-desc">Coleta periódica (a cada 30 min) de switches e outros equipamentos com SNMP habilitado.</p>
                                <form method="post" action="<?= url('/ativos/snmp/config') ?>" class="d-flex gap-1 align-items-end">
                                    <div class="flex-grow-1">
                                        <label class="form-label small mb-1">Community padrão</label>
                                        <input type="text" name="comunidade" class="form-control form-control-sm" value="<?= htmlspecialchars($comunidadePadrao) ?>">
                                    </div>
                                    <button class="btn btn-sm btn-outline-light" title="Salvar"><i class="bi bi-check-lg"></i></button>
                                </form>
                                <?php if (!$coletaSnmpAtiva): ?>
                                    <button type="button" class="btn btn-sm btn-outline-light" id="botaoAtivarColetaSnmp">Ativar coleta periódica</button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="integration-tile">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="integration-icon" style="background:rgba(88,166,255,.18); color:#58a6ff"><i class="bi bi-wifi"></i></div>
                                    <div>
                                        <div class="integration-name">UniFi Controller</div>
                                        <div class="integration-status <?= $coletaUnifiAtiva ? 'is-ativa' : '' ?>">
                                            <span class="status-dot"></span> <?= $coletaUnifiAtiva ? 'Ativa' : 'Inativa' ?>
                                        </div>
                                    </div>
                                </div>
                                <p class="integration-desc">Modelo, firmware, status, rádios, clientes conectados e WAN dos gateways/APs UniFi -- alternativa ao SNMP, que não funciona nessa linha.</p>
                                <div class="d-flex justify-content-between align-items-center mt-auto">
                                    <a href="<?= url('/administracao/integracoes/unifi') ?>" class="small">Configurar credenciais</a>
                                    <?php if (!$coletaUnifiAtiva): ?>
                                        <button type="button" class="btn btn-sm btn-outline-light" id="botaoAtivarColetaUnifi">Ativar coleta</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="integration-tile">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="integration-icon" style="background:rgba(63,185,80,.18); color:#3fb950"><i class="bi bi-hdd-network"></i></div>
                                    <div>
                                        <div class="integration-name">Omada Controller</div>
                                        <div class="integration-status <?= $coletaOmadaAtiva ? 'is-ativa' : '' ?>">
                                            <span class="status-dot"></span> <?= $coletaOmadaAtiva ? 'Ativa' : 'Inativa' ?>
                                        </div>
                                    </div>
                                </div>
                                <p class="integration-desc">Modelo, firmware, status e uptime dos switches TP-Link -- esses switches não têm API própria em modo standalone.</p>
                                <div class="d-flex justify-content-between align-items-center mt-auto">
                                    <a href="<?= url('/administracao/integracoes/omada') ?>" class="small">Configurar credenciais</a>
                                    <?php if (!$coletaOmadaAtiva): ?>
                                        <button type="button" class="btn btn-sm btn-outline-light" id="botaoAtivarColetaOmada">Ativar coleta</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="tabComunicacao" role="tabpanel">
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

    const botaoUnifi = document.getElementById('botaoAtivarColetaUnifi');
    if (botaoUnifi) {
        botaoUnifi.addEventListener('click', async function () {
            botaoUnifi.disabled = true;
            try {
                const res = await fetch(<?= json_encode(url('/ativos/unifi/ativar-coleta')) ?>, { method: 'POST' });
                const dados = await res.json();
                alert(dados.message || (dados.success ? 'Ativado.' : 'Falha ao ativar.'));
                location.reload();
            } catch (e) {
                botaoUnifi.disabled = false;
            }
        });
    }

    const botaoOmada = document.getElementById('botaoAtivarColetaOmada');
    if (botaoOmada) {
        botaoOmada.addEventListener('click', async function () {
            botaoOmada.disabled = true;
            try {
                const res = await fetch(<?= json_encode(url('/ativos/omada/ativar-coleta')) ?>, { method: 'POST' });
                const dados = await res.json();
                alert(dados.message || (dados.success ? 'Ativado.' : 'Falha ao ativar.'));
                location.reload();
            } catch (e) {
                botaoOmada.disabled = false;
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
