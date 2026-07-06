<?php
ob_start();

use App\Components\Alert;

$ipAtual = $atual['ipv4'][0] ?? '';
?>

<style>
.cfg-card { border:0; border-radius:14px; box-shadow:0 4px 14px rgba(0,0,0,.06); margin-bottom:1.25rem; }
.cfg-card .card-header { background:#f8fafc; border-bottom:1px solid #e9ecef; border-radius:14px 14px 0 0; padding:14px 20px; }
.field-help { font-size:11px; color:#9ca3af; margin-top:3px; }
</style>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-pencil-square me-1"></i> Editar Interface: <?= htmlspecialchars($interface) ?></h4>
        <span class="text-muted" style="font-size:13px">
            Estado atual: <?= $atual['estado'] === 'up' ? '<span class="badge bg-success">Up</span>' : '<span class="badge bg-secondary">Down</span>' ?>
            &nbsp;|&nbsp; Endereços: <?= htmlspecialchars(implode(', ', array_merge($atual['ipv4'], $atual['ipv6'])) ?: '-') ?>
            &nbsp;|&nbsp; MAC: <code><?= htmlspecialchars($atual['mac']) ?></code>
        </span>
    </div>
    <a href="<?= url('/infraestrutura/rede') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Voltar
    </a>
</div>

<div id="alerta-pendente" class="alert alert-warning d-none">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <strong><i class="bi bi-exclamation-triangle"></i> Alteração aplicada, aguardando confirmação.</strong><br>
            Se não for confirmada, a configuração anterior será restaurada automaticamente em
            <strong id="segundos-restantes">--</strong> segundo(s).
        </div>
        <button class="btn btn-success" id="btn-confirmar">
            <i class="bi bi-check-lg"></i> Confirmar Alteração
        </button>
    </div>
</div>

<div class="card cfg-card">
    <div class="card-header">
        <i class="bi bi-diagram-3 me-1"></i> Configuração de Rede
    </div>
    <div class="card-body">
        <form id="form-rede">
            <input type="hidden" name="interface" value="<?= htmlspecialchars($interface) ?>">

            <div class="mb-3">
                <label class="form-label">Modo</label>
                <select class="form-select" name="modo" id="campo-modo">
                    <option value="estatico">IP Estático</option>
                    <option value="dhcp">Automático (DHCP)</option>
                </select>
                <div class="field-help">DHCP obtém o endereço automaticamente do roteador/servidor DHCP da rede.</div>
            </div>

            <div id="campos-estatico">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Endereço IP / CIDR</label>
                        <input type="text" class="form-control" name="ip_cidr" placeholder="192.168.1.15/24" value="<?= htmlspecialchars($ipAtual) ?>">
                        <div class="field-help">Formato: endereço/prefixo, ex: 192.168.1.15/24</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Gateway</label>
                        <input type="text" class="form-control" name="gateway" placeholder="192.168.1.1">
                        <div class="field-help">Endereço do roteador padrão desta rede</div>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Servidores DNS</label>
                        <input type="text" class="form-control" name="dns" placeholder="8.8.8.8, 1.1.1.1">
                        <div class="field-help">Separe múltiplos endereços por vírgula</div>
                    </div>
                </div>
            </div>

            <div class="mt-4 d-flex align-items-center gap-2">
                <button type="submit" class="btn btn-primary" id="btn-aplicar">
                    <i class="bi bi-check2-circle"></i> Aplicar Alteração
                </button>
                <small class="text-muted">
                    A alteração será revertida automaticamente em 120s caso não seja confirmada — seguro para testar sem risco de perder o acesso.
                </small>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const APLICAR_URL   = '<?= url('/infraestrutura/servidor/rede/aplicar') ?>';
    const CONFIRMAR_URL = '<?= url('/infraestrutura/servidor/rede/confirmar') ?>';
    const STATUS_URL    = '<?= url('/infraestrutura/servidor/rede/status') ?>';

    const selModo   = document.getElementById('campo-modo');
    const camposEst = document.getElementById('campos-estatico');
    const alertaPendente = document.getElementById('alerta-pendente');
    const segundosEl = document.getElementById('segundos-restantes');
    const btnConfirmar = document.getElementById('btn-confirmar');
    const btnAplicar = document.getElementById('btn-aplicar');

    let poll = null;

    function alternarCampos() {
        camposEst.style.display = selModo.value === 'dhcp' ? 'none' : '';
    }
    selModo.addEventListener('change', alternarCampos);
    alternarCampos();

    function mostrarAlerta(msg, ok) {
        alert(msg);
    }

    async function verificarStatus() {
        try {
            const res = await fetch(STATUS_URL);
            const data = await res.json();
            if (data.pendente) {
                alertaPendente.classList.remove('d-none');
                segundosEl.textContent = data.segundos_restantes;
                if (!poll) {
                    poll = setInterval(verificarStatus, 3000);
                }
                if (data.segundos_restantes <= 0) {
                    clearInterval(poll);
                    poll = null;
                    alertaPendente.classList.add('d-none');
                }
            } else {
                alertaPendente.classList.add('d-none');
                if (poll) { clearInterval(poll); poll = null; }
            }
        } catch (e) {
            console.warn('Falha ao verificar status de rede:', e);
        }
    }

    document.getElementById('form-rede').addEventListener('submit', async function (e) {
        e.preventDefault();

        if (!confirm('Confirma aplicar esta configuração de rede? Você terá 120 segundos para confirmar antes da reversão automática.')) {
            return;
        }

        btnAplicar.disabled = true;
        try {
            const fd = new FormData(e.target);
            const res = await fetch(APLICAR_URL, { method: 'POST', body: fd });
            const data = await res.json();
            mostrarAlerta(data.message, data.success);
            if (data.success) verificarStatus();
        } catch (err) {
            mostrarAlerta('Erro ao comunicar com o servidor.', false);
        } finally {
            btnAplicar.disabled = false;
        }
    });

    btnConfirmar.addEventListener('click', async function () {
        btnConfirmar.disabled = true;
        try {
            const res = await fetch(CONFIRMAR_URL, { method: 'POST' });
            const data = await res.json();
            mostrarAlerta(data.message, data.success);
            if (data.success) {
                alertaPendente.classList.add('d-none');
                if (poll) { clearInterval(poll); poll = null; }
            }
        } catch (err) {
            mostrarAlerta('Erro ao comunicar com o servidor.', false);
        } finally {
            btnConfirmar.disabled = false;
        }
    });

    verificarStatus();
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Infraestrutura - Editar Rede';

require __DIR__ . '/../layouts/main.php';
