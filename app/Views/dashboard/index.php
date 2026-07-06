<?php
ob_start();

$hora = date('H:i');
$dataExtenso = date('d/m/Y');

$temAlgumModulo = $samba !== null || $apache !== null || $servidor !== null;

function techCorPercentual(float $p): string {
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
.tech-clock {
    font-family: 'SFMono-Regular', Consolas, monospace;
    font-size: 40px;
    font-weight: 700;
    letter-spacing: .03em;
}
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
.tech-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px rgba(0,0,0,.35);
    color: #e2e8f0;
}
.tech-card .accent {
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 4px;
}
.tech-card .card-body { padding: 22px 24px; }
.tech-label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: #94a3b8;
}
.tech-num {
    font-family: 'SFMono-Regular', Consolas, monospace;
    font-size: 30px;
    font-weight: 700;
    line-height: 1.1;
}
.tech-mini {
    font-family: 'SFMono-Regular', Consolas, monospace;
}
.pulse-dot {
    width: 9px; height: 9px;
    border-radius: 50%;
    display: inline-block;
    position: relative;
}
.pulse-dot::after {
    content: '';
    position: absolute;
    inset: -5px;
    border-radius: 50%;
    border: 2px solid currentColor;
    opacity: .55;
    animation: rd-pulse 2s infinite;
}
.pulse-dot.online { background: #22c55e; color: #22c55e; }
.pulse-dot.offline { background: #ef4444; color: #ef4444; }
@keyframes rd-pulse {
    0% { transform: scale(.6); opacity: .6; }
    100% { transform: scale(1.8); opacity: 0; }
}
.progress-tech {
    height: 6px;
    border-radius: 3px;
    background: #1e293b;
    overflow: hidden;
}
.progress-tech > div { height: 100%; border-radius: 3px; }
.stat-mini-row { display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px solid #1e293b; }
.stat-mini-row:last-child { border-bottom:0; }
</style>

<div class="tech-hero mb-4">
    <div class="hero-content d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <div class="tech-label mb-1">Central de Comando</div>
            <h2 class="mb-1">Olá, <?= htmlspecialchars(explode(' ', $_SESSION['usuario']['nome'] ?? 'Administrador')[0]) ?></h2>
            <small class="text-white-50">RD Tecnologia &mdash; <?= htmlspecialchars($dataExtenso) ?></small>
        </div>
        <div class="text-end">
            <div class="tech-clock" id="rd-clock"><?= htmlspecialchars($hora) ?></div>
            <?php if ($servidor): ?>
                <?php $saude = (int)($servidor['saude']['percentual'] ?? 100); ?>
                <span class="badge" style="background:<?= techCorPercentual(100 - $saude) ?>">
                    Saúde do servidor: <?= $saude ?>%
                </span>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!$temAlgumModulo): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-info-circle display-6 text-muted d-block mb-3"></i>
            <p class="text-muted mb-0">Nenhum módulo liberado para o seu usuário ainda. Fale com um administrador.</p>
        </div>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">

    <?php if ($samba): ?>
    <div class="col-md-4">
        <a href="<?= url('/samba/dashboard') ?>" class="tech-card">
            <div class="accent" style="background:#6366f1"></div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="tech-label">Módulo</div>
                        <h5 class="mb-0"><i class="bi bi-hdd-network-fill me-1"></i> Samba</h5>
                    </div>
                    <span class="pulse-dot online" title="Ativo"></span>
                </div>

                <div class="stat-mini-row">
                    <span class="tech-label mb-0">Usuários</span>
                    <span class="tech-num" style="font-size:18px"><?= (int)$samba['ativos'] ?>/<?= (int)$samba['total'] ?></span>
                </div>
                <div class="stat-mini-row">
                    <span class="tech-label mb-0">Com SSH</span>
                    <span class="tech-mini"><?= (int)$samba['ssh'] ?></span>
                </div>
                <div class="stat-mini-row">
                    <span class="tech-label mb-0">Compartilhamentos</span>
                    <span class="tech-mini"><?= (int)$samba['compartilhamentos'] ?></span>
                </div>
            </div>
        </a>
    </div>
    <?php endif; ?>

    <?php if ($apache): ?>
    <div class="col-md-4">
        <a href="<?= url('/apache/dashboard') ?>" class="tech-card">
            <div class="accent" style="background:#06b6d4"></div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="tech-label">Módulo</div>
                        <h5 class="mb-0"><i class="bi bi-server me-1"></i> Apache</h5>
                    </div>
                    <span class="pulse-dot <?= $apache['servico_status'] === 'active' ? 'online' : 'offline' ?>"
                          title="<?= $apache['servico_status'] === 'active' ? 'Ativo' : 'Inativo' ?>"></span>
                </div>

                <div class="stat-mini-row">
                    <span class="tech-label mb-0">Versão</span>
                    <span class="tech-mini"><?= htmlspecialchars($apache['versao']) ?></span>
                </div>
                <div class="stat-mini-row">
                    <span class="tech-label mb-0">Sites</span>
                    <span class="tech-mini"><?= (int)$apache['sites_habilitados'] ?>/<?= (int)$apache['sites_disponiveis'] ?> habilitados</span>
                </div>
                <div class="stat-mini-row">
                    <span class="tech-label mb-0">Configtest</span>
                    <span class="tech-mini" style="color:<?= $apache['configtest'] === 'OK' ? '#22c55e' : '#ef4444' ?>">
                        <?= htmlspecialchars($apache['configtest']) ?>
                    </span>
                </div>
            </div>
        </a>
    </div>
    <?php endif; ?>

    <?php if ($servidor): ?>
    <div class="col-md-4">
        <a href="<?= url('/infraestrutura/servidor') ?>" class="tech-card">
            <div class="accent" style="background:#22c55e"></div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="tech-label">Infraestrutura</div>
                        <h5 class="mb-0"><i class="bi bi-hdd-rack me-1"></i> Servidor</h5>
                    </div>
                    <span class="pulse-dot online" title="Ativo"></span>
                </div>

                <?php foreach ([
                    ['label' => 'CPU', 'valor' => $servidor['cpu']['percentual']],
                    ['label' => 'Memória', 'valor' => $servidor['memoria']['percentual']],
                    ['label' => 'Disco', 'valor' => $servidor['disco']['principal']['percentual'] ?? 0],
                ] as $item): ?>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between">
                            <span class="tech-label mb-0"><?= $item['label'] ?></span>
                            <span class="tech-mini" style="font-size:12px"><?= $item['valor'] ?>%</span>
                        </div>
                        <div class="progress-tech">
                            <div style="width:<?= min(100, $item['valor']) ?>%; background:<?= techCorPercentual($item['valor']) ?>"></div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="tech-label mt-3">
                    Uptime: <span class="text-light"><?= htmlspecialchars($servidor['uptime']['texto']) ?></span>
                </div>
            </div>
        </a>
    </div>
    <?php endif; ?>

</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <h5><i class="bi bi-cpu me-1"></i> RD Intranet</h5>
        <p class="mb-0 text-muted">
            Plataforma administrativa da RD Tecnologia para gestão de usuários, Samba, Apache,
            servidores, VPN, backup e monitoramento.
        </p>
    </div>
</div>

<script>
(function () {
    var el = document.getElementById('rd-clock');
    if (!el) return;
    setInterval(function () {
        var d = new Date();
        var hh = String(d.getHours()).padStart(2, '0');
        var mm = String(d.getMinutes()).padStart(2, '0');
        var ss = String(d.getSeconds()).padStart(2, '0');
        el.textContent = hh + ':' + mm + ':' + ss;
    }, 1000);
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Dashboard';

require __DIR__ . '/../layouts/main.php';
