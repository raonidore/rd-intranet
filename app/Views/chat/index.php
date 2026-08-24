<?php
ob_start();

use App\Components\Alert;

/** Realça @menções no texto -- roda DEPOIS do htmlspecialchars, então nunca reabre marcação escapada sem querer. */
function chatTextoComMencao(string $texto): string
{
    $escapado = htmlspecialchars($texto);
    $destacado = preg_replace('/@([a-zA-Z0-9._-]+)/', '<strong class="text-primary">@$1</strong>', $escapado);

    return '<div style="white-space:pre-wrap">' . $destacado . '</div>';
}

/** Mesmo switch imagem/áudio/documento do WhatsApp -- anexo é sempre servido por rota autenticada (nunca URL direta). */
function chatConteudoBolha(array $m): string
{
    if (empty($m['midia_path'])) {
        return chatTextoComMencao($m['conteudo']);
    }

    $url = url('/chat/midia?id=' . (int)$m['id']);

    if ($m['tipo'] === 'imagem') {
        return '<a href="' . htmlspecialchars($url) . '" target="_blank">'
            . '<img src="' . htmlspecialchars($url) . '" style="max-width:100%; border-radius:6px; display:block; margin-bottom:4px">'
            . '</a>'
            . ($m['conteudo'] !== '' ? chatTextoComMencao($m['conteudo']) : '');
    }

    if ($m['tipo'] === 'audio') {
        return '<audio controls style="max-width:220px; display:block"><source src="' . htmlspecialchars($url) . '"></audio>';
    }

    return '<a href="' . htmlspecialchars($url) . '" target="_blank" class="d-flex align-items-center gap-2 text-decoration-none text-reset">'
        . '<i class="bi bi-file-earmark-arrow-down fs-4"></i>'
        . '<span>' . htmlspecialchars($m['conteudo'] !== '' ? $m['conteudo'] : 'Documento') . '</span>'
        . '</a>';
}

function chatReacoesHtml(int $mensagemId, array $reacoesPorMensagem): string
{
    if (empty($reacoesPorMensagem[$mensagemId])) {
        return '';
    }

    $html = '<div class="d-flex gap-1 flex-wrap mt-1 area-reacoes" data-mensagem-id="' . $mensagemId . '">';
    foreach ($reacoesPorMensagem[$mensagemId] as $r) {
        $classeAtiva = $r['reagiuEu'] ? 'border-primary' : 'border-secondary-subtle';
        $html .= '<button type="button" class="btn btn-sm py-0 px-2 border ' . $classeAtiva . ' botao-reacao" '
            . 'data-mensagem-id="' . $mensagemId . '" data-emoji="' . htmlspecialchars($r['emoji']) . '" '
            . 'style="font-size:.75rem; border-radius:10px; background:#fff;">'
            . htmlspecialchars($r['emoji']) . ' ' . (int)$r['total'] . '</button>';
    }
    $html .= '</div>';

    return $html;
}

/** Bolha de mensagem -- mesmo estilo visual do restante do sistema (WhatsApp/Chamados). */
function chatBolha(array $m, int $usuarioId, array $reacoesPorMensagem): string
{
    $minha = (int)$m['usuario_id'] === $usuarioId;
    $corBolha = $minha ? '#dcf8c6' : '#ffffff';
    $alinhamento = $minha ? 'flex-end' : 'flex-start';
    $estiloMencao = !empty($m['mencionado_eu']) ? ' outline:2px solid #f59e0b; outline-offset:1px;' : '';

    $html = '<div class="d-flex mb-2" style="justify-content:' . $alinhamento . '" data-msg-id="' . (int)$m['id'] . '">';
    $html .= '<div style="max-width:70%;">';
    $html .= '<div style="background:' . $corBolha . '; border-radius:10px; padding:8px 12px; box-shadow:0 1px 2px rgba(0,0,0,.1);' . $estiloMencao . '">';
    if (!$minha) {
        $html .= '<div class="small text-muted mb-1">' . htmlspecialchars($m['usuario_nome']) . '</div>';
    }
    $html .= chatConteudoBolha($m);
    $html .= '<div class="text-muted text-end" style="font-size:10px">' . htmlspecialchars(data_br($m['criado_em'], 'H:i')) . '</div>';
    $html .= '</div>';
    $html .= chatReacoesHtml((int)$m['id'], $reacoesPorMensagem);
    $html .= '<button type="button" class="btn btn-sm btn-link text-muted p-0 mt-1 botao-abrir-reacoes" data-mensagem-id="' . (int)$m['id'] . '" style="font-size:.7rem; text-decoration:none;">'
        . '<i class="bi bi-emoji-smile"></i> reagir</button>';
    $html .= '</div></div>';

    return $html;
}
?>

