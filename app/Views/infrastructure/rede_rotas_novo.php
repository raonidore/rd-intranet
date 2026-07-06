<?php
ob_start();

use App\Components\Alert;
?>

<style>
.field-help { font-size:11px; color:#9ca3af; margin-top:3px; }
</style>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-plus-lg me-1"></i> Nova Rota</h4>
    </div>
    <a href="<?= url('/infraestrutura/rede/rotas') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Voltar
    </a>
</div>

<div id="alerta-pendente" class="alert alert-warning d-none">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <strong><i class="bi bi-exclamation-triangle"></i> Rota aplicada, aguardando confirmação.</strong><br>
            Se não for confirmada, será revertida automaticamente em
            <strong id="segundos-restantes">--</strong> segundo(s).
        </div>
        <button class="btn btn-success" id="btn-confirmar">
            <i class="bi bi-check-lg"></i> Confirmar Rota
        </button>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <i class="bi bi-signpost-split me-1"></i> Dados da rota
    </div>
    <div class="card-body">
        <form id="form-rota">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Destino (CIDR)</label>
                    <input type="text" class="form-control" name="destino" placeholder="203.0.113.0/24" required>
                    <div class="field-help">Rede de destino no formato endereço/prefixo</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Via (gateway)</label>
                    <input type="text" class="form-control" name="via" placeholder="192.168.1.1" required>
                    <div class="field-help">Endereço do roteador para essa rede</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Interface</label>
                    <select class="form-select" name="dev" required>
                        <?php foreach ($interfaces as $i): ?>
                            <option value="<?= htmlspecialchars($i) ?>"><?= htmlspecialchars($i) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mt-4 d-flex align-items-center gap-2">
                <button type="submit" class="btn btn-primary" id="btn-aplicar">
                    <i class="bi bi-check2-circle"></i> Aplicar Rota
                </button>
                <small class="text-muted">
                    A rota será revertida automaticamente em 120s caso não seja confirmada — seguro para testar sem risco de perder o acesso.
                </small>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const APLICAR_URL = '<?= url('/infraestrutura/rede/rotas/novo') ?>';
    const CONFIRMAR_URL = '<?= url('/infraestrutura/rede/rotas/confirmar') ?>';
    const STATUS_URL = '<?= url('/infraestrutura/rede/rotas/status') ?>';

    const alertaPendente = document.getElementById('alerta-pendente');
    const segundosEl = document.getElementById('segundos-restantes');
    const btnConfirmar = document.getElementById('btn-confirmar');
    const btnAplicar = document.getElementById('btn-aplicar');
    const form = document.getElementById('form-rota');

    let poll = null;

    async function verificarStatus() {
        try {
            const res = await fetch(STATUS_URL);
            const data = await res.json();
            if (data.pendente) {
                alertaPendente.classList.remove('d-none');
                segundosEl.textContent = data.segundos_restantes;
                if (!poll) poll = setInterval(verificarStatus, 3000);
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
            console.warn('Falha ao verificar status de rota:', e);
        }
    }

    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        if (!confirm('Confirma aplicar esta rota? Você terá 120 segundos para confirmar antes da reversão automática.')) {
            return;
        }

        btnAplicar.disabled = true;
        try {
            const fd = new FormData(form);
            const res = await fetch(APLICAR_URL, { method: 'POST', body: fd });
            const data = await res.json();
            alert(data.message);
            if (data.success) verificarStatus();
        } catch (err) {
            alert('Erro ao comunicar com o servidor.');
        } finally {
            btnAplicar.disabled = false;
        }
    });

    btnConfirmar.addEventListener('click', async function () {
        btnConfirmar.disabled = true;
        try {
            const res = await fetch(CONFIRMAR_URL, { method: 'POST' });
            const data = await res.json();
            alert(data.message);
            if (data.success) {
                alertaPendente.classList.add('d-none');
                if (poll) { clearInterval(poll); poll = null; }
                window.location.href = '<?= url('/infraestrutura/rede/rotas') ?>';
            }
        } catch (err) {
            alert('Erro ao comunicar com o servidor.');
        } finally {
            btnConfirmar.disabled = false;
        }
    });

    verificarStatus();
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Infraestrutura - Nova Rota';

require __DIR__ . '/../layouts/main.php';
