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

// Explorador de arquivos/processos depende do heartbeat (poucos segundos
// de ida e volta) -- o script .ps1 roda como Tarefa Agendada (sem
// processo residente) e nunca manda heartbeat, então nunca vai responder
// a essas solicitações. Só oferece pra quem está no agente de bandeja (.exe).
$agenteSuportaExplorador = $ativo['origem'] === 'agente' && ($ativo['agente_versao'] ?? '') !== 'ps1';

// Campos de "Componentes" ficam numa aba própria -- o resto dos
// detalhes técnicos (SO, funcao, snmp, etc.) fica na Visão Geral.
$camposComponentes = ['processador', 'memoria_ram', 'tipo_memoria', 'placa_mae', 'placa_video', 'placa_som', 'armazenamento'];
$camposVisaoGeral = array_diff_key($camposTipo, array_flip($camposComponentes));

function parseGb($texto): float
{
    if (!$texto) return 0.0;
    preg_match('/([\d.]+)/', (string)$texto, $m);
    return isset($m[1]) ? (float)$m[1] : 0.0;
}

function gaugeRadial(float $percentual, string $label, string $sublabel): string
{
    $percentual = max(0, min(100, $percentual));
    $cor = $percentual >= 90 ? '#ef4444' : ($percentual >= 75 ? '#f59e0b' : '#22c55e');
    $graus = $percentual * 3.6;

    return '
        <div class="text-center">
            <div class="gauge-radial" style="--pct:' . $graus . 'deg; --cor:' . $cor . '">
                <div class="gauge-valor">' . round($percentual) . '%</div>
            </div>
            <div class="gauge-label mt-2">' . htmlspecialchars($label) . '</div>
            <div class="gauge-sublabel text-muted small">' . htmlspecialchars($sublabel) . '</div>
        </div>
    ';
}

$memoriaTotalGb = parseGb($detalhes['memoria_ram'] ?? null);
$memoriaUsadaGb = parseGb($detalhes['memoria_usada'] ?? null);
$memoriaPct = $memoriaTotalGb > 0 ? ($memoriaUsadaGb / $memoriaTotalGb) * 100 : null;

$volumePrincipal = $volumes[0] ?? null;
$discoPct = null;
if ($volumePrincipal && (float)$volumePrincipal['total_gb'] > 0) {
    $discoPct = ((float)$volumePrincipal['usado_gb'] / (float)$volumePrincipal['total_gb']) * 100;
}
?>

