<?php
ob_start();

use App\Components\Alert;

/**
 * Bolha de mensagem (HTML) -- usada só na primeira renderização (SSR);
 * o polling de mensagens novas monta o equivalente em JS (montarBolha()
 * no script abaixo), mesma lógica de cor/alinhamento nos dois lugares.
 */
function renderizarBolha(array $m): string
{
    $minhas = $m['direcao'] === 'saida';
    $corBolha = $m['origem'] === 'bot' ? '#e0e7ff' : ($minhas ? '#dcf8c6' : '#ffffff');
    $alinhamento = $minhas ? 'flex-end' : 'flex-start';
    $rotulo = $m['origem'] === 'bot' ? 'Bot' : ($m['origem'] === 'cliente' ? '' : ($m['usuario_nome'] ?? 'Você'));

    return '<div class="d-flex mb-2" style="justify-content:' . $alinhamento . '" data-msg-id="' . (int)$m['id'] . '">'
        . '<div style="max-width:70%; background:' . $corBolha . '; border-radius:10px; padding:8px 12px; box-shadow:0 1px 2px rgba(0,0,0,.1);">'
        . ($rotulo ? '<div class="small text-muted mb-1">' . htmlspecialchars($rotulo) . '</div>' : '')
        . '<div style="white-space:pre-wrap">' . htmlspecialchars($m['conteudo']) . '</div>'
        . '<div class="text-muted text-end" style="font-size:10px">' . htmlspecialchars(data_br($m['criado_em'], 'H:i')) . '</div>'
        . '</div></div>';
}
?>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-1"><i class="bi bi-chat-dots me-1"></i> Cliente: <?= htmlspecialchars($atendimento['contato_nome'] ?: '(sem nome)') ?></h4>
        <small class="text-muted">
            <?= htmlspecialchars(telefone_br($atendimento['numero'])) ?>
            <?php if (!empty($atendimento['setor_nome'])): ?>
                &middot; Setor: <?= htmlspecialchars($atendimento['setor_nome']) ?>
            <?php endif; ?>
        </small>
    </div>
    <div>
        <a href="<?= url('/whatsapp/atendimentos') ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Atendimentos
        </a>
        <form method="post" action="<?= url('/whatsapp/atendimentos/encerrar') ?>" class="d-inline" onsubmit="return confirm('Encerrar este atendimento?');">
            <input type="hidden" name="id" value="<?= (int)$atendimento['id'] ?>">
            <button type="submit" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-check2-circle"></i> Encerrar
            </button>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body" id="listaMensagens" data-atendimento-id="<?= (int)$atendimento['id'] ?>"
         style="height:420px; overflow-y:auto; background:#f5f7fb;">
        <?php foreach ($mensagens as $m): ?>
            <?= renderizarBolha($m) ?>
        <?php endforeach; ?>
    </div>
    <div class="card-footer bg-white position-relative">
        <div id="listaAutocompleteRapidas" class="list-group position-absolute shadow-sm"
             style="display:none; bottom:100%; left:16px; right:16px; max-height:200px; overflow-y:auto; z-index:10;"></div>
        <form id="formResponder" class="d-flex gap-2">
            <input type="text" id="campoTexto" class="form-control" placeholder="Digite uma mensagem... (use /comando pra respostas rápidas)" autocomplete="off" required>
            <button type="submit" class="btn btn-primary text-nowrap">
                <i class="bi bi-send"></i> Enviar
            </button>
        </form>
    </div>
</div>

