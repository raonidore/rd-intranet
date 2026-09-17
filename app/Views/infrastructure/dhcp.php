<?php

use App\Components\Alert;
use App\Components\Badge;

ob_start();
?>

<div class="mb-4 d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h4 class="mb-1"><i class="bi bi-hdd-network-fill me-1"></i> Servidor DHCP</h4>
        <small class="text-muted">Infraestrutura</small>
    </div>
    <a href="<?= url('/infraestrutura/guia-dhcp-vlan') ?>" class="btn btn-outline-primary btn-sm" target="_blank">
        <i class="bi bi-book"></i> Como usar (DHCP + VLANs)
    </a>
</div>

<?= Alert::flash() ?>

<div class="alert alert-danger" style="max-width:960px">
    <strong><i class="bi bi-exclamation-triangle-fill"></i> Só pode haver UM servidor DHCP respondendo por rede.</strong>
    <p class="small mb-0 mt-1">
        Ligar este serviço numa rede que já tem outro DHCP (o roteador do cliente, o controller da UniFi, um Windows
        Server com o papel DHCP, etc.) faz os dois brigarem pra responder cada pedido -- resultado imprevisível:
        parte dos dispositivos pega IP de um servidor, parte do outro, alguns não pegam nada. <strong>Confirme que não
        existe outro DHCP ativo na mesma rede antes de ligar.</strong> Toda aplicação de configuração tem 90 segundos
        de reversão automática caso algo dê errado (ver "Aplicar" abaixo).
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

<div class="card border-0 shadow-sm mb-3" style="max-width:960px">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <strong><i class="bi bi-hdd-rack"></i> Status</strong>
            <div class="d-flex gap-2 align-items-center">
                <?php if (!$instalado): ?>
                    <?= Badge::make('Não instalado', 'secondary') ?>
                <?php elseif ($servicoAtivo): ?>
                    <?= Badge::make('<i class="bi bi-circle-fill" style="font-size:8px"></i> Rodando', 'success') ?>
                <?php else: ?>
                    <?= Badge::make('Instalado, parado', 'warning') ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$instalado): ?>
            <p class="text-muted small">Instala o <code>isc-dhcp-server</code> -- fica parado logo depois de instalar, até você configurar e aplicar.</p>
            <button type="button" class="btn btn-primary" id="btn-instalar"><i class="bi bi-download"></i> Instalar</button>
        <?php else: ?>
            <div class="d-flex gap-2">
                <?php if ($servicoAtivo): ?>
                    <button type="button" class="btn btn-outline-danger" id="btn-desligar"><i class="bi bi-stop-fill"></i> Desligar</button>
                <?php else: ?>
                    <button type="button" class="btn btn-outline-success" id="btn-ligar"><i class="bi bi-play-fill"></i> Ligar</button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($instalado): ?>

