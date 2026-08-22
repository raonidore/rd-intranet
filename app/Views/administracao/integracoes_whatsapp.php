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
                    <label class="border rounded p-2 h-100 d-flex align-items-start gap-2 mb-0" for="tipoQrcode" style="cursor:pointer">
                        <input type="radio" name="tipo" value="qrcode" class="form-check-input mt-1 flex-shrink-0" id="tipoQrcode" <?= $tipoAtual === 'qrcode' ? 'checked' : '' ?>>
                        <span>
                            <strong class="d-block">QR Code</strong>
                            <span class="text-muted small">Sem custo por mensagem, sem aprovação prévia. Não é a API oficial da Meta.</span>
                        </span>
                    </label>
                </div>
                <div class="col-md-4">
                    <label class="border rounded p-2 h-100 d-flex align-items-start gap-2 mb-0" for="tipoApiOficial" style="cursor:pointer">
                        <input type="radio" name="tipo" value="api_oficial" class="form-check-input mt-1 flex-shrink-0" id="tipoApiOficial" <?= $tipoAtual === 'api_oficial' ? 'checked' : '' ?>>
                        <span>
                            <strong class="d-block">API Oficial (Meta)</strong>
                            <span class="text-muted small">Precisa de conta Meta Business verificada e número aprovado. <?= $metaConfigurado ? '<span class="text-success">Configurado.</span>' : '' ?></span>
                        </span>
                    </label>
                </div>
                <div class="col-md-4">
                    <label class="border rounded p-2 h-100 d-flex align-items-start gap-2 mb-0" for="tipoTwilio" style="cursor:pointer">
                        <input type="radio" name="tipo" value="twilio" class="form-check-input mt-1 flex-shrink-0" id="tipoTwilio" <?= $tipoAtual === 'twilio' ? 'checked' : '' ?>>
                        <span>
                            <strong class="d-block">Twilio</strong>
                            <span class="text-muted small">Custo por mensagem via Twilio, aprovação mais rápida. <?= $twilioConfigurado ? '<span class="text-success">Configurado.</span>' : '' ?></span>
                        </span>
                    </label>
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

<?php if ($tipoAtual === 'api_oficial'): ?>
<div class="card border-0 shadow-sm" style="max-width:720px">
    <div class="card-header bg-white">Configuração -- API Oficial (Meta)</div>
    <div class="card-body">
        <p class="text-muted small">
            Crie um app no <a href="https://developers.facebook.com/" target="_blank" rel="noopener">Meta for Developers</a>,
            adicione o produto WhatsApp e pegue o <strong>Phone Number ID</strong> e o <strong>Access Token</strong> por lá.
            No painel de configuração do webhook do app, cole a URL abaixo e o Verify Token que você escolher aqui.
        </p>
        <div class="mb-3">
            <label class="form-label small">URL do webhook (colar no painel da Meta)</label>
            <input type="text" class="form-control form-control-sm" value="<?= htmlspecialchars($webhookMetaUrl) ?>" readonly onclick="this.select()">
        </div>
        <form method="post" action="<?= url('/administracao/integracoes/whatsapp/meta') ?>">
            <div class="mb-2">
                <label class="form-label small">Phone Number ID</label>
                <input type="text" name="phone_number_id" class="form-control form-control-sm" value="<?= htmlspecialchars($metaPhoneNumberId) ?>" required>
            </div>
            <div class="mb-2">
                <label class="form-label small">Access Token</label>
                <input type="password" name="access_token" class="form-control form-control-sm" placeholder="<?= $metaConfigurado ? '•••••••• (deixe em branco para manter)' : 'token de acesso permanente' ?>">
            </div>
            <div class="mb-2">
                <label class="form-label small">Verify Token (você escolhe -- cole o mesmo valor no painel da Meta)</label>
                <input type="text" name="verify_token" class="form-control form-control-sm" value="<?= htmlspecialchars($metaVerifyToken) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label small">App Secret (opcional -- valida a assinatura do webhook)</label>
                <input type="password" name="app_secret" class="form-control form-control-sm" placeholder="opcional, mais seguro se preenchido">
            </div>
            <button type="submit" class="btn btn-sm btn-primary">
                <i class="bi bi-check-lg"></i> Salvar
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($tipoAtual === 'twilio'): ?>
<div class="card border-0 shadow-sm" style="max-width:720px">
    <div class="card-header bg-white">Configuração -- Twilio</div>
    <div class="card-body">
        <p class="text-muted small">
            No <a href="https://console.twilio.com/" target="_blank" rel="noopener">console da Twilio</a>, pegue o
            <strong>Account SID</strong>, o <strong>Auth Token</strong> e o número habilitado pra WhatsApp (sandbox ou
            número aprovado). Na configuração do WhatsApp Sender, cole a URL abaixo como webhook de mensagem recebida.
        </p>
        <div class="mb-3">
            <label class="form-label small">URL do webhook (colar no console da Twilio)</label>
            <input type="text" class="form-control form-control-sm" value="<?= htmlspecialchars($webhookTwilioUrl) ?>" readonly onclick="this.select()">
        </div>
        <form method="post" action="<?= url('/administracao/integracoes/whatsapp/twilio') ?>">
            <div class="mb-2">
                <label class="form-label small">Account SID</label>
                <input type="text" name="account_sid" class="form-control form-control-sm" value="<?= htmlspecialchars($twilioAccountSid) ?>" required>
            </div>
            <div class="mb-2">
                <label class="form-label small">Auth Token</label>
                <input type="password" name="auth_token" class="form-control form-control-sm" placeholder="<?= $twilioConfigurado ? '•••••••• (deixe em branco para manter)' : 'auth token' ?>">
            </div>
            <div class="mb-3">
                <label class="form-label small">Número do WhatsApp (Twilio)</label>
                <input type="text" name="numero" class="form-control form-control-sm" value="<?= htmlspecialchars($twilioNumero) ?>" placeholder="+14155238886" required>
            </div>
            <button type="submit" class="btn btn-sm btn-primary">
                <i class="bi bi-check-lg"></i> Salvar
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<?php
$conteudo = ob_get_clean();
$titulo = 'Integrações - WhatsApp';

require __DIR__ . '/../layouts/main.php';
