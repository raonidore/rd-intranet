<?php
ob_start();

use App\Services\PermissionService;

$hora = date('H:i');
$dataExtenso = date('d/m/Y');

$temAlgumModulo = $samba !== null || $apache !== null || $servidor !== null || $ativos !== null;

function techCorPercentual(float $p): string {
    if ($p >= 90) return '#ef4444';
    if ($p >= 75) return '#f59e0b';
    return '#22c55e';
}

function formatBytesDashboard(int $bytes): string
{
    if ($bytes <= 0) return '0 B';
    $unidades = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = (int)floor(log($bytes, 1024));
    $i = min($i, count($unidades) - 1);
    return round($bytes / (1024 ** $i), 1) . ' ' . $unidades[$i];
}

/**
 * Catálogo dos cards que dá pra escolher mostrar/esconder aqui --
 * mesmo padrão de AtivoService::COLUNAS_LISTA (chave => label + padrão
 * visível). Os 4 originais (samba/apache/servidor/ativos) continuam
 * visíveis por padrão; os 5 sub-cards da Infraestrutura nascem
 * desmarcados pra não mudar a visão de quem já usa o dashboard hoje.
 */
$cardsDisponiveis = [
    'samba' => ['label' => 'Módulo Samba', 'padrao' => true],
    'apache' => ['label' => 'Módulo Apache', 'padrao' => true],
    'servidor' => ['label' => 'Servidor', 'padrao' => true],
    'ativos' => ['label' => 'Ativos', 'padrao' => true],
    'hardware' => ['label' => 'Hardware', 'padrao' => false],
    'rede' => ['label' => 'Network', 'padrao' => false],
    'servicos' => ['label' => 'Serviços', 'padrao' => false],
    'trafego' => ['label' => 'Tráfego', 'padrao' => false],
    'velocidade' => ['label' => 'Teste de Velocidade', 'padrao' => false],
];

