<?php
ob_start();

use App\Components\Alert;
use App\Components\Badge;
?>

<?= Alert::flash() ?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-whatsapp me-1"></i> WhatsApp</h4>
    <small class="text-muted d-block mb-1">
        <a href="<?= url('/administracao/integracoes') ?>"><i class="bi bi-arrow-left"></i> Integrações</a>
    </small>
    <small class="text-muted">
        A conexão configurada aqui alimenta o módulo inteiro de Atendimento (Fila, Atendimentos, Chatbot, NPS) --
        veja o tutorial completo mais abaixo.
    </small>
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

<div class="card border-0 shadow-sm mt-3 wpp-doc-card" style="max-width:960px">
    <div class="card-body">
        <strong><i class="bi bi-signpost-split"></i> Qual tipo de conexão escolher?</strong>
        <p class="text-muted small mt-2 mb-3">Os três falam com o mesmo módulo de Atendimento por trás -- a diferença é só como as mensagens entram e saem do WhatsApp.</p>
        <div class="table-responsive">
            <table class="table table-sm wpp-tabela-tipos align-middle mb-0">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Custo</th>
                        <th>Aprovação prévia</th>
                        <th>Risco</th>
                        <th>Como conecta</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><i class="bi bi-qr-code text-success"></i> <strong>QR Code</strong></td>
                        <td class="text-muted small">Nenhum</td>
                        <td class="text-muted small">Nenhuma</td>
                        <td class="text-muted small">Não é a API oficial -- número pode ser banido pelo WhatsApp por uso fora dos termos (alto volume, automação agressiva)</td>
                        <td class="text-muted small">Escaneia o QR Code com o celular, igual ao WhatsApp Web</td>
                    </tr>
                    <tr>
                        <td><i class="bi bi-patch-check text-primary"></i> <strong>API Oficial (Meta)</strong></td>
                        <td class="text-muted small">Cobrado pela Meta por conversa, fora de uma cota gratuita mensal</td>
                        <td class="text-muted small">Conta Meta Business verificada + número aprovado</td>
                        <td class="text-muted small">Baixo -- canal oficial, sem risco de ban</td>
                        <td class="text-muted small">Phone Number ID + Access Token, webhook direto na Meta</td>
                    </tr>
                    <tr>
                        <td><i class="bi bi-patch-check text-info"></i> <strong>Twilio</strong></td>
                        <td class="text-muted small">Cobrado pela Twilio por mensagem</td>
                        <td class="text-muted small">Mais rápida -- sandbox pra testar na hora, número de produção depois</td>
                        <td class="text-muted small">Baixo -- canal oficial via parceiro Meta</td>
                        <td class="text-muted small">Account SID + Auth Token, webhook direto na Twilio</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="wpp-callout wpp-callout-warning mt-3">
            <i class="bi bi-exclamation-triangle"></i>
            <div>
                Comece pelo <strong>QR Code</strong> pra testar o módulo sem custo nem burocracia -- migrar pra Meta
                ou Twilio depois é só trocar o "Tipo de integração" acima e preencher as credenciais, o resto do
                sistema (Fila, Atendimentos, Chatbot, NPS) não muda em nada.
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-3 wpp-doc-card" style="max-width:960px">
    <div class="card-body">
        <strong><i class="bi bi-diagram-3"></i> O que acontece depois de conectado -- visão geral</strong>
        <p class="text-muted small mt-2 mb-3">O caminho de uma mensagem recebida, do primeiro "oi" até o atendimento encerrado.</p>
        <div class="table-responsive">
            <table class="table table-sm wpp-tabela-modulos align-middle mb-0">
                <thead>
                    <tr>
                        <th>Etapa</th>
                        <th>Onde configurar</th>
                        <th>O que faz</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><i class="bi bi-diagram-2 text-primary"></i> Chatbot (árvore de menus)</td>
                        <td class="text-muted small"><a href="<?= url('/whatsapp/chatbot') ?>">WhatsApp &gt; Chatbot</a></td>
                        <td class="text-muted small">Primeira coisa que o cliente vê -- menu numerado, cada opção pode dar uma resposta pronta, encaminhar pra um setor ou abrir chamado direto</td>
                    </tr>
                    <tr>
                        <td><i class="bi bi-diagram-3 text-primary"></i> Setores</td>
                        <td class="text-muted small"><a href="<?= url('/whatsapp/setores') ?>">WhatsApp &gt; Setores</a></td>
                        <td class="text-muted small">Departamentos que recebem atendimento encaminhado pelo bot -- cada conexão (número) escolhe quais setores aparecem no menu dela</td>
                    </tr>
                    <tr>
                        <td><i class="bi bi-hourglass-split text-warning"></i> Fila</td>
                        <td class="text-muted small"><a href="<?= url('/whatsapp/fila') ?>">WhatsApp &gt; Fila</a></td>
                        <td class="text-muted small">Atendimentos encaminhados pelo bot esperando um atendente humano assumir</td>
                    </tr>
                    <tr>
                        <td><i class="bi bi-chat-dots text-success"></i> Atendimentos</td>
                        <td class="text-muted small"><a href="<?= url('/whatsapp/atendimentos') ?>">WhatsApp &gt; Atendimentos</a></td>
                        <td class="text-muted small">Conversa em andamento com um atendente -- responder, anexar arquivo, transferir de setor/atendente, encerrar</td>
                    </tr>
                    <tr>
                        <td><i class="bi bi-star text-warning"></i> NPS (satisfação)</td>
                        <td class="text-muted small">Ativado por setor em <a href="<?= url('/whatsapp/setores') ?>">WhatsApp &gt; Setores</a></td>
                        <td class="text-muted small">Ao encerrar, pergunta satisfação (1-5) e se resolveu (sim/não) -- opcional, por setor</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-3 wpp-doc-card" style="max-width:960px">
    <div class="card-body">
        <strong><i class="bi bi-box-arrow-right"></i> Quem mais usa esta conexão</strong>
        <p class="text-muted small mt-2 mb-3">Além do módulo de Atendimento (o uso principal), dois outros módulos recorrem ao WhatsApp como canal alternativo:</p>
        <div class="table-responsive">
            <table class="table table-sm wpp-tabela-modulos align-middle mb-0">
                <thead>
                    <tr>
                        <th>Módulo</th>
                        <th>Quando usa o WhatsApp</th>
                        <th>Tipo</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><i class="bi bi-star text-warning"></i> Chamados -- avaliação de atendimento</td>
                        <td class="text-muted small">Chamado marcado "Resolvido" e o solicitante <strong>não</strong> tem e-mail cadastrado, só telefone</td>
                        <td><?= Badge::make('Alternativa ao e-mail', 'secondary') ?></td>
                    </tr>
                    <tr>
                        <td><i class="bi bi-people text-primary"></i> Notificações de Projetos</td>
                        <td class="text-muted small">Participante externo com telefone cadastrado é incluído numa tarefa</td>
                        <td><?= Badge::make('Complementar ao e-mail', 'secondary') ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-3 wpp-doc-card" style="max-width:960px">
    <div class="card-body">
        <strong><i class="bi bi-palette me-2"></i> Legenda do status (conexão via QR Code)</strong>
        <p class="text-muted small mt-2 mb-3">O badge no topo de cada cartão de conexão muda sozinho, atualizado a cada 3 segundos.</p>
        <div class="wpp-legenda">
            <div class="wpp-legenda-item">
                <span class="badge text-bg-secondary" tabindex="-1">verificando...</span>
                <span>Consultando o bridge pela primeira vez, assim que a página carrega</span>
            </div>
            <div class="wpp-legenda-item">
                <span class="badge text-bg-info" tabindex="-1"><span class="spinner-border spinner-border-sm me-1"></span>Instalando...</span>
                <span>Bridge sendo instalado no servidor -- pode levar até 1 minuto na primeira vez</span>
            </div>
            <div class="wpp-legenda-item">
                <span class="badge text-bg-warning" tabindex="-1">Aguardando leitura do QR Code</span>
                <span>Bridge no ar, esperando alguém escanear o código com o celular</span>
            </div>
            <div class="wpp-legenda-item">
                <span class="badge text-bg-success" tabindex="-1">Conectado</span>
                <span>Funcionando -- mostra o número conectado logo abaixo</span>
            </div>
            <div class="wpp-legenda-item">
                <span class="badge text-bg-secondary" tabindex="-1">Desconectado</span>
                <span>Bridge instalado, mas a sessão do WhatsApp caiu -- clique em "Reinstalar bridge" pra gerar um QR Code novo</span>
            </div>
            <div class="wpp-legenda-item">
                <span class="badge text-bg-secondary" tabindex="-1">bridge não respondeu</span>
                <span>Ainda não foi instalado neste servidor (ou o processo caiu) -- clique em "Instalar bridge"</span>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-3 wpp-doc-card" style="max-width:960px">
    <div class="card-body">
        <strong><i class="bi bi-book"></i> Documentação técnica</strong>
        <p class="text-muted small mt-2 mb-3">Detalhe de cada tipo de conexão e como o módulo funciona por baixo dos panos.</p>

        <div class="accordion wpp-accordion" id="acordeaoDocWpp">

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#docQrcode">
                        <i class="bi bi-qr-code text-success me-2"></i> QR Code -- bridge próprio (não oficial)
                    </button>
                </h2>
                <div id="docQrcode" class="accordion-collapse collapse" data-bs-parent="#acordeaoDocWpp">
                    <div class="accordion-body">
                        <p class="text-muted small">
                            O "bridge" é um processo Node.js à parte (rodando como serviço systemd) que fala com o
                            WhatsApp do mesmo jeito que o WhatsApp Web -- sessão pareada por QR Code, sem usar a API
                            oficial da Meta. Cada <strong>conexão</strong> cadastrada (um cartão na tela) é um bridge
                            independente, com sua própria porta e sessão -- dá pra ter vários números ao mesmo tempo,
                            cada um atendendo setores diferentes.
                        </p>
                        <ul class="small text-muted mb-0">
                            <li><strong>Instalar bridge</strong> dispara um script em segundo plano (<code>npm install</code> + subir o serviço) -- a tela não trava esperando, só fica consultando o status sozinha.</li>
                            <li><strong>Reinstalar bridge</strong> é o mesmo processo -- usado quando a sessão cai e precisa de um QR Code novo, ou pra atualizar o código do bridge sem perder a instalação.</li>
                            <li><strong>Desconectar</strong> encerra a sessão do WhatsApp de propósito (equivalente a remover o aparelho em "Aparelhos conectados" no app) -- o bridge continua instalado, só precisa ler um QR Code novo pra voltar.</li>
                            <li>Setores marcados no cartão da conexão definem quais opções aparecem no menu do chatbot <strong>pra quem manda mensagem naquele número</strong> -- números diferentes podem ter menus diferentes.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#docMeta">
                        <i class="bi bi-patch-check text-primary me-2"></i> API Oficial (Meta) -- configuração
                    </button>
                </h2>
                <div id="docMeta" class="accordion-collapse collapse" data-bs-parent="#acordeaoDocWpp">
                    <div class="accordion-body">
                        <ol class="small text-muted mb-0">
                            <li class="mb-2">Crie (ou use) um app em <a href="https://developers.facebook.com/" target="_blank" rel="noopener">Meta for Developers</a> e adicione o produto <strong>WhatsApp</strong>.</li>
                            <li class="mb-2">Copie o <strong>Phone Number ID</strong> e gere um <strong>Access Token</strong> (permanente, não o temporário de 24h que a Meta mostra por padrão).</li>
                            <li class="mb-2">Escolha um <strong>Verify Token</strong> (qualquer texto seu) e cole o mesmo valor aqui e no painel de configuração do webhook da Meta.</li>
                            <li class="mb-2">Cole a <strong>URL do webhook</strong> mostrada na tela (acima do formulário) no painel da Meta -- é assim que as mensagens recebidas chegam neste sistema.</li>
                            <li><strong>App Secret</strong> é opcional, mas recomendado -- com ele, o sistema confere a assinatura de cada webhook recebido, garantindo que veio mesmo da Meta.</li>
                        </ol>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#docTwilio">
                        <i class="bi bi-patch-check text-info me-2"></i> Twilio -- configuração
                    </button>
                </h2>
                <div id="docTwilio" class="accordion-collapse collapse" data-bs-parent="#acordeaoDocWpp">
                    <div class="accordion-body">
                        <ol class="small text-muted mb-0">
                            <li class="mb-2">No <a href="https://console.twilio.com/" target="_blank" rel="noopener">console da Twilio</a>, copie o <strong>Account SID</strong> e o <strong>Auth Token</strong> (na página inicial do console).</li>
                            <li class="mb-2">Ative o <strong>WhatsApp Sender</strong> -- pra testar sem custo, use o número de sandbox da própria Twilio; pra produção, precisa de um número aprovado.</li>
                            <li class="mb-2">Cole a <strong>URL do webhook</strong> mostrada na tela na configuração do Sender ("When a message comes in").</li>
                            <li>Salve aqui o Account SID, Auth Token e o número (formato internacional, ex: <code>+14155238886</code>).</li>
                        </ol>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#docChatbotWpp">
                        <i class="bi bi-diagram-2 text-primary me-2"></i> Chatbot -- árvore de menus
                    </button>
                </h2>
                <div id="docChatbotWpp" class="accordion-collapse collapse" data-bs-parent="#acordeaoDocWpp">
                    <div class="accordion-body">
                        <p class="text-muted small">
                            Configurado em <a href="<?= url('/whatsapp/chatbot') ?>">WhatsApp &gt; Chatbot</a>. A
                            mensagem de um nó tipo "menu" é só o texto de saudação -- a lista numerada das opções
                            filhas é <strong>gerada automaticamente</strong>, o admin nunca digita "1 - Suporte, 2 -
                            Financeiro" na mão. O cliente responde só o número, e o motor interpreta como a posição
                            entre as opções ativas daquele nó (por isso o texto mostrado e o número aceito nunca
                            desincronizam).
                        </p>
                        <ul class="small text-muted mb-0">
                            <li><strong>Menu</strong> -- abre mais um nível de opções.</li>
                            <li><strong>Resposta final</strong> -- responde um texto pronto e encerra ali (ex: horário de funcionamento).</li>
                            <li><strong>Encaminhar setor</strong> -- manda o atendimento pra fila de um departamento (WhatsApp &gt; Fila).</li>
                            <li><strong>Abrir chamado</strong> -- cria um chamado direto no módulo de Chamados, sem precisar de atendente humano no WhatsApp pra isso.</li>
                            <li>Depois de <strong>3 respostas inválidas</strong> seguidas, o bot desiste de interpretar número e segue um caminho de erro (configurável).</li>
                            <li>Expediente (horário de funcionamento) também é configurável ali -- fora do horário, o bot pode responder diferente em vez de fingir que tem gente disponível.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#docNpsWpp">
                        <i class="bi bi-star text-warning me-2"></i> NPS -- pesquisa de satisfação
                    </button>
                </h2>
                <div id="docNpsWpp" class="accordion-collapse collapse" data-bs-parent="#acordeaoDocWpp">
                    <div class="accordion-body">
                        <p class="text-muted small mb-0">
                            Ativado <strong>por setor</strong> (não é geral) em <a href="<?= url('/whatsapp/setores') ?>">WhatsApp &gt; Setores</a>.
                            Ao encerrar um atendimento de um setor com NPS ativo, o cliente recebe duas perguntas em
                            sequência: uma nota de satisfação com o atendente (1 a 5, com legenda explicando cada
                            nota) e se o problema foi resolvido (sim/não). A pergunta em si é configurável, mas a
                            legenda das notas é fixa de propósito -- muda o texto, mas o significado de "nota 3" tem
                            que continuar igual pra sempre, senão as estatísticas históricas perdem sentido.
                        </p>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#docTecnicoWpp">
                        <i class="bi bi-cpu me-2"></i> Como funciona por baixo dos panos
                    </button>
                </h2>
                <div id="docTecnicoWpp" class="accordion-collapse collapse" data-bs-parent="#acordeaoDocWpp">
                    <div class="accordion-body">
                        <p class="text-muted small mb-0">
                            Os três tipos convergem pro mesmo <strong>webhook interno</strong> e pro mesmo motor de
                            atendimento -- o que muda é só a "porta de entrada". No QR Code, cada conexão roda um
                            processo Node (biblioteca Baileys) como serviço systemd próprio, chamando de volta o
                            webhook deste sistema via <code>127.0.0.1</code> (loopback, nunca pelo host/domínio
                            público -- evita problema de certificado autoassinado entre o bridge e o painel, já que
                            os dois sempre rodam na mesma máquina). Na Meta e na Twilio, quem chama o webhook é o
                            próprio provedor, direto da internet -- por isso a URL mostrada na tela precisa ser
                            acessível de fora. A chave de API de cada conexão (cifrada, mesmo esquema da senha SMTP)
                            é quem identifica de qual número/credencial veio cada mensagem recebida.
                        </p>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<style>
