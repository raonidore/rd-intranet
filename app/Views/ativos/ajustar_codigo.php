<?php

use App\Components\Alert;

ob_start();
?>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h5 class="mb-1"><i class="bi bi-upc-scan"></i> Ajustar Código de Patrimônio</h5>
        <small class="text-muted d-block mb-3">
            Busque um ativo e escolha o novo número sequencial -- a sigla da empresa, da unidade e do tipo sempre vêm
            do cadastro do ativo (não dá pra digitar uma unidade/tipo que não existe no sistema).
        </small>

        <div class="position-relative mb-3" style="max-width:480px">
            <label class="form-label">Buscar ativo</label>
            <input type="text" class="form-control" id="campoBuscaAtivo" placeholder="Nome, código ou nº de série" autocomplete="off">
            <div id="listaAtivosSugeridos" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index:10; max-height:280px; overflow-y:auto;"></div>
        </div>

        <div id="blocoAtivoSelecionado" class="d-none">
            <hr>
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Ativo</label>
                    <input type="text" class="form-control" id="campoNomeAtivo" disabled>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Unidade / Tipo</label>
                    <input type="text" class="form-control" id="campoUnidadeTipoAtivo" disabled>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Código atual</label>
                    <input type="text" class="form-control font-monospace" id="campoCodigoAtual" disabled>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Novo número</label>
                    <input type="number" min="1" class="form-control" id="campoNovoNumero">
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-primary w-100" id="botaoSalvarCodigo">
                        <i class="bi bi-check-lg"></i> Salvar
                    </button>
                </div>
            </div>
            <div class="mt-2">
                <small class="text-muted">Novo código ficará: <strong class="font-monospace" id="previaNovoCodigo">--</strong></small>
            </div>
            <div id="resultadoAjuste" class="mt-3"></div>
        </div>
    </div>
</div>

<script>
(function () {
    const campoBusca = document.getElementById('campoBuscaAtivo');
    const lista = document.getElementById('listaAtivosSugeridos');
    const bloco = document.getElementById('blocoAtivoSelecionado');
    const campoNome = document.getElementById('campoNomeAtivo');
    const campoUnidadeTipo = document.getElementById('campoUnidadeTipoAtivo');
    const campoCodigoAtual = document.getElementById('campoCodigoAtual');
    const campoNovoNumero = document.getElementById('campoNovoNumero');
    const previaCodigo = document.getElementById('previaNovoCodigo');
    const resultadoDiv = document.getElementById('resultadoAjuste');
    const botaoSalvar = document.getElementById('botaoSalvarCodigo');

    let ativoIdAtual = null;
    let prefixoCodigo = '';
    let digitosCodigo = 4;
    let timerBusca = null;

    function escapeHtml(texto) {
        const div = document.createElement('div');
        div.textContent = texto;
        return div.innerHTML;
    }

    function atualizarPrevia() {
        const numero = parseInt(campoNovoNumero.value, 10);
        if (!prefixoCodigo || !numero || numero < 1) {
            previaCodigo.textContent = '--';
            return;
        }
        previaCodigo.textContent = prefixoCodigo + String(numero).padStart(digitosCodigo, '0');
    }

    campoBusca.addEventListener('input', () => {
        bloco.classList.add('d-none');
        resultadoDiv.innerHTML = '';
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
                + 'data-id="' + a.id + '" data-nome="' + escapeHtml(a.nome) + '" data-codigo="' + escapeHtml(a.codigo) + '" '
                + 'data-unidade="' + escapeHtml(a.unidade) + '" data-tipo="' + escapeHtml(a.tipo) + '">'
                + '<span class="font-monospace small">' + escapeHtml(a.codigo) + '</span> ' + escapeHtml(a.nome)
                + ' <span class="text-muted small">(' + escapeHtml(a.tipo) + ')</span></button>'
            ).join('');
            lista.classList.remove('d-none');
        } catch (e) { /* rede instável -- só não sugere nada */ }
    }

    lista.addEventListener('click', async (ev) => {
        const botao = ev.target.closest('.opcao-ativo');
        if (!botao) return;

        ativoIdAtual = botao.dataset.id;
        campoBusca.value = botao.dataset.codigo + ' -- ' + botao.dataset.nome;
        campoNome.value = botao.dataset.nome;
        campoUnidadeTipo.value = botao.dataset.unidade + ' / ' + botao.dataset.tipo;
        campoCodigoAtual.value = botao.dataset.codigo;
        resultadoDiv.innerHTML = '';
        lista.classList.add('d-none');
        bloco.classList.remove('d-none');

        campoNovoNumero.value = '';
        previaCodigo.textContent = '--';

        try {
            const resp = await fetch(<?= json_encode(url('/ativos/proximo-codigo')) ?> + '?id=' + ativoIdAtual);
            const previsao = await resp.json();
            if (!previsao.success) {
                resultadoDiv.innerHTML = '<div class="alert alert-danger small mb-0">' + (previsao.message || 'Não foi possível calcular a sugestão.') + '</div>';
                return;
            }

            const ultimoTraco = previsao.codigo.lastIndexOf('-');
            prefixoCodigo = previsao.codigo.substring(0, ultimoTraco + 1);
            digitosCodigo = previsao.codigo.length - ultimoTraco - 1;
            campoNovoNumero.value = previsao.numero_atual || previsao.numero;
            atualizarPrevia();
        } catch (e) {
            resultadoDiv.innerHTML = '<div class="alert alert-danger small mb-0">Erro ao comunicar com o servidor.</div>';
        }
    });

    campoNovoNumero.addEventListener('input', atualizarPrevia);

    document.addEventListener('click', (ev) => {
        if (!lista.contains(ev.target) && ev.target !== campoBusca) {
            lista.classList.add('d-none');
        }
    });

    botaoSalvar.addEventListener('click', async () => {
        const numero = parseInt(campoNovoNumero.value, 10);
        if (!ativoIdAtual || !numero || numero < 1) {
            resultadoDiv.innerHTML = '<div class="alert alert-danger small mb-0">Selecione um ativo e informe um número válido.</div>';
            return;
        }

        if (!confirm('Trocar o código de "' + campoCodigoAtual.value + '" para "' + previaCodigo.textContent + '"?\n\nSe o equipamento já tem etiqueta impressa, ela precisa ser reimpressa e trocada -- o código antigo deixa de existir.')) return;

        botaoSalvar.disabled = true;
        resultadoDiv.innerHTML = '';

        const dados = new URLSearchParams();
        dados.set('id', ativoIdAtual);
        dados.set('numero', numero);

        try {
            const res = await fetch(<?= json_encode(url('/ativos/ajustar-codigo')) ?>, { method: 'POST', body: dados });
            const resultado = await res.json();

            if (resultado.success) {
                campoCodigoAtual.value = resultado.codigo;
                resultadoDiv.innerHTML = '<div class="alert alert-success small mb-0">Código atualizado para "' + resultado.codigo + '".</div>';
            } else {
                resultadoDiv.innerHTML = '<div class="alert alert-danger small mb-0">' + (resultado.message || 'Falha ao ajustar o código.') + '</div>';
            }
        } catch (e) {
            resultadoDiv.innerHTML = '<div class="alert alert-danger small mb-0">Erro ao comunicar com o servidor.</div>';
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
