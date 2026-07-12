<?php
ob_start();

use App\Components\Alert;
use App\Components\Badge;

$config = $status['config'];
$instalado = (bool)($config['instalado'] ?? false);
$pkiOk = (bool)($config['pki_inicializada'] ?? false);
?>

<style>
.tech-card { background: #0f172a; border-radius: 16px; border: 1px solid #1e293b; color: #e2e8f0; }
.tech-label { font-size:11px; text-transform:uppercase; letter-spacing:.08em; color:#94a3b8; }
.tech-num { font-family:'SFMono-Regular',Consolas,monospace; font-weight:700; }
</style>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1"><i class="bi bi-globe-americas me-1"></i> IKEv2/IPsec - Servidor</h4>
        <small class="text-muted"><a href="<?= url('/vpn') ?>"><i class="bi bi-arrow-left"></i> Dashboard VPN</a></small>
    </div>
</div>

<?php if ($firewallPendente['pendente']): ?>
    <div class="alert alert-warning d-flex justify-content-between align-items-center" id="alertaFirewallPendente">
        <div>
            <i class="bi bi-exclamation-triangle"></i>
            Uma alteração no firewall está aguardando confirmação (reverte sozinha em <span id="segundosRestantes"><?= (int)$firewallPendente['segundos_restantes'] ?></span>s).
        </div>
        <button type="button" class="btn btn-sm btn-warning" id="botaoConfirmarFirewall">Confirmar agora</button>
    </div>
<?php endif; ?>

<?php if (!$instalado): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body text-center py-5">
            <i class="bi bi-globe-americas display-5 text-muted"></i>
            <h5 class="mt-3">strongSwan não está instalado neste servidor</h5>
            <p class="text-muted">Instala o strongSwan (IKEv2/IPsec) + easy-rsa (PKI compartilhada com o OpenVPN).</p>
            <button type="button" class="btn btn-primary" id="botaoInstalar">
                <i class="bi bi-download"></i> Instalar strongSwan
            </button>
        </div>
    </div>
<?php elseif (!$pkiOk): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body text-center py-5">
            <i class="bi bi-key display-5 text-muted"></i>
            <h5 class="mt-3">A PKI ainda não foi inicializada</h5>
            <p class="text-muted">
                Emite um certificado de servidor com o CN igual ao endereço público configurado abaixo
                (necessário pros clientes nativos validarem o servidor). Configure o "Endereço público" primeiro se ainda não detectou automaticamente.
            </p>
            <form method="post" action="<?= url('/vpn/ikev2/salvar-config') ?>" class="mb-3" style="max-width:420px; margin:0 auto;">
                <input type="hidden" name="subnet_cidr" value="<?= htmlspecialchars($config['subnet_cidr'] ?? '10.10.0.0/24') ?>">
                <input type="hidden" name="dns_push" value="<?= htmlspecialchars($config['dns_push'] ?? '') ?>">
                <label class="form-label small">Endereço público (hostname ou IP fixo)</label>
                <div class="input-group">
                    <input type="text" name="endpoint_publico" class="form-control" value="<?= htmlspecialchars($config['endpoint_publico'] ?? '') ?>" placeholder="vpn.exemplo.com">
                    <button type="submit" class="btn btn-outline-secondary">Salvar</button>
                </div>
            </form>
            <button type="button" class="btn btn-primary" id="botaoInicializarPki">
                <i class="bi bi-key"></i> Inicializar PKI
            </button>
        </div>
    </div>
<?php else: ?>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="tech-card p-3">
                <div class="tech-label mb-1">Servidor</div>
                <div class="tech-num" style="font-size:20px"><?= $status['servidor_ativo'] ? Badge::make('Ativo', 'success') : Badge::make('Inativo', 'secondary') ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="tech-card p-3">
                <div class="tech-label mb-1">Portas</div>
                <div class="tech-num" style="font-size:20px">500 + 4500 / udp</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="tech-card p-3">
                <div class="tech-label mb-1">Exposto à internet</div>
                <div class="tech-num" style="font-size:20px; color:<?= $config['exposto_internet'] ? '#22c55e' : '#94a3b8' ?>">
                    <?= $config['exposto_internet'] ? 'Sim' : 'Não' ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <strong><i class="bi bi-wifi"></i> Expor à internet</strong>
        </div>
        <div class="card-body">
            <?php if (empty($status['clientes'])): ?>
                <p class="text-muted small mb-0">Crie pelo menos um cliente antes de expor as portas à internet.</p>
            <?php else: ?>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="toggleExpor" <?= $config['exposto_internet'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="toggleExpor">
                        Liberar as portas 500 e 4500/udp no firewall pra este servidor aceitar conexões IKEv2 da internet
                    </label>
                </div>
                <p class="text-muted small mt-2 mb-0">
                    A mudança no firewall precisa ser confirmada em até 90s (Infraestrutura &gt; Firewall), senão reverte sozinha.
                </p>
            <?php endif; ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <strong><i class="bi bi-gear"></i> Configuração do servidor</strong>
        </div>
        <div class="card-body">
            <form method="post" action="<?= url('/vpn/ikev2/salvar-config') ?>">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Subnet da VPN</label>
                        <input type="text" name="subnet_cidr" class="form-control" required placeholder="10.10.0.0/24"
                               value="<?= htmlspecialchars($config['subnet_cidr'] ?? '10.10.0.0/24') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">DNS a enviar aos clientes (opcional)</label>
                        <input type="text" name="dns_push" class="form-control" placeholder="1.1.1.1"
                               value="<?= htmlspecialchars($config['dns_push'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Endereço público</label>
                        <input type="text" name="endpoint_publico" class="form-control" value="<?= htmlspecialchars($config['endpoint_publico'] ?? '') ?>">
                        <div class="form-text">Mudar aqui não reemite o certificado automaticamente.</div>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar e aplicar</button>
                </div>
            </form>
        </div>
    </div>

<?php endif; ?>

<div class="modal fade" id="modalAcao" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalAcaoTitulo">Processando</h5>
            </div>
            <div class="modal-body" id="modalAcaoCorpo">
                <div class="text-center text-muted py-3"><i class="bi bi-hourglass-split"></i> Aguarde...</div>
            </div>
            <div class="modal-footer" id="modalAcaoRodape" style="display:none">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="location.reload()">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const URLS = {
        instalar: <?= json_encode(url('/vpn/ikev2/instalar')) ?>,
        pki: <?= json_encode(url('/vpn/ikev2/pki/inicializar')) ?>,
        expor: <?= json_encode(url('/vpn/ikev2/expor')) ?>,
        confirmarFirewall: <?= json_encode(url('/infraestrutura/iptables/confirmar')) ?>,
    };

    async function executar(url, titulo, corpoPost) {
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAcao'));
        const corpo = document.getElementById('modalAcaoCorpo');
        const rodape = document.getElementById('modalAcaoRodape');

        document.getElementById('modalAcaoTitulo').textContent = titulo;
        corpo.innerHTML = '<div class="text-center text-muted py-3"><i class="bi bi-hourglass-split"></i> Aguarde, pode levar até 1 minuto...</div>';
        rodape.style.display = 'none';
        modal.show();

        try {
            const res = await fetch(url, { method: 'POST', body: corpoPost });
            const dados = await res.json();

            const cor = dados.success ? 'success' : 'danger';
            const icone = dados.success ? 'check-circle' : 'x-circle';
            corpo.innerHTML = '<div class="alert alert-' + cor + '"><i class="bi bi-' + icone + '"></i> ' +
                String(dados.message || '').replace(/</g, '&lt;') + '</div>';
        } catch (e) {
            corpo.innerHTML = '<div class="alert alert-danger mb-0">Erro ao comunicar com o servidor.</div>';
        } finally {
            rodape.style.display = '';
        }
    }

    const botaoInstalar = document.getElementById('botaoInstalar');
    if (botaoInstalar) {
        botaoInstalar.addEventListener('click', function () {
            if (!confirm('Instalar o strongSwan neste servidor?')) return;
            executar(URLS.instalar, 'Instalando strongSwan');
        });
    }

    const botaoInicializarPki = document.getElementById('botaoInicializarPki');
    if (botaoInicializarPki) {
        botaoInicializarPki.addEventListener('click', function () {
            if (!confirm('Inicializar a PKI e emitir o certificado do servidor? Isso só deve ser feito uma vez.')) return;
            executar(URLS.pki, 'Inicializando PKI');
        });
    }

    const toggleExpor = document.getElementById('toggleExpor');
    if (toggleExpor) {
        toggleExpor.addEventListener('change', function () {
            const ligar = toggleExpor.checked;
            const mensagem = ligar
                ? 'Liberar as portas do IKEv2 para a internet?'
                : 'Fechar as portas do IKEv2 para a internet?';
            if (!confirm(mensagem)) {
                toggleExpor.checked = !ligar;
                return;
            }
            const dados = new URLSearchParams();
            dados.set('expor', ligar ? '1' : '0');
            executar(URLS.expor, ligar ? 'Liberando portas' : 'Fechando portas', dados);
        });
    }

    const botaoConfirmarFirewall = document.getElementById('botaoConfirmarFirewall');
    if (botaoConfirmarFirewall) {
        botaoConfirmarFirewall.addEventListener('click', async function () {
            botaoConfirmarFirewall.disabled = true;
            try {
                const res = await fetch(URLS.confirmarFirewall, { method: 'POST' });
                const dados = await res.json();
                if (dados.success) {
                    document.getElementById('alertaFirewallPendente').remove();
                } else {
                    alert(dados.message || 'Falha ao confirmar.');
                    botaoConfirmarFirewall.disabled = false;
                }
            } catch (e) {
                botaoConfirmarFirewall.disabled = false;
            }
        });
    }
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'VPN - IKEv2 - Servidor';

require __DIR__ . '/../layouts/main.php';