.wpp-doc-card .card-body { padding: 1.25rem 1.5rem; }

.wpp-tabela-tipos th, .wpp-tabela-modulos th { font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: #6c757d; border-top: none; }
.wpp-tabela-tipos td, .wpp-tabela-modulos td { font-size: .85rem; }

.wpp-legenda { display: flex; flex-direction: column; gap: .65rem; }
.wpp-legenda-item { display: flex; align-items: center; gap: .75rem; }
.wpp-legenda-item > .badge { flex: 0 0 auto; }
.wpp-legenda-item > span:last-child { font-size: .85rem; color: #495057; }

.wpp-accordion .accordion-button {
    font-size: .88rem; font-weight: 600; background: #f8f9fa;
}
.wpp-accordion .accordion-button:not(.collapsed) {
    background: #eef4ff; color: #0d3b8c; box-shadow: none;
}
.wpp-accordion .accordion-button:focus { box-shadow: none; }
.wpp-accordion .accordion-item { border-color: #e9ecef; }

.wpp-callout {
    display: flex; gap: .6rem; padding: .75rem .9rem; border-radius: .5rem; font-size: .82rem;
}
.wpp-callout i { font-size: 1.1rem; flex: 0 0 auto; }
.wpp-callout-warning { background: #fff8e6; color: #664d03; border: 1px solid #ffe69c; }
.wpp-callout-warning i { color: #997404; }
</style>

<?php
$conteudo = ob_get_clean();
$titulo = 'Integrações - WhatsApp';

require __DIR__ . '/../layouts/main.php';
