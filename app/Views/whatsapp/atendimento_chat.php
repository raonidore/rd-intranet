<?php
ob_start();

use App\Components\Alert;

/**
 * Bolha de mensagem (HTML) -- usada só na primeira renderização (SSR);
 * o polling de mensagens novas monta o equivalente em JS (montarBolha()
 * no script abaixo), mesma lógica de cor/alinhamento nos dois lugares.
 */
/** Imagem/áudio/documento em vez do texto simples, quando a mensagem tiver midia_path. */
function renderizarConteudoBolha(array $m): string
{
    if (empty($m['midia_path'])) {
        return '<div style="white-space:pre-wrap">' . htmlspecialchars($m['conteudo']) . '</div>';
    }

    $url = url('/whatsapp/atendimentos/midia?id=' . (int)$m['id']);

    if ($m['tipo'] === 'imagem') {
        return '<a href="' . htmlspecialchars($url) . '" target="_blank">'
            . '<img src="' . htmlspecialchars($url) . '" style="max-width:100%; border-radius:6px; display:block; margin-bottom:4px">'
            . '</a>'
            . ($m['conteudo'] !== '' ? '<div style="white-space:pre-wrap">' . htmlspecialchars($m['conteudo']) . '</div>' : '');
    }

    if ($m['tipo'] === 'audio') {
        return '<audio controls style="max-width:220px; display:block"><source src="' . htmlspecialchars($url) . '"></audio>';
    }

    if ($m['tipo'] === 'documento') {
        return '<a href="' . htmlspecialchars($url) . '" target="_blank" class="d-flex align-items-center gap-2 text-decoration-none text-reset">'
            . '<i class="bi bi-file-earmark-arrow-down fs-4"></i>'
            . '<span>' . htmlspecialchars($m['conteudo'] !== '' ? $m['conteudo'] : 'Documento') . '</span>'
            . '</a>';
    }

    return '<div style="white-space:pre-wrap">' . htmlspecialchars($m['conteudo']) . '</div>';
}

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

    $avisoFalha = $m['status_entrega'] === 'falhou'
        ? '<div class="small text-danger mt-1"><i class="bi bi-exclamation-triangle-fill"></i> Falha ao enviar -- não chegou no WhatsApp do cliente.</div>'
        : '';

    return '<div class="d-flex mb-2" style="justify-content:' . $alinhamento . '" data-msg-id="' . (int)$m['id'] . '">'
        . '<div style="max-width:70%; background:' . $corBolha . '; border-radius:10px; padding:8px 12px; box-shadow:0 1px 2px rgba(0,0,0,.1);">'
        . ($rotulo ? '<div class="small text-muted mb-1">' . htmlspecialchars($rotulo) . '</div>' : '')
        . renderizarConteudoBolha($m)
        . $avisoFalha
        . '<div class="text-muted text-end" style="font-size:10px">' . htmlspecialchars(data_br($m['criado_em'], 'H:i')) . '</div>'
        . '</div></div>';
}

$ehEncerrado = $atendimento['status'] === 'encerrado';
$somenteLeitura = $ehEncerrado || !$souDono;
$podeAssumirComoSupervisor = !$ehEncerrado && !$souDono && $souSupervisorDoSetor && $atendimento['status'] === 'em_atendimento';
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
            <?php if (!$souDono && !empty($atendimento['usuario_nome'])): ?>
                &middot; Atendido por: <?= htmlspecialchars($atendimento['usuario_nome']) ?>
            <?php endif; ?>
        </small>
    </div>
    <div>
        <?php if (!empty($atendimento['chamado_id'])): ?>
            <a href="<?= url('/chamados/atendimentos/ver?id=' . (int)$atendimento['chamado_id']) ?>" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-ticket-perforated"></i> Chamado #<?= (int)$atendimento['chamado_id'] ?>
            </a>
        <?php endif; ?>
        <a href="<?= url('/whatsapp/atendimentos') ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Atendimentos
        </a>
        <button type="button" id="botaoExportarPdf" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-file-earmark-pdf"></i> Exportar PDF
        </button>
        <?php if (!$somenteLeitura): ?>
            <?php if (!empty($setoresAtivos) || !empty($colegasSetor)): ?>
                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalTransferir">
                    <i class="bi bi-arrow-left-right"></i> Transferir
                </button>
            <?php endif; ?>
            <form method="post" action="<?= url('/whatsapp/atendimentos/encerrar') ?>" class="d-inline" onsubmit="return confirm('Encerrar este atendimento?');">
                <input type="hidden" name="id" value="<?= (int)$atendimento['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-check2-circle"></i> Encerrar
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($ehEncerrado): ?>
    <div class="alert alert-secondary py-2 small">
        <i class="bi bi-lock"></i> Atendimento encerrado em <?= data_br($atendimento['encerrado_em']) ?> -- só consulta, não é possível responder por aqui.
    </div>
