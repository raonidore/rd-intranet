<?php
ob_start();

function formatBytesVpn(int $bytes): string
{
    if ($bytes <= 0) return '0 B';
    $unidades = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = (int)floor(log($bytes, 1024));
    $i = min($i, count($unidades) - 1);
    return round($bytes / (1024 ** $i), 1) . ' ' . $unidades[$i];
}
?>

<style>
.tech-hero {
    background: radial-gradient(circle at top left, #1e293b, #0b1220 65%);
    border-radius: 20px; color: #e2e8f0; position: relative; overflow: hidden; padding: 32px;
}
.tech-hero::after {
    content: ''; position: absolute; inset: 0;
    background-image: linear-gradient(rgba(255,255,255,.04) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.04) 1px, transparent 1px);
    background-size: 26px 26px; pointer-events: none;
}
.tech-hero .hero-content { position: relative; z-index: 1; }
.tech-card {
    background: #0f172a; border-radius: 16px; border: 1px solid #1e293b; color: #e2e8f0;
    position: relative; overflow: hidden; text-decoration: none; display: block; height: 100%;
    transition: transform .2s ease, box-shadow .2s ease;
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
.pulse-dot.neutro { background:#94a3b8; color:#94a3b8; }
@keyframes rd-pulse { 0% { transform:scale(.6); opacity:.6; } 100% { transform:scale(1.8); opacity:0; } }
.stat-mini-row { display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px solid #1e293b; }
.stat-mini-row:last-child { border-bottom:0; }
</style>

<div class="tech-hero mb-4">
    <div class="hero-content d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <div class="tech-label mb-1">VPN</div>
            <h4 class="mb-1"><i class="bi bi-shield-shaded"></i> Concentrador VPN</h4>
            <small class="text-white-50">WireGuard, OpenVPN e IKEv2/IPsec num painel só.</small>
        </div>
        <div class="text-end">
            <div class="tech-num" style="font-size:36px; color:<?= $wireguard['instalado'] ? '#22c55e' : '#94a3b8' ?>">
                <?= $wireguard['peers_online'] ?>/<?= $wireguard['peers_total'] ?>
            </div>
            <span class="tech-label">Peers online (WireGuard)</span>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4 col-md-6">
        <a href="<?= url('/vpn/wireguard/servidor') ?>" class="tech-card">
            <div class="accent" style="background:#22c55e"></div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="tech-label">Protocolo</div>
                        <h5 class="mb-0"><i class="bi bi-lock me-1"></i> WireGuard</h5>
                    </div>
                    <span class="pulse-dot <?= !$wireguard['instalado'] ? 'neutro' : ($wireguard['peers_online'] > 0 ? 'online' : 'offline') ?>"></span>
                </div>

                <?php if (!$wireguard['instalado']): ?>
                    <div class="stat-mini-row">
                        <span class="tech-label mb-0">Status</span>
                        <span style="font-size:12px">Não instalado</span>
                    </div>
                <?php else: ?>
                    <div class="stat-mini-row">
                        <span class="tech-label mb-0">Peers ativos</span>
                        <span class="tech-num" style="font-size:16px"><?= $wireguard['peers_total'] ?></span>
                    </div>
                    <div class="stat-mini-row">
                        <span class="tech-label mb-0">Exposto à internet</span>
                        <span style="font-size:12px"><?= $wireguard['exposto'] ? 'Sim' : 'Não' ?></span>
                    </div>
                    <div class="stat-mini-row">
                        <span class="tech-label mb-0">Tráfego hoje</span>
                        <span style="font-size:12px"><?= formatBytesVpn($wireguard['rx_hoje']) ?> ↓ / <?= formatBytesVpn($wireguard['tx_hoje']) ?> ↑</span>
                    </div>
                <?php endif; ?>
            </div>
        </a>
    </div>

    <div class="col-lg-4 col-md-6">
        <a href="<?= url('/vpn/wireguard/saida') ?>" class="tech-card">
            <div class="accent" style="background:#a855f7"></div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="tech-label">Este servidor como cliente</div>
                        <h5 class="mb-0"><i class="bi bi-box-arrow-up-right me-1"></i> WireGuard - Saída</h5>
                    </div>
                    <span class="pulse-dot <?= $wireguard['conexoes_saida_ativas'] > 0 ? 'online' : 'neutro' ?>"></span>
                </div>
                <div class="stat-mini-row">
                    <span class="tech-label mb-0">Conectadas</span>
                    <span class="tech-num" style="font-size:16px"><?= $wireguard['conexoes_saida_ativas'] ?>/<?= $wireguard['conexoes_saida_total'] ?></span>
                </div>
                <div class="stat-mini-row">
                    <span class="tech-label mb-0">Uso</span>
                    <span style="font-size:12px">Conectar a um WireGuard existente</span>
                </div>
            </div>
        </a>
    </div>

    <div class="col-lg-4 col-md-6">
        <a href="<?= url('/vpn/openvpn/servidor') ?>" class="tech-card">
            <div class="accent" style="background:#3b82f6"></div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="tech-label">Protocolo (também "SSL VPN")</div>
                        <h5 class="mb-0"><i class="bi bi-shield-lock me-1"></i> OpenVPN</h5>
                    </div>
                    <span class="pulse-dot <?= !$openvpn['instalado'] ? 'neutro' : ($openvpn['clientes_online'] > 0 ? 'online' : 'offline') ?>"></span>
                </div>

                <?php if (!$openvpn['instalado']): ?>
                    <div class="stat-mini-row">
                        <span class="tech-label mb-0">Status</span>
                        <span style="font-size:12px">Não instalado</span>
                    </div>
                <?php else: ?>
                    <div class="stat-mini-row">
                        <span class="tech-label mb-0">Clientes ativos</span>
                        <span class="tech-num" style="font-size:16px"><?= $openvpn['clientes_total'] ?></span>
                    </div>
                    <div class="stat-mini-row">
                        <span class="tech-label mb-0">Exposto à internet</span>
                        <span style="font-size:12px"><?= $openvpn['exposto'] ? 'Sim' : 'Não' ?></span>
                    </div>
                    <div class="stat-mini-row">
                        <span class="tech-label mb-0">Tráfego hoje</span>
                        <span style="font-size:12px"><?= formatBytesVpn($openvpn['rx_hoje']) ?> ↓ / <?= formatBytesVpn($openvpn['tx_hoje']) ?> ↑</span>
                    </div>
                <?php endif; ?>
            </div>
        </a>
    </div>

    <div class="col-lg-4 col-md-6">
        <a href="<?= url('/vpn/openvpn/saida') ?>" class="tech-card">
            <div class="accent" style="background:#a855f7"></div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="tech-label">Este servidor como cliente</div>
                        <h5 class="mb-0"><i class="bi bi-box-arrow-up-right me-1"></i> Conexões de Saída</h5>
                    </div>
                    <span class="pulse-dot <?= $openvpn['conexoes_saida_ativas'] > 0 ? 'online' : 'neutro' ?>"></span>
                </div>
                <div class="stat-mini-row">
                    <span class="tech-label mb-0">Conectadas</span>
                    <span class="tech-num" style="font-size:16px"><?= $openvpn['conexoes_saida_ativas'] ?>/<?= $openvpn['conexoes_saida_total'] ?></span>
                </div>
                <div class="stat-mini-row">
                    <span class="tech-label mb-0">Uso</span>
                    <span style="font-size:12px">Conectar a OpenVPN de terceiros (matriz, provedor...)</span>
                </div>
            </div>
        </a>
    </div>

    <div class="col-lg-4 col-md-6">
        <a href="<?= url('/vpn/ikev2/servidor') ?>" class="tech-card">
            <div class="accent" style="background:#f59e0b"></div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="tech-label">Protocolo</div>
                        <h5 class="mb-0"><i class="bi bi-globe-americas me-1"></i> IKEv2 / IPsec</h5>
                    </div>
                    <span class="pulse-dot <?= !$ikev2['instalado'] ? 'neutro' : ($ikev2['clientes_online'] > 0 ? 'online' : 'offline') ?>"></span>
                </div>

                <?php if (!$ikev2['instalado']): ?>
                    <div class="stat-mini-row">
                        <span class="tech-label mb-0">Status</span>
                        <span style="font-size:12px">Não instalado</span>
                    </div>
                <?php else: ?>
                    <div class="stat-mini-row">
                        <span class="tech-label mb-0">Clientes ativos</span>
                        <span class="tech-num" style="font-size:16px"><?= $ikev2['clientes_total'] ?></span>
                    </div>
                    <div class="stat-mini-row">
                        <span class="tech-label mb-0">Exposto à internet</span>
                        <span style="font-size:12px"><?= $ikev2['exposto'] ? 'Sim' : 'Não' ?></span>
                    </div>
                    <div class="stat-mini-row">
                        <span class="tech-label mb-0">Tráfego hoje</span>
                        <span style="font-size:12px"><?= formatBytesVpn($ikev2['rx_hoje']) ?> ↓ / <?= formatBytesVpn($ikev2['tx_hoje']) ?> ↑</span>
                    </div>
                <?php endif; ?>
            </div>
        </a>
    </div>

    <div class="col-lg-4 col-md-6">
        <a href="<?= url('/vpn/ikev2/saida') ?>" class="tech-card">
            <div class="accent" style="background:#a855f7"></div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="tech-label">Este servidor como cliente</div>
                        <h5 class="mb-0"><i class="bi bi-box-arrow-up-right me-1"></i> IKEv2 - Saída</h5>
                    </div>
                    <span class="pulse-dot <?= $ikev2['conexoes_saida_ativas'] > 0 ? 'online' : 'neutro' ?>"></span>
                </div>
                <div class="stat-mini-row">
                    <span class="tech-label mb-0">Conectadas</span>
                    <span class="tech-num" style="font-size:16px"><?= $ikev2['conexoes_saida_ativas'] ?>/<?= $ikev2['conexoes_saida_total'] ?></span>
                </div>
                <div class="stat-mini-row">
                    <span class="tech-label mb-0">Uso</span>
                    <span style="font-size:12px">Conectar a um gateway IPsec existente</span>
                </div>
            </div>
        </a>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'VPN - Dashboard';

require __DIR__ . '/../layouts/main.php';