<div class="card border-0 shadow-sm mb-3" style="max-width:960px">
    <div class="card-body">
        <strong><i class="bi bi-gear"></i> Configuração geral</strong>
        <form id="form-config" class="mt-3">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Interfaces de rede</label>
                    <div class="d-flex flex-wrap gap-3 p-2 border rounded">
                        <?php $interfacesSelecionadas = array_filter(explode(' ', trim($config['interface'] ?? ''))); ?>
                        <?php foreach ($interfaces as $iface): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="interfaces[]" value="<?= htmlspecialchars($iface) ?>"
                                       id="iface-<?= htmlspecialchars($iface) ?>" <?= in_array($iface, $interfacesSelecionadas, true) ? 'checked' : '' ?>>
                                <label class="form-check-label font-monospace" for="iface-<?= htmlspecialchars($iface) ?>"><?= htmlspecialchars($iface) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-text">O DHCP escuta pedidos em cada interface marcada -- selecione a física e/ou cada VLAN (Infraestrutura &gt; VLANs) que precisa de concessão automática de IP.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Domínio (opcional)</label>
                    <input type="text" class="form-control" name="dominio" value="<?= htmlspecialchars($config['dominio'] ?? '') ?>" placeholder="empresa.local">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Concessão padrão (s)</label>
                    <input type="number" class="form-control" name="lease_padrao_segundos" value="<?= (int)($config['lease_padrao_segundos'] ?? 43200) ?>" min="60" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Concessão máxima (s)</label>
                    <input type="number" class="form-control" name="lease_maximo_segundos" value="<?= (int)($config['lease_maximo_segundos'] ?? 86400) ?>" min="60" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">DNS primário</label>
                    <input type="text" class="form-control" name="dns_primario" value="<?= htmlspecialchars($config['dns_primario'] ?? '') ?>" placeholder="8.8.8.8">
                </div>
                <div class="col-md-6">
                    <label class="form-label">DNS secundário</label>
                    <input type="text" class="form-control" name="dns_secundario" value="<?= htmlspecialchars($config['dns_secundario'] ?? '') ?>" placeholder="1.1.1.1">
                </div>
            </div>
            <button type="submit" class="btn btn-outline-primary btn-sm mt-3"><i class="bi bi-save"></i> Salvar configuração geral</button>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3" style="max-width:960px">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <strong><i class="bi bi-diagram-3"></i> Redes (subnets)</strong>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalSubnet" id="btn-nova-subnet">
                <i class="bi bi-plus-lg"></i> Nova rede
            </button>
        </div>

        <?php if (empty($subnets)): ?>
            <p class="text-muted small mb-0">Nenhuma rede cadastrada ainda -- cadastre pelo menos uma antes de aplicar.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Nome</th><th>Rede</th><th>Máscara</th><th>Faixa</th><th>Gateway</th><th class="text-end">Ações</th></tr></thead>
                    <tbody>
                        <?php foreach ($subnets as $s): ?>
                            <tr>
                                <td><?= htmlspecialchars($s['nome']) ?></td>
                                <td class="font-monospace small"><?= htmlspecialchars($s['rede']) ?></td>
                                <td class="font-monospace small"><?= htmlspecialchars($s['mascara']) ?></td>
                                <td class="font-monospace small"><?= htmlspecialchars($s['faixa_inicio']) ?> -- <?= htmlspecialchars($s['faixa_fim']) ?></td>
                                <td class="font-monospace small"><?= htmlspecialchars($s['gateway'] ?: '—') ?></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-secondary btn-editar-subnet"
                                        data-id="<?= (int)$s['id'] ?>" data-nome="<?= htmlspecialchars($s['nome']) ?>"
                                        data-rede="<?= htmlspecialchars($s['rede']) ?>" data-mascara="<?= htmlspecialchars($s['mascara']) ?>"
                                        data-inicio="<?= htmlspecialchars($s['faixa_inicio']) ?>" data-fim="<?= htmlspecialchars($s['faixa_fim']) ?>"
                                        data-gateway="<?= htmlspecialchars($s['gateway'] ?? '') ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-excluir-subnet" data-id="<?= (int)$s['id'] ?>" data-nome="<?= htmlspecialchars($s['nome']) ?>">
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

<div class="card border-0 shadow-sm mb-3" style="max-width:960px">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <strong><i class="bi bi-pin-map"></i> Reservas (IP fixo por MAC)</strong>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalReserva" <?= empty($subnets) ? 'disabled title="Cadastre uma rede primeiro"' : '' ?>>
                <i class="bi bi-plus-lg"></i> Nova reserva
            </button>
        </div>

        <?php if (empty($reservas)): ?>
            <p class="text-muted small mb-0">Nenhuma reserva cadastrada.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Descrição</th><th>MAC</th><th>IP</th><th>Rede</th><th class="text-end">Ações</th></tr></thead>
                    <tbody>
                        <?php foreach ($reservas as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['descricao']) ?></td>
                                <td class="font-monospace small"><?= htmlspecialchars($r['mac_address']) ?></td>
                                <td class="font-monospace small"><?= htmlspecialchars($r['ip']) ?></td>
                                <td class="text-muted small"><?= htmlspecialchars($r['subnet_nome']) ?></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-excluir-reserva" data-id="<?= (int)$r['id'] ?>" data-descricao="<?= htmlspecialchars($r['descricao']) ?>">
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

