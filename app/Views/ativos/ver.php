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
</style>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <small class="text-muted"><a href="<?= url('/ativos/lista') ?>"><i class="bi bi-arrow-left"></i> Lista de Ativos</a></small>
        <h4 class="mb-1 mt-1">
            <i class="bi <?= AtivoService::TIPOS[$ativo['tipo']]['icone'] ?> me-1"></i>
            <?= htmlspecialchars($ativo['nome']) ?>
        </h4>
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
        <a href="<?= url('/ativos/editar?id=' . $ativo['id']) ?>" class="btn btn-primary"><i class="bi bi-pencil"></i> Editar</a>
        <a href="<?= url('/ativos/excluir?id=' . $ativo['id']) ?>" class="btn btn-outline-danger"><i class="bi bi-trash"></i></a>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <?php if (!empty($ativo['ultimo_checkin'])): ?>
        <div class="alert alert-light border small mb-0 py-2">
            <i class="bi bi-clock-history"></i>
            <?php if ($ativo['origem'] === 'agente'): ?>
                Não é ao vivo: última comunicação com o agente foi há <strong><?= $minutosDesdeCheckin ?> min</strong>
                (<?= htmlspecialchars(data_br($ativo['ultimo_checkin'])) ?>), próxima esperada em até
                <strong><?= $intervaloComunicacao ?> min</strong>.
                <?php if ($estaLigada && $uptime): ?>
                    · ligado há <?= htmlspecialchars($uptime) ?>
                    <?php if (!empty($detalhes['ligado_desde'])): ?>
                        (desde <?= htmlspecialchars(data_br($detalhes['ligado_desde'])) ?>)
                    <?php endif; ?>
                <?php endif; ?>
            <?php else: ?>
                Última atualização: <?= htmlspecialchars(data_br($ativo['ultimo_checkin'])) ?>
                via <?= $ativo['origem'] === 'snmp' ? 'SNMP' : 'manual' ?>
            <?php endif; ?>
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
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-white"><strong>Comandos remotos</strong></div>
            <div class="card-body p-0">
                <?php if (empty($comandos)): ?>
                    <p class="text-muted p-3 mb-0">Nenhum comando enviado ainda.</p>
                <?php else: ?>
                    <table class="table table-sm mb-0">
                        <tbody>
                            <?php foreach ($comandos as $c): ?>
                                <tr>
                                    <td class="text-capitalize"><?= htmlspecialchars(str_replace('_', ' ', $c['comando'])) ?><?= !empty($c['alvo_label']) ? ': ' . htmlspecialchars($c['alvo_label']) : '' ?></td>
                                    <td><?= Badge::make($c['status'] === 'entregue' ? 'Entregue' : 'Pendente', $c['status'] === 'entregue' ? 'success' : 'secondary') ?></td>
                                    <td class="text-muted small text-end"><?= htmlspecialchars(data_br($c['criado_em'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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

(function () {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });
})();

(function () {
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
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Ativo - ' . $ativo['nome'];

require __DIR__ . '/../layouts/main.php';
