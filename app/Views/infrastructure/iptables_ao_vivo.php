<?php

ob_start();

$porTabela = ['filter' => [], 'nat' => []];
foreach ($estado['regras'] as $r) {
    $porTabela[$r['tabela']][] = $r;
}
?>

<style>
.fw-card { border: 0; border-radius: 14px; box-shadow: 0 4px 14px rgba(0,0,0,.06); margin-bottom: 1.25rem; }
.fw-card .card-header { background: #f8fafc; border-bottom: 1px solid #e9ecef; border-radius: 14px 14px 0 0; padding: 14px 20px; }
.regra-linha { font-family: 'SFMono-Regular', Consolas, monospace; font-size: 12px; }
.contador-pkts { font-family: 'SFMono-Regular', Consolas, monospace; font-weight: 600; }
.linha-bloqueando { background: rgba(220,53,69,.07); }
.badge-bloqueando {
    background: #dc3545; color: #fff; font-size: 10px; padding: 3px 8px; border-radius: 20px;
    animation: rd-pulse-badge 1.4s infinite;
}
@keyframes rd-pulse-badge { 0%, 100% { opacity: 1; } 50% { opacity: .45; } }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-eye me-1"></i> Firewall Ao Vivo</h4>
        <small class="text-muted">
            O que está rodando de verdade no kernel agora — inclui regras feitas fora desta tela.
            Pacotes/bytes atualizam a cada 3s.
        </small>
    </div>
    <a href="<?= url('/infraestrutura/iptables') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Voltar
    </a>
</div>

<?php if ($estado['ufw_ativo']): ?>
    <div class="alert alert-danger">
        <strong><i class="bi bi-exclamation-triangle"></i> O ufw está ativo neste servidor.</strong>
        O ufw gerencia suas próprias regras de iptables por baixo dos panos — misturar as duas ferramentas pode causar
        comportamento inesperado. Considere desativar o ufw (<code>ufw disable</code>) se for gerenciar o firewall por aqui.
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card fw-card p-3">
            <div class="text-muted small">Porta(s) SSH detectada(s)</div>
            <div class="fs-5 fw-bold"><?= htmlspecialchars(implode(', ', $estado['ssh_portas'])) ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card fw-card p-3">
            <div class="text-muted small">Encaminhamento de pacotes (ip_forward)</div>
            <div class="fs-5 fw-bold"><?= $estado['ip_forward'] ? 'Habilitado' : 'Desabilitado' ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card fw-card p-3">
            <div class="text-muted small">ufw</div>
            <div class="fs-5 fw-bold"><?= $estado['ufw_ativo'] ? 'Ativo' : 'Inativo/não instalado' ?></div>
        </div>
    </div>
</div>

<?php foreach (['filter' => 'Tabela filter', 'nat' => 'Tabela nat'] as $tabela => $titulo): ?>
    <div class="card fw-card">
        <div class="card-header"><?= $titulo ?></div>
        <div class="card-body p-0">
            <?php if (empty($porTabela[$tabela])): ?>
                <p class="text-muted text-center py-4 mb-0">Nenhuma regra ativa nesta tabela.</p>
            <?php else: ?>
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Regra (bruta)</th>
                            <th>Explicação</th>
                            <th class="text-end">Pacotes</th>
                            <th class="text-end">Bytes</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($porTabela[$tabela] as $r): ?>
                            <?php
                            $ehBloqueio = (bool)preg_match('/-j\s+(DROP|REJECT)\b/', $r['linha']);
                            $temHit = $ehBloqueio && (int)$r['pkts'] > 0;
                            ?>
                            <tr class="linha-contador <?= $temHit ? 'linha-bloqueando' : '' ?>"
                                data-tabela="<?= htmlspecialchars($r['tabela']) ?>"
                                data-cadeia="<?= htmlspecialchars($r['cadeia']) ?>"
                                data-indice="<?= (int)$r['indice'] ?>"
                                data-alvo-bloqueio="<?= $ehBloqueio ? '1' : '0' ?>">
                                <td class="regra-linha"><?= htmlspecialchars($r['linha']) ?></td>
                                <td class="small text-muted"><?= htmlspecialchars($r['explicacao']) ?></td>
                                <td class="text-end contador-pkts campo-pkts"><?= number_format($r['pkts'], 0, ',', '.') ?></td>
                                <td class="text-end contador-pkts campo-bytes"><?= number_format($r['bytes'], 0, ',', '.') ?></td>
                                <td class="campo-badge"><?= $temHit ? '<span class="badge-bloqueando"><i class="bi bi-shield-fill-exclamation"></i> bloqueando</span>' : '' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>

<script>
(function () {
    const CONTADORES_URL = <?= json_encode(url('/infraestrutura/iptables/ao-vivo/contadores')) ?>;

    async function atualizar() {
        try {
            const res = await fetch(CONTADORES_URL);
            const dados = await res.json();

            document.querySelectorAll('.linha-contador').forEach(function (tr) {
                const tabela = tr.dataset.tabela;
                const cadeia = tr.dataset.cadeia;
                const indice = parseInt(tr.dataset.indice, 10) - 1;

                const regra = dados?.[tabela]?.[cadeia]?.regras?.[indice];
                if (!regra) return;

                tr.querySelector('.campo-pkts').textContent = regra.pkts.toLocaleString('pt-BR');
                tr.querySelector('.campo-bytes').textContent = regra.bytes.toLocaleString('pt-BR');

                const bloqueando = tr.dataset.alvoBloqueio === '1' && regra.pkts > 0;
                tr.classList.toggle('linha-bloqueando', bloqueando);
                tr.querySelector('.campo-badge').innerHTML = bloqueando
                    ? '<span class="badge-bloqueando"><i class="bi bi-shield-fill-exclamation"></i> bloqueando</span>'
                    : '';
            });
        } catch (e) {
            console.warn('Falha ao atualizar contadores do firewall:', e);
        }
    }

    setInterval(atualizar, 3000);
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Infraestrutura - Firewall Ao Vivo';

require __DIR__ . '/../layouts/main.php';