<?= Alert::flash() ?>

<div class="mb-4 d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h4 class="mb-1"><i class="bi bi-chat-dots-fill me-1"></i> Chat</h4>
        <small class="text-muted">Conversas diretas e em grupo, dentro do próprio sistema.</small>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary text-nowrap" data-bs-toggle="modal" data-bs-target="#modalBuscaChat">
            <i class="bi bi-search"></i> Buscar
        </button>
        <button type="button" class="btn btn-primary text-nowrap" data-bs-toggle="modal" data-bs-target="#modalNovaConversa">
            <i class="bi bi-plus-lg"></i> Nova conversa
        </button>
    </div>
</div>

<div class="card border-0 shadow-sm" style="max-width:1300px">
    <div class="row g-0" style="min-height:560px">
        <div class="col-4 border-end">
            <div class="p-2 border-bottom">
                <input type="text" id="buscaConversas" class="form-control form-control-sm" placeholder="Filtrar conversa...">
            </div>
            <div id="listaConversas" style="max-height:560px; overflow-y:auto">
                <?php if (empty($conversas)): ?>
                    <div class="text-center text-muted py-5 px-3">
                        <i class="bi bi-chat-square-text" style="font-size:1.8rem;"></i>
                        <p class="small mb-0 mt-2">Nenhuma conversa ainda -- clique em "Nova conversa" pra começar.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($conversas as $item): ?>
                        <?php
                        $selecionada = $conversaId === (int)$item['id'];
                        $online = $item['outro_usuario_id'] !== null && in_array((int)$item['outro_usuario_id'], $onlineIds, true);
                        $nome = $item['nome_exibicao'] ?: ($item['tipo'] === 'grupo' ? '(grupo sem nome)' : '(usuário removido)');
                        ?>
                        <a href="<?= url('/chat?conversa_id=' . (int)$item['id']) ?>"
                           class="d-block text-decoration-none text-reset px-3 py-2 border-bottom item-conversa <?= $selecionada ? 'bg-light' : '' ?>"
                           data-conversa-id="<?= (int)$item['id'] ?>"
                           data-busca="<?= htmlspecialchars(mb_strtolower($nome)) ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <strong class="text-truncate d-flex align-items-center gap-1">
                                    <?php if ($item['tipo'] === 'grupo'): ?>
                                        <i class="bi bi-people-fill text-muted small"></i>
                                    <?php elseif ($online): ?>
                                        <span style="width:8px; height:8px; border-radius:50%; background:#22c55e; display:inline-block;"></span>
                                    <?php else: ?>
                                        <span style="width:8px; height:8px; border-radius:50%; background:#cbd5e1; display:inline-block;"></span>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($nome) ?>
                                </strong>
                                <small class="text-muted text-nowrap ms-2"><?= data_br($item['ultima_mensagem_em'], 'H:i') ?></small>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="text-muted small text-truncate">
                                    <?= $item['ultima_mensagem'] !== null ? htmlspecialchars($item['ultima_mensagem']) : '(sem mensagens)' ?>
                                </div>
                                <?php if ((int)$item['nao_lidas'] > 0): ?>
                                    <span class="badge rounded-pill bg-primary ms-1"><?= (int)$item['nao_lidas'] ?></span>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-8 d-flex flex-column">
            <?php if (!$conversaSelecionada): ?>
                <div class="d-flex align-items-center justify-content-center flex-grow-1 text-muted">
                    <div class="text-center">
                        <i class="bi bi-chat-square-text" style="font-size:2rem;"></i>
                        <p class="mb-0 mt-2">Selecione uma conversa, ou comece uma nova.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="p-3 border-bottom">
                    <strong class="d-flex align-items-center gap-2">
                        <?php if ($conversaSelecionada['tipo'] === 'grupo'): ?>
                            <i class="bi bi-people-fill text-muted"></i>
                        <?php endif; ?>
                        <?= htmlspecialchars($tituloConversa) ?>
                    </strong>
                    <div class="text-muted small">
                        <?php if ($conversaSelecionada['tipo'] === 'grupo'): ?>
                            <?php $rotulos = array_map(fn ($p) => $p['nome'] . ' (@' . $p['login'] . ')', $participantes); ?>
                            <?= count($participantes) ?> participante(s): <?= htmlspecialchars(implode(', ', $rotulos)) ?>
                        <?php else: ?>
                            <?= $outroOnline ? '<span class="text-success">Online agora</span>' : 'Offline' ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="p-3 flex-grow-1" id="listaMensagensChat" data-conversa-id="<?= (int)$conversaSelecionada['id'] ?>"
                     style="overflow-y:auto; max-height:420px; background:#f5f7fb">
                    <?php foreach ($mensagens as $m): ?>
                        <?= chatBolha($m, $usuarioId, $reacoes) ?>
                    <?php endforeach; ?>
                </div>
                <div class="p-3 border-top position-relative">
                    <div id="painelEmojiReacaoChat" class="shadow-sm"
                         style="display:none; position:absolute; bottom:100%; background:#fff; border:1px solid #dee2e6; border-radius:10px; padding:8px; z-index:20;">
                        <?php foreach (['👍', '❤️', '😂', '😮', '😢', '🙏'] as $emojiRapido): ?>
                            <button type="button" class="btn btn-sm emoji-rapido" style="font-size:1.1rem;" data-emoji="<?= $emojiRapido ?>"><?= $emojiRapido ?></button>
                        <?php endforeach; ?>
                    </div>
                    <form id="formEnviarChat" class="d-flex gap-2">
                        <button type="button" id="botaoAnexoChat" class="btn btn-outline-secondary" title="Anexar arquivo">
                            <i class="bi bi-paperclip"></i>
                        </button>
                        <input type="file" id="campoArquivoChat" style="display:none"
                               accept="image/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip">
                        <input type="text" id="campoTextoChat" class="form-control" placeholder="Digite uma mensagem... (use @login pra mencionar alguém)" autocomplete="off" required>
                        <button type="submit" class="btn btn-primary text-nowrap">
                            <i class="bi bi-send"></i> Enviar
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.getElementById('buscaConversas').addEventListener('input', function () {
    const filtro = this.value.trim().toLowerCase();
    document.querySelectorAll('#listaConversas .item-conversa').forEach(function (item) {
        item.style.display = item.dataset.busca.includes(filtro) ? '' : 'none';
    });
});
</script>