<style>
.gauge-radial {
    width: 92px; height: 92px; border-radius: 50%; margin: 0 auto;
    background: conic-gradient(var(--cor) var(--pct), #e9ecef 0deg);
    display: flex; align-items: center; justify-content: center; position: relative;
}
.gauge-radial::before {
    content: ''; position: absolute; inset: 9px; border-radius: 50%; background: #fff;
}
.gauge-valor { position: relative; z-index: 1; font-weight: 700; font-size: 15px; font-family: 'SFMono-Regular', Consolas, monospace; }
.gauge-label { font-size: 13px; font-weight: 600; }

/* Explorador de arquivos / gerenciador de processos -- painel escuro,
   monoespaçado, visual "console remoto" pra deixar claro que é uma
   sessão ao vivo com a máquina, não um relatório estático. */
.hitech-panel {
    background: #0d1117; border-radius: 12px; border: 1px solid #30363d;
    box-shadow: 0 0 24px rgba(88,166,255,.08);
    font-family: 'SFMono-Regular', Consolas, 'Courier New', monospace;
    color: #c9d1d9; overflow: hidden;
}
.hitech-topbar {
    display: flex; align-items: center; justify-content: space-between; gap: 10px;
    padding: 10px 14px; background: #161b22; border-bottom: 1px solid #30363d;
}
.hitech-breadcrumb { font-size: 13px; color: #58a6ff; overflow-x: auto; white-space: nowrap; }
.hitech-breadcrumb .segmento { cursor: pointer; padding: 2px 4px; border-radius: 4px; }
.hitech-breadcrumb .segmento:hover { background: rgba(88,166,255,.15); text-decoration: underline; }
.hitech-breadcrumb .separador { color: #6e7681; margin: 0 2px; }
.hitech-body { max-height: 420px; overflow-y: auto; }
.hitech-table { width: 100%; font-size: 13px; border-collapse: collapse; }
.hitech-table th {
    position: sticky; top: 0; background: #161b22; color: #8b949e;
    text-transform: uppercase; font-size: 10px; letter-spacing: .04em;
    padding: 8px 12px; text-align: left; border-bottom: 1px solid #30363d; z-index: 1;
}
.hitech-table td { padding: 7px 12px; border-bottom: 1px solid #21262d; vertical-align: middle; }
.hitech-table tr.linha-pasta { cursor: pointer; }
.hitech-table tr.linha-pasta:hover, .hitech-table tr.linha-processo:hover { background: rgba(88,166,255,.07); }
.hitech-table .icone-pasta { color: #58a6ff; }
.hitech-table .icone-arquivo { color: #8b949e; }
.hitech-empty, .hitech-loading, .hitech-erro { padding: 32px 16px; text-align: center; color: #8b949e; font-size: 13px; }
.hitech-erro { color: #f85149; }
.hitech-loading .spinner-border { color: #58a6ff; width: 1.6rem; height: 1.6rem; }
.hitech-btn {
    background: transparent; border: 1px solid #30363d; color: #c9d1d9;
    border-radius: 6px; padding: 3px 9px; font-size: 12px; font-family: inherit;
}
.hitech-btn:hover { border-color: #58a6ff; color: #58a6ff; }
.hitech-btn-danger:hover { border-color: #f85149; color: #f85149; }
</style>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <small class="text-muted"><a href="<?= url('/ativos/lista') ?>"><i class="bi bi-arrow-left"></i> Lista de Ativos</a></small>
        <h4 class="mb-1 mt-1">
            <i class="bi <?= AtivoService::TIPOS[$ativo['tipo']]['icone'] ?> me-1"></i>
            <?= htmlspecialchars($ativo['apelido'] ?: $ativo['nome']) ?>
        </h4>
        <?php if (!empty($ativo['apelido'])): ?>
            <div class="text-muted small mb-1">Nome: <?= htmlspecialchars($ativo['nome']) ?></div>
        <?php endif; ?>
        <span class="font-monospace text-muted"><?= htmlspecialchars($ativo['codigo_patrimonio']) ?></span>
        <?= Badge::make(htmlspecialchars(AtivoService::STATUS[$ativo['status']] ?? $ativo['status']), $statusCores[$ativo['status']] ?? 'secondary') ?>
        <?php if ($ativo['origem'] === 'agente'): ?>
            <?= Badge::make($estaLigada ? '<i class="bi bi-circle-fill" style="font-size:8px"></i> Ligado' : 'Desligado', $estaLigada ? 'success' : 'secondary') ?>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-2">
        <?php if (!empty($ativo['mesh_device_id'])): ?>
            <button type="button" class="btn btn-outline-primary" id="botaoTelaRemota" data-id="<?= (int)$ativo['id'] ?>">
                <i class="bi bi-display"></i> Tela remota
            </button>
        <?php endif; ?>
        <?php if (!empty($ativo['snmp_habilitado']) && !empty($ativo['ip'])): ?>
            <button type="button" class="btn btn-outline-secondary" id="botaoColetarSnmp" data-id="<?= (int)$ativo['id'] ?>">
                <i class="bi bi-arrow-repeat"></i> Coletar via SNMP
            </button>
        <?php endif; ?>
        <?php if ($ativo['origem'] === 'agente'): ?>
            <button type="button" class="btn btn-outline-secondary" id="botaoForcarCheckin" data-id="<?= (int)$ativo['id'] ?>">
                <i class="bi bi-arrow-repeat"></i> Forçar coleta agora
            </button>
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
        <button type="button" class="btn btn-outline-secondary" id="botaoImprimirZebra" data-id="<?= (int)$ativo['id'] ?>">
            <i class="bi bi-printer"></i> Imprimir etiqueta (Zebra)
        </button>
        <a href="<?= url('/ativos/editar?id=' . $ativo['id']) ?>" class="btn btn-primary"><i class="bi bi-pencil"></i> Editar</a>
        <a href="<?= url('/ativos/excluir?id=' . $ativo['id']) ?>" class="btn btn-outline-danger"><i class="bi bi-trash"></i></a>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <?php if ($ativo['origem'] === 'agente'): ?>
        <div class="alert alert-light border small mb-0 py-2">
            <i class="bi bi-broadcast"></i>
            <?php if ($segundosDesdeHeartbeat !== null): ?>
                Status Ligado/Desligado ao vivo -- último ping há <strong><?= $segundosDesdeHeartbeat ?>s</strong>.
            <?php else: ?>
                Ainda sem heartbeat (agente antigo ou recém-instalado) -- status Ligado/Desligado está usando o
                último check-in completo como aproximação.
            <?php endif; ?>
            <?php if (!empty($ativo['ultimo_checkin'])): ?>
                <br><i class="bi bi-hdd-stack"></i> Dados completos (hardware/programas/alertas): última coleta há
                <strong><?= $minutosDesdeCheckin ?> min</strong> (<?= htmlspecialchars(data_br($ativo['ultimo_checkin'])) ?>),
                próxima esperada em até <strong><?= $intervaloComunicacao ?> min</strong>
                -- ou use "Forçar coleta agora".
            <?php endif; ?>
            <?php if ($estaLigada && $uptime): ?>
                <br>Ligado há <?= htmlspecialchars($uptime) ?>
                <?php if (!empty($detalhes['ligado_desde'])): ?>
                    (desde <?= htmlspecialchars(data_br($detalhes['ligado_desde'])) ?>)
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php elseif (!empty($ativo['ultimo_checkin'])): ?>
        <div class="alert alert-light border small mb-0 py-2">
            <i class="bi bi-clock-history"></i>
            Última atualização: <?= htmlspecialchars(data_br($ativo['ultimo_checkin'])) ?>
            via <?= $ativo['origem'] === 'snmp' ? 'SNMP' : 'manual' ?>
        </div>
    <?php endif; ?>

    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalAlertas">
        <i class="bi bi-exclamation-triangle"></i> Ver Alertas (<?= count($alertas) ?>)
    </button>
</div>

<ul class="nav nav-tabs mb-3" id="abasAtivo" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#abaGeral" type="button">Visão Geral</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#abaComponentes" type="button">Componentes</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#abaMemoria" type="button">Memória <?= !empty($memoria) ? '<span class="badge text-bg-secondary ms-1">' . count($memoria) . '</span>' : '' ?></button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#abaVolumes" type="button">Volumes lógicos <?= !empty($volumes) ? '<span class="badge text-bg-secondary ms-1">' . count($volumes) . '</span>' : '' ?></button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#abaNetwork" type="button">Network <?= !empty($redes) ? '<span class="badge text-bg-secondary ms-1">' . count($redes) . '</span>' : '' ?></button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#abaPortas" type="button">Portas <?= !empty($portas) ? '<span class="badge text-bg-secondary ms-1">' . count($portas) . '</span>' : '' ?></button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#abaProgramas" type="button">Programas <?= !empty($programas) ? '<span class="badge text-bg-secondary ms-1">' . count($programas) . '</span>' : '' ?></button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#abaAtualizacoes" type="button">Atualizações do Windows <?= !empty($atualizacoesWindows) ? '<span class="badge text-bg-secondary ms-1">' . count($atualizacoesWindows) . '</span>' : '' ?></button>
    </li>
    <?php if ($ativo['origem'] === 'agente'): ?>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#abaProcessos" type="button"><i class="bi bi-cpu"></i> Processos</button>
    </li>
    <?php endif; ?>
</ul>

<div class="tab-content">
    <!-- Visão Geral -->
    <div class="tab-pane fade show active" id="abaGeral">
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white"><strong>Dados gerais</strong></div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span class="text-muted">Marca / Fabricante</span><span><?= htmlspecialchars($ativo['marca'] ?? '—') ?></span>
                        </div>
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span class="text-muted">Modelo</span><span><?= htmlspecialchars($ativo['modelo'] ?? '—') ?></span>
                        </div>
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span class="text-muted">Nº de série</span><span><?= htmlspecialchars($ativo['numero_serie'] ?? '—') ?></span>
                        </div>
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span class="text-muted">IP principal</span><span><?= htmlspecialchars($ativo['ip'] ?? '—') ?></span>
                        </div>
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span class="text-muted">Setor</span><span><?= htmlspecialchars($ativo['setor_nome'] ?? '—') ?></span>
                        </div>
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span class="text-muted">Localização</span><span><?= htmlspecialchars($ativo['localizacao_nome'] ?? '—') ?></span>
                        </div>
                        <div class="d-flex justify-content-between py-2">
                            <span class="text-muted">Responsável</span><span><?= htmlspecialchars($ativo['responsavel'] ?? '—') ?></span>
                        </div>
                        <?php if (!empty($camposVisaoGeral)): ?>
                            <hr>
                            <?php foreach ($camposVisaoGeral as $campo => $label): ?>
                                <?php if (!empty($detalhes[$campo]) && $campo !== 'ligado_desde'): ?>
                                    <div class="d-flex justify-content-between py-2 border-bottom">
                                        <span class="text-muted"><?= htmlspecialchars($label) ?></span>
                                        <span><?= htmlspecialchars($detalhes[$campo]) ?></span>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <?php if (!empty($ativo['observacoes'])): ?>
                            <hr>
                            <div class="text-muted small mb-1">Observações</div>
                            <p class="mb-0"><?= nl2br(htmlspecialchars($ativo['observacoes'])) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white"><strong>Uso de Memória</strong></div>
                    <div class="card-body d-flex align-items-center justify-content-center">
                        <?php if ($memoriaPct !== null): ?>
                            <?= gaugeRadial($memoriaPct, 'RAM', round($memoriaUsadaGb, 1) . ' / ' . round($memoriaTotalGb, 1) . ' GB') ?>
                        <?php else: ?>
                            <p class="text-muted small mb-0 text-center py-4">Sem dado de memória em uso ainda.<br>Preenchido automaticamente pelo agente Windows.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white"><strong>Uso do Volume Lógico</strong></div>
                    <div class="card-body d-flex flex-column align-items-center justify-content-center">
                        <?php if ($discoPct !== null): ?>
                            <?= gaugeRadial($discoPct, 'Unidade ' . $volumePrincipal['unidade'], round((float)$volumePrincipal['usado_gb'], 1) . ' / ' . round((float)$volumePrincipal['total_gb'], 1) . ' GB') ?>
                            <?php if (count($volumes) > 1): ?>
                                <p class="text-muted text-center mb-0" style="font-size:11px">+<?= count($volumes) - 1 ?> outra(s) na aba Volumes lógicos</p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="text-muted small mb-0 text-center py-4">Sem dado de volume ainda.<br>Preenchido automaticamente pelo agente Windows.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Componentes -->
    <div class="tab-pane fade" id="abaComponentes">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <?php
                $temComponente = false;
                foreach ($camposComponentes as $campo) {
                    if (!empty($detalhes[$campo])) $temComponente = true;
                }
                ?>
                <?php if (!$temComponente): ?>
                    <p class="text-muted mb-0">Nenhum componente detalhado ainda.</p>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($camposComponentes as $campo): ?>
                            <?php if (!empty($detalhes[$campo]) && isset($camposTipo[$campo])): ?>
                                <div class="col-md-6 d-flex justify-content-between py-2 border-bottom">
                                    <span class="text-muted"><?= htmlspecialchars($camposTipo[$campo]) ?></span>
                                    <span class="text-end"><?= htmlspecialchars($detalhes[$campo]) ?></span>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Memória -->
    <div class="tab-pane fade" id="abaMemoria">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <?php if (empty($memoria)): ?>
                    <p class="text-muted p-3 mb-0">Nenhum módulo de memória coletado ainda. Preenchido automaticamente pelo agente Windows.</p>
                <?php else: ?>
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr><th>Fabricante</th><th>Modelo</th><th>Capacidade</th><th>Frequência</th><th>Nº de série</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($memoria as $m): ?>
                                <tr>
                                    <td><?= htmlspecialchars($m['fabricante'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($m['modelo'] ?? '—') ?></td>
                                    <td><?= !empty($m['capacidade_gb']) ? htmlspecialchars($m['capacidade_gb']) . ' GB' : '—' ?></td>
                                    <td><?= !empty($m['frequencia_mhz']) ? htmlspecialchars($m['frequencia_mhz']) . ' MHz' : '—' ?></td>
                                    <td class="font-monospace small"><?= htmlspecialchars($m['numero_serie'] ?? '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="text-muted small p-2 mb-0">Fabricante e número de série nem sempre são informados pelo Windows -- depende do fabricante do módulo.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Volumes lógicos -->
    <div class="tab-pane fade" id="abaVolumes">
        <?php if (empty($volumes)): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body text-muted">Nenhum volume coletado ainda. Preenchido automaticamente pelo agente Windows.</div>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($volumes as $v): ?>
                    <?php
                    $total = (float)$v['total_gb'];
                    $usado = (float)$v['usado_gb'];
                    $pct = $total > 0 ? ($usado / $total) * 100 : 0;
                    ?>
                    <div class="col-md-3 col-sm-4 col-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center">
                                <?= gaugeRadial($pct, 'Unidade ' . $v['unidade'], round($usado, 1) . ' / ' . round($total, 1) . ' GB') ?>
                                <?php if (!empty($v['modelo_disco']) || !empty($v['fabricante_disco']) || !empty($v['serial_disco'])): ?>
                                    <hr class="my-2">
                                    <div class="text-start" style="font-size:11px">
                                        <?php if (!empty($v['modelo_disco'])): ?><div class="text-muted">Modelo: <?= htmlspecialchars($v['modelo_disco']) ?></div><?php endif; ?>
                                        <?php if (!empty($v['fabricante_disco'])): ?><div class="text-muted">Fabricante: <?= htmlspecialchars($v['fabricante_disco']) ?></div><?php endif; ?>
                                        <?php if (!empty($v['serial_disco'])): ?><div class="text-muted">Série: <?= htmlspecialchars($v['serial_disco']) ?></div><?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($agenteSuportaExplorador): ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary mt-2 botao-explorar-volume"
                                            data-unidade="<?= htmlspecialchars($v['unidade']) ?>">
                                        <i class="bi bi-folder2-open"></i> Explorar arquivos
                                    </button>
                                <?php elseif ($ativo['origem'] === 'agente'): ?>
                                    <div class="text-muted mt-2" style="font-size:10px" title="Precisa do agente de bandeja (.exe), não do script .ps1">
                                        <i class="bi bi-folder2"></i> Explorar arquivos indisponível (.ps1)
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="text-muted small mt-2 mb-0">Fabricante e número de série do disco físico nem sempre são informados pelo Windows -- depende do driver/controlador de cada fabricante.</p>
        <?php endif; ?>
    </div>

    <!-- Network -->
    <div class="tab-pane fade" id="abaNetwork">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <?php if (empty($redes)): ?>
                    <p class="text-muted p-3 mb-0">Nenhum adaptador de rede coletado ainda.</p>
                <?php else: ?>
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr><th>Adaptador</th><th>MAC</th><th>IP</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($redes as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['nome_adaptador'] ?? '—') ?></td>
                                    <td class="font-monospace small"><?= htmlspecialchars($r['mac'] ?? '—') ?></td>
                                    <td class="font-monospace small"><?= htmlspecialchars($r['ip'] ?? '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-white"><strong>Portas de rede abertas</strong></div>
            <div class="card-body p-0">
                <?php if (empty($portasRede)): ?>
                    <p class="text-muted p-3 mb-0">Nenhuma porta de rede coletada ainda. Preenchido automaticamente pelo agente Windows (portas em escuta no momento da coleta).</p>
                <?php else: ?>
                    <p class="text-muted small p-2 mb-0">Portas em escuta (LISTENING) no momento da última coleta -- útil pra checar exposição de serviços na máquina. Não é ao vivo, reflete o último check-in do agente.</p>
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr><th>Protocolo</th><th>Porta</th><th>Endereço</th><th>Processo</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($portasRede as $p): ?>
                                <tr>
                                    <td class="text-uppercase small"><?= htmlspecialchars($p['protocolo']) ?></td>
                                    <td class="font-monospace"><?= (int)$p['porta_local'] ?></td>
                                    <td class="font-monospace small"><?= htmlspecialchars($p['endereco_local'] ?? '—') ?></td>
                                    <td class="small"><?= htmlspecialchars($p['processo'] ?? '—') ?><?= !empty($p['pid']) ? ' <span class="text-muted">(PID ' . (int)$p['pid'] . ')</span>' : '' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Portas -->
    <div class="tab-pane fade" id="abaPortas">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <?php if (empty($portas)): ?>
                    <p class="text-muted mb-0">Nenhuma porta coletada ainda.</p>
                <?php else: ?>
                    <p class="text-muted small">Dispositivos USB conectados no momento da coleta e portas seriais (COM) disponíveis. O Windows não expõe de forma padronizada portas de vídeo (HDMI/DisplayPort/VGA), por isso não aparecem aqui.</p>
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="small text-uppercase text-muted">USB</h6>
                            <ul class="list-group list-group-flush mb-3">
                                <?php $temUsb = false; foreach ($portas as $p): if ($p['tipo'] === 'usb'): $temUsb = true; ?>
                                    <li class="list-group-item px-0 small"><?= htmlspecialchars($p['descricao']) ?></li>
                                <?php endif; endforeach; ?>
                                <?php if (!$temUsb): ?><li class="list-group-item px-0 small text-muted">Nenhum dispositivo USB coletado.</li><?php endif; ?>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="small text-uppercase text-muted">Seriais (COM)</h6>
                            <ul class="list-group list-group-flush">
                                <?php $temSerial = false; foreach ($portas as $p): if ($p['tipo'] === 'serial'): $temSerial = true; ?>
                                    <li class="list-group-item px-0 small"><?= htmlspecialchars($p['descricao']) ?></li>
                                <?php endif; endforeach; ?>
                                <?php if (!$temSerial): ?><li class="list-group-item px-0 small text-muted">Nenhuma porta serial encontrada.</li><?php endif; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Programas -->
    <div class="tab-pane fade" id="abaProgramas">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <?php if (empty($programas)): ?>
                    <p class="text-muted p-3 mb-0">Nenhum programa coletado ainda. Isso é preenchido automaticamente pelo agente Windows quando instalado neste ativo.</p>
                <?php else: ?>
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr><th>Programa</th><th>Versão</th><th>Instalado em</th><?php if ($ativo['origem'] === 'agente'): ?><th class="text-end">Ações</th><?php endif; ?></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($programas as $p): ?>
                                <tr>
                                    <td><?= htmlspecialchars($p['nome']) ?></td>
                                    <td class="text-muted small"><?= htmlspecialchars($p['versao'] ?? '—') ?></td>
                                    <td class="text-muted small"><?= !empty($p['data_instalacao']) ? htmlspecialchars(data_br($p['data_instalacao'], 'd/m/Y')) : '—' ?></td>
                                    <?php if ($ativo['origem'] === 'agente'): ?>
                                        <td class="text-end">
                                            <?php if (!empty($p['uninstall_string'])): ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger botao-desinstalar-programa"
                                                        data-ativo-id="<?= (int)$ativo['id'] ?>" data-programa-id="<?= (int)$p['id'] ?>" data-nome="<?= htmlspecialchars($p['nome']) ?>">
                                                    <i class="bi bi-trash"></i> Desinstalar
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted small" data-bs-toggle="tooltip" title="Sem comando de desinstalação registrado pelo instalador">—</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Atualizações do Windows -->
    <div class="tab-pane fade" id="abaAtualizacoes">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <?php if (empty($atualizacoesWindows)): ?>
                    <p class="text-muted p-3 mb-0">Nenhuma atualização coletada ainda. Preenchido automaticamente pelo agente Windows.</p>
                <?php else: ?>
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr><th>KB</th><th>Descrição</th><th>Instalado em</th><?php if ($ativo['origem'] === 'agente'): ?><th class="text-end">Ações</th><?php endif; ?></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($atualizacoesWindows as $a): ?>
                                <tr>
                                    <td class="font-monospace small"><?= htmlspecialchars($a['kb']) ?></td>
                                    <td class="small"><?= htmlspecialchars($a['descricao'] ?? '—') ?></td>
                                    <td class="text-muted small"><?= !empty($a['instalado_em']) ? htmlspecialchars(data_br($a['instalado_em'], 'd/m/Y')) : '—' ?></td>
                                    <?php if ($ativo['origem'] === 'agente'): ?>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-danger botao-desinstalar-atualizacao"
                                                    data-ativo-id="<?= (int)$ativo['id'] ?>" data-atualizacao-id="<?= (int)$a['id'] ?>" data-kb="<?= htmlspecialchars($a['kb']) ?>">
                                                <i class="bi bi-trash"></i> Desinstalar
                                            </button>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="text-muted small p-2 mb-0">Algumas atualizações não podem mais ser removidas depois de "substituídas" por atualizações cumulativas mais novas -- isso é uma limitação do próprio Windows, não do agente.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($ativo['origem'] === 'agente'): ?>
    <!-- Processos -->
    <div class="tab-pane fade" id="abaProcessos">
        <?php if (!$agenteSuportaExplorador): ?>
            <div class="alert alert-warning small mb-0">
                <i class="bi bi-exclamation-triangle"></i> Este ativo está usando o script <code>.ps1</code>, que roda
                como Tarefa Agendada (sem processo residente) e não manda o heartbeat necessário pra essa função.
                Instale o agente de bandeja (.exe) nesta máquina pra ter o gerenciador de processos ao vivo.
            </div>
        <?php else: ?>
        <div class="d-flex justify-content-between align-items-center mb-2">
            <p class="text-muted small mb-0">
                <i class="bi bi-info-circle"></i> Ao vivo -- pede a lista de processos rodando agora nessa máquina, na hora.
                Encerrar um processo é imediato e sem aviso ao usuário (diferente de Desligar/Reiniciar).
            </p>
            <button type="button" class="btn btn-sm hitech-btn" id="botaoAtualizarProcessos">
                <i class="bi bi-arrow-repeat"></i> Atualizar
            </button>
        </div>
        <div class="hitech-panel">
            <div class="hitech-topbar">
                <span class="hitech-breadcrumb"><i class="bi bi-terminal"></i> Gerenciador de processos</span>
                <span class="text-muted small" id="totalProcessos"></span>
            </div>
            <div class="hitech-body" id="corpoProcessos">
                <div class="hitech-loading"><div class="spinner-border" role="status"></div><div class="mt-2">Consultando processos...</div></div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($ativo['origem'] === 'agente'): ?>
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-white"><strong>Comandos remotos</strong></div>
            <div class="card-body p-0">
                <?php if (empty($comandos)): ?>
                    <p class="text-muted p-3 mb-0">Nenhum comando enviado ainda.</p>
                <?php else: ?>
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr><th>Comando</th><th>Status</th><th>Solicitado por</th><th class="text-end">Quando</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($comandos as $c): ?>
                                <tr>
                                    <td class="text-capitalize"><?= htmlspecialchars(str_replace('_', ' ', $c['comando'])) ?><?= !empty($c['alvo_label']) ? ': ' . htmlspecialchars($c['alvo_label']) : '' ?></td>
                                    <td><?= Badge::make($c['status'] === 'entregue' ? 'Entregue' : 'Pendente', $c['status'] === 'entregue' ? 'success' : 'secondary') ?></td>
                                    <td class="text-muted small"><?= htmlspecialchars($c['solicitado_por'] ?? '—') ?></td>
                                    <td class="text-muted small text-end"><?= htmlspecialchars(data_br($c['criado_em'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-white"><strong><i class="bi bi-terminal"></i> Executar comando (CMD / PowerShell)</strong></div>
            <div class="card-body">
                <p class="text-muted small mb-2">
                    Roda na hora (chega em poucos segundos pelo heartbeat) e devolve a saída aqui. Sem confirmação em
                    duas etapas -- confira o comando antes de executar, principalmente com elevação marcada.
                </p>
                <div class="row g-2 align-items-end mb-2">
                    <div class="col-auto">
                        <label class="form-label small mb-0">Tipo</label>
                        <select class="form-select form-select-sm" id="campoTipoComando">
                            <option value="executar_cmd">CMD</option>
                            <option value="executar_powershell">PowerShell</option>
                        </select>
                    </div>
                    <div class="col-auto">
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="checkbox" id="campoElevado">
                            <label class="form-check-label small" for="campoElevado">
                                Executar com elevação (como administrador)
                            </label>
                        </div>
                    </div>
                </div>
                <div class="hitech-panel mb-2">
                    <textarea class="form-control form-control-sm" id="campoComando" rows="2"
                              style="background:#0d1117; color:#c9d1d9; border:0; font-family:'SFMono-Regular',Consolas,monospace; resize:vertical"
                              placeholder="ex: ipconfig /all"></textarea>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="botaoExecutarComando">
                    <i class="bi bi-play-fill"></i> Executar
                </button>

                <div class="hitech-panel mt-3 d-none" id="painelSaidaComando">
                    <div class="hitech-topbar">
                        <span class="hitech-breadcrumb"><i class="bi bi-terminal"></i> Saída</span>
                        <span class="text-muted small" id="statusSaidaComando"></span>
                    </div>
                    <div class="hitech-body" style="max-height:280px">
                        <pre class="m-0 p-3" id="conteudoSaidaComando" style="white-space:pre-wrap; word-break:break-word; font-size:12px; color:#c9d1d9;"></pre>
                    </div>
                </div>

                <?php if (!empty($historicoComandosExecucao)): ?>
                    <hr>
                    <p class="text-muted small mb-2">Últimos <?= count($historicoComandosExecucao) ?> comandos (clique numa linha pra ver a saída):</p>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0" id="tabelaHistoricoComandos">
                            <thead>
                                <tr><th>Tipo</th><th>Comando</th><th>Elevado</th><th>Solicitado por</th><th>Status</th><th class="text-end">Quando</th></tr>
                            </thead>
                            <tbody id="corpoHistoricoComandos">
                                <?php foreach ($historicoComandosExecucao as $h): ?>
                                    <?php
                                        $resultadoH = json_decode($h['resultado'] ?? '', true) ?: [];
                                        $saidaH = trim((string)($resultadoH['saida'] ?? '')) . (!empty($resultadoH['erro']) ? "\n--- erro ---\n" . $resultadoH['erro'] : '');
                                        if ($h['status'] === 'erro') {
                                            $saidaH = $h['erro_mensagem'] ?? '';
                                        }
                                    ?>
                                    <tr class="linha-historico-comando" style="cursor:pointer" data-alvo="detalheHistorico<?= (int)$h['id'] ?>">
                                        <td class="small"><?= $h['tipo'] === 'executar_cmd' ? 'CMD' : 'PowerShell' ?></td>
                                        <td class="small font-monospace" style="max-width:280px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap"><?= htmlspecialchars($h['parametro']) ?></td>
                                        <td><?= $h['elevado'] ? Badge::make('Sim', 'warning') : '—' ?></td>
                                        <td class="text-muted small"><?= htmlspecialchars($h['solicitado_por'] ?? '—') ?></td>
                                        <td><?= Badge::make(
                                            $h['status'] === 'concluido' ? 'Concluído' : ($h['status'] === 'erro' ? 'Erro' : 'Pendente'),
                                            $h['status'] === 'concluido' ? 'success' : ($h['status'] === 'erro' ? 'danger' : 'secondary')
                                        ) ?></td>
                                        <td class="text-muted small text-end"><?= htmlspecialchars(data_br($h['solicitado_em'])) ?></td>
                                    </tr>
                                    <tr class="d-none" id="detalheHistorico<?= (int)$h['id'] ?>">
                                        <td colspan="6" class="p-0">
                                            <pre class="m-0 p-2 small" style="background:#0d1117; color:#c9d1d9; white-space:pre-wrap; word-break:break-word; max-height:220px; overflow:auto;"><?= htmlspecialchars($saidaH !== '' ? $saidaH : '(sem saída)') ?></pre>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal de Alertas -->
<div class="modal fade" id="modalAlertas" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle"></i> Alertas (<?= count($alertas) ?>)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if (empty($alertas)): ?>
                    <p class="text-muted mb-0">Nenhum alerta coletado ainda. Isso é preenchido automaticamente pelo agente Windows quando instalado neste ativo.</p>
                <?php else: ?>
                    <input type="text" id="buscaAlertas" class="form-control mb-3" placeholder="Buscar por palavra, origem ou nível...">
                    <table class="table table-sm table-hover mb-0" id="tabelaAlertas">
                        <tbody>
                            <?php foreach ($alertas as $al): ?>
                                <tr class="linha-alerta">
                                    <td style="width:90px"><?= Badge::make(htmlspecialchars($al['nivel']), $al['nivel'] === 'erro' ? 'danger' : ($al['nivel'] === 'aviso' ? 'warning' : 'secondary')) ?></td>
                                    <td class="small">
                                        <?= htmlspecialchars($al['mensagem']) ?>
                                        <div class="text-muted" style="font-size:11px">
                                            <?= htmlspecialchars($al['origem_evento'] ?? '') ?>
                                            <?= !empty($al['ocorrido_em']) ? ' · ' . htmlspecialchars(data_br($al['ocorrido_em'])) : '' ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="text-muted small mt-2 mb-0" id="contadorBuscaAlertas"></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Tela Remota -->
<div class="modal fade" id="modalTelaRemota" tabindex="-1">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title"><i class="bi bi-display"></i> Tela remota -- <?= htmlspecialchars($ativo['nome']) ?></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" id="botaoFecharTelaRemota"></button>
            </div>
            <div class="modal-body p-0 d-flex align-items-center justify-content-center bg-dark" id="corpoTelaRemota">
                <div class="text-white-50" id="statusTelaRemota">
                    <i class="bi bi-hourglass-split"></i> Abrindo sessão remota via MeshCentral...
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal do Explorador de Arquivos -->
<div class="modal fade" id="modalExplorador" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content bg-transparent border-0">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title text-white"><i class="bi bi-folder2-open"></i> Explorador de arquivos -- <?= htmlspecialchars($ativo['nome']) ?></h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <div class="hitech-panel">
                    <div class="hitech-topbar">
                        <div class="hitech-breadcrumb" id="breadcrumbExplorador"></div>
                    </div>
                    <div class="hitech-body" id="corpoExplorador">
                        <div class="hitech-loading"><div class="spinner-border" role="status"></div><div class="mt-2">Consultando arquivos...</div></div>
                    </div>
                    <div class="hitech-topbar" style="border-top:1px solid #30363d; border-bottom:0">
                        <div class="d-flex align-items-center gap-2 w-100">
                            <i class="bi bi-upload text-muted"></i>
                            <input type="file" id="inputEnviarArquivo" class="form-control form-control-sm" style="max-width:320px">
                            <button type="button" class="hitech-btn" id="botaoEnviarArquivo"><i class="bi bi-upload"></i> Enviar</button>
                            <span class="text-muted small ms-auto">envia pra pasta atual</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
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
    const botao = document.getElementById('botaoImprimirZebra');
    if (!botao) return;

    const PORTA_AGENTE_LOCAL = 8734;
    const textoOriginal = botao.innerHTML;

    botao.addEventListener('click', async function () {
        botao.disabled = true;
        botao.innerHTML = '<i class="bi bi-hourglass-split"></i> Gerando etiqueta...';

        try {
            const resZpl = await fetch(<?= json_encode(url('/ativos/etiqueta/zpl')) ?> + '?id=' + botao.dataset.id);
            const dadosZpl = await resZpl.json();

            if (!dadosZpl.success) {
                alert(dadosZpl.message || 'Falha ao gerar a etiqueta.');
                return;
            }

            botao.innerHTML = '<i class="bi bi-hourglass-split"></i> Enviando pra impressora...';

            // Chama o agente Windows rodando NESTA maquina (o navegador),
            // nao o servidor -- e por isso que precisa da impressora Zebra
            // ligada no PC de quem esta clicando, com o agente configurado.
            const resImpressao = await fetch('http://127.0.0.1:' + PORTA_AGENTE_LOCAL + '/imprimir', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ zpl: dadosZpl.zpl })
            });
            const resultado = await resImpressao.json();
            alert(resultado.message || (resultado.success ? 'Enviado pra impressora.' : 'Falha ao imprimir.'));
        } catch (e) {
            alert('Não foi possível falar com o agente RD Intranet nesta máquina. Confirme que ele está rodando (ícone na bandeja) e com uma impressora Zebra configurada em Configurações...');
        } finally {
            botao.disabled = false;
            botao.innerHTML = textoOriginal;
        }
    });
})();

(function () {
    const botao = document.getElementById('botaoTelaRemota');
    if (!botao) return;

    const modalEl = document.getElementById('modalTelaRemota');
    const corpo = document.getElementById('corpoTelaRemota');
    const statusInicial = corpo.innerHTML;

    botao.addEventListener('click', async function () {
        corpo.innerHTML = statusInicial;
        bootstrap.Modal.getOrCreateInstance(modalEl).show();

        const dados = new URLSearchParams();
        dados.set('ativo_id', botao.dataset.id);

        try {
            const res = await fetch(<?= json_encode(url('/ativos/acesso-remoto/compartilhar')) ?>, { method: 'POST', body: dados });
            const resultado = await res.json();

            if (!resultado.success) {
                corpo.innerHTML = '<div class="text-white-50 text-center p-4">' + (resultado.message || 'Falha ao abrir a tela remota.') + '</div>';
                return;
            }

            const iframe = document.createElement('iframe');
            iframe.src = resultado.url;
            iframe.style.width = '100%';
            iframe.style.height = '100%';
            iframe.style.border = '0';
            corpo.innerHTML = '';
            corpo.appendChild(iframe);
        } catch (e) {
            corpo.innerHTML = '<div class="text-white-50 text-center p-4">Erro ao comunicar com o servidor.</div>';
        }
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
        corpo.innerHTML = statusInicial;
    });
})();

/**
 * Solicita uma leitura ao vivo (listar_arquivos/listar_processos) e
 * espera o agente responder via heartbeat -- ver AtivoService::
 * solicitarListagem()/resultadoSolicitacao(). Compartilhado pelo
 * explorador de arquivos e pelo gerenciador de processos abaixo.
 */
async function pedirEAguardarSolicitacao(ativoId, tipo, parametro) {
    const dadosSolicitar = new URLSearchParams();
    dadosSolicitar.set('id', ativoId);
    dadosSolicitar.set('tipo', tipo);
    if (parametro !== null) dadosSolicitar.set('parametro', parametro);

    const resSolicitar = await fetch(<?= json_encode(url('/ativos/solicitacoes/listar')) ?>, { method: 'POST', body: dadosSolicitar });
    const dadosResultado = await resSolicitar.json();

    if (!dadosResultado.success) {
        throw new Error(dadosResultado.message || 'Falha ao solicitar.');
    }

    const id = dadosResultado.id;
    const inicio = Date.now();

    while (Date.now() - inicio < 20000) {
        await new Promise(function (resolve) { setTimeout(resolve, 700); });

        const resPoll = await fetch(<?= json_encode(url('/ativos/solicitacoes/resultado')) ?> + '?id=' + id + '&ativo_id=' + ativoId);
        const poll = await resPoll.json();

        if (!poll.success) throw new Error(poll.message || 'Falha ao consultar resultado.');
        if (poll.status === 'concluido') return { id: id, resultado: poll.resultado };
        if (poll.status === 'erro') throw new Error(poll.mensagem || 'O agente reportou um erro.');
        // status "pendente" -- continua esperando
    }

    throw new Error('Sem resposta do agente em 20s (a máquina está ligada e conectada?).');
}

(function () {
    const botoes = document.querySelectorAll('.botao-explorar-volume');
    if (!botoes.length) return;

    const modalEl = document.getElementById('modalExplorador');
    const breadcrumbEl = document.getElementById('breadcrumbExplorador');
    const corpoEl = document.getElementById('corpoExplorador');
    const ativoId = <?= (int)$ativo['id'] ?>;

    let caminhoAtual = '';

    function formatarTamanho(bytes) {
        if (bytes === null || bytes === undefined) return '';
        const unidades = ['B', 'KB', 'MB', 'GB'];
        let valor = bytes, i = 0;
        while (valor >= 1024 && i < unidades.length - 1) { valor /= 1024; i++; }
        return valor.toFixed(i === 0 ? 0 : 1) + ' ' + unidades[i];
    }

    function renderBreadcrumb(caminho) {
        const partes = caminho.replace(/\\+$/, '').split('\\').filter(Boolean);
        breadcrumbEl.innerHTML = '';

        if (!partes.length) {
            breadcrumbEl.textContent = caminho;
            return;
        }

        let acumulado = '';
        partes.forEach(function (parte, i) {
            acumulado += parte + '\\';
            const caminhoDoSegmento = acumulado;

            const span = document.createElement('span');
            span.className = 'segmento';
            span.textContent = parte;
            span.addEventListener('click', function () { carregarPasta(caminhoDoSegmento); });
            breadcrumbEl.appendChild(span);

            if (i < partes.length - 1) {
                const sep = document.createElement('span');
                sep.className = 'separador';
                sep.textContent = '\\';
                breadcrumbEl.appendChild(sep);
            }
        });
    }

    function renderTabela(itens) {
        if (!itens.length) {
            corpoEl.innerHTML = '<div class="hitech-empty">Pasta vazia.</div>';
            return;
        }

        const tabela = document.createElement('table');
        tabela.className = 'hitech-table';
        const thead = document.createElement('thead');
        thead.innerHTML = '<tr><th>Nome</th><th>Tamanho</th><th>Modificado</th><th></th></tr>';
        const tbody = document.createElement('tbody');

        itens.forEach(function (item) {
            const tr = document.createElement('tr');
            const tdNome = document.createElement('td');
            const icone = document.createElement('i');
            const tdTamanho = document.createElement('td');
            const tdModificado = document.createElement('td');
            const tdAcao = document.createElement('td');

            tdTamanho.className = 'text-muted';
            tdModificado.className = 'text-muted';
            tdAcao.className = 'text-end d-flex gap-1 justify-content-end';
            tdModificado.textContent = item.modificado_em || '';

            const botaoRenomear = document.createElement('button');
            botaoRenomear.type = 'button';
            botaoRenomear.className = 'hitech-btn';
            botaoRenomear.title = 'Renomear';
            botaoRenomear.innerHTML = '<i class="bi bi-pencil"></i>';
            botaoRenomear.addEventListener('click', function (e) {
                e.stopPropagation();
                const separador = caminhoAtual.endsWith('\\') ? '' : '\\';
                renomearItem(caminhoAtual + separador + item.nome, item.nome);
            });

            if (item.tipo === 'pasta') {
                tr.className = 'linha-pasta';
                icone.className = 'bi bi-folder-fill icone-pasta';
                tdNome.appendChild(icone);
                tdNome.appendChild(document.createTextNode(' ' + item.nome));
                tdTamanho.textContent = '--';
                tdAcao.appendChild(botaoRenomear);

                tr.addEventListener('click', function () {
                    const separador = caminhoAtual.endsWith('\\') ? '' : '\\';
                    carregarPasta(caminhoAtual + separador + item.nome);
                });
            } else {
                icone.className = 'bi bi-file-earmark icone-arquivo';
                tdNome.appendChild(icone);
                tdNome.appendChild(document.createTextNode(' ' + item.nome));
                tdTamanho.textContent = formatarTamanho(item.tamanho);

                const botaoBaixar = document.createElement('button');
                botaoBaixar.type = 'button';
                botaoBaixar.className = 'hitech-btn';
                botaoBaixar.title = 'Baixar';
                botaoBaixar.innerHTML = '<i class="bi bi-download"></i>';
                botaoBaixar.addEventListener('click', function (e) {
                    e.stopPropagation();
                    const separador = caminhoAtual.endsWith('\\') ? '' : '\\';
                    baixarArquivo(caminhoAtual + separador + item.nome, botaoBaixar);
                });

                const botaoExecutar = document.createElement('button');
                botaoExecutar.type = 'button';
                botaoExecutar.className = 'hitech-btn';
                botaoExecutar.title = 'Executar';
                botaoExecutar.innerHTML = '<i class="bi bi-play-fill"></i>';
                botaoExecutar.addEventListener('click', function (e) {
                    e.stopPropagation();
                    const separador = caminhoAtual.endsWith('\\') ? '' : '\\';
                    executarArquivo(caminhoAtual + separador + item.nome, item.nome);
                });

                tdAcao.appendChild(botaoRenomear);
                tdAcao.appendChild(botaoBaixar);
                tdAcao.appendChild(botaoExecutar);
            }

            tr.appendChild(tdNome);
            tr.appendChild(tdTamanho);
            tr.appendChild(tdModificado);
            tr.appendChild(tdAcao);
            tbody.appendChild(tr);
        });

        tabela.appendChild(thead);
        tabela.appendChild(tbody);
        corpoEl.innerHTML = '';
        corpoEl.appendChild(tabela);
    }

    async function carregarPasta(caminho) {
        caminhoAtual = caminho;
        renderBreadcrumb(caminho);
        corpoEl.innerHTML = '<div class="hitech-loading"><div class="spinner-border" role="status"></div><div class="mt-2">Consultando...</div></div>';

        try {
            const resposta = await pedirEAguardarSolicitacao(ativoId, 'listar_arquivos', caminho);
            renderTabela(resposta.resultado);
        } catch (e) {
            corpoEl.innerHTML = '';
            const div = document.createElement('div');
            div.className = 'hitech-erro';
            div.innerHTML = '<i class="bi bi-exclamation-triangle"></i> ';
            div.append(e.message);
            corpoEl.appendChild(div);
        }
    }

    async function executarArquivo(caminhoCompleto, nome) {
        if (!confirm('Executar "' + nome + '" remotamente nesta máquina agora?\n\nCaminho completo:\n' + caminhoCompleto + '\n\nO arquivo roda imediatamente, sem confirmação na tela do usuário.')) {
            return;
        }

        const dados = new URLSearchParams();
        dados.set('id', ativoId);
        dados.set('comando', 'executar_arquivo');
        dados.set('alvo', caminhoCompleto);
        dados.set('alvo_label', nome);

        try {
            const res = await fetch(<?= json_encode(url('/ativos/comando')) ?>, { method: 'POST', body: dados });
            const resultado = await res.json();
            alert(resultado.message || (resultado.success ? 'Enviado.' : 'Falha ao enviar.'));
        } catch (e) {
            alert('Erro ao comunicar com o servidor.');
        }
    }

    async function renomearItem(caminhoAtualCompleto, nomeAtual) {
        const nomeNovo = prompt('Novo nome para "' + nomeAtual + '":', nomeAtual);
        if (!nomeNovo || nomeNovo === nomeAtual) return;

        const dados = new URLSearchParams();
        dados.set('id', ativoId);
        dados.set('comando', 'renomear_arquivo');
        dados.set('alvo', caminhoAtualCompleto);
        dados.set('alvo_label', nomeNovo);

        try {
            const res = await fetch(<?= json_encode(url('/ativos/comando')) ?>, { method: 'POST', body: dados });
            const resultado = await res.json();
            alert(resultado.message || (resultado.success ? 'Enviado.' : 'Falha ao enviar.'));
            if (resultado.success) {
                setTimeout(function () { carregarPasta(caminhoAtual); }, 3000);
            }
        } catch (e) {
            alert('Erro ao comunicar com o servidor.');
        }
    }

    async function baixarArquivo(caminhoCompleto, botao) {
        const textoOriginal = botao.innerHTML;
        botao.disabled = true;
        botao.innerHTML = '<i class="bi bi-hourglass-split"></i>';

        try {
            const resposta = await pedirEAguardarSolicitacao(ativoId, 'baixar_arquivo', caminhoCompleto);
            if (!resposta.resultado.arquivo_pronto) {
                throw new Error('O agente não conseguiu enviar o arquivo.');
            }
            window.location.href = <?= json_encode(url('/ativos/solicitacoes/arquivo')) ?> + '?id=' + resposta.id + '&ativo_id=' + ativoId;
        } catch (e) {
            alert('Falha ao baixar: ' + e.message);
        } finally {
            botao.disabled = false;
            botao.innerHTML = textoOriginal;
        }
    }

    const inputEnviar = document.getElementById('inputEnviarArquivo');
    const botaoEnviar = document.getElementById('botaoEnviarArquivo');
    if (inputEnviar && botaoEnviar) {
        botaoEnviar.addEventListener('click', async function () {
            const arquivo = inputEnviar.files[0];
            if (!arquivo) {
                alert('Escolha um arquivo primeiro.');
                return;
            }

            if (!confirm('Enviar "' + arquivo.name + '" pra pasta atual (' + caminhoAtual + ') nesta máquina?')) {
                return;
            }

            const dados = new FormData();
            dados.set('id', ativoId);
            dados.set('destino', caminhoAtual);
            dados.set('arquivo', arquivo);

            botaoEnviar.disabled = true;
            botaoEnviar.innerHTML = '<i class="bi bi-hourglass-split"></i>';

            try {
                const res = await fetch(<?= json_encode(url('/ativos/comando/enviar-arquivo')) ?>, { method: 'POST', body: dados });
                const resultado = await res.json();
                alert(resultado.message || (resultado.success ? 'Enviado.' : 'Falha ao enviar.'));
                if (resultado.success) {
                    inputEnviar.value = '';
                    setTimeout(function () { carregarPasta(caminhoAtual); }, 3000);
                }
            } catch (e) {
                alert('Erro ao comunicar com o servidor.');
            } finally {
                botaoEnviar.disabled = false;
                botaoEnviar.innerHTML = '<i class="bi bi-upload"></i> Enviar';
            }
        });
    }

    botoes.forEach(function (botao) {
        botao.addEventListener('click', function () {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
            carregarPasta(botao.dataset.unidade + '\\');
        });
    });
})();

(function () {
    const botaoAtualizar = document.getElementById('botaoAtualizarProcessos');
    if (!botaoAtualizar) return;

    const corpoEl = document.getElementById('corpoProcessos');
    const totalEl = document.getElementById('totalProcessos');
    const ativoId = <?= (int)$ativo['id'] ?>;
    let jaCarregouUmaVez = false;

    function renderTabela(itens) {
        totalEl.textContent = itens.length + ' processo(s)';

        if (!itens.length) {
            corpoEl.innerHTML = '<div class="hitech-empty">Nenhum processo retornado.</div>';
            return;
        }

        const tabela = document.createElement('table');
        tabela.className = 'hitech-table';
        const thead = document.createElement('thead');
        thead.innerHTML = '<tr><th>PID</th><th>Nome</th><th>Memória</th><th>Janela</th><th>Iniciado em</th><th></th></tr>';
        const tbody = document.createElement('tbody');

        itens.forEach(function (item) {
            const tr = document.createElement('tr');
            tr.className = 'linha-processo';

            const tdPid = document.createElement('td');
            tdPid.className = 'text-muted';
            tdPid.textContent = item.pid;

            const tdNome = document.createElement('td');
            const icone = document.createElement('i');
            icone.className = 'bi bi-cpu-fill icone-arquivo';
            tdNome.appendChild(icone);
            tdNome.appendChild(document.createTextNode(' ' + item.nome));

            const tdMemoria = document.createElement('td');
            tdMemoria.className = 'text-muted';
            tdMemoria.textContent = item.memoria_mb + ' MB';

            const tdJanela = document.createElement('td');
            tdJanela.className = 'text-muted small';
            tdJanela.textContent = item.janela || '—';

            const tdIniciado = document.createElement('td');
            tdIniciado.className = 'text-muted small';
            tdIniciado.textContent = item.iniciado_em || '—';

            const tdAcao = document.createElement('td');
            tdAcao.className = 'text-end';
            const botaoEncerrar = document.createElement('button');
            botaoEncerrar.type = 'button';
            botaoEncerrar.className = 'hitech-btn hitech-btn-danger';
            botaoEncerrar.innerHTML = '<i class="bi bi-x-lg"></i> Encerrar';
            botaoEncerrar.addEventListener('click', function () { encerrarProcesso(item.pid, item.nome); });
            tdAcao.appendChild(botaoEncerrar);

            tr.appendChild(tdPid);
            tr.appendChild(tdNome);
            tr.appendChild(tdMemoria);
            tr.appendChild(tdJanela);
            tr.appendChild(tdIniciado);
            tr.appendChild(tdAcao);
            tbody.appendChild(tr);
        });

        tabela.appendChild(thead);
        tabela.appendChild(tbody);
        corpoEl.innerHTML = '';
        corpoEl.appendChild(tabela);
    }

    async function carregarProcessos() {
        corpoEl.innerHTML = '<div class="hitech-loading"><div class="spinner-border" role="status"></div><div class="mt-2">Consultando processos...</div></div>';
        totalEl.textContent = '';

        try {
            const resposta = await pedirEAguardarSolicitacao(ativoId, 'listar_processos', null);
            renderTabela(resposta.resultado);
        } catch (e) {
            corpoEl.innerHTML = '';
            const div = document.createElement('div');
            div.className = 'hitech-erro';
            div.innerHTML = '<i class="bi bi-exclamation-triangle"></i> ';
            div.append(e.message);
            corpoEl.appendChild(div);
        }
    }

    async function encerrarProcesso(pid, nome) {
        if (!confirm('Encerrar o processo "' + nome + '" (PID ' + pid + ') agora?\n\nIsso é imediato -- se tiver trabalho não salvo nesse programa, será perdido.')) {
            return;
        }

        const dados = new URLSearchParams();
        dados.set('id', ativoId);
        dados.set('comando', 'encerrar_processo');
        dados.set('alvo', pid);
        dados.set('alvo_label', nome);

        try {
            const res = await fetch(<?= json_encode(url('/ativos/comando')) ?>, { method: 'POST', body: dados });
            const resultado = await res.json();
            alert(resultado.message || (resultado.success ? 'Enviado.' : 'Falha ao enviar.'));
        } catch (e) {
            alert('Erro ao comunicar com o servidor.');
        }
    }

    botaoAtualizar.addEventListener('click', carregarProcessos);

    const abaProcessosBotao = document.querySelector('[data-bs-target="#abaProcessos"]');
    if (abaProcessosBotao) {
        abaProcessosBotao.addEventListener('shown.bs.tab', function () {
            if (!jaCarregouUmaVez) {
                jaCarregouUmaVez = true;
                carregarProcessos();
            }
        });
    }
})();

(function () {
    const botaoExecutar = document.getElementById('botaoExecutarComando');
    if (!botaoExecutar) return;

    const ativoId = <?= (int)$ativo['id'] ?>;
    const campoTipo = document.getElementById('campoTipoComando');
    const campoElevado = document.getElementById('campoElevado');
    const campoComando = document.getElementById('campoComando');
    const painelSaida = document.getElementById('painelSaidaComando');
    const statusSaida = document.getElementById('statusSaidaComando');
    const conteudoSaida = document.getElementById('conteudoSaidaComando');
    const corpoHistorico = document.getElementById('corpoHistoricoComandos');
    const nomeUsuarioAtual = <?= json_encode($_SESSION['usuario']['nome'] ?? '') ?>;

    const MAX_HISTORICO = 5;

    // Delegacao de evento no tbody -- funciona tanto pras linhas que já
    // vieram prontas do servidor quanto pras que a gente insere depois de
    // executar um comando, sem precisar religar listener toda hora.
    if (corpoHistorico) {
        corpoHistorico.addEventListener('click', function (e) {
            const linha = e.target.closest('.linha-historico-comando');
            if (!linha) return;
            const detalhe = document.getElementById(linha.dataset.alvo);
            if (detalhe) detalhe.classList.toggle('d-none');
        });
    }

    function adicionarNoHistorico(tipoLabelCurto, comandoTexto, elevadoFlag, status, saidaTexto) {
        if (!corpoHistorico) return;

        const idDetalhe = 'detalheHistoricoNovo' + Date.now();

        const tr = document.createElement('tr');
        tr.className = 'linha-historico-comando';
        tr.style.cursor = 'pointer';
        tr.dataset.alvo = idDetalhe;

        const tdTipo = document.createElement('td');
        tdTipo.className = 'small';
        tdTipo.textContent = tipoLabelCurto;

        const tdComando = document.createElement('td');
        tdComando.className = 'small font-monospace';
        tdComando.style.cssText = 'max-width:280px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap';
        tdComando.textContent = comandoTexto;

        const tdElevado = document.createElement('td');
        if (elevadoFlag) {
            const badge = document.createElement('span');
            badge.className = 'badge text-bg-warning';
            badge.textContent = 'Sim';
            tdElevado.appendChild(badge);
        } else {
            tdElevado.textContent = '—';
        }

        const tdQuem = document.createElement('td');
        tdQuem.className = 'text-muted small';
        tdQuem.textContent = nomeUsuarioAtual || '—';

        const tdStatus = document.createElement('td');
        const badgeStatus = document.createElement('span');
        badgeStatus.className = 'badge text-bg-' + (status === 'concluido' ? 'success' : 'danger');
        badgeStatus.textContent = status === 'concluido' ? 'Concluído' : 'Erro';
        tdStatus.appendChild(badgeStatus);

        const tdQuando = document.createElement('td');
        tdQuando.className = 'text-muted small text-end';
        tdQuando.textContent = 'agora';

        tr.append(tdTipo, tdComando, tdElevado, tdQuem, tdStatus, tdQuando);

        const trDetalhe = document.createElement('tr');
        trDetalhe.id = idDetalhe;
        trDetalhe.className = 'd-none';
        const tdDetalhe = document.createElement('td');
        tdDetalhe.colSpan = 6;
        tdDetalhe.className = 'p-0';
        const pre = document.createElement('pre');
        pre.className = 'm-0 p-2 small';
        pre.style.cssText = 'background:#0d1117; color:#c9d1d9; white-space:pre-wrap; word-break:break-word; max-height:220px; overflow:auto;';
        pre.textContent = saidaTexto || '(sem saída)';
        tdDetalhe.appendChild(pre);
        trDetalhe.appendChild(tdDetalhe);

        corpoHistorico.insertBefore(trDetalhe, corpoHistorico.firstChild);
        corpoHistorico.insertBefore(tr, trDetalhe);

        // Mantem so os ultimos MAX_HISTORICO (cada entrada = 2 <tr>: linha + detalhe)
        while (corpoHistorico.children.length > MAX_HISTORICO * 2) {
            corpoHistorico.removeChild(corpoHistorico.lastChild);
        }
    }

    botaoExecutar.addEventListener('click', async function () {
        const comando = campoComando.value.trim();
        if (!comando) {
            alert('Digite um comando primeiro.');
            return;
        }

        const tipo = campoTipo.value;
        const elevado = campoElevado.checked;
        const tipoLabel = tipo === 'executar_cmd' ? 'CMD' : 'PowerShell';

        if (!confirm(
            'Executar via ' + tipoLabel + (elevado ? ' (ELEVADO -- como administrador)' : '') + '?\n\n' +
            comando + '\n\nRoda imediatamente nesta máquina, sem confirmação na tela do usuário.'
        )) {
            return;
        }

        botaoExecutar.disabled = true;
        painelSaida.classList.remove('d-none');
        statusSaida.textContent = 'Executando...';
        conteudoSaida.textContent = '';

        // Não usa o pedirEAguardarSolicitacao() compartilhado aqui porque
        // esse comando precisa mandar "elevado" também, e o helper genérico
        // só manda id/tipo/parametro -- por isso repete a lógica de
        // solicitar+aguardar aqui, com o campo extra.
        try {
            const dadosSolicitar = new URLSearchParams();
            dadosSolicitar.set('id', ativoId);
            dadosSolicitar.set('tipo', tipo);
            dadosSolicitar.set('parametro', comando);
            if (elevado) dadosSolicitar.set('elevado', '1');

            const resSolicitar = await fetch(<?= json_encode(url('/ativos/solicitacoes/listar')) ?>, { method: 'POST', body: dadosSolicitar });
            const dadosResultado = await resSolicitar.json();

            if (!dadosResultado.success) {
                throw new Error(dadosResultado.message || 'Falha ao solicitar.');
            }

            const id = dadosResultado.id;
            const inicio = Date.now();
            let concluido = false;

            while (Date.now() - inicio < 45000) {
                await new Promise(function (resolve) { setTimeout(resolve, 700); });

                const resPoll = await fetch(<?= json_encode(url('/ativos/solicitacoes/resultado')) ?> + '?id=' + id + '&ativo_id=' + ativoId);
                const poll = await resPoll.json();

                if (!poll.success) throw new Error(poll.message || 'Falha ao consultar resultado.');

                if (poll.status === 'concluido') {
                    concluido = true;
                    const r = poll.resultado;
                    const saidaCompleta = (r.saida || '') + (r.erro ? '\n--- erro ---\n' + r.erro : '');
                    statusSaida.textContent = 'Código de saída: ' + r.codigo_saida;
                    conteudoSaida.textContent = saidaCompleta || '(sem saída)';
                    adicionarNoHistorico(tipoLabel, comando, elevado, 'concluido', saidaCompleta);
                    break;
                }
                if (poll.status === 'erro') {
                    concluido = true;
                    statusSaida.textContent = 'Erro';
                    conteudoSaida.textContent = poll.mensagem || 'O agente reportou um erro.';
                    adicionarNoHistorico(tipoLabel, comando, elevado, 'erro', poll.mensagem);
                    break;
                }
            }

            if (!concluido) {
                statusSaida.textContent = 'Sem resposta';
                conteudoSaida.textContent = 'Sem resposta do agente em 45s (a máquina está ligada e conectada?).';
            }
        } catch (e) {
            statusSaida.textContent = 'Falha';
            conteudoSaida.textContent = e.message;
        } finally {
            botaoExecutar.disabled = false;
        }
    });
})();

(function () {
    const botao = document.getElementById('botaoForcarCheckin');
    if (!botao) return;

    botao.addEventListener('click', async function () {
        botao.disabled = true;

        const dados = new URLSearchParams();
        dados.set('id', botao.dataset.id);

        try {
            const res = await fetch(<?= json_encode(url('/ativos/solicitar-checkin')) ?>, { method: 'POST', body: dados });
            const resultado = await res.json();
            alert(resultado.message || (resultado.success ? 'Solicitado.' : 'Falha ao solicitar.'));
        } catch (e) {
            alert('Erro ao comunicar com o servidor.');
        } finally {
            botao.disabled = false;
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
                'localmente). Pode levar até <?= $intervaloComunicacao * 2 ?> minutos pra chegar até lá, ' +
                'dependendo de quando o agente fizer a próxima coleta.'
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

(function () {
    async function enviarDesinstalacao(dados, mensagemConfirmacao) {
        if (!confirm(mensagemConfirmacao)) return;

        try {
            const res = await fetch(<?= json_encode(url('/ativos/comando')) ?>, { method: 'POST', body: dados });
            const resultado = await res.json();
            alert(resultado.message || (resultado.success ? 'Comando enviado.' : 'Falha ao enviar comando.'));
            if (resultado.success) location.reload();
        } catch (e) {
            alert('Erro ao comunicar com o servidor.');
        }
    }

    document.querySelectorAll('.botao-desinstalar-programa').forEach(function (botao) {
        botao.addEventListener('click', function () {
            const dados = new URLSearchParams();
            dados.set('id', botao.dataset.ativoId);
            dados.set('comando', 'desinstalar_programa');
            dados.set('programa_id', botao.dataset.programaId);

            enviarDesinstalacao(
                dados,
                'Desinstalar "' + botao.dataset.nome + '" remotamente?\n\n' +
                'O comando é executado silenciosamente quando o instalador suporta isso (ex: MSI). ' +
                'Instaladores mais antigos/não-padrão podem abrir uma tela de confirmação no ' +
                'computador remoto -- não há garantia de desinstalação 100% silenciosa em todos os casos.'
            );
        });
    });

    document.querySelectorAll('.botao-desinstalar-atualizacao').forEach(function (botao) {
        botao.addEventListener('click', function () {
            const dados = new URLSearchParams();
            dados.set('id', botao.dataset.ativoId);
            dados.set('comando', 'desinstalar_atualizacao');
            dados.set('atualizacao_id', botao.dataset.atualizacaoId);

            enviarDesinstalacao(
                dados,
                'Desinstalar a atualização ' + botao.dataset.kb + ' remotamente?\n\n' +
                'Algumas atualizações não podem mais ser removidas se já foram substituídas por ' +
                'atualizações cumulativas mais novas -- isso é uma limitação do Windows, o comando ' +
                'pode falhar silenciosamente nesse caso.'
            );
        });
    });
})();

// window.addEventListener('load', ...) porque bootstrap.bundle.min.js só
// carrega no fim do layout (depois deste conteúdo) -- chamar
// new bootstrap.Tooltip(...) direto aqui (antes do "load") lança
// ReferenceError e derruba o resto deste <script>, incluindo o filtro de
// busca de alertas logo abaixo.
window.addEventListener('load', function () {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });

    const campoBusca = document.getElementById('buscaAlertas');
    if (!campoBusca) return;

    const linhas = document.querySelectorAll('#tabelaAlertas .linha-alerta');
    const contador = document.getElementById('contadorBuscaAlertas');

    campoBusca.addEventListener('input', function () {
        const termo = campoBusca.value.trim().toLowerCase();
        let visiveis = 0;

        linhas.forEach(function (linha) {
            const bate = linha.textContent.toLowerCase().includes(termo);
            linha.style.display = bate ? '' : 'none';
            if (bate) visiveis++;
        });

        contador.textContent = termo ? visiveis + ' de ' + linhas.length + ' alertas' : '';
    });
});
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Ativo - ' . $ativo['nome'];

require __DIR__ . '/../layouts/main.php';
