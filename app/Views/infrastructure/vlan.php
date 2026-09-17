<?php

use App\Components\Alert;
use App\Components\Badge;

ob_start();
?>

<div class="mb-4 d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h4 class="mb-1"><i class="bi bi-diagram-3-fill me-1"></i> VLANs</h4>
        <small class="text-muted">Infraestrutura</small>
    </div>
    <a href="<?= url('/infraestrutura/guia-dhcp-vlan') ?>" class="btn btn-outline-primary btn-sm" target="_blank">
        <i class="bi bi-book"></i> Como usar (DHCP + VLANs)
    </a>
</div>

<?= Alert::flash() ?>

<div class="alert alert-warning" style="max-width:960px">
    <strong><i class="bi bi-exclamation-triangle-fill"></i> Cuidado ao criar uma VLAN na interface que você está usando pra acessar este servidor.</strong>
    <p class="small mb-0 mt-1">
        Cada VLAN vira uma sub-interface de verdade (ex: <code>eth0.10</code>) na máquina. Se a interface física
        escolhida for a mesma pela qual você está conectado agora (SSH/painel), uma configuração errada pode
        derrubar seu próprio acesso. Por isso toda aplicação tem <strong>90 segundos de reversão automática</strong> --
        se perder o acesso, espere a contagem, que a configuração anterior volta sozinha.
    </p>
</div>

<div id="alerta-pendente" class="alert alert-warning d-none" style="max-width:960px">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <strong><i class="bi bi-hourglass-split"></i> Configuração aplicada, aguardando confirmação.</strong><br>
            Se não for confirmada, a configuração anterior volta sozinha em <strong id="segundos-restantes">--</strong> segundo(s).
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-success btn-sm" id="btn-confirmar"><i class="bi bi-check-lg"></i> Confirmar</button>
            <button class="btn btn-outline-danger btn-sm" id="btn-reverter-agora"><i class="bi bi-arrow-counterclockwise"></i> Reverter agora</button>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3" style="max-width:1100px">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <strong><i class="bi bi-diagram-3"></i> VLANs cadastradas</strong>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalVlan" id="btn-nova-vlan"
                    <?= empty($interfaces) ? 'disabled title="Nenhuma interface física disponível"' : '' ?>>
                <i class="bi bi-plus-lg"></i> Nova VLAN
            </button>
        </div>

        <?php if (empty($vlans)): ?>
            <p class="text-muted small mb-0">Nenhuma VLAN cadastrada ainda.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Interface</th>
                            <th>IP (gateway)</th>
                            <th>Rede</th>
                            <th>Descrição</th>
                            <th>Status</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vlans as $v): ?>
                            <tr>
                                <td><?= htmlspecialchars($v['nome']) ?></td>
                                <td class="font-monospace small">
                                    <?= htmlspecialchars($v['interface']) ?>
                                    <span class="text-muted">(tag <?= (int)$v['vlan_id'] ?>)</span>
                                </td>
                                <td class="font-monospace small"><?= htmlspecialchars($v['cidr']) ?></td>
                                <td class="font-monospace small text-muted"><?= htmlspecialchars($v['rede']) ?>/<?= (int)$v['prefixo'] ?></td>
                                <td class="text-muted small"><?= htmlspecialchars($v['descricao'] ?: '—') ?></td>
                                <td>
                                    <?= $v['aplicada'] ? Badge::make('<i class="bi bi-circle-fill" style="font-size:7px"></i> Aplicada', 'success') : Badge::make('Pendente de aplicar', 'secondary') ?>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-secondary btn-editar-vlan"
                                        data-id="<?= (int)$v['id'] ?>" data-nome="<?= htmlspecialchars($v['nome']) ?>"
                                        data-interface-pai="<?= htmlspecialchars($v['interface_pai']) ?>" data-vlan-id="<?= (int)$v['vlan_id'] ?>"
                                        data-ip="<?= htmlspecialchars($v['ip_endereco']) ?>" data-prefixo="<?= (int)$v['prefixo'] ?>"
                                        data-descricao="<?= htmlspecialchars($v['descricao'] ?? '') ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-excluir-vlan" data-id="<?= (int)$v['id'] ?>" data-nome="<?= htmlspecialchars($v['nome']) ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3" style="max-width:1100px">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <strong><i class="bi bi-cloud-upload"></i> Aplicar configuração</strong>
            <p class="text-muted small mb-0 mt-1">
                Cria/atualiza/remove as sub-interfaces de verdade (netplan) com tudo que está cadastrado acima.
                Valida a sintaxe antes de aplicar; se algo der errado, você tem 90s pra clicar "Reverter agora"
                (ou não confirmar -- reverte sozinho).
            </p>
        </div>
        <button type="button" class="btn btn-primary" id="btn-aplicar"><i class="bi bi-check2-circle"></i> Aplicar</button>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3" style="max-width:1100px">
    <div class="card-body">
        <strong><i class="bi bi-signpost-split"></i> Próximos passos depois de aplicar</strong>
        <ol class="small text-muted mt-2 mb-0 ps-3">
            <li>Vá em <a href="<?= url('/infraestrutura/dhcp') ?>">Infraestrutura &gt; Servidor DHCP</a> e marque cada interface de VLAN criada aqui, com uma rede (subnet) correspondente -- é isso que faz cada VLAN distribuir IP sozinha.</li>
            <li>Se as VLANs precisam conversar entre si ou sair pra internet, habilite em <a href="<?= url('/infraestrutura/iptables') ?>">Infraestrutura &gt; Firewall &gt; Templates</a> o encaminhamento de pacotes (<code>ip_forward</code>) e, se for o caso, um Masquerade de saída na interface de internet (WAN).</li>
        </ol>
    </div>