<?php elseif (!$souDono): ?>
    <div class="alert alert-info py-2 small d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>
            <i class="bi bi-eye"></i> Você está vendo esse atendimento porque faz parte do setor
            <?= htmlspecialchars($atendimento['setor_nome'] ?? '') ?> -- só quem está atendendo
            <?= $podeAssumirComoSupervisor ? 'ou um supervisor' : '' ?> pode responder.
        </span>
        <?php if ($podeAssumirComoSupervisor): ?>
            <form method="post" action="<?= url('/whatsapp/atendimentos/assumir-supervisor') ?>" class="d-inline" onsubmit="return confirm('Assumir este atendimento? Ele deixa de ser do atendente atual.');">
                <input type="hidden" name="id" value="<?= (int)$atendimento['id'] ?>">
                <button type="submit" class="btn btn-sm btn-warning">
                    <i class="bi bi-person-check"></i> Assumir atendimento
                </button>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body" id="listaMensagens" data-atendimento-id="<?= (int)$atendimento['id'] ?>"
         style="height:420px; overflow-y:auto; background:#f5f7fb;">
        <?php foreach ($mensagens as $m): ?>
            <?= renderizarBolha($m) ?>
        <?php endforeach; ?>
    </div>
    <?php if (!$somenteLeitura): ?>
    <div class="card-footer bg-white position-relative">
        <div id="listaAutocompleteRapidas" class="list-group position-absolute shadow-sm"
             style="display:none; bottom:100%; left:16px; right:16px; max-height:200px; overflow-y:auto; z-index:10;"></div>
        <div id="painelEmojiChat" class="shadow-sm"
             style="display:none; position:absolute; bottom:100%; right:16px; margin-bottom:6px; background:#fff; border:1px solid #dee2e6; border-radius:10px; padding:8px; width:220px; grid-template-columns:repeat(5, 1fr); gap:2px; z-index:10;"></div>
        <form id="formResponder" class="d-flex gap-2">
            <?php if ($anexosAtivos): ?>
                <button type="button" id="botaoAnexoChat" class="btn btn-outline-secondary" title="Anexar arquivo">
                    <i class="bi bi-paperclip"></i>
                </button>
                <input type="file" id="campoArquivoChat" style="display:none"
                       accept="image/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip">
            <?php endif; ?>
            <button type="button" id="botaoEmojiChat" class="btn btn-outline-secondary" title="Emoji">
                <i class="bi bi-emoji-smile"></i>
            </button>
            <input type="text" id="campoTexto" class="form-control" placeholder="Digite uma mensagem... (use /comando pra respostas rápidas)" autocomplete="off" required>
            <button type="submit" class="btn btn-primary text-nowrap">
                <i class="bi bi-send"></i> Enviar
            </button>
        </form>
    </div>
    <?php endif; ?>
</div>

