<?php
ob_start();

use App\Components\Alert;

$host = $info['host'];
$saude = $info['saude'];
$cpu = $info['cpu'];
$memoria = $info['memoria'];
$disco = $info['disco']['principal'];
$rede = $info['rede'];
$servicos = $info['servicos'];

$interfacesUp = count(array_filter($rede['interfaces'], fn($i) => $i['estado'] === 'up'));
$interfacesTotal = count($rede['interfaces']);

function corSaudeInfra(int $pct): string
{
    return $pct >= 90 ? '#22c55e' : ($pct >= 70 ? '#f59e0b' : '#ef4444');
}

function corPercentualInfra(float $p): string
{
    if ($p >= 90) return '#ef4444';
    if ($p >= 75) return '#f59e0b';
    return '#22c55e';
}
?>

<style>
.tech-hero {
    background: radial-gradient(circle at top left, #1e293b, #0b1220 65%);
    border-radius: 20px;
    color: #e2e8f0;
    position: relative;
    overflow: hidden;
    padding: 32px;
}
.tech-hero::after {
    content: '';
    position: absolute;
    inset: 0;
    background-image:
        linear-gradient(rgba(255,255,255,.04) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,.04) 1px, transparent 1px);
    background-size: 26px 26px;
    pointer-events: none;
}
.tech-hero .hero-content { position: relative; z-index: 1; }
.tech-card {
    background: #0f172a;
    border-radius: 16px;
    border: 1px solid #1e293b;
    color: #e2e8f0;
    position: relative;
    overflow: hidden;
    transition: transform .2s ease, box-shadow .2s ease;
    text-decoration: none;
    display: block;
    height: 100%;
}
.tech-card:hover { transform: translateY(-4px); box-shadow: 0 12px 28px rgba(0,0,0,.35); color:#e2e8f0; }
.tech-card .accent { position: absolute; left: 0; top: 0; bottom: 0; width: 4px; }
.tech-card .card-body { padding: 22px 24px; }
.tech-label { font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: #94a3b8; }
.tech-num { font-family:'SFMono-Regular', Consolas, monospace; font-size: 26px; font-weight: 700; }
.pulse-dot { width: 9px; height: 9px; border-radius: 50%; display: inline-block; position: relative; }
.pulse-dot::after { content:''; position:absolute; inset:-5px; border-radius:50%; border:2px solid currentColor; opacity:.55; animation:rd-pulse 2s infinite; }
.pulse-dot.online { background:#22c55e; color:#22c55e; }
.pulse-dot.offline { background:#ef4444; color:#ef4444; }
@keyframes rd-pulse { 0% { transform:scale(.6); opacity:.6; } 100% { transform:scale(1.8); opacity:0; } }
.progress-tech { height:6px; border-radius:3px; background:#1e293b; overflow:hidden; }
.progress-tech > div { height:100%; border-radius:3px; }
.stat-mini-row { display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px solid #1e293b; }
.stat-mini-row:last-child { border-bottom:0; }
</style>

<?= Alert::flash() ?>

<div class="tech-hero mb-4">
    <div class="hero-content d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <div class="tech-label mb-1">Infraestrutura</div>
            <h4 class="mb-1"><i class="bi bi-hdd-rack"></i> <?= htmlspecialchars($host['hostname']) ?></h4>
            <small class="text-white-50"><?= htmlspecialchars($host['os']) ?> &mdash; Kernel <?= htmlspecialchars($host['kernel']) ?></small>
        </div>
        <div class="text-end">
            <div class="tech-num" style="font-size:36px; color:<?= corSaudeInfra($saude['percentual']) ?>"><?= $saude['percentual'] ?>%</div>
            <span class="tech-label">Saúde geral</span>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <a href="<?= url('/infraestrutura/hardware') ?>" class="tech-card">
            <div class="accent" style="background:#22c55e"></div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="tech-label">Infraestrutura</div>
                        <h5 class="mb-0"><i class="bi bi-cpu me-1"></i> Hardware</h5>
                    </div>
                    <span class="pulse-dot online"></span>
                </div>

                <?php foreach ([
                    ['label' => 'CPU', 'valor' => $cpu['percentual']],
                    ['label' => 'Memória', 'valor' => $memoria['percentual']],
                    ['label' => 'Disco (/)', 'valor' => $disco['percentual'] ?? 0],
                ] as $item): ?>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between">
                            <span class="tech-label mb-0"><?= $item['label'] ?></span>
                            <span style="font-size:12px" class="font-monospace"><?= $item['valor'] ?>%</span>
                        </div>
                        <div class="progress-tech">
                            <div style="width:<?= min(100, $item['valor']) ?>%; background:<?= corPercentualInfra($item['valor']) ?>"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </a>
    </div>

    <div class="col-md-4">
        <a href="<?= url('/infraestrutura/rede') ?>" class="tech-card">
            <div class="accent" style="background:#06b6d4"></div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="tech-label">Infraestrutura</div>
                        <h5 class="mb-0"><i class="bi bi-diagram-2 me-1"></i> Network</h5>
                    </div>
                    <span class="pulse-dot <?= $interfacesUp > 0 ? 'online' : 'offline' ?>"></span>
                </div>

                <div class="stat-mini-row">
                    <span class="tech-label mb-0">Interfaces</span>
                    <span class="tech-num" style="font-size:16px"><?= $interfacesUp ?>/<?= $interfacesTotal ?> up</span>
                </div>
                <div class="stat-mini-row">
                    <span class="tech-label mb-0">Rotas</span>
                    <span class="font-monospace" style="font-size:13px"><?= count($rede['rotas']) ?></span>
                </div>
                <div class="stat-mini-row">
                    <span class="tech-label mb-0">Ferramentas</span>
                    <span style="font-size:12px">ARP · Ping · Traceroute · Tráfego</span>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4">
        <a href="<?= url('/infraestrutura/servicos') ?>" class="tech-card">
            <div class="accent" style="background:#6366f1"></div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="tech-label">Infraestrutura</div>
                        <h5 class="mb-0"><i class="bi bi-hdd-network me-1"></i> Serviços</h5>
                    </div>
                    <span class="pulse-dot <?= $servicos['falharam'] > 0 ? 'offline' : 'online' ?>"></span>
                </div>

                <div class="stat-mini-row">
                    <span class="tech-label mb-0">Em execução</span>
                    <span class="tech-num" style="font-size:16px; color:#22c55e"><?= (int)$servicos['rodando'] ?></span>
                </div>
                <div class="stat-mini-row">
                    <span class="tech-label mb-0">Com falha</span>
                    <span class="tech-num" style="font-size:16px; color:<?= $servicos['falharam'] > 0 ? '#ef4444' : '#94a3b8' ?>"><?= (int)$servicos['falharam'] ?></span>
                </div>
            </div>
        </a>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Infraestrutura - Servidor';

require __DIR__ . '/../layouts/main.php';