if ($servidor) {
    $interfacesUp = count(array_filter($servidor['rede']['interfaces'], fn($i) => $i['estado'] === 'up'));
    $interfacesTotal = count($servidor['rede']['interfaces']);
    $interfacesTrafego = array_filter($servidor['rede']['interfaces'], fn($i) => $i['nome'] !== 'lo');
    $totalRxBytes = array_sum(array_column($interfacesTrafego, 'rx_bytes'));
    $totalTxBytes = array_sum(array_column($interfacesTrafego, 'tx_bytes'));
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
<?php else: ?>

<div class="d-flex justify-content-end mb-2">
    <div class="dropdown">
        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">
            <i class="bi bi-sliders"></i> Personalizar
        </button>
        <div class="dropdown-menu p-3" id="menuCardsDashboard" style="min-width:240px">
            <?php foreach ($cardsDisponiveis as $chaveCard => $infoCard): ?>
                <div class="form-check">
                    <input class="form-check-input campo-card-dashboard" type="checkbox" value="<?= htmlspecialchars($chaveCard) ?>" id="card-<?= htmlspecialchars($chaveCard) ?>">
                    <label class="form-check-label small" for="card-<?= htmlspecialchars($chaveCard) ?>"><?= htmlspecialchars($infoCard['label']) ?></label>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="row g-3 mb-4" id="linhaCardsDashboard">

    <?php if ($samba): ?>
    <div class="col-md-4" data-card="samba">
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
    <div class="col-md-4" data-card="apache">
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
    <div class="col-md-4" data-card="servidor">
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

    <?php if ($ativos): ?>
    <div class="col-md-4" data-card="ativos">
        <a href="<?= url('/ativos') ?>" class="tech-card">
            <div class="accent" style="background:#f59e0b"></div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="tech-label">Módulo</div>
                        <h5 class="mb-0"><i class="bi bi-pc-display me-1"></i> Ativos</h5>
                    </div>
                    <span class="pulse-dot <?= $ativos['ligados'] > 0 ? 'online' : 'offline' ?>"></span>
                </div>

                <div class="stat-mini-row">
                    <span class="tech-label mb-0">PCs cadastrados</span>
                    <span class="tech-num" style="font-size:18px"><?= (int)$ativos['total'] ?></span>
                </div>
                <div class="stat-mini-row">
                    <span class="tech-label mb-0">Ligados agora</span>
                    <span class="tech-num" style="font-size:18px; color:#22c55e"><?= (int)$ativos['ligados'] ?></span>
                </div>
            </div>
        </a>
    </div>
    <?php endif; ?>

    <?php if ($servidor): ?>
    <div class="col-lg-3 col-md-6" data-card="hardware">
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
                    ['label' => 'CPU', 'valor' => $servidor['cpu']['percentual']],
                    ['label' => 'Memória', 'valor' => $servidor['memoria']['percentual']],
                    ['label' => 'Disco (/)', 'valor' => $servidor['disco']['principal']['percentual'] ?? 0],
                ] as $item): ?>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between">
                            <span class="tech-label mb-0"><?= $item['label'] ?></span>
                            <span style="font-size:12px" class="font-monospace"><?= $item['valor'] ?>%</span>
                        </div>
                        <div class="progress-tech">
                            <div style="width:<?= min(100, $item['valor']) ?>%; background:<?= techCorPercentual($item['valor']) ?>"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </a>
    </div>

    <div class="col-md-4" data-card="rede">
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
                    <span class="font-monospace" style="font-size:13px"><?= count($servidor['rede']['rotas']) ?></span>
                </div>
                <div class="stat-mini-row">
                    <span class="tech-label mb-0">Ferramentas</span>
                    <span style="font-size:12px">ARP · Ping · Traceroute · Tráfego</span>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4" data-card="servicos">
        <a href="<?= url('/infraestrutura/servicos') ?>" class="tech-card">
            <div class="accent" style="background:#6366f1"></div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="tech-label">Infraestrutura</div>
                        <h5 class="mb-0"><i class="bi bi-hdd-network me-1"></i> Serviços</h5>
                    </div>
                    <span class="pulse-dot <?= $servidor['servicos']['falharam'] > 0 ? 'offline' : 'online' ?>"></span>
                </div>

                <div class="stat-mini-row">
                    <span class="tech-label mb-0">Em execução</span>
                    <span class="tech-num" style="font-size:16px; color:#22c55e"><?= (int)$servidor['servicos']['rodando'] ?></span>
                </div>
                <div class="stat-mini-row">
                    <span class="tech-label mb-0">Com falha</span>
                    <span class="tech-num" style="font-size:16px; color:<?= $servidor['servicos']['falharam'] > 0 ? '#ef4444' : '#94a3b8' ?>"><?= (int)$servidor['servicos']['falharam'] ?></span>
                </div>
            </div>
        </a>
    </div>

    <div class="col-lg-3 col-md-6" data-card="trafego">
        <a href="<?= url('/infraestrutura/rede/trafego/historico') ?>" class="tech-card">
            <div class="accent" style="background:#f59e0b"></div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="tech-label">Infraestrutura</div>
                        <h5 class="mb-0"><i class="bi bi-bar-chart-line me-1"></i> Tráfego</h5>
                    </div>
                    <span class="pulse-dot online"></span>
                </div>

                <div class="stat-mini-row">
                    <span class="tech-label mb-0"><i class="bi bi-arrow-down-circle me-1"></i> Download</span>
                    <span class="tech-num" style="font-size:16px; color:#22c55e"><?= formatBytesDashboard($totalRxBytes) ?></span>
                </div>
                <div class="stat-mini-row">
                    <span class="tech-label mb-0"><i class="bi bi-arrow-up-circle me-1"></i> Upload</span>
                    <span class="tech-num" style="font-size:16px; color:#06b6d4"><?= formatBytesDashboard($totalTxBytes) ?></span>
                </div>
                <div class="stat-mini-row">
                    <span class="tech-label mb-0">Histórico</span>
                    <span style="font-size:12px">Ver consumo por dia</span>
                </div>
            </div>
        </a>
    </div>
    <?php endif; ?>

    <?php if (PermissionService::temAcesso('infra_speedtest')): ?>
    <div class="col-lg-3 col-md-6" data-card="velocidade">
        <a href="<?= url('/infraestrutura/velocidade') ?>" class="tech-card">
            <div class="accent" style="background:#22c55e"></div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="tech-label">Infraestrutura</div>
                        <h5 class="mb-0"><i class="bi bi-speedometer2 me-1"></i> Teste de Velocidade</h5>
                    </div>
                    <span class="pulse-dot <?= $speedtest ? 'online' : 'offline' ?>"></span>
                </div>

                <?php if ($speedtest): ?>
                    <div class="stat-mini-row">
                        <span class="tech-label mb-0"><i class="bi bi-arrow-down-circle me-1"></i> Download</span>
                        <span class="tech-num" style="font-size:16px; color:#22c55e"><?= number_format((float)$speedtest['download_mbps'], 1, ',', '.') ?> Mbps</span>
                    </div>
                    <div class="stat-mini-row">
                        <span class="tech-label mb-0"><i class="bi bi-arrow-up-circle me-1"></i> Upload</span>
                        <span class="tech-num" style="font-size:16px; color:#06b6d4"><?= number_format((float)$speedtest['upload_mbps'], 1, ',', '.') ?> Mbps</span>
                    </div>
                <?php else: ?>
                    <div class="stat-mini-row">
                        <span class="tech-label mb-0">Status</span>
                        <span style="font-size:12px">Nenhum teste executado</span>
                    </div>
                <?php endif; ?>
            </div>
        </a>
    </div>
    <?php endif; ?>

</div>
<?php endif; ?>

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

// Visibilidade de card é preferência de tela (mesmo padrão do seletor
// de colunas em ativos/lista.php) -- localStorage deste navegador, sem
// round-trip no servidor.
(function () {
    const CHAVE_STORAGE = 'rd_dashboard_cards';
    const cardsPadrao = <?= json_encode(array_keys(array_filter($cardsDisponiveis, fn($info) => $info['padrao']))) ?>;

    function cardsSalvos() {
        try {
            const bruto = localStorage.getItem(CHAVE_STORAGE);
            const lista = bruto ? JSON.parse(bruto) : null;
            return Array.isArray(lista) ? lista : cardsPadrao;
        } catch (e) {
            return cardsPadrao;
        }
    }

    function aplicarVisibilidade(cardsVisiveis) {
        document.querySelectorAll('#linhaCardsDashboard [data-card]').forEach(function (el) {
            el.style.display = cardsVisiveis.includes(el.dataset.card) ? '' : 'none';
        });
    }

    const cardsAtuais = cardsSalvos();
    aplicarVisibilidade(cardsAtuais);

    document.querySelectorAll('.campo-card-dashboard').forEach(function (checkbox) {
        checkbox.checked = cardsAtuais.includes(checkbox.value);
        checkbox.addEventListener('change', function () {
            const marcados = Array.from(document.querySelectorAll('.campo-card-dashboard'))
                .filter(function (c) { return c.checked; })
                .map(function (c) { return c.value; });

            localStorage.setItem(CHAVE_STORAGE, JSON.stringify(marcados));
            aplicarVisibilidade(marcados);
        });
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Dashboard';

require __DIR__ . '/../layouts/main.php';