<?php if ($conversaSelecionada): ?>
<script>
(function () {
    const lista = document.getElementById('listaMensagensChat');
    const form = document.getElementById('formEnviarChat');
    const campo = document.getElementById('campoTextoChat');
    const conversaId = lista.dataset.conversaId;
    const meuUsuarioId = <?= json_encode((string)$usuarioId) ?>;
    const painelEmoji = document.getElementById('painelEmojiReacaoChat');
    let mensagemAlvoReacao = null;

    lista.scrollTop = lista.scrollHeight;

    function ultimoIdRenderizado() {
        const bolhas = lista.querySelectorAll('[data-msg-id]');
        let maior = 0;
        bolhas.forEach(function (b) { maior = Math.max(maior, parseInt(b.dataset.msgId, 10) || 0); });
        return maior;
    }

    function conteudoComMencao(texto) {
        const div = document.createElement('div');
        div.style.whiteSpace = 'pre-wrap';
        const partes = texto.split(/(@[a-zA-Z0-9._-]+)/g);
        partes.forEach(function (parte) {
            if (parte.startsWith('@')) {
                const forte = document.createElement('strong');
                forte.className = 'text-primary';
                forte.textContent = parte;
                div.appendChild(forte);
            } else if (parte) {
                div.appendChild(document.createTextNode(parte));
            }
        });
        return div;
    }

    function montarConteudoBolha(bolha, m) {
        if (!m.midia_path) {
            bolha.appendChild(conteudoComMencao(m.conteudo));
            return;
        }

        const url = <?= json_encode(url('/chat/midia')) ?> + '?id=' + m.id;

        if (m.tipo === 'imagem') {
            const link = document.createElement('a');
            link.href = url;
            link.target = '_blank';
            const img = document.createElement('img');
            img.src = url;
            img.style.cssText = 'max-width:100%; border-radius:6px; display:block; margin-bottom:4px';
            link.appendChild(img);
            bolha.appendChild(link);
            if (m.conteudo) bolha.appendChild(conteudoComMencao(m.conteudo));
        } else if (m.tipo === 'audio') {
            const audio = document.createElement('audio');
            audio.controls = true;
            audio.style.cssText = 'max-width:220px; display:block';
            const source = document.createElement('source');
            source.src = url;
            audio.appendChild(source);
            bolha.appendChild(audio);
        } else {
            const link = document.createElement('a');
            link.href = url;
            link.target = '_blank';
            link.className = 'd-flex align-items-center gap-2 text-decoration-none text-reset';
            link.innerHTML = '<i class="bi bi-file-earmark-arrow-down fs-4"></i>';
            const span = document.createElement('span');
            span.textContent = m.conteudo || 'Documento';
            link.appendChild(span);
            bolha.appendChild(link);
        }
    }

    function montarBolha(m) {
        const minha = String(m.usuario_id) === meuUsuarioId;
        const div = document.createElement('div');
        div.className = 'd-flex mb-2';
        div.style.justifyContent = minha ? 'flex-end' : 'flex-start';
        div.dataset.msgId = m.id;

        const wrap = document.createElement('div');
        wrap.style.maxWidth = '70%';

        const bolha = document.createElement('div');
        bolha.style.cssText = 'background:' + (minha ? '#dcf8c6' : '#ffffff') + '; border-radius:10px; padding:8px 12px; box-shadow:0 1px 2px rgba(0,0,0,.1);'
            + (m.mencionado_eu ? ' outline:2px solid #f59e0b; outline-offset:1px;' : '');

        if (!minha) {
            const nome = document.createElement('div');
            nome.className = 'small text-muted mb-1';
            nome.textContent = m.usuario_nome;
            bolha.appendChild(nome);
        }

        montarConteudoBolha(bolha, m);

        const hora = document.createElement('div');
        hora.className = 'text-muted text-end';
        hora.style.fontSize = '10px';
        hora.textContent = m.criado_em.substring(11, 16);
        bolha.appendChild(hora);

        wrap.appendChild(bolha);

        const areaReacoes = document.createElement('div');
        areaReacoes.className = 'area-reacoes mt-1';
        areaReacoes.dataset.mensagemId = m.id;
        wrap.appendChild(areaReacoes);

        const botaoReagir = document.createElement('button');
        botaoReagir.type = 'button';
        botaoReagir.className = 'btn btn-sm btn-link text-muted p-0 mt-1 botao-abrir-reacoes';
        botaoReagir.dataset.mensagemId = m.id;
        botaoReagir.style.cssText = 'font-size:.7rem; text-decoration:none;';
        botaoReagir.innerHTML = '<i class="bi bi-emoji-smile"></i> reagir';
        wrap.appendChild(botaoReagir);

        div.appendChild(wrap);
        return div;
    }

    function buscarNovas() {
        fetch(<?= json_encode(url('/chat/mensagens')) ?> + '?conversa_id=' + encodeURIComponent(conversaId) + '&desde=' + ultimoIdRenderizado())
            .then(function (r) { return r.json(); })
            .then(function (dados) {
                if (!dados.success) return;
                let chegouAlgo = false;
                dados.mensagens.forEach(function (m) {
                    lista.appendChild(montarBolha(m));
                    chegouAlgo = true;
                });
                if (chegouAlgo) lista.scrollTop = lista.scrollHeight;
            })
            .catch(function () {});
    }

    function atualizarReacoes() {
        fetch(<?= json_encode(url('/chat/reacoes')) ?> + '?conversa_id=' + encodeURIComponent(conversaId))
            .then(function (r) { return r.json(); })
            .then(function (dados) {
                if (!dados.success) return;

                document.querySelectorAll('.area-reacoes').forEach(function (area) {
                    area.innerHTML = '';
                });

                Object.keys(dados.reacoes).forEach(function (mensagemId) {
                    const area = lista.querySelector('.area-reacoes[data-mensagem-id="' + mensagemId + '"]');
                    if (!area) return;

                    dados.reacoes[mensagemId].forEach(function (r) {
                        const botao = document.createElement('button');
                        botao.type = 'button';
                        botao.className = 'btn btn-sm py-0 px-2 border botao-reacao ' + (r.reagiuEu ? 'border-primary' : 'border-secondary-subtle');
                        botao.style.cssText = 'font-size:.75rem; border-radius:10px; background:#fff;';
                        botao.dataset.mensagemId = mensagemId;
                        botao.dataset.emoji = r.emoji;
                        botao.textContent = r.emoji + ' ' + r.total;
                        area.appendChild(botao);
                    });
                });
            })
            .catch(function () {});
    }

    function reagir(mensagemId, emoji) {
        const dados = new URLSearchParams();
        dados.set('mensagem_id', mensagemId);
        dados.set('emoji', emoji);

        fetch(<?= json_encode(url('/chat/reagir')) ?>, { method: 'POST', body: dados })
            .then(function (r) { return r.json(); })
            .then(function () { atualizarReacoes(); })
            .catch(function () {});
    }

    lista.addEventListener('click', function (evento) {
        const botaoReacaoExistente = evento.target.closest('.botao-reacao');
        if (botaoReacaoExistente) {
            reagir(botaoReacaoExistente.dataset.mensagemId, botaoReacaoExistente.dataset.emoji);
            return;
        }

        const botaoAbrir = evento.target.closest('.botao-abrir-reacoes');
        if (botaoAbrir) {
            mensagemAlvoReacao = botaoAbrir.dataset.mensagemId;
            const posicao = botaoAbrir.getBoundingClientRect();
            painelEmoji.style.left = posicao.left + 'px';
            painelEmoji.style.top = (posicao.top - 46) + 'px';
            painelEmoji.style.position = 'fixed';
            painelEmoji.style.display = '';
        }
    });

    document.querySelectorAll('.emoji-rapido').forEach(function (botao) {
        botao.addEventListener('click', function () {
            if (mensagemAlvoReacao) {
                reagir(mensagemAlvoReacao, botao.dataset.emoji);
            }
            painelEmoji.style.display = 'none';
        });
    });

    document.addEventListener('click', function (evento) {
        if (!painelEmoji.contains(evento.target) && !evento.target.closest('.botao-abrir-reacoes')) {
            painelEmoji.style.display = 'none';
        }
    });

    form.addEventListener('submit', function (evento) {
        evento.preventDefault();
        const texto = campo.value.trim();
        if (!texto) return;

        const botao = form.querySelector('button[type="submit"]');
        botao.disabled = true;
        campo.disabled = true;

        const dados = new URLSearchParams();
        dados.set('conversa_id', conversaId);
        dados.set('texto', texto);

        fetch(<?= json_encode(url('/chat/enviar')) ?>, { method: 'POST', body: dados })
            .then(function (r) { return r.json(); })
            .then(function (resultado) {
                if (resultado.success) {
                    campo.value = '';
                    buscarNovas();
                } else {
                    alert(resultado.message || 'Falha ao enviar.');
                }
            })
            .catch(function () { alert('Erro ao comunicar com o servidor.'); })
            .finally(function () {
                botao.disabled = false;
                campo.disabled = false;
                campo.focus();
            });
    });

    const botaoAnexo = document.getElementById('botaoAnexoChat');
    const campoArquivo = document.getElementById('campoArquivoChat');

    botaoAnexo.addEventListener('click', function () { campoArquivo.click(); });

    campoArquivo.addEventListener('change', function () {
        const arquivo = campoArquivo.files[0];
        if (!arquivo) return;

        botaoAnexo.disabled = true;
        campo.disabled = true;

        const dados = new FormData();
        dados.set('conversa_id', conversaId);
        dados.set('legenda', campo.value.trim());
        dados.set('arquivo', arquivo);

        fetch(<?= json_encode(url('/chat/anexo')) ?>, { method: 'POST', body: dados })
            .then(function (r) { return r.json(); })
            .then(function (resultado) {
                if (resultado.success) {
                    campo.value = '';
                    buscarNovas();
                } else {
                    alert(resultado.message || 'Falha ao enviar anexo.');
                }
            })
            .catch(function () { alert('Erro ao comunicar com o servidor.'); })
            .finally(function () {
                botaoAnexo.disabled = false;
                campo.disabled = false;
                campoArquivo.value = '';
            });
    });

    setInterval(buscarNovas, 3000);
    setInterval(atualizarReacoes, 5000);

    // Fase 2 -- acelerador via WebSocket, só pra ESTA conversa aberta
    // (o acelerador do badge sitewide já roda via layouts/main.php).
    // Puramente em cima do polling acima -- se não conectar, nada muda.
    async function conectarSocketConversa() {
        try {
            const resp = await fetch(<?= json_encode(url('/chat/socket-token')) ?>);
            const dados = await resp.json();
            if (!dados.success) {
                return;
            }

            const protocolo = location.protocol === 'https:' ? 'wss:' : 'ws:';
            const ws = new WebSocket(protocolo + '//' + location.host + '/chat-ws?token=' + encodeURIComponent(dados.token));

            ws.addEventListener('message', function (evento) {
                try {
                    const evento2 = JSON.parse(evento.data);
                    if (String(evento2.dados.conversaId) !== String(conversaId)) {
                        return;
                    }
                    if (evento2.evento === 'mensagem_nova') {
                        buscarNovas();
                    } else if (evento2.evento === 'reacao_atualizada') {
                        atualizarReacoes();
                    }
                } catch (e) {}
            });

            ws.addEventListener('close', function () {
                setTimeout(conectarSocketConversa, 10000);
            });
        } catch (e) {
            // sem bridge/proxy disponível -- o polling acima cobre normalmente
        }
    }

    conectarSocketConversa();
})();
</script>
<?php endif; ?>

