<style>
.db-layout { display: flex; gap: 1rem; align-items: flex-start; }
.db-sidebar { width: 270px; flex-shrink: 0; border-radius: 12px; overflow: hidden; }
.db-content { flex: 1; min-width: 0; }
.db-tree { max-height: 75vh; overflow-y: auto; }
.db-tree-banco-titulo {
    display: flex; align-items: center; gap: 6px; padding: 8px 12px; cursor: pointer;
    font-size: 13px; color: #333; border-bottom: 1px solid #f1f3f5;
}
.db-tree-banco-titulo:hover { background: #f8f9fa; }
.db-tree-banco-titulo.ativo { background: #eef4ff; font-weight: 600; color: #0d6efd; }
.db-tree-banco-titulo .chevron { transition: transform .15s ease; font-size: 10px; color: #999; }
.db-tree-banco-titulo.aberto .chevron { transform: rotate(90deg); }
.db-tree-tabelas { padding-left: 8px; display: none; }
.db-tree-tabelas.aberto { display: block; }
.db-tree-tabela {
    display: flex; align-items: center; gap: 6px; padding: 5px 12px 5px 26px;
    font-size: 12.5px; color: #555; text-decoration: none; border-bottom: 1px solid #f8f9fa;
}
.db-tree-tabela:hover { background: #f8f9fa; color: #0d6efd; }
.db-tree-tabela.ativa { background: #eef4ff; color: #0d6efd; font-weight: 600; }
</style>

<div class="db-sidebar card border-0 shadow-sm">
    <div class="card-header bg-white small text-muted d-flex justify-content-between align-items-center">
        <span><i class="bi bi-hdd-stack"></i> <?= htmlspecialchars($conexao['nome']) ?></span>
        <a href="<?= url('/banco-dados/conexoes') ?>" class="text-muted" title="Trocar conexão"><i class="bi bi-arrow-left-right"></i></a>
    </div>
    <div class="db-tree" id="dbArvore">
        <div class="text-muted small p-3"><i class="bi bi-hourglass-split"></i> Carregando bancos...</div>
    </div>
</div>

<script>
(function () {
    const CONEXAO_ID = <?= (int)$conexao['id'] ?>;
    const BANCO_ATUAL = <?= json_encode($banco ?? '') ?>;
    const TABELA_ATUAL = <?= json_encode($tabela ?? '') ?>;
    const BANCOS_URL = <?= json_encode(url('/banco-dados/console/arvore/bancos')) ?>;
    const TABELAS_URL = <?= json_encode(url('/banco-dados/console/arvore')) ?>;
    const DADOS_URL = <?= json_encode(url('/banco-dados/console/dados')) ?>;

    const arvore = document.getElementById('dbArvore');

    function escapeHtml(texto) {
        const div = document.createElement('div');
        div.textContent = texto;
        return div.innerHTML;
    }

    function linkDados(banco, tabela) {
        return DADOS_URL + '?conexao=' + CONEXAO_ID + '&banco=' + encodeURIComponent(banco) + '&tabela=' + encodeURIComponent(tabela);
    }

    async function carregarTabelas(banco, container) {
        container.innerHTML = '<div class="text-muted small px-3 py-2"><i class="bi bi-hourglass-split"></i> Carregando...</div>';
        try {
            const res = await fetch(TABELAS_URL + '?conexao=' + CONEXAO_ID + '&banco=' + encodeURIComponent(banco));
            const dados = await res.json();
            if (!dados.success) {
                container.innerHTML = '<div class="text-danger small px-3 py-2">Erro ao listar tabelas.</div>';
                return;
            }
            if (!dados.tabelas.length) {
                container.innerHTML = '<div class="text-muted small px-3 py-2">Nenhuma tabela.</div>';
                return;
            }
            container.innerHTML = dados.tabelas.map(function (t) {
                const ativa = (banco === BANCO_ATUAL && t === TABELA_ATUAL) ? ' ativa' : '';
                return '<a class="db-tree-tabela' + ativa + '" href="' + linkDados(banco, t) + '"><i class="bi bi-table"></i> ' + escapeHtml(t) + '</a>';
            }).join('');
        } catch (e) {
            container.innerHTML = '<div class="text-danger small px-3 py-2">Erro de conexão.</div>';
        }
    }

    async function carregar() {
        try {
            const res = await fetch(BANCOS_URL + '?conexao=' + CONEXAO_ID);
            const dados = await res.json();

            if (!dados.success) {
                arvore.innerHTML = '<div class="text-danger small p-3">Erro ao listar bancos.</div>';
                return;
            }
            if (!dados.bancos.length) {
                arvore.innerHTML = '<div class="text-muted small p-3">Nenhum banco encontrado.</div>';
                return;
            }

            arvore.innerHTML = dados.bancos.map(function (b) {
                const ativo = b === BANCO_ATUAL ? ' ativo aberto' : '';
                const bEscapado = escapeHtml(b);
                return (
                    '<div>' +
                    '<div class="db-tree-banco-titulo' + ativo + '" data-banco="' + bEscapado + '">' +
                    '<i class="bi bi-chevron-right chevron"></i><i class="bi bi-database"></i> ' + bEscapado +
                    '</div>' +
                    '<div class="db-tree-tabelas' + (b === BANCO_ATUAL ? ' aberto' : '') + '" data-banco-tabelas="' + bEscapado + '"></div>' +
                    '</div>'
                );
            }).join('');

            document.querySelectorAll('.db-tree-banco-titulo').forEach(function (titulo) {
                titulo.addEventListener('click', function () {
                    const banco = titulo.dataset.banco;
                    const painel = document.querySelector('.db-tree-tabelas[data-banco-tabelas="' + banco + '"]');
                    const jaAberto = titulo.classList.toggle('aberto');
                    painel.classList.toggle('aberto', jaAberto);
                    if (jaAberto && !painel.dataset.carregado) {
                        painel.dataset.carregado = '1';
                        carregarTabelas(banco, painel);
                    }
                });
            });

            if (BANCO_ATUAL) {
                const painelAtual = document.querySelector('.db-tree-tabelas[data-banco-tabelas="' + BANCO_ATUAL + '"]');
                if (painelAtual) {
                    painelAtual.dataset.carregado = '1';
                    carregarTabelas(BANCO_ATUAL, painelAtual);
                }
            }
        } catch (e) {
            arvore.innerHTML = '<div class="text-danger small p-3">Erro de conexão.</div>';
        }
    }

    carregar();
})();
</script>
