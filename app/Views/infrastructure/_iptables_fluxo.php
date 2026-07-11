<?php
/**
 * Diagrama do fluxo de pacotes do iptables, simplificado em 3 caminhos.
 * Espera $porGrupo (mesmo array que iptables.php monta: [tabela][cadeia] =>
 * lista de regras) e $politicas (INPUT/FORWARD/OUTPUT).
 *
 * Clicar numa caixa rola até a tabela correspondente e filtra a busca
 * (Fase 4) pelo nome da cadeia.
 */
$contagem = fn(string $tabela, string $cadeia) => count($porGrupo[$tabela][$cadeia] ?? []);
?>
<style>
.fluxo-caixa {
    border: 1px solid #dee2e6;
    border-radius: 10px;
    padding: 10px 14px;
    background: #fff;
    cursor: pointer;
    text-align: center;
    min-width: 130px;
    transition: box-shadow .15s;
}
.fluxo-caixa:hover { box-shadow: 0 2px 10px rgba(0,0,0,.10); border-color: #adb5bd; }
.fluxo-caixa .fluxo-titulo { font-weight: 600; font-size: 13px; }
.fluxo-caixa .fluxo-conta { font-size: 11px; color: #6c757d; }
.fluxo-seta { color: #adb5bd; font-size: 20px; padding: 0 4px; }
.fluxo-linha { display: flex; align-items: center; flex-wrap: wrap; gap: 4px; margin-bottom: 10px; }
.fluxo-rotulo { font-size: 12px; color: #6c757d; min-width: 220px; }
</style>

<div class="card fw-card">
    <div class="card-header"><i class="bi bi-diagram-3 me-1"></i> Fluxo de pacotes</div>
    <div class="card-body">
        <div class="fluxo-linha">
            <div class="fluxo-rotulo"><i class="bi bi-arrow-down-right"></i> Chegando, destinado a este servidor</div>
            <div class="fluxo-caixa" data-tabela="nat" data-cadeia="PREROUTING">
                <div class="fluxo-titulo">PREROUTING</div>
                <div class="fluxo-conta"><?= $contagem('nat', 'PREROUTING') ?> regra(s) · nat</div>
            </div>
            <span class="fluxo-seta">→</span>
            <div class="fluxo-caixa" data-tabela="filter" data-cadeia="INPUT">
                <div class="fluxo-titulo">INPUT</div>
                <div class="fluxo-conta"><?= $contagem('filter', 'INPUT') ?> regra(s) · política <?= htmlspecialchars($politicas['INPUT']) ?></div>
            </div>
            <span class="fluxo-seta">→</span>
            <div class="fluxo-caixa" style="cursor:default">
                <div class="fluxo-titulo"><i class="bi bi-hdd-stack"></i> Aplicação local</div>
                <div class="fluxo-conta">este servidor</div>
            </div>
        </div>

        <div class="fluxo-linha">
            <div class="fluxo-rotulo"><i class="bi bi-arrow-left-right"></i> Roteado através deste servidor (NAT/gateway)</div>
            <div class="fluxo-caixa" data-tabela="nat" data-cadeia="PREROUTING">
                <div class="fluxo-titulo">PREROUTING</div>
                <div class="fluxo-conta"><?= $contagem('nat', 'PREROUTING') ?> regra(s) · nat</div>
            </div>
            <span class="fluxo-seta">→</span>
            <div class="fluxo-caixa" data-tabela="filter" data-cadeia="FORWARD">
                <div class="fluxo-titulo">FORWARD</div>
                <div class="fluxo-conta"><?= $contagem('filter', 'FORWARD') ?> regra(s) · política <?= htmlspecialchars($politicas['FORWARD']) ?></div>
            </div>
            <span class="fluxo-seta">→</span>
            <div class="fluxo-caixa" data-tabela="nat" data-cadeia="POSTROUTING">
                <div class="fluxo-titulo">POSTROUTING</div>
                <div class="fluxo-conta"><?= $contagem('nat', 'POSTROUTING') ?> regra(s) · nat</div>
            </div>
            <span class="fluxo-seta">→</span>
            <div class="fluxo-caixa" style="cursor:default">
                <div class="fluxo-titulo"><i class="bi bi-arrow-up-right"></i> Saindo</div>
            </div>
        </div>

        <div class="fluxo-linha mb-0">
            <div class="fluxo-rotulo"><i class="bi bi-arrow-up-right"></i> Gerado por este servidor</div>
            <div class="fluxo-caixa" style="cursor:default">
                <div class="fluxo-titulo"><i class="bi bi-hdd-stack"></i> Aplicação local</div>
                <div class="fluxo-conta">este servidor</div>
            </div>
            <span class="fluxo-seta">→</span>
            <div class="fluxo-caixa" data-tabela="filter" data-cadeia="OUTPUT">
                <div class="fluxo-titulo">OUTPUT</div>
                <div class="fluxo-conta"><?= $contagem('filter', 'OUTPUT') ?> regra(s) · política <?= htmlspecialchars($politicas['OUTPUT']) ?></div>
            </div>
            <span class="fluxo-seta">→</span>
            <div class="fluxo-caixa" data-tabela="nat" data-cadeia="POSTROUTING">
                <div class="fluxo-titulo">POSTROUTING</div>
                <div class="fluxo-conta"><?= $contagem('nat', 'POSTROUTING') ?> regra(s) · nat</div>
            </div>
            <span class="fluxo-seta">→</span>
            <div class="fluxo-caixa" style="cursor:default">
                <div class="fluxo-titulo"><i class="bi bi-arrow-up-right"></i> Saindo</div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    document.querySelectorAll('.fluxo-caixa[data-cadeia]').forEach(function (caixa) {
        caixa.addEventListener('click', function () {
            const tabela = caixa.dataset.tabela;
            const cadeia = caixa.dataset.cadeia;
            const card = document.getElementById('tabela-' + tabela);
            const busca = document.getElementById('busca-regras');

            if (busca) {
                busca.value = cadeia.toLowerCase();
                busca.dispatchEvent(new Event('input'));
            }
            if (card) {
                card.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
})();
</script>