<div class="modal fade" id="modalNovaConversa" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nova conversa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-pills mb-3" id="tabsNovaConversa">
                    <li class="nav-item"><button type="button" class="nav-link active" data-tab="direta">Conversa direta</button></li>
                    <li class="nav-item"><button type="button" class="nav-link" data-tab="grupo">Novo grupo</button></li>
                </ul>

                <div id="tabDireta">
                    <?php if (empty($usuariosDisponiveis)): ?>
                        <p class="text-muted small">Nenhum outro usuário cadastrado no sistema ainda.</p>
                    <?php else: ?>
                        <div style="max-height:280px; overflow-y:auto">
                            <?php foreach ($usuariosDisponiveis as $usuario): ?>
                                <?php $online = in_array((int)$usuario['id'], $onlineDisponiveis, true); ?>
                                <form method="post" action="<?= url('/chat/nova-direta') ?>">
                                    <input type="hidden" name="usuario_id" value="<?= (int)$usuario['id'] ?>">
                                    <button type="submit" class="btn btn-outline-secondary w-100 mb-1 text-start d-flex align-items-center gap-2">
                                        <span style="width:8px; height:8px; border-radius:50%; background:<?= $online ? '#22c55e' : '#cbd5e1' ?>; display:inline-block; flex-shrink:0;"></span>
                                        <?= htmlspecialchars($usuario['nome']) ?>
                                        <span class="text-muted small ms-auto"><?= $online ? 'online' : 'offline' ?></span>
                                    </button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div id="tabGrupo" style="display:none">
                    <form method="post" action="<?= url('/chat/novo-grupo') ?>">
                        <div class="mb-3">
                            <label class="form-label small">Nome do grupo</label>
                            <input type="text" name="nome" class="form-control form-control-sm" placeholder="Ex: Suporte TI" required maxlength="150">
                        </div>
                        <label class="form-label small">Participantes</label>
                        <div class="row row-cols-1 row-cols-md-2 g-1 mb-3" style="max-height:220px; overflow-y:auto">
                            <?php foreach ($usuariosDisponiveis as $usuario): ?>
                                <div class="col">
                                    <div class="form-check">
                                        <input type="checkbox" name="participantes[]" value="<?= (int)$usuario['id'] ?>" class="form-check-input" id="grp<?= (int)$usuario['id'] ?>">
                                        <label class="form-check-label small" for="grp<?= (int)$usuario['id'] ?>"><?= htmlspecialchars($usuario['nome']) ?></label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-people-fill"></i> Criar grupo</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('#tabsNovaConversa .nav-link').forEach(function (aba) {
    aba.addEventListener('click', function () {
        document.querySelectorAll('#tabsNovaConversa .nav-link').forEach(function (a) { a.classList.remove('active'); });
        aba.classList.add('active');
        document.getElementById('tabDireta').style.display = aba.dataset.tab === 'direta' ? '' : 'none';
        document.getElementById('tabGrupo').style.display = aba.dataset.tab === 'grupo' ? '' : 'none';
    });
});
</script>

