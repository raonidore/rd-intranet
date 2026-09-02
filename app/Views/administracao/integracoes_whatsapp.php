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
<div style="max-width:720px">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="mb-0">Conexões via QR Code</h6>
    </div>
    <p class="text-muted small">Um cartão por número conectado -- cada um pode ser vinculado aos setores que atende, pra só mostrar essas opções no menu do bot desse número.</p>

    <?php foreach ($conexoes as $conexao): ?>
        <div class="card border-0 shadow-sm mb-3" data-conexao-id="<?= (int)$conexao['id'] ?>">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span><i class="bi bi-whatsapp me-1"></i> <?= htmlspecialchars($conexao['nome']) ?></span>
                <span class="badge-status-wpp badge text-bg-secondary">verificando...</span>
            </div>
            <div class="card-body text-center corpo-status-wpp">
                <p class="text-muted small d-flex align-items-center justify-content-center gap-2 texto-status-wpp">
                    <?= $conexao['instalado']
                        ? 'Bridge já instalado. Verificando status da conexão...'
                        : 'O bridge (processo que fala com o WhatsApp) ainda não foi instalado neste servidor.' ?>
                </p>
                <button type="button" class="btn btn-primary botao-instalar-wpp">
                    <i class="bi bi-cloud-download"></i> <?= $conexao['instalado'] ? 'Reinstalar bridge' : 'Instalar bridge' ?>
                </button>
                <div class="area-qrcode-wpp mt-3" style="display:none">
                    <img class="img-qrcode-wpp" src="" alt="QR Code do WhatsApp" style="max-width:260px; border:1px solid #e2e8f0; border-radius:8px;">
                    <p class="text-muted small mt-2">Abra o WhatsApp no celular &gt; Aparelhos conectados &gt; Conectar um aparelho, e escaneie o código acima.</p>
                </div>
                <div class="area-conectado-wpp mt-3" style="display:none">
                    <p class="mb-2">Conectado como <strong class="numero-conectado-wpp"></strong></p>
                    <form method="post" action="<?= url('/administracao/integracoes/whatsapp/desconectar') ?>" onsubmit="return confirm('Desconectar esta conexão do WhatsApp?');">
                        <input type="hidden" name="id" value="<?= (int)$conexao['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-x-circle"></i> Desconectar
                        </button>
                    </form>
                </div>

                <?php if (!empty($setores)): ?>
                    <hr>
                    <form method="post" action="<?= url('/administracao/integracoes/whatsapp/conexao/setores') ?>" class="text-start">
                        <input type="hidden" name="id" value="<?= (int)$conexao['id'] ?>">
                        <label class="form-label small text-muted">Setores visíveis nesse número</label>
                        <div class="row row-cols-2 g-1 mb-2">
                            <?php foreach ($setores as $setor): ?>
                                <div class="col">
                                    <div class="form-check">
                                        <input type="checkbox" name="setor_ids[]" value="<?= (int)$setor['id'] ?>" class="form-check-input" id="setorConexao<?= (int)$conexao['id'] ?>_<?= (int)$setor['id'] ?>" <?= in_array((int)$setor['id'], $conexao['setor_ids'], true) ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="setorConexao<?= (int)$conexao['id'] ?>_<?= (int)$setor['id'] ?>"><?= htmlspecialchars($setor['nome']) ?></label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-check-lg"></i> Salvar setores</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="post" action="<?= url('/administracao/integracoes/whatsapp/conexao') ?>" class="d-flex gap-2">
                <input type="text" name="nome" class="form-control form-control-sm" placeholder="Nome da conexão (ex: Comercial)" required>
                <button type="submit" class="btn btn-sm btn-outline-primary text-nowrap"><i class="bi bi-plus-lg"></i> Nova conexão</button>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    // "npm install" do bridge pode legitimamente levar quase um minuto
    // num servidor mais lento -- durante essa janela, o /status ainda
    // não responde (porta nem subiu), o que sem esse controle parecia
    // erro ("bridge não respondeu") mesmo estando tudo normal. Depois
    // da janela, troca só o texto (sem virar "erro" sozinho -- não dá
    // pra saber se travou ou só está demorando mais que o normal).
    const JANELA_INSTALACAO_MS = 70000;

    // Cada cartão é uma conexão independente -- mesma lógica de sempre,
    // só que instanciada uma vez por id em vez de uma vez só pra tela
    // inteira.
    document.querySelectorAll('[data-conexao-id]').forEach(function (cartao) {
        const id = cartao.dataset.conexaoId;
        const badge = cartao.querySelector('.badge-status-wpp');
        const texto = cartao.querySelector('.texto-status-wpp');
        const botaoInstalar = cartao.querySelector('.botao-instalar-wpp');
        const areaQrcode = cartao.querySelector('.area-qrcode-wpp');
        const imgQrcode = cartao.querySelector('.img-qrcode-wpp');
        const areaConectado = cartao.querySelector('.area-conectado-wpp');
        const numeroConectado = cartao.querySelector('.numero-conectado-wpp');

        let instalando = false;
        let inicioInstalacao = null;
        let timerQrcode = null;

        function pararQrcode() {
            if (timerQrcode) { clearInterval(timerQrcode); timerQrcode = null; }
            areaQrcode.style.display = 'none';
        }

        function buscarQrcode() {
            fetch(<?= json_encode(url('/administracao/integracoes/whatsapp/qrcode')) ?> + '?id=' + id)
                .then(r => r.json())
                .then(dados => {
                    if (dados.success && dados.qrcode) {
                        imgQrcode.src = dados.qrcode;
                        areaQrcode.style.display = '';
                    }
                })
                .catch(() => {});
        }

        function marcarInstalando(demorando) {
            badge.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Instalando...';
            badge.className = 'badge-status-wpp badge text-bg-info';
            texto.textContent = demorando
                ? 'Ainda instalando o bridge... está demorando mais que o normal, mas pode ser só um servidor mais lento. Continue aguardando.'
                : 'Instalando o bridge no servidor -- isso pode levar até 1 minuto. Esta página atualiza sozinha, não precisa recarregar.';
            pararQrcode();
            areaConectado.style.display = 'none';
        }

        function atualizarStatus() {
            fetch(<?= json_encode(url('/administracao/integracoes/whatsapp/status')) ?> + '?id=' + id)
                .then(r => r.json())
                .then(dados => {
                    if (!dados.success) {
                        if (instalando) {
                            const decorrido = Date.now() - inicioInstalacao;
                            marcarInstalando(decorrido > JANELA_INSTALACAO_MS);
                            return;
                        }

                        badge.textContent = 'bridge não respondeu';
                        badge.className = 'badge-status-wpp badge text-bg-secondary';
                        texto.textContent = 'O bridge não respondeu. Se ainda não foi instalado neste servidor, clique em "Instalar bridge" acima.';
                        pararQrcode();
                        areaConectado.style.display = 'none';
                        return;
                    }

                    // Qualquer resposta válida do bridge confirma que ele já
                    // subiu -- a partir daqui os status abaixo é que mandam.
                    instalando = false;

                    if (dados.status === 'conectado') {
                        badge.textContent = 'Conectado';
                        badge.className = 'badge-status-wpp badge text-bg-success';
                        texto.textContent = 'Bridge conectado e funcionando.';
                        pararQrcode();
                        areaConectado.style.display = '';
                        numeroConectado.textContent = dados.numero || '-';
                    } else if (dados.status === 'aguardando_qrcode') {
                        badge.textContent = 'Aguardando leitura do QR Code';
                        badge.className = 'badge-status-wpp badge text-bg-warning';
                        texto.textContent = 'Bridge instalado e rodando -- escaneie o QR Code abaixo para conectar.';
                        areaConectado.style.display = 'none';
                        if (!timerQrcode) {
                            buscarQrcode();
                            timerQrcode = setInterval(buscarQrcode, 2000);
                        }
                    } else {
                        badge.textContent = 'Desconectado';
                        badge.className = 'badge-status-wpp badge text-bg-secondary';
                        texto.textContent = 'Bridge instalado, mas desconectado do WhatsApp. Clique em "Reinstalar bridge" para gerar um novo QR Code.';
                        pararQrcode();
                        areaConectado.style.display = 'none';
                    }
                })
                .catch(() => {
                    if (instalando) {
                        marcarInstalando(Date.now() - inicioInstalacao > JANELA_INSTALACAO_MS);
                        return;
                    }

                    badge.textContent = 'bridge não respondeu';
                    badge.className = 'badge-status-wpp badge text-bg-secondary';
                });
        }

        botaoInstalar.addEventListener('click', function () {
            botaoInstalar.disabled = true;
            botaoInstalar.innerHTML = '<i class="bi bi-hourglass-split"></i> Instalando...';

            const dadosForm = new URLSearchParams();
            dadosForm.set('id', id);

            fetch(<?= json_encode(url('/administracao/integracoes/whatsapp/instalar')) ?>, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: dadosForm.toString(),
            })
                .then(r => r.json())
                .then(() => {
                    instalando = true;
                    inicioInstalacao = Date.now();
                    marcarInstalando(false);
                    atualizarStatus();
                })
                .catch(() => {
                    texto.textContent = 'Erro ao comunicar com o servidor -- tente novamente.';
                })
                .finally(() => {
                    botaoInstalar.disabled = false;
                    botaoInstalar.innerHTML = '<i class="bi bi-cloud-download"></i> Reinstalar bridge';
                });
        });

        atualizarStatus();
        setInterval(atualizarStatus, 3000);
    });
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