<script>
(function () {
    const lista = document.getElementById('listaMensagens');
    const form = document.getElementById('formResponder');
    const campo = document.getElementById('campoTexto');
    const atendimentoId = lista.dataset.atendimentoId;

    function ultimoIdRenderizado() {
        const bolhas = lista.querySelectorAll('[data-msg-id]');
        let maior = 0;
        bolhas.forEach(function (b) {
            maior = Math.max(maior, parseInt(b.dataset.msgId, 10) || 0);
        });
        return maior;
    }

    function corBolha(origem, direcao) {
        if (origem === 'bot') return '#e0e7ff';
        return direcao === 'saida' ? '#dcf8c6' : '#ffffff';
    }

    function montarBolha(m) {
        const minhas = m.direcao === 'saida';

        const div = document.createElement('div');
        div.className = 'd-flex mb-2';
        div.style.justifyContent = minhas ? 'flex-end' : 'flex-start';
        div.dataset.msgId = m.id;

        const rotulo = m.origem === 'bot' ? 'Bot' : (m.origem === 'cliente' ? '' : (m.usuario_nome || 'Você'));

        const bolha = document.createElement('div');
        bolha.style.maxWidth = '70%';
        bolha.style.background = corBolha(m.origem, m.direcao);
        bolha.style.borderRadius = '10px';
        bolha.style.padding = '8px 12px';
        bolha.style.boxShadow = '0 1px 2px rgba(0,0,0,.1)';

        if (rotulo) {
            const elRotulo = document.createElement('div');
            elRotulo.className = 'small text-muted mb-1';
            elRotulo.textContent = rotulo;
            bolha.appendChild(elRotulo);
        }

        const elTexto = document.createElement('div');
        elTexto.style.whiteSpace = 'pre-wrap';
        elTexto.textContent = m.conteudo;
        bolha.appendChild(elTexto);

        const elData = document.createElement('div');
        elData.className = 'text-muted text-end';
        elData.style.fontSize = '10px';
        elData.textContent = m.criado_em.substring(11, 16);
        bolha.appendChild(elData);

        div.appendChild(bolha);
        return div;
    }

    function buscarNovas() {
        fetch(<?= json_encode(url('/whatsapp/atendimentos/mensagens')) ?> + '?id=' + encodeURIComponent(atendimentoId) + '&desde=' + ultimoIdRenderizado())
            .then(function (r) { return r.json(); })
            .then(function (dados) {
                if (!dados.success) {
                    return;
                }
                let chegouAlgo = false;
                dados.mensagens.forEach(function (m) {
                    lista.appendChild(montarBolha(m));
                    chegouAlgo = true;
                });
                if (chegouAlgo) {
                    lista.scrollTop = lista.scrollHeight;
                }
            })
            .catch(function () {});
    }

    form.addEventListener('submit', function (evento) {
        evento.preventDefault();

        const texto = campo.value.trim();
        if (!texto) {
            return;
        }

        const botao = form.querySelector('button');
        botao.disabled = true;
        campo.disabled = true;

        const dados = new URLSearchParams();
        dados.set('id', atendimentoId);
        dados.set('texto', texto);

        fetch(<?= json_encode(url('/whatsapp/atendimentos/responder')) ?>, { method: 'POST', body: dados })
            .then(function (r) { return r.json(); })
            .then(function (resultado) {
                if (resultado.success) {
                    campo.value = '';
                    buscarNovas();
                } else {
                    alert(resultado.message || 'Falha ao enviar.');
                }
            })
            .catch(function () {
                alert('Erro ao comunicar com o servidor.');
            })
            .finally(function () {
                botao.disabled = false;
                campo.disabled = false;
                campo.focus();
            });
    });

    lista.scrollTop = lista.scrollHeight;
    setInterval(buscarNovas, 3000);

    // -- Mensagens rápidas: digitar "/comando" mostra sugestões, clicar
    // substitui o campo pelo texto completo da mensagem cadastrada.
    const listaAutocomplete = document.getElementById('listaAutocompleteRapidas');
    let mensagensRapidas = null;

    function carregarMensagensRapidas() {
        if (mensagensRapidas !== null) {
            return Promise.resolve(mensagensRapidas);
        }
        return fetch(<?= json_encode(url('/whatsapp/mensagens-rapidas/buscar')) ?>)
            .then(function (r) { return r.json(); })
            .then(function (dados) {
                mensagensRapidas = (dados.success && dados.mensagens) ? dados.mensagens : [];
                return mensagensRapidas;
            })
            .catch(function () { return []; });
    }

    function esconderAutocomplete() {
        listaAutocomplete.style.display = 'none';
        listaAutocomplete.innerHTML = '';
    }

    function mostrarAutocomplete(itens) {
        listaAutocomplete.innerHTML = '';

        if (!itens.length) {
            esconderAutocomplete();
            return;
        }

        itens.forEach(function (item) {
            const botao = document.createElement('button');
            botao.type = 'button';
            botao.className = 'list-group-item list-group-item-action py-1';

            const elComando = document.createElement('code');
            elComando.textContent = '/' + item.comando;
            botao.appendChild(elComando);

            const elTexto = document.createElement('span');
            elTexto.className = 'text-muted small ms-2';
            elTexto.textContent = item.mensagem;
            botao.appendChild(elTexto);

            botao.addEventListener('click', function () {
                campo.value = item.mensagem;
                esconderAutocomplete();
                campo.focus();
            });

            listaAutocomplete.appendChild(botao);
        });

        listaAutocomplete.style.display = '';
    }

    campo.addEventListener('input', function () {
        const valor = campo.value;

        if (!valor.startsWith('/')) {
            esconderAutocomplete();
            return;
        }

        const filtro = valor.slice(1).toLowerCase();

        carregarMensagensRapidas().then(function (itens) {
            const filtrados = itens.filter(function (item) {
                return item.comando.toLowerCase().startsWith(filtro);
            });
            mostrarAutocomplete(filtrados);
        });
    });

    document.addEventListener('click', function (evento) {
        if (evento.target !== campo && !listaAutocomplete.contains(evento.target)) {
            esconderAutocomplete();
        }
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'WhatsApp - Atendimento';

require __DIR__ . '/../layouts/main.php';
