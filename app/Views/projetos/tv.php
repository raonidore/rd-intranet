<?php
ob_start();
?>
<style>
    .tv-tag { font-family: monospace; font-size: 12px; letter-spacing: .08em; text-transform: uppercase; color: var(--tv-accent); margin-bottom: 6px; }
    .tv-title { font-size: 30px; font-weight: 600; }
    .tv-clock { font-family: monospace; font-size: 24px; color: var(--tv-muted); }
    .tv-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 28px; }
    .tv-phases { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 18px; margin-bottom: 30px; }
    .tv-phase > span:first-child { font-size: 15px; color: var(--tv-muted); display: block; margin-bottom: 8px; }
    .tv-track { height: 10px; border-radius: 100px; background: var(--tv-line); overflow: hidden; }
    .tv-fill { height: 100%; border-radius: 100px; background: var(--tv-accent); }
    .tv-pct { font-family: monospace; font-size: 16px; font-weight: 600; margin-top: 6px; display: block; }
    .tv-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; margin-bottom: 30px; }
    .tv-stat { background: rgba(255,255,255,.03); border: 1px solid var(--tv-line); border-radius: 14px; padding: 20px; text-align: center; }
    .tv-stat.danger { border-color: var(--tv-danger); background: rgba(255,110,98,.08); }
    .tv-num { font-size: 46px; font-weight: 700; line-height: 1; }
    .tv-stat.danger .tv-num { color: var(--tv-danger); }
    .tv-lbl { font-size: 14px; color: var(--tv-muted); margin-top: 8px; }
    .tv-feed { display: flex; flex-direction: column; gap: 10px; font-size: 16px; color: var(--tv-muted); border-top: 1px solid var(--tv-line); padding-top: 20px; }
    .tv-empty { text-align: center; color: var(--tv-muted); padding: 100px 0; font-size: 20px; }
</style>

<div id="tvConteudo">
    <div class="tv-empty">Carregando painel da área <?= htmlspecialchars($area['nome'] ?? '') ?>...</div>
</div>

<script>
(function () {
    const TOKEN = <?= json_encode($token) ?>;
    const URL_DADOS = <?= json_encode(url('/projetos/tv/dados')) ?> + '?token=' + encodeURIComponent(TOKEN);
    const container = document.getElementById('tvConteudo');

    let projetos = [];
    let indiceAtual = 0;
    const ROTACAO_MS = 15000;
    const REFRESH_MS = 60000;

    function escapeHtml(texto) {
        const div = document.createElement('div');
        div.textContent = texto == null ? '' : String(texto);
        return div.innerHTML;
    }

    function renderizar() {
        if (!projetos.length) {
            container.innerHTML = '<div class="tv-empty">Nenhum projeto ativo nessa área no momento.</div>';
            return;
        }

        const p = projetos[indiceAtual % projetos.length];
        const agora = new Date();

        let fasesHtml = (p.fases || []).map(function (f) {
            return '<div class="tv-phase"><span>' + escapeHtml(f.nome) + '</span>'
                + '<div class="tv-track"><div class="tv-fill" style="width:' + f.progresso + '%"></div></div>'
                + '<span class="tv-pct">' + f.progresso + '%</span></div>';
        }).join('');

        let feedHtml = (p.timeline || []).map(function (item) {
            const quem = item.usuario_nome || item.participante_nome || 'Sistema';
            return '<div>' + (item.tipo === 'sistema' ? '⚙️ ' : '💬 ') + escapeHtml(quem) + ': ' + escapeHtml(item.conteudo) + '</div>';
        }).join('') || '<div>Sem atividade recente.</div>';

        container.innerHTML =
            '<div class="tv-top">'
            + '<div><div class="tv-tag"><?= htmlspecialchars($area['nome'] ?? '') ?> · ' + escapeHtml(p.cliente || 'Projeto') + '</div>'
            + '<div class="tv-title">' + escapeHtml(p.titulo) + '</div></div>'
            + '<div class="tv-clock">' + agora.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) + '</div>'
            + '</div>'
            + (fasesHtml ? '<div class="tv-phases">' + fasesHtml + '</div>' : '')
            + '<div class="tv-stats">'
            + '<div class="tv-stat"><div class="tv-num">' + p.resumo.total + '</div><div class="tv-lbl">Tarefas ativas</div></div>'
            + '<div class="tv-stat ' + (p.resumo.atrasadas > 0 ? 'danger' : '') + '"><div class="tv-num">' + p.resumo.atrasadas + '</div><div class="tv-lbl">Atrasadas</div></div>'
            + '<div class="tv-stat"><div class="tv-num">' + p.resumo.aguardando_terceiro + '</div><div class="tv-lbl">Aguardando terceiro</div></div>'
            + '</div>'
            + '<div class="tv-feed">' + feedHtml + '</div>'
            + (projetos.length > 1 ? '<p class="text-center mt-4" style="color:var(--tv-muted);font-size:13px">Projeto ' + (indiceAtual % projetos.length + 1) + ' de ' + projetos.length + '</p>' : '');
    }

    async function buscarDados() {
        try {
            const res = await fetch(URL_DADOS);
            const dados = await res.json();
            if (dados.success) {
                projetos = dados.projetos || [];
                renderizar();
            }
        } catch (e) { /* rede instável -- mantém o que já estava na tela */ }
    }

    buscarDados();
    setInterval(buscarDados, REFRESH_MS);
    setInterval(function () {
        indiceAtual++;
        renderizar();
    }, ROTACAO_MS);
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Painel de Projetos -- ' . ($area['nome'] ?? '');

require __DIR__ . '/../layouts/tv.php';