</div>

<!-- Modal Nova/Editar VLAN -->
<div class="modal fade" id="modalVlan" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tituloModalVlan"><i class="bi bi-diagram-3 me-2"></i>Nova VLAN</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="campo-vlan-id" value="0">
                <div class="mb-2">
                    <label class="form-label small">Nome</label>
                    <input type="text" class="form-control" id="campo-vlan-nome" placeholder="Ex: VLAN Financeiro">
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-7">
                        <label class="form-label small">Interface física (trunk)</label>
                        <select class="form-select" id="campo-vlan-interface-pai">
                            <?php foreach ($interfaces as $iface): ?>
                                <option value="<?= htmlspecialchars($iface) ?>"><?= htmlspecialchars($iface) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-5">
                        <label class="form-label small">ID da VLAN (tag)</label>
                        <div class="input-group">
                            <input type="number" class="form-control font-monospace" id="campo-vlan-id-tag" min="1" max="4094" value="<?= (int)$proximoIdSugerido ?>">
                            <button type="button" class="btn btn-outline-secondary" id="btn-sugerir-id" title="Sugerir próximo ID livre"><i class="bi bi-magic"></i></button>
                        </div>
                    </div>
                </div>
                <div class="row g-2 mb-1">
                    <div class="col-7">
                        <label class="form-label small">IP deste servidor na VLAN (gateway)</label>
                        <input type="text" class="form-control font-monospace" id="campo-vlan-ip" placeholder="192.168.10.1">
                    </div>
                    <div class="col-5">
                        <label class="form-label small">Prefixo (máscara)</label>
                        <div class="input-group">
                            <span class="input-group-text">/</span>
                            <input type="number" class="form-control font-monospace" id="campo-vlan-prefixo" min="1" max="32" value="24">
                        </div>
                    </div>
                </div>
                <div class="form-text mb-2" id="preview-rede">Rede resultante: --</div>
                <div class="mb-2">
                    <label class="form-label small">Descrição (opcional)</label>
                    <input type="text" class="form-control" id="campo-vlan-descricao" placeholder="Ex: Setor financeiro, 3º andar">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-salvar-vlan">Salvar</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const URLS = {
        proximoId: <?= json_encode(url('/infraestrutura/vlan/proximo-id')) ?>,
        salvar: <?= json_encode(url('/infraestrutura/vlan/salvar')) ?>,
        excluir: <?= json_encode(url('/infraestrutura/vlan/excluir')) ?>,
        aplicar: <?= json_encode(url('/infraestrutura/vlan/aplicar')) ?>,
        confirmar: <?= json_encode(url('/infraestrutura/vlan/confirmar')) ?>,
        reverter: <?= json_encode(url('/infraestrutura/vlan/reverter')) ?>,
        status: <?= json_encode(url('/infraestrutura/vlan/status')) ?>,
    };

    async function postJson(url, dados) {
        const body = new URLSearchParams(dados || {});
        const res = await fetch(url, { method: 'POST', body });
        return res.json();
    }

    function recarregar() { location.reload(); }

    // ── Cálculo de rede/broadcast, só pra preview (a validação de verdade é no servidor) ──
    function ipParaLong(ip) {
        const partes = ip.split('.').map(Number);
        if (partes.length !== 4 || partes.some(p => isNaN(p) || p < 0 || p > 255)) return null;
        return ((partes[0] << 24) | (partes[1] << 16) | (partes[2] << 8) | partes[3]) >>> 0;
    }

    function longParaIp(valor) {
        return [(valor >>> 24) & 255, (valor >>> 16) & 255, (valor >>> 8) & 255, valor & 255].join('.');
    }

    function atualizarPreviewRede() {
        const ip = document.getElementById('campo-vlan-ip').value.trim();
        const prefixo = parseInt(document.getElementById('campo-vlan-prefixo').value, 10);
        const previewEl = document.getElementById('preview-rede');
        const ipLong = ipParaLong(ip);

        if (ipLong === null || isNaN(prefixo) || prefixo < 1 || prefixo > 32) {
            previewEl.textContent = 'Rede resultante: --';
            return;
        }

        const mascara = prefixo === 32 ? 0xFFFFFFFF : (0xFFFFFFFF << (32 - prefixo)) >>> 0;
        const rede = (ipLong & mascara) >>> 0;
        const broadcast = (rede | (~mascara >>> 0)) >>> 0;
        previewEl.textContent = 'Rede resultante: ' + longParaIp(rede) + '/' + prefixo + ' (broadcast ' + longParaIp(broadcast) + ')';
    }

    ['campo-vlan-ip', 'campo-vlan-prefixo'].forEach(id => {
        document.getElementById(id).addEventListener('input', atualizarPreviewRede);
    });

    // ── Sugerir próximo ID livre ──────────────────────────────────────────
    async function sugerirId() {
        const iface = document.getElementById('campo-vlan-interface-pai').value;
        try {
            const res = await fetch(URLS.proximoId + '?interface=' + encodeURIComponent(iface));
            const data = await res.json();
            document.getElementById('campo-vlan-id-tag').value = data.id;
        } catch (e) { /* mantém o valor atual do campo */ }
    }
    document.getElementById('btn-sugerir-id')?.addEventListener('click', sugerirId);
    document.getElementById('campo-vlan-interface-pai')?.addEventListener('change', function () {
        if (document.getElementById('campo-vlan-id').value === '0') sugerirId();
    });

    // ── Nova / Editar VLAN ───────────────────────────────────────────────
    const modalVlanEl = document.getElementById('modalVlan');

    document.getElementById('btn-nova-vlan')?.addEventListener('click', function () {
        document.getElementById('tituloModalVlan').textContent = 'Nova VLAN';
        document.getElementById('campo-vlan-id').value = '0';
        document.getElementById('campo-vlan-nome').value = '';
        document.getElementById('campo-vlan-ip').value = '';
        document.getElementById('campo-vlan-prefixo').value = '24';
        document.getElementById('campo-vlan-descricao').value = '';
        atualizarPreviewRede();
        sugerirId();
    });

    document.querySelectorAll('.btn-editar-vlan').forEach(function (botao) {
        botao.addEventListener('click', function () {
            document.getElementById('tituloModalVlan').textContent = 'Editar VLAN';
            document.getElementById('campo-vlan-id').value = botao.dataset.id;
            document.getElementById('campo-vlan-nome').value = botao.dataset.nome;
            document.getElementById('campo-vlan-interface-pai').value = botao.dataset.interfacePai;
            document.getElementById('campo-vlan-id-tag').value = botao.dataset.vlanId;
            document.getElementById('campo-vlan-ip').value = botao.dataset.ip;
            document.getElementById('campo-vlan-prefixo').value = botao.dataset.prefixo;
            document.getElementById('campo-vlan-descricao').value = botao.dataset.descricao;
            atualizarPreviewRede();
            bootstrap.Modal.getOrCreateInstance(modalVlanEl).show();
        });
    });

    document.getElementById('btn-salvar-vlan').addEventListener('click', async function () {
        const dados = {
            id: document.getElementById('campo-vlan-id').value,
            nome: document.getElementById('campo-vlan-nome').value.trim(),
            interface_pai: document.getElementById('campo-vlan-interface-pai').value,
            vlan_id: document.getElementById('campo-vlan-id-tag').value,
            ip_endereco: document.getElementById('campo-vlan-ip').value.trim(),
            prefixo: document.getElementById('campo-vlan-prefixo').value,
            descricao: document.getElementById('campo-vlan-descricao').value.trim(),
        };
        const r = await postJson(URLS.salvar, dados);
        alert(r.message);
        if (r.success) recarregar();
    });

    document.querySelectorAll('.btn-excluir-vlan').forEach(function (botao) {
        botao.addEventListener('click', async function () {
            if (!confirm('Excluir a VLAN "' + botao.dataset.nome + '"?\n\nSó remove o cadastro -- clique em "Aplicar" depois pra remover a interface de verdade.')) return;
            const r = await postJson(URLS.excluir, { id: botao.dataset.id });
            alert(r.message);
            if (r.success) recarregar();
        });
    });

    // ── Aplicar / Confirmar / Reverter (janela de segurança) ────────────
    const alertaPendente = document.getElementById('alerta-pendente');
    const segundosEl = document.getElementById('segundos-restantes');
    let poll = null;

    async function verificarStatus() {
        try {
            const res = await fetch(URLS.status);
            const data = await res.json();
            if (data.pendente) {
                alertaPendente.classList.remove('d-none');
                segundosEl.textContent = data.segundos_restantes;
                if (!poll) poll = setInterval(verificarStatus, 3000);
                if (data.segundos_restantes <= 0) { clearInterval(poll); poll = null; alertaPendente.classList.add('d-none'); }
            } else {
                alertaPendente.classList.add('d-none');
                if (poll) { clearInterval(poll); poll = null; }
            }
        } catch (e) { /* falha pontual, tenta de novo no próximo poll */ }
    }

    const btnAplicar = document.getElementById('btn-aplicar');
    if (btnAplicar) {
        btnAplicar.addEventListener('click', async function () {
            if (!confirm('Aplicar esta configuração de VLANs?\n\nAs sub-interfaces são criadas/atualizadas/removidas agora. Você terá 90 segundos pra confirmar antes da reversão automática.\n\nCONFIRME que a interface física escolhida não é a única forma de acesso a este servidor.')) return;
            btnAplicar.disabled = true;
            const r = await postJson(URLS.aplicar);
            alert(r.message);
            btnAplicar.disabled = false;
            verificarStatus();
            if (r.success) setTimeout(recarregar, 1500);
        });
    }

    document.getElementById('btn-confirmar')?.addEventListener('click', async function () {
        const r = await postJson(URLS.confirmar);
        alert(r.message);
        if (r.success) { alertaPendente.classList.add('d-none'); if (poll) { clearInterval(poll); poll = null; } recarregar(); }
    });

    document.getElementById('btn-reverter-agora')?.addEventListener('click', async function () {
        if (!confirm('Reverter agora pra configuração anterior?')) return;
        const r = await postJson(URLS.reverter);
        alert(r.message);
        recarregar();
    });

    verificarStatus();
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Infraestrutura - VLANs';

require __DIR__ . '/../layouts/main.php';
