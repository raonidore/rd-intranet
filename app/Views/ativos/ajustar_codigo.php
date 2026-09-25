<?php

use App\Components\Alert;

ob_start();
?>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h5 class="mb-1"><i class="bi bi-upc-scan"></i> Ajustar Código de Patrimônio</h5>
        <small class="text-muted d-block mb-3">
            Busque um ativo e digite o novo código -- não deixa salvar se o código já pertencer a outro ativo.
        </small>

        <div class="position-relative mb-3" style="max-width:480px">
            <label class="form-label">Buscar ativo</label>
            <input type="text" class="form-control" id="campoBuscaAtivo" placeholder="Nome, código ou nº de série" autocomplete="off">
            <div id="listaAtivosSugeridos" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index:10; max-height:280px; overflow-y:auto;"></div>
        </div>

        <div id="blocoAtivoSelecionado" class="d-none">
            <hr>
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Ativo</label>
                    <input type="text" class="form-control" id="campoNomeAtivo" disabled>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Unidade</label>
                    <input type="text" class="form-control" id="campoUnidadeAtivo" disabled>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Código atual</label>
                    <input type="text" class="form-control font-monospace" id="campoCodigoAtual" disabled>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Novo código</label>
                    <input type="text" class="form-control font-monospace" id="campoNovoCodigo" placeholder="EP-AV-PC-0002">
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-primary w-100" id="botaoSalvarCodigo">
                        <i class="bi bi-check-lg"></i> Salvar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const campoBusca = document.getElementById('campoBuscaAtivo');
    const lista = document.getElementById('listaAtivosSugeridos');
    const bloco = document.getElementById('blocoAtivoSelecionado');
    const campoNome = document.getElementById('campoNomeAtivo');
    const campoUnidade = document.getElementById('campoUnidadeAtivo');
    const campoCodigoAtual = document.getElementById('campoCodigoAtual');
    const campoNovoCodigo = document.getElementById('campoNovoCodigo');
    const botaoSalvar = document.getElementById('botaoSalvarCodigo');

    let ativoIdAtual = null;
    let timerBusca = null;

    function escapeHtml(texto) {
        const div = document.createElement('div');
        div.textContent = texto;
        return div.innerHTML;
    }

    campoBusca.addEventListener('input', () => {
        bloco.classList.add('d-none');
        ativoIdAtual = null;
        clearTimeout(timerBusca);
        const termo = campoBusca.value.trim();
        if (termo.length < 2) {
            lista.classList.add('d-none');
            return;
        }
        timerBusca = setTimeout(() => buscarAtivos(termo), 300);
    });

    async function buscarAtivos(termo) {
        try {
            const resp = await fetch(<?= json_encode(url('/ativos/buscar')) ?> + '?q=' + encodeURIComponent(termo));
            const dados = await resp.json();
            if (!dados.success || dados.ativos.length === 0) {
                lista.classList.add('d-none');
                return;
            }
            lista.innerHTML = dados.ativos.map((a) =>
                '<button type="button" class="list-group-item list-group-item-action opcao-ativo" '
                + 'data-id="' + a.id + '" data-nome="' + escapeHtml(a.nome) + '" data-codigo="' + escapeHtml(a.codigo) + '" data-unidade="' + escapeHtml(a.unidade) + '">'
                + '<span class="font-monospace small">' + escapeHtml(a.codigo) + '</span> ' + escapeHtml(a.nome)
                + ' <span class="text-muted small">(' + escapeHtml(a.tipo) + ')</span></button>'
            ).join('');
            lista.classList.remove('d-none');
        } catch (e) { /* rede instável -- só não sugere nada */ }
    }

    lista.addEventListener('click', (ev) => {
        const botao = ev.target.closest('.opcao-ativo');
        if (!botao) return;

        ativoIdAtual = botao.dataset.id;
        campoBusca.value = botao.dataset.codigo + ' -- ' + botao.dataset.nome;
        campoNome.value = botao.dataset.nome;
        campoUnidade.value = botao.dataset.unidade;
        campoCodigoAtual.value = botao.dataset.codigo;
        campoNovoCodigo.value = '';
        bloco.classList.remove('d-none');
        lista.classList.add('d-none');
    });

    document.addEventListener('click', (ev) => {
        if (!lista.contains(ev.target) && ev.target !== campoBusca) {
            lista.classList.add('d-none');
        }
    });

    botaoSalvar.addEventListener('click', async () => {
        const novoCodigo = campoNovoCodigo.value.trim();
        if (!ativoIdAtual || !novoCodigo) {
            alert('Selecione um ativo e informe o novo código.');
            return;
        }

        if (!confirm('Trocar o código de "' + campoCodigoAtual.value + '" para "' + novoCodigo + '"?\n\nSe o equipamento já tem etiqueta impressa, ela precisa ser reimpressa e trocada -- o código antigo deixa de existir.')) return;

        botaoSalvar.disabled = true;

        const dados = new URLSearchParams();
        dados.set('id', ativoIdAtual);
        dados.set('codigo', novoCodigo);

        try {
            const res = await fetch(<?= json_encode(url('/ativos/ajustar-codigo')) ?>, { method: 'POST', body: dados });
            const resultado = await res.json();

            if (resultado.success) {
                campoCodigoAtual.value = resultado.codigo;
                campoNovoCodigo.value = '';
            }
            alert(resultado.success ? ('Código atualizado para "' + resultado.codigo + '".') : (resultado.message || 'Falha ao ajustar o código.'));
        } catch (e) {
            alert('Erro ao comunicar com o servidor.');
        } finally {
            botaoSalvar.disabled = false;
        }
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Ativos - Ajustar Código';

require __DIR__ . '/../layouts/main.php';
