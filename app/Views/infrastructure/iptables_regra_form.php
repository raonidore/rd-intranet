<?php

use App\Components\Alert;

ob_start();

$editando = $regra !== null;
$acao = $editando ? url('/infraestrutura/iptables/editar') : url('/infraestrutura/iptables/novo');

function sel($valorAtual, $valor): string
{
    return $valorAtual === $valor ? 'selected' : '';
}
?>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0">
            <i class="bi bi-hdd-network"></i>
            <?= $editando ? 'Editar regra de firewall' : 'Nova regra de firewall (manual)' ?>
        </h5>
    </div>

    <div class="card-body">
        <div class="alert alert-info small">
            <i class="bi bi-info-circle"></i>
            Prefere algo mais guiado? Use as <a href="<?= url('/infraestrutura/iptables/templates') ?>">regras prontas</a>
            (SSH, NAT, bloqueio de IP, etc). Este formulário é para regras personalizadas.
        </div>

        <form method="post" action="<?= $acao ?>" id="form-regra">
            <?php if ($editando): ?>
                <input type="hidden" name="id" value="<?= (int)$regra['id'] ?>">
            <?php endif; ?>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Nome</label>
                    <input type="text" name="nome" class="form-control" required
                           placeholder="Ex: Libera acesso ao painel web"
                           value="<?= htmlspecialchars($regra['nome'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Descrição (opcional)</label>
                    <input type="text" name="descricao" class="form-control"
                           value="<?= htmlspecialchars($regra['descricao'] ?? '') ?>">
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <label class="form-label">Tabela</label>
                    <select name="tabela" class="form-select">
                        <option value="filter" <?= sel($regra['tabela'] ?? 'filter', 'filter') ?>>filter (firewall)</option>
                        <option value="nat" <?= sel($regra['tabela'] ?? 'filter', 'nat') ?>>nat (NAT)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Cadeia</label>
                    <select name="cadeia" class="form-select">
                        <?php foreach (['INPUT', 'OUTPUT', 'FORWARD', 'PREROUTING', 'POSTROUTING'] as $c): ?>
                            <option value="<?= $c ?>" <?= sel($regra['cadeia'] ?? 'INPUT', $c) ?>><?= $c ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Ação</label>
                    <select name="acao" id="campo-acao" class="form-select">
                        <?php foreach (['ACCEPT' => 'ACCEPT (permitir)', 'DROP' => 'DROP (descartar)', 'REJECT' => 'REJECT (rejeitar)',
                                        'MASQUERADE' => 'MASQUERADE (NAT saída)', 'DNAT' => 'DNAT (redirecionar destino)',
                                        'SNAT' => 'SNAT (trocar origem)', 'LOG' => 'LOG (só registrar)',
                                        'NONE' => 'Sem ação (só rastrear)'] as $val => $label): ?>
                            <option value="<?= $val ?>" <?= sel($regra['acao'] ?? 'ACCEPT', $val) ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Protocolo</label>
                    <select name="protocolo" class="form-select">
                        <?php foreach (['tcp', 'udp', 'icmp', 'all'] as $p): ?>
                            <option value="<?= $p ?>" <?= sel($regra['protocolo'] ?? 'tcp', $p) ?>><?= strtoupper($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <label class="form-label">Porta destino</label>
                    <input type="text" name="porta_destino" class="form-control font-monospace" placeholder="22 ou 1000:2000"
                           value="<?= htmlspecialchars($regra['porta_destino'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Porta origem</label>
                    <input type="text" name="porta_origem" class="form-control font-monospace"
                           value="<?= htmlspecialchars($regra['porta_origem'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">IP/rede origem</label>
                    <input type="text" name="ip_origem" class="form-control font-monospace" placeholder="192.168.1.0/24"
                           value="<?= htmlspecialchars($regra['ip_origem'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">IP/rede destino</label>
                    <input type="text" name="ip_destino" class="form-control font-monospace"
                           value="<?= htmlspecialchars($regra['ip_destino'] ?? '') ?>">
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <label class="form-label">Interface de entrada</label>
                    <select name="interface_entrada" class="form-select">
                        <option value="">(qualquer)</option>
                        <?php foreach ($interfaces as $if): ?>
                            <option value="<?= htmlspecialchars($if) ?>" <?= sel($regra['interface_entrada'] ?? '', $if) ?>><?= htmlspecialchars($if) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Interface de saída</label>
                    <select name="interface_saida" class="form-select">
                        <option value="">(qualquer)</option>
                        <?php foreach ($interfaces as $if): ?>
                            <option value="<?= htmlspecialchars($if) ?>" <?= sel($regra['interface_saida'] ?? '', $if) ?>><?= htmlspecialchars($if) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3" id="campo-nat-destino">
                    <label class="form-label">Destino do NAT (IP:porta)</label>
                    <input type="text" name="nat_destino" class="form-control font-monospace" placeholder="192.168.1.50:8080"
                           value="<?= htmlspecialchars($regra['nat_destino'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Ordem</label>
                    <input type="number" name="ordem" class="form-control" value="<?= (int)($regra['ordem'] ?? 100) ?>">
                    <small class="text-muted">Menor = avaliada antes.</small>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-12">
                    <label class="form-label">Avançado (fragmento extra de iptables, opcional)</label>
                    <input type="text" name="extra" class="form-control font-monospace"
                           placeholder="-m conntrack --ctstate NEW -m recent --set"
                           value="<?= htmlspecialchars($regra['extra'] ?? '') ?>">
                    <small class="text-muted">Só letras, números, espaços e <code>- . _ , : /</code>. Inserido antes do <code>-j</code>.</small>
                </div>
            </div>

            <div class="form-check mb-2">
                <input type="checkbox" name="ativo" id="ativo" class="form-check-input"
                       <?= (!$editando || (int)($regra['ativo'] ?? 1) === 1) ? 'checked' : '' ?>>
                <label for="ativo" class="form-check-label">Ativa (entra no ruleset aplicado)</label>
            </div>

            <div class="form-check mb-3">
                <input type="checkbox" name="registrar_log" id="registrar_log" class="form-check-input"
                       <?= (int)($regra['registrar_log'] ?? 0) === 1 ? 'checked' : '' ?>>
                <label for="registrar_log" class="form-check-label">
                    Registrar no log do sistema (permite ver depois quais IPs bateram nesta regra, em "Ver IPs")
                </label>
            </div>

            <div class="alert alert-warning small mb-3">
                <i class="bi bi-shield-check"></i>
                Conexões já estabelecidas, a interface local (loopback) e a porta SSH atual são sempre liberadas automaticamente,
                independente desta regra — e qualquer alteração pode ser revertida em segundos após salvar.
            </div>

            <div class="d-flex justify-content-between mt-3">
                <a href="<?= url('/infraestrutura/iptables') ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Voltar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> Salvar e aplicar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const campoAcao = document.getElementById('campo-acao');
    const campoNatDestino = document.getElementById('campo-nat-destino');

    function atualizar() {
        const precisaNat = campoAcao.value === 'DNAT' || campoAcao.value === 'SNAT';
        campoNatDestino.style.display = precisaNat ? '' : 'none';
    }

    campoAcao.addEventListener('change', atualizar);
    atualizar();
})();

(function () {
    const AVALIAR_RISCO_URL = <?= json_encode(url('/infraestrutura/iptables/avaliar-risco')) ?>;
    const form = document.getElementById('form-regra');
    let confirmado = false;

    form.addEventListener('submit', async function (e) {
        if (confirmado) return;
        e.preventDefault();

        try {
            const res = await fetch(AVALIAR_RISCO_URL, { method: 'POST', body: new FormData(form) });
            const dados = await res.json();

            if (dados.risco && !confirm(dados.risco + '\n\nDeseja continuar mesmo assim?')) {
                return;
            }
        } catch (err) {
            // checagem falhou -- nao trava o salvamento, so nao avisa
        }

        confirmado = true;
        form.submit();
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = $editando ? 'Editar Regra de Firewall' : 'Nova Regra de Firewall';

require __DIR__ . '/../layouts/main.php';
