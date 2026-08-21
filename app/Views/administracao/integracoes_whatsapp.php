<?php
ob_start();

use App\Components\Alert;
?>

<?= Alert::flash() ?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-whatsapp me-1"></i> WhatsApp</h4>
    <small class="text-muted d-block mb-1">
        <a href="<?= url('/administracao/integracoes') ?>"><i class="bi bi-arrow-left"></i> Integrações</a>
    </small>
    <small class="text-muted">Conexão do módulo de Atendimento com o WhatsApp, usada em WhatsApp &gt; Fila/Atendimentos.</small>
</div>

<div class="card border-0 shadow-sm mb-3" style="max-width:720px">
    <div class="card-body">
        <h6 class="mb-3">Tipo de integração</h6>
        <form method="post" action="<?= url('/administracao/integracoes/whatsapp/tipo') ?>">
            <div class="row g-2 mb-3">
                <div class="col-md-4">
                    <div class="form-check border rounded p-2 h-100">
                        <input type="radio" name="tipo" value="qrcode" class="form-check-input" id="tipoQrcode" <?= $tipoAtual === 'qrcode' ? 'checked' : '' ?>>
                        <label class="form-check-label d-block" for="tipoQrcode">
                            <strong>QR Code</strong>
                            <div class="text-muted small">Sem custo por mensagem, sem aprovação prévia. Não é a API oficial da Meta.</div>
                        </label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check border rounded p-2 h-100 bg-light">
                        <input type="radio" name="tipo" value="api_oficial" class="form-check-input" disabled>
                        <label class="form-check-label d-block text-muted" for="tipoApiOficial">
                            <strong>API Oficial (Meta)</strong>
                            <div class="small">Em breve.</div>
                        </label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check border rounded p-2 h-100 bg-light">
                        <input type="radio" name="tipo" value="twilio" class="form-check-input" disabled>
                        <label class="form-check-label d-block text-muted" for="tipoTwilio">
                            <strong>Twilio</strong>
                            <div class="small">Em breve.</div>
                        </label>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-check-lg"></i> Salvar tipo de integração
            </button>
        </form>
    </div>
</div>

<?php if ($tipoAtual === 'qrcode'): ?>
<div class="card border-0 shadow-sm" style="max-width:720px">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span>Conexão via QR Code</span>
        <span id="badgeStatusWpp" class="badge text-bg-secondary">verificando...</span>
    </div>
    <div class="card-body text-center" id="corpoStatusWpp">
        <p class="text-muted small">
            <?= $bridgeInstalado
                ? 'Bridge já instalado. Verificando status da conexão...'
                : 'O bridge (processo que fala com o WhatsApp) ainda não foi instalado neste servidor.' ?>
        </p>
        <button type="button" class="btn btn-primary" id="botaoInstalarWpp">
            <i class="bi bi-cloud-download"></i> <?= $bridgeInstalado ? 'Reinstalar bridge' : 'Instalar bridge' ?>
        </button>
        <div id="areaQrcodeWpp" class="mt-3" style="display:none">
            <img id="imgQrcodeWpp" src="" alt="QR Code do WhatsApp" style="max-width:260px; border:1px solid #e2e8f0; border-radius:8px;">
            <p class="text-muted small mt-2">Abra o WhatsApp no celular &gt; Aparelhos conectados &gt; Conectar um aparelho, e escaneie o código acima.</p>
        </div>
        <div id="areaConectadoWpp" class="mt-3" style="display:none">
            <p class="mb-2">Conectado como <strong id="numeroConectadoWpp"></strong></p>
            <form method="post" action="<?= url('/administracao/integracoes/whatsapp/desconectar') ?>" onsubmit="return confirm('Desconectar o WhatsApp deste servidor?');">
                <button type="submit" class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-x-circle"></i> Desconectar
                </button>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    const badge = document.getElementById('badgeStatusWpp');
    const botaoInstalar = document.getElementById('botaoInstalarWpp');
    const areaQrcode = document.getElementById('areaQrcodeWpp');
    const imgQrcode = document.getElementById('imgQrcodeWpp');
    const areaConectado = document.getElementById('areaConectadoWpp');
    const numeroConectado = document.getElementById('numeroConectadoWpp');

    let timerStatus = null;
    let timerQrcode = null;

    function pararQrcode() {
        if (timerQrcode) { clearInterval(timerQrcode); timerQrcode = null; }
        areaQrcode.style.display = 'none';
    }

    function buscarQrcode() {
        fetch(<?= json_encode(url('/administracao/integracoes/whatsapp/qrcode')) ?>)
            .then(r => r.json())
            .then(dados => {
                if (dados.success && dados.qrcode) {
                    imgQrcode.src = dados.qrcode;
                    areaQrcode.style.display = '';
                }
            })
            .catch(() => {});
    }

    function atualizarStatus() {
        fetch(<?= json_encode(url('/administracao/integracoes/whatsapp/status')) ?>)
            .then(r => r.json())
            .then(dados => {
                if (!dados.success) {
                    badge.textContent = 'bridge não respondeu';
                    badge.className = 'badge text-bg-secondary';
                    pararQrcode();
                    areaConectado.style.display = 'none';
                    return;
                }

                if (dados.status === 'conectado') {
                    badge.textContent = 'Conectado';
                    badge.className = 'badge text-bg-success';
                    pararQrcode();
                    areaConectado.style.display = '';
                    numeroConectado.textContent = dados.numero || '-';
                } else if (dados.status === 'aguardando_qrcode') {
                    badge.textContent = 'Aguardando leitura do QR Code';
                    badge.className = 'badge text-bg-warning';
                    areaConectado.style.display = 'none';
                    if (!timerQrcode) {
                        buscarQrcode();
                        timerQrcode = setInterval(buscarQrcode, 2000);
                    }
                } else {
                    badge.textContent = 'Desconectado';
                    badge.className = 'badge text-bg-secondary';
                    pararQrcode();
                    areaConectado.style.display = 'none';
                }
            })
            .catch(() => {
                badge.textContent = 'bridge não respondeu';
                badge.className = 'badge text-bg-secondary';
            });
    }

    botaoInstalar.addEventListener('click', function () {
        botaoInstalar.disabled = true;
        botaoInstalar.innerHTML = '<i class="bi bi-hourglass-split"></i> Instalando...';

        fetch(<?= json_encode(url('/administracao/integracoes/whatsapp/instalar')) ?>, { method: 'POST' })
            .then(r => r.json())
            .then(dados => {
                alert(dados.message || 'Instalação iniciada.');
            })
            .catch(() => alert('Erro ao comunicar com o servidor.'))
            .finally(() => {
                botaoInstalar.disabled = false;
                botaoInstalar.innerHTML = '<i class="bi bi-cloud-download"></i> Reinstalar bridge';
            });
    });

    atualizarStatus();
    timerStatus = setInterval(atualizarStatus, 3000);
})();
</script>
<?php endif; ?>

<?php
$conteudo = ob_get_clean();
$titulo = 'Integrações - WhatsApp';

require __DIR__ . '/../layouts/main.php';