<div class="modal fade" id="modalBuscaChat" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Buscar nas conversas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="text" id="campoBuscaChat" class="form-control mb-3" placeholder="Digite pra buscar no texto das mensagens...">
                <div id="resultadosBuscaChat" style="max-height:360px; overflow-y:auto"></div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const campo = document.getElementById('campoBuscaChat');
    const resultados = document.getElementById('resultadosBuscaChat');
    let temporizador = null;

    campo.addEventListener('input', function () {
        clearTimeout(temporizador);
        const termo = campo.value.trim();

        if (termo.length < 2) {
            resultados.innerHTML = '';
            return;
        }

        temporizador = setTimeout(function () {
            fetch(<?= json_encode(url('/chat/buscar')) ?> + '?q=' + encodeURIComponent(termo))
                .then(function (r) { return r.json(); })
                .then(function (dados) {
                    resultados.innerHTML = '';
                    if (!dados.success || dados.resultados.length === 0) {
                        resultados.innerHTML = '<p class="text-muted small text-center py-3">Nada encontrado.</p>';
                        return;
                    }

                    dados.resultados.forEach(function (r) {
                        const link = document.createElement('a');
                        link.href = <?= json_encode(url('/chat?conversa_id=')) ?> + r.conversa_id;
                        link.className = 'd-block text-decoration-none text-reset border-bottom py-2';

                        const titulo = document.createElement('strong');
                        titulo.className = 'small';
                        titulo.textContent = r.conversa_nome_exibicao || '(conversa)';
                        link.appendChild(titulo);

                        const trecho = document.createElement('div');
                        trecho.className = 'text-muted small';
                        trecho.textContent = r.usuario_nome + ': ' + r.conteudo.substring(0, 120);
                        link.appendChild(trecho);

                        resultados.appendChild(link);
                    });
                })
                .catch(function () {});
        }, 300);
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Chat';

require __DIR__ . '/../layouts/main.php';
