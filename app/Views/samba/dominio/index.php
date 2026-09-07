<?php

use App\Components\Alert;
use App\Components\Badge;

ob_start();

$ehDC = $status['is_dc'] ?? false;
?>

<style>
.dom-card { border: 0; border-radius: 14px; box-shadow: 0 4px 14px rgba(0,0,0,.06); margin-bottom: 1.25rem; }
.dom-card .card-header { background: #f8fafc; border-bottom: 1px solid #e9ecef; border-radius: 14px 14px 0 0; padding: 14px 20px; }
.dom-atalho { text-decoration: none; color: inherit; display: block; height: 100%; }
.dom-atalho:hover { color: inherit; }
.dom-atalho .card { transition: box-shadow .15s ease; }
.dom-atalho:hover .card { box-shadow: 0 6px 18px rgba(0,0,0,.10); }
</style>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-diagram-3 me-2"></i>Samba &gt; Domínio</h4>
        <small class="text-muted">Controlador de Domínio (Active Directory) -- provisionamento e gestão básica</small>
    </div>
</div>

<?php if ($ehDC): ?>

<div class="alert alert-success border-0 shadow-sm mb-4">
    <i class="bi bi-check-circle me-2"></i>
    Este servidor é um <strong>Controlador de Domínio</strong>.
</div>

<div class="card dom-card">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-server text-primary"></i>
        <strong>Visão geral</strong>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="text-muted small">Realm</div>
                <div class="fw-semibold font-monospace"><?= htmlspecialchars($status['realm'] ?: '-') ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Workgroup / Domínio</div>
                <div class="fw-semibold font-monospace"><?= htmlspecialchars($status['workgroup'] ?: '-') ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Hostname / FQDN</div>
                <div class="fw-semibold font-monospace"><?= htmlspecialchars($status['hostname'] ?: '-') ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Serviços</div>
                <div>
                    <?php foreach (($status['servicos'] ?? []) as $nome => $estado): ?>
                        <?= Badge::make($nome . ': ' . $estado, $estado === 'active' ? 'success' : 'secondary') ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <a href="<?= url('/samba/dominio/usuarios') ?>" class="dom-atalho">
            <div class="card dom-card mb-0 h-100">
                <div class="card-body text-center py-4">
                    <i class="bi bi-people fs-2 text-primary d-block mb-2"></i>
                    <strong>Usuários do domínio</strong>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="<?= url('/samba/dominio/grupos') ?>" class="dom-atalho">
            <div class="card dom-card mb-0 h-100">
                <div class="card-body text-center py-4">
                    <i class="bi bi-collection fs-2 text-primary d-block mb-2"></i>
                    <strong>Grupos do domínio</strong>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="<?= url('/samba/dominio/computadores') ?>" class="dom-atalho">
            <div class="card dom-card mb-0 h-100">
                <div class="card-body text-center py-4">
                    <i class="bi bi-pc-display fs-2 text-primary d-block mb-2"></i>
                    <strong>Computadores ingressados</strong>
                </div>
            </div>
        </a>
    </div>
</div>

<?php else: ?>

<div class="alert alert-info border-0 shadow-sm mb-4">
    <i class="bi bi-info-circle me-2"></i>
    Este servidor ainda é <strong>standalone</strong> (fileserver). Resolva o checklist abaixo antes de promover a Controlador de Domínio -- é uma operação demorada e, embora reversível, deve ser feita com cuidado.
</div>

<!-- Checklist: pacotes -->
<div class="card dom-card">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-box-seam text-primary"></i>
        <strong>1. Pacotes necessários</strong>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Ferramenta</th>
                    <th>Para que serve</th>
                    <th>Status</th>
                    <th class="text-end">Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($checklist['pacotes'] as $item): ?>
                    <tr data-chave="<?= htmlspecialchars($item['chave']) ?>">
                        <td class="font-monospace"><?= htmlspecialchars($item['nome']) ?></td>
                        <td class="small text-muted"><?= htmlspecialchars($item['descricao']) ?></td>
                        <td class="celula-status">
                            <?= $item['instalado'] ? Badge::make('Instalado', 'success') : Badge::make('Não instalado', 'warning') ?>
                        </td>
                        <td class="text-end celula-acao">
                            <?php if (!$item['instalado']): ?>
                                <button type="button" class="btn btn-sm btn-primary botao-instalar" data-chave="<?= htmlspecialchars($item['chave']) ?>">
                                    <i class="bi bi-download"></i> Instalar
                                </button>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Checklist: hostname -->
<div class="card dom-card">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-hdd text-primary"></i>
        <strong>2. Hostname</strong>
    </div>
    <div class="card-body">
        <p class="small text-muted">O <code>samba-tool</code> deriva o nome do Controlador de Domínio a partir do hostname curto da máquina. Confirme que está correto antes de provisionar -- trocar depois exige reprovisionar.</p>
        <form id="form-hostname" class="d-flex gap-2 align-items-end" style="max-width:420px">
            <div class="flex-grow-1">
                <label class="form-label small mb-1">Hostname atual</label>
                <input type="text" class="form-control form-control-sm font-monospace" name="hostname" value="<?= htmlspecialchars($checklist['hostname']) ?>">
            </div>
            <button type="submit" class="btn btn-sm btn-outline-primary">Salvar</button>
        </form>
        <div class="field-help text-muted small mt-1">FQDN atual: <span class="font-monospace"><?= htmlspecialchars($checklist['fqdn']) ?></span></div>
    </div>
</div>

<!-- Checklist: IP -->
<div class="card dom-card">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-hdd-network text-primary"></i>
        <strong>3. IP estático</strong>
    </div>
    <div class="card-body">
        <?php if ($checklist['ip_estatico_ok']): ?>
            <div class="text-success small"><i class="bi bi-check-circle me-1"></i>Pelo menos uma interface está em modo estático.</div>
        <?php else: ?>
            <div class="text-warning small mb-2"><i class="bi bi-exclamation-triangle me-1"></i>Nenhuma interface em modo estático foi encontrada. Um Controlador de Domínio precisa de IP fixo.</div>
        <?php endif; ?>
        <a href="<?= url('/infraestrutura/rede') ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-gear me-1"></i>Configurar rede
        </a>
    </div>
</div>

<!-- Provisionamento -->
<div class="card dom-card">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-rocket-takeoff text-primary"></i>
        <strong>4. Provisionar como Controlador de Domínio</strong>
    </div>
    <div class="card-body">
        <form id="form-provisionar">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small mb-1">Realm (domínio DNS)</label>
                    <input type="text" class="form-control form-control-sm font-monospace" name="realm" placeholder="EMPRESA.LOCAL" required>
                    <div class="field-help text-muted small mt-1">Formato de domínio DNS, em maiúsculas.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small mb-1">Workgroup / Domínio NetBIOS</label>
                    <input type="text" class="form-control form-control-sm font-monospace" name="workgroup" placeholder="EMPRESA" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small mb-1">Senha do Administrator</label>
                    <input type="password" class="form-control form-control-sm" name="senha" minlength="8" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small mb-1">Confirmar senha</label>
                    <input type="password" class="form-control form-control-sm" name="confirmacao" minlength="8" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small mb-1">Backend de DNS</label>
                    <input type="text" class="form-control form-control-sm" value="SAMBA_INTERNAL (único suportado nesta versão)" disabled>
                </div>
            </div>

            <div class="alert alert-warning mt-3 mb-3 small">
                <i class="bi bi-exclamation-triangle me-1"></i>
                Operação irreversível pela UI e demorada (minutos). Um backup do <code>smb.conf</code> atual é feito automaticamente antes de começar.
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-rocket-takeoff me-1"></i>Promover a Controlador de Domínio
            </button>
        </form>

        <div id="prov-progresso" class="mt-3 d-none">
            <div class="progress" style="height:22px">
                <div id="prov-progresso-barra" class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%">0%</div>
            </div>
            <div id="prov-progresso-texto" class="small text-muted mt-2"></div>
            <div id="prov-progresso-erro" class="alert alert-danger small mt-2 d-none"></div>
        </div>
    </div>
</div>

<script>
(function () {
    const INSTALAR_URL = <?= json_encode(url('/infraestrutura/dependencias/instalar')) ?>;
    const HOSTNAME_URL = <?= json_encode(url('/samba/dominio/hostname')) ?>;
    const PROVISIONAR_URL = <?= json_encode(url('/samba/dominio/provisionar')) ?>;
    const PROVISIONAR_STATUS_URL = <?= json_encode(url('/samba/dominio/provisionar/status')) ?>;

    document.querySelectorAll('.botao-instalar').forEach(function (botao) {
        botao.addEventListener('click', async function () {
            const chave = botao.dataset.chave;
            botao.disabled = true;
            botao.innerHTML = '<i class="bi bi-hourglass-split"></i> Instalando...';

            try {
                const res = await fetch(INSTALAR_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'chave=' + encodeURIComponent(chave)
                });
                const dados = await res.json();

                if (dados.success) {
                    const linha = document.querySelector('tr[data-chave="' + chave + '"]');
                    if (linha) {
                        linha.querySelector('.celula-status').innerHTML = '<span class="badge text-bg-success">Instalado</span>';
                        linha.querySelector('.celula-acao').innerHTML = '<span class="text-muted small">—</span>';
                    }
                } else {
                    alert(dados.message || 'Falha ao instalar.');
                    botao.disabled = false;
                    botao.innerHTML = '<i class="bi bi-download"></i> Instalar';
                }
            } catch (e) {
                alert('Erro ao comunicar com o servidor.');
                botao.disabled = false;
                botao.innerHTML = '<i class="bi bi-download"></i> Instalar';
            }
        });
    });

    document.getElementById('form-hostname').addEventListener('submit', async function (e) {
        e.preventDefault();
        const fd = new FormData(this);
        try {
            const res = await fetch(HOSTNAME_URL, { method: 'POST', body: fd });
            const dados = await res.json();
            alert(dados.message || (dados.success ? 'Hostname alterado.' : 'Falha ao alterar hostname.'));
            if (dados.success) location.reload();
        } catch (e) {
            alert('Erro ao comunicar com o servidor.');
        }
    });

    let provPollInterval = null;
    function provPararPoll() {
        if (provPollInterval) { clearInterval(provPollInterval); provPollInterval = null; }
    }

    function provAcompanhar(execucaoId) {
        var painel = document.getElementById('prov-progresso');
        var texto = document.getElementById('prov-progresso-texto');
        var barra = document.getElementById('prov-progresso-barra');
        var erroBox = document.getElementById('prov-progresso-erro');

        provPararPoll();
        painel.classList.remove('d-none');
        erroBox.classList.add('d-none');
        texto.classList.remove('d-none');
        barra.classList.add('progress-bar-animated');
        barra.style.width = '0%';
        barra.textContent = '0%';
        painel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

        provPollInterval = setInterval(async function () {
            try {
                var res = await fetch(PROVISIONAR_STATUS_URL + '?id=' + encodeURIComponent(execucaoId));
                var dados = await res.json();

                if (dados.status === 'rodando') {
                    var pct = dados.percentual || 0;
                    barra.style.width = pct + '%';
                    barra.textContent = pct + '%';
                    texto.textContent = dados.mensagem || ('Etapa ' + (dados.etapa || '?') + ' de ' + (dados.total_etapas || '?') + '...');
                    return;
                }

                if (dados.status === 'concluido') {
                    provPararPoll();
                    barra.style.width = '100%';
                    barra.textContent = '100%';
                    barra.classList.remove('progress-bar-animated');
                    texto.textContent = dados.mensagem || 'Provisionamento concluído.';
                    setTimeout(function () { location.reload(); }, 1500);
                    return;
                }

                if (dados.status === 'erro') {
                    provPararPoll();
                    barra.classList.remove('progress-bar-animated');
                    texto.classList.add('d-none');
                    erroBox.textContent = dados.mensagem || 'Falha desconhecida.';
                    erroBox.classList.remove('d-none');
                    return;
                }
                // "desconhecido" -- job ainda nao escreveu o primeiro status, continua tentando
            } catch (e) {
                // falha de rede pontual -- tenta de novo no proximo tick
            }
        }, 1500);
    }

    document.getElementById('form-provisionar').addEventListener('submit', async function (e) {
        e.preventDefault();

        const fd = new FormData(this);
        if (fd.get('senha') !== fd.get('confirmacao')) {
            alert('As senhas não coincidem.');
            return;
        }

        if (!confirm('Confirma o provisionamento deste servidor como Controlador de Domínio?\n\nRealm: ' + fd.get('realm') + '\nWorkgroup: ' + fd.get('workgroup') + '\n\nEssa operação é demorada e não pode ser desfeita pela interface.')) {
            return;
        }

        const botao = this.querySelector('button[type="submit"]');
        botao.disabled = true;

        try {
            const res = await fetch(PROVISIONAR_URL, { method: 'POST', body: fd });
            const dados = await res.json();

            if (dados.success) {
                provAcompanhar(dados.execucao_id);
            } else {
                alert(dados.message || 'Falha ao iniciar o provisionamento.');
                botao.disabled = false;
            }
        } catch (e) {
            alert('Erro ao comunicar com o servidor.');
            botao.disabled = false;
        }
    });
})();
</script>

<?php endif; ?>

<?php
$conteudo = ob_get_clean();
$titulo = 'Samba - Domínio';
require __DIR__ . '/../../layouts/main.php';