<script>
(function () {
    const lista = document.getElementById('listaMensagens');
    const form = document.getElementById('formResponder');
    const campo = document.getElementById('campoTexto');
    const atendimentoId = lista.dataset.atendimentoId;

    lista.scrollTop = lista.scrollHeight;

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

    function montarConteudoBolha(bolha, m) {
        if (!m.midia_path) {
            const elTexto = document.createElement('div');
            elTexto.style.whiteSpace = 'pre-wrap';
            elTexto.textContent = m.conteudo;
            bolha.appendChild(elTexto);
            return;
        }

        const url = <?= json_encode(url('/whatsapp/atendimentos/midia')) ?> + '?id=' + m.id;

        if (m.tipo === 'imagem') {
            const link = document.createElement('a');
            link.href = url;
            link.target = '_blank';
            const img = document.createElement('img');
            img.src = url;
            img.style.maxWidth = '100%';
            img.style.borderRadius = '6px';
            img.style.display = 'block';
            img.style.marginBottom = '4px';
            link.appendChild(img);
            bolha.appendChild(link);

            if (m.conteudo) {
                const elLegenda = document.createElement('div');
                elLegenda.style.whiteSpace = 'pre-wrap';
                elLegenda.textContent = m.conteudo;
                bolha.appendChild(elLegenda);
            }
            return;
        }

        if (m.tipo === 'audio') {
            const audio = document.createElement('audio');
            audio.controls = true;
            audio.style.maxWidth = '220px';
            audio.style.display = 'block';
            const fonte = document.createElement('source');
            fonte.src = url;
            audio.appendChild(fonte);
            bolha.appendChild(audio);
            return;
        }

        if (m.tipo === 'documento') {
            const link = document.createElement('a');
            link.href = url;
            link.target = '_blank';
            link.className = 'd-flex align-items-center gap-2 text-decoration-none text-reset';
            link.innerHTML = '<i class="bi bi-file-earmark-arrow-down fs-4"></i>';
            const elNome = document.createElement('span');
            elNome.textContent = m.conteudo || 'Documento';
            link.appendChild(elNome);
            bolha.appendChild(link);
            return;
        }

        const elTexto = document.createElement('div');
        elTexto.style.whiteSpace = 'pre-wrap';
        elTexto.textContent = m.conteudo;
        bolha.appendChild(elTexto);
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

        montarConteudoBolha(bolha, m);

        if (m.status_entrega === 'falhou') {
            const elFalha = document.createElement('div');
            elFalha.className = 'small text-danger mt-1';
            elFalha.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i> Falha ao enviar -- não chegou no WhatsApp do cliente.';
            bolha.appendChild(elFalha);
        }

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

    if (form) {
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

    setInterval(buscarNovas, 3000);

    // -- Anexo: pega o que tiver digitado no campo como legenda (fica
    // vazio se não digitou nada), sobe o arquivo e limpa o campo --
    // mesmo endpoint de envio de texto normal, só que multipart.
    const botaoAnexo = document.getElementById('botaoAnexoChat');
    const campoArquivo = document.getElementById('campoArquivoChat');

    if (botaoAnexo && campoArquivo) {
        botaoAnexo.addEventListener('click', function () {
            campoArquivo.click();
        });

        campoArquivo.addEventListener('change', function () {
            const arquivo = campoArquivo.files[0];
            if (!arquivo) {
                return;
            }

            botaoAnexo.disabled = true;
            campo.disabled = true;

            const dados = new FormData();
            dados.set('id', atendimentoId);
            dados.set('legenda', campo.value.trim());
            dados.set('arquivo', arquivo);

            fetch(<?= json_encode(url('/whatsapp/atendimentos/anexo')) ?>, { method: 'POST', body: dados })
                .then(function (r) { return r.json(); })
                .then(function (resultado) {
                    if (resultado.success) {
                        campo.value = '';
                        buscarNovas();
                    } else {
                        alert(resultado.message || 'Falha ao enviar anexo.');
                    }
                })
                .catch(function () {
                    alert('Erro ao comunicar com o servidor.');
                })
                .finally(function () {
                    botaoAnexo.disabled = false;
                    campo.disabled = false;
                    campoArquivo.value = '';
                });
        });
    }

    // -- Emoji: mesma ideia dos chips de {nome}/{periodo} do Chatbot,
    // mas aqui insere direto no campo de resposta em vez de um textarea.
    const EMOJIS = ['😀', '🙂', '😉', '😅', '🙁', '😢', '😡', '👍', '👎', '🙏', '👋', '✅', '❌', '⏳', '📞', '📅', '💰', '🎉', '❤️', '🤝'];
    const botaoEmoji = document.getElementById('botaoEmojiChat');
    const painelEmoji = document.getElementById('painelEmojiChat');

    EMOJIS.forEach(function (emoji) {
        const botao = document.createElement('button');
        botao.type = 'button';
        botao.textContent = emoji;
        botao.style.cssText = 'border:none; background:none; font-size:1.2rem; line-height:1; padding:6px; border-radius:6px; cursor:pointer;';
        botao.addEventListener('mouseenter', function () { botao.style.background = '#eef2ff'; });
        botao.addEventListener('mouseleave', function () { botao.style.background = 'none'; });
        botao.addEventListener('click', function () {
            const inicio = campo.selectionStart != null ? campo.selectionStart : campo.value.length;
            const fim = campo.selectionEnd != null ? campo.selectionEnd : campo.value.length;
            const valor = campo.value;

            campo.value = valor.slice(0, inicio) + emoji + valor.slice(fim);

            const novaPosicao = inicio + emoji.length;
            campo.focus();
            campo.setSelectionRange(novaPosicao, novaPosicao);

            painelEmoji.style.display = 'none';
        });
        painelEmoji.appendChild(botao);
    });

    botaoEmoji.addEventListener('click', function (evento) {
        evento.stopPropagation();
        painelEmoji.style.display = painelEmoji.style.display === 'none' ? 'grid' : 'none';
    });

    document.addEventListener('click', function (evento) {
        if (painelEmoji.style.display !== 'none' && !painelEmoji.contains(evento.target) && evento.target !== botaoEmoji) {
            painelEmoji.style.display = 'none';
        }
    });

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
    } // if (form)

    const botaoExportarPdf = document.getElementById('botaoExportarPdf');
    botaoExportarPdf.addEventListener('click', function () {
        const jsPDFClasse = window.jspdf && window.jspdf.jsPDF;
        if (!jsPDFClasse || !window.html2canvas) {
            alert('Não foi possível carregar as bibliotecas de exportação.');
            return;
        }

        botaoExportarPdf.disabled = true;

        const alturaOriginal = lista.style.height;
        const overflowOriginal = lista.style.overflowY;
        lista.style.height = 'auto';
        lista.style.overflowY = 'visible';

        window.html2canvas(lista, { backgroundColor: '#f5f7fb', scale: 2 }).then(function (canvas) {
            lista.style.height = alturaOriginal;
            lista.style.overflowY = overflowOriginal;

            const pdf = new jsPDFClasse('p', 'pt', 'a4');
            const margem = 24;
            const pageWidth = pdf.internal.pageSize.getWidth();
            const pageHeight = pdf.internal.pageSize.getHeight();
            const imgWidth = pageWidth - margem * 2;
            const imgHeight = (canvas.height * imgWidth) / canvas.width;
            const imgData = canvas.toDataURL('image/jpeg', 0.92);
            const areaUtil = pageHeight - margem * 2;

            let restante = imgHeight;
            let deslocamento = 0;

            pdf.addImage(imgData, 'JPEG', margem, margem, imgWidth, imgHeight);
            restante -= areaUtil;

            while (restante > 0) {
                deslocamento += areaUtil;
                pdf.addPage();
                pdf.addImage(imgData, 'JPEG', margem, margem - deslocamento, imgWidth, imgHeight);
                restante -= areaUtil;
            }

            pdf.save('atendimento-' + atendimentoId + '.pdf');
        }).catch(function () {
            lista.style.height = alturaOriginal;
            lista.style.overflowY = overflowOriginal;
            alert('Falha ao gerar o PDF.');
        }).finally(function () {
            botaoExportarPdf.disabled = false;
        });
    });
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.2/dist/jspdf.umd.min.js"></script>

<?php if (!empty($setoresAtivos) || !empty($colegasSetor)): ?>
<div class="modal fade" id="modalTransferir" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Transferir atendimento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if (!empty($colegasSetor)): ?>
                    <h6 class="small text-uppercase text-muted mb-2">Transferir direto para um colega</h6>
                    <p class="text-muted small">
                        Continua em atendimento, só troca o dono na hora -- só aparecem colegas do setor
                        <?= htmlspecialchars($atendimento['setor_nome'] ?? '') ?> que estão online agora.
                    </p>
                    <form method="post" action="<?= url('/whatsapp/atendimentos/transferir-usuario') ?>" class="mb-4">
                        <input type="hidden" name="id" value="<?= (int)$atendimento['id'] ?>">
                        <div class="input-group">
                            <select name="usuario_id" class="form-select" required>
                                <option value="">Selecione um colega online...</option>
                                <?php foreach ($colegasSetor as $colega): ?>
                                    <option value="<?= (int)$colega['id'] ?>" <?= $colega['online'] ? '' : 'disabled' ?>>
                                        <?= $colega['online'] ? '🟢' : '⚪' ?> <?= htmlspecialchars($colega['nome']) ?><?= $colega['online'] ? '' : ' (offline)' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-person-check"></i> Transferir</button>
                        </div>
                    </form>
                <?php endif; ?>

                <?php if (!empty($setoresAtivos)): ?>
                    <h6 class="small text-uppercase text-muted mb-2">Transferir para outro setor</h6>
                    <p class="text-muted small">
                        Volta pra fila do setor escolhido, sem dono -- deixa de ser seu, qualquer atendente daquele setor
                        pode assumir.
                    </p>
                    <form method="post" action="<?= url('/whatsapp/atendimentos/transferir') ?>">
                        <input type="hidden" name="id" value="<?= (int)$atendimento['id'] ?>">
                        <div class="input-group">
                            <select name="setor_id" class="form-select" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($setoresAtivos as $setor): ?>
                                    <option value="<?= (int)$setor['id'] ?>"><?= htmlspecialchars($setor['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-outline-primary"><i class="bi bi-arrow-left-right"></i> Transferir</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$conteudo = ob_get_clean();
$titulo = 'WhatsApp - Atendimento';

require __DIR__ . '/../layouts/main.php';