<div class="card border-0 shadow-sm mb-3" style="max-width:960px">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <strong><i class="bi bi-cloud-upload"></i> Aplicar configuração</strong>
            <p class="text-muted small mb-0 mt-1">
                Regrava o <code>dhcpd.conf</code> e reinicia o serviço com tudo que está cadastrado acima.
                Valida a sintaxe antes de aplicar; se o serviço não subir, reverte na hora. Se subir mas
                causar problema na rede, você tem 90s pra clicar "Reverter agora" (ou não confirmar --
                reverte sozinho).
            </p>
        </div>
        <button type="button" class="btn btn-primary" id="btn-aplicar"><i class="bi bi-check2-circle"></i> Aplicar</button>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3" style="max-width:960px">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <strong><i class="bi bi-list-check"></i> Concessões ativas agora</strong>
            <span class="text-muted small" id="leases-total"><?= count($leases) ?> ativa(s)</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>IP</th><th>MAC</th><th>Hostname</th><th>Expira em</th></tr></thead>
                <tbody id="corpo-leases">
                    <?php if (empty($leases)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">Nenhuma concessão ativa no momento.</td></tr>
                    <?php else: ?>
                        <?php foreach ($leases as $l): ?>
                            <tr>
                                <td class="font-monospace small"><?= htmlspecialchars($l['ip']) ?></td>
                                <td class="font-monospace small"><?= htmlspecialchars($l['mac']) ?></td>
                                <td class="small"><?= htmlspecialchars($l['hostname'] ?: '—') ?></td>
                                <td class="text-muted small"><?= htmlspecialchars($l['expira_em']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>

<!-- Modal Nova/Editar Subnet -->
<div class="modal fade" id="modalSubnet" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tituloModalSubnet"><i class="bi bi-diagram-3 me-2"></i>Nova rede</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="campo-subnet-id" value="0">
                <div class="mb-2">
                    <label class="form-label small">Nome</label>
                    <input type="text" class="form-control" id="campo-subnet-nome" placeholder="Ex: Rede local">
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small">Rede</label>
                        <input type="text" class="form-control font-monospace" id="campo-subnet-rede" placeholder="192.168.1.0">
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Máscara</label>
                        <input type="text" class="form-control font-monospace" id="campo-subnet-mascara" placeholder="255.255.255.0">
                    </div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small">Início da faixa</label>
                        <input type="text" class="form-control font-monospace" id="campo-subnet-inicio" placeholder="192.168.1.100">
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Fim da faixa</label>
                        <input type="text" class="form-control font-monospace" id="campo-subnet-fim" placeholder="192.168.1.200">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label small">Gateway (opcional)</label>
                    <input type="text" class="form-control font-monospace" id="campo-subnet-gateway" placeholder="192.168.1.1">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-salvar-subnet">Salvar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nova Reserva -->
<div class="modal fade" id="modalReserva" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pin-map me-2"></i>Nova reserva</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label small">Rede</label>
                    <select class="form-select" id="campo-reserva-subnet">
                        <?php foreach ($subnets as $s): ?>
                            <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['nome']) ?> (<?= htmlspecialchars($s['rede']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label small">Descrição</label>
                    <input type="text" class="form-control" id="campo-reserva-descricao" placeholder="Ex: Impressora da Recepção">
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label small">Endereço MAC</label>
                        <input type="text" class="form-control font-monospace" id="campo-reserva-mac" placeholder="aa:bb:cc:dd:ee:ff">
                    </div>
                    <div class="col-6">
                        <label class="form-label small">IP fixo</label>
                        <input type="text" class="form-control font-monospace" id="campo-reserva-ip" placeholder="192.168.1.50">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-salvar-reserva">Salvar</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const URLS = {
        instalar: <?= json_encode(url('/infraestrutura/dhcp/instalar')) ?>,
        config: <?= json_encode(url('/infraestrutura/dhcp/config')) ?>,
        subnet: <?= json_encode(url('/infraestrutura/dhcp/subnet')) ?>,
        subnetExcluir: <?= json_encode(url('/infraestrutura/dhcp/subnet/excluir')) ?>,
        reserva: <?= json_encode(url('/infraestrutura/dhcp/reserva')) ?>,
        reservaExcluir: <?= json_encode(url('/infraestrutura/dhcp/reserva/excluir')) ?>,
        aplicar: <?= json_encode(url('/infraestrutura/dhcp/aplicar')) ?>,
        confirmar: <?= json_encode(url('/infraestrutura/dhcp/confirmar')) ?>,
        reverter: <?= json_encode(url('/infraestrutura/dhcp/reverter')) ?>,
        status: <?= json_encode(url('/infraestrutura/dhcp/status')) ?>,
        aoVivo: <?= json_encode(url('/infraestrutura/dhcp/ao-vivo')) ?>,
        ligar: <?= json_encode(url('/infraestrutura/dhcp/ligar')) ?>,
        desligar: <?= json_encode(url('/infraestrutura/dhcp/desligar')) ?>,
    };

    async function postJson(url, dados) {
        const body = new URLSearchParams(dados || {});
        const res = await fetch(url, { method: 'POST', body });
        return res.json();
    }

    function recarregar() { location.reload(); }

    // ── Instalar / Ligar / Desligar ──────────────────────────────────────
    const btnInstalar = document.getElementById('btn-instalar');
    if (btnInstalar) {
        btnInstalar.addEventListener('click', async function () {
            btnInstalar.disabled = true;
            const r = await postJson(URLS.instalar);
            alert(r.message);
            if (r.success) recarregar();
            btnInstalar.disabled = false;
        });
    }

    const btnLigar = document.getElementById('btn-ligar');
    if (btnLigar) {
        btnLigar.addEventListener('click', async function () {
            if (!confirm('Ligar o servidor DHCP nesta interface?\n\nCONFIRME antes que não existe outro servidor DHCP já respondendo nesta mesma rede -- ligar os dois juntos causa conflito de IP.')) return;
            btnLigar.disabled = true;
            const r = await postJson(URLS.ligar);
            alert(r.message);
            if (r.success) recarregar();
            btnLigar.disabled = false;
        });
    }

    const btnDesligar = document.getElementById('btn-desligar');
    if (btnDesligar) {
        btnDesligar.addEventListener('click', async function () {
            if (!confirm('Desligar o servidor DHCP? Nenhum dispositivo novo vai receber IP até ligar de novo.')) return;
            btnDesligar.disabled = true;
            const r = await postJson(URLS.desligar);
            alert(r.message);
            if (r.success) recarregar();
            btnDesligar.disabled = false;
        });
    }

    // ── Configuração geral ───────────────────────────────────────────────
    const formConfig = document.getElementById('form-config');
    if (formConfig) {
        formConfig.addEventListener('submit', async function (e) {
            e.preventDefault();
            const r = await postJson(URLS.config, new FormData(formConfig));
            alert(r.message);
        });
    }

    // ── Subnets ──────────────────────────────────────────────────────────
    const modalSubnetEl = document.getElementById('modalSubnet');
    document.getElementById('btn-nova-subnet')?.addEventListener('click', function () {
        document.getElementById('tituloModalSubnet').textContent = 'Nova rede';
        ['id', 'nome', 'rede', 'mascara', 'inicio', 'fim', 'gateway'].forEach(campo => {
            document.getElementById('campo-subnet-' + campo).value = campo === 'id' ? '0' : '';
        });
    });

    document.querySelectorAll('.btn-editar-subnet').forEach(function (botao) {
        botao.addEventListener('click', function () {
            document.getElementById('tituloModalSubnet').textContent = 'Editar rede';
            document.getElementById('campo-subnet-id').value = botao.dataset.id;
            document.getElementById('campo-subnet-nome').value = botao.dataset.nome;
            document.getElementById('campo-subnet-rede').value = botao.dataset.rede;
            document.getElementById('campo-subnet-mascara').value = botao.dataset.mascara;
            document.getElementById('campo-subnet-inicio').value = botao.dataset.inicio;
            document.getElementById('campo-subnet-fim').value = botao.dataset.fim;
            document.getElementById('campo-subnet-gateway').value = botao.dataset.gateway;
            bootstrap.Modal.getOrCreateInstance(modalSubnetEl).show();
        });
    });

    document.getElementById('btn-salvar-subnet').addEventListener('click', async function () {
        const dados = {
            id: document.getElementById('campo-subnet-id').value,
            nome: document.getElementById('campo-subnet-nome').value.trim(),
            rede: document.getElementById('campo-subnet-rede').value.trim(),
            mascara: document.getElementById('campo-subnet-mascara').value.trim(),
            faixa_inicio: document.getElementById('campo-subnet-inicio').value.trim(),
            faixa_fim: document.getElementById('campo-subnet-fim').value.trim(),
            gateway: document.getElementById('campo-subnet-gateway').value.trim(),
        };
        const r = await postJson(URLS.subnet, dados);
        alert(r.message);
        if (r.success) recarregar();
    });

    document.querySelectorAll('.btn-excluir-subnet').forEach(function (botao) {
        botao.addEventListener('click', async function () {
            if (!confirm('Excluir a rede "' + botao.dataset.nome + '"? Reservas associadas também são removidas.')) return;
            const r = await postJson(URLS.subnetExcluir, { id: botao.dataset.id });
            alert(r.message);
            if (r.success) recarregar();
        });
    });

    // ── Reservas ─────────────────────────────────────────────────────────
    const btnSalvarReserva = document.getElementById('btn-salvar-reserva');
    if (btnSalvarReserva) {
        btnSalvarReserva.addEventListener('click', async function () {
            const dados = {
                subnet_id: document.getElementById('campo-reserva-subnet').value,
                descricao: document.getElementById('campo-reserva-descricao').value.trim(),
                mac_address: document.getElementById('campo-reserva-mac').value.trim(),
                ip: document.getElementById('campo-reserva-ip').value.trim(),
            };
            const r = await postJson(URLS.reserva, dados);
            alert(r.message);
            if (r.success) recarregar();
        });
    }

    document.querySelectorAll('.btn-excluir-reserva').forEach(function (botao) {
        botao.addEventListener('click', async function () {
            if (!confirm('Excluir a reserva "' + botao.dataset.descricao + '"?')) return;
            const r = await postJson(URLS.reservaExcluir, { id: botao.dataset.id });
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
            if (!confirm('Aplicar esta configuração de DHCP?\n\nO serviço reinicia com a config nova. Você terá 90 segundos pra confirmar antes da reversão automática.\n\nCONFIRME antes que não existe outro servidor DHCP já respondendo nesta rede.')) return;
            btnAplicar.disabled = true;
            const r = await postJson(URLS.aplicar);
            alert(r.message);
            btnAplicar.disabled = false;
            verificarStatus();
            if (r.success) atualizarLeases();
        });
    }

    document.getElementById('btn-confirmar')?.addEventListener('click', async function () {
        const r = await postJson(URLS.confirmar);
        alert(r.message);
        if (r.success) { alertaPendente.classList.add('d-none'); if (poll) { clearInterval(poll); poll = null; } }
    });

    document.getElementById('btn-reverter-agora')?.addEventListener('click', async function () {
        if (!confirm('Reverter agora pra configuração anterior?')) return;
        const r = await postJson(URLS.reverter);
        alert(r.message);
        recarregar();
    });

    verificarStatus();

    // ── Leases ao vivo ───────────────────────────────────────────────────
    // client-hostname vem do PRÓPRIO dispositivo cliente do DHCP -- texto
    // não confiável (qualquer coisa na rede pode mandar um hostname
    // malicioso no pedido). Nunca concatenar direto em innerHTML.
    function esc(str) {
        const d = document.createElement('div');
        d.textContent = (str == null || str === '') ? '' : String(str);
        return d.innerHTML;
    }

    async function atualizarLeases() {
        const corpo = document.getElementById('corpo-leases');
        if (!corpo) return;

        try {
            const res = await fetch(URLS.aoVivo);
            const data = await res.json();

            document.getElementById('leases-total').textContent = data.leases.length + ' ativa(s)';

            if (data.leases.length === 0) {
                corpo.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">Nenhuma concessão ativa no momento.</td></tr>';
                return;
            }

            corpo.innerHTML = data.leases.map(function (l) {
                return '<tr>'
                    + '<td class="font-monospace small">' + esc(l.ip) + '</td>'
                    + '<td class="font-monospace small">' + esc(l.mac) + '</td>'
                    + '<td class="small">' + (l.hostname ? esc(l.hostname) : '—') + '</td>'
                    + '<td class="text-muted small">' + esc(l.expira_em) + '</td>'
                    + '</tr>';
            }).join('');
        } catch (e) { /* falha pontual, mantém a última lista mostrada */ }
    }

    if (document.getElementById('corpo-leases')) {
        setInterval(atualizarLeases, 15000);
    }
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Infraestrutura - Servidor DHCP';

require __DIR__ . '/../layouts/main.php';
