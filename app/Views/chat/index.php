<?php
ob_start();

use App\Components\Alert;

/** Bolha de mensagem -- mesmo estilo visual do restante do sistema (WhatsApp/Chamados). */
function chatBolha(array $m, int $usuarioId): string
{
    $minha = (int)$m['usuario_id'] === $usuarioId;
    $corBolha = $minha ? '#dcf8c6' : '#ffffff';
    $alinhamento = $minha ? 'flex-end' : 'flex-start';

    return '<div class="d-flex mb-2" style="justify-content:' . $alinhamento . '" data-msg-id="' . (int)$m['id'] . '">'
        . '<div style="max-width:70%; background:' . $corBolha . '; border-radius:10px; padding:8px 12px; box-shadow:0 1px 2px rgba(0,0,0,.1);">'
        . (!$minha ? '<div class="small text-muted mb-1">' . htmlspecialchars($m['usuario_nome']) . '</div>' : '')
        . '<div style="white-space:pre-wrap">' . htmlspecialchars($m['conteudo']) . '</div>'
        . '<div class="text-muted text-end" style="font-size:10px">' . htmlspecialchars(data_br($m['criado_em'], 'H:i')) . '</div>'
        . '</div></div>';
}
?>

<?= Alert::flash() ?>

<div class="mb-4 d-flex justify-content-between align-items-start">
    <div>
        <h4 class="mb-1"><i class="bi bi-chat-dots-fill me-1"></i> Chat</h4>
        <small class="text-muted">Conversas diretas e em grupo, dentro do próprio sistema.</small>
    </div>
    <button type="button" class="btn btn-primary text-nowrap" data-bs-toggle="modal" data-bs-target="#modalNovaConversa">
        <i class="bi bi-plus-lg"></i> Nova conversa
    </button>
</div>

<div class="card border-0 shadow-sm" style="max-width:1300px">
    <div class="row g-0" style="min-height:560px">
        <div class="col-4 border-end">
            <div class="p-2 border-bottom">
                <input type="text" id="buscaConversas" class="form-control form-control-sm" placeholder="Buscar conversa...">
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
                            <?= count($participantes) ?> participante(s): <?= htmlspecialchars(implode(', ', array_column($participantes, 'nome'))) ?>
                        <?php else: ?>
                            <?= $outroOnline ? '<span class="text-success">Online agora</span>' : 'Offline' ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="p-3 flex-grow-1" id="listaMensagensChat" data-conversa-id="<?= (int)$conversaSelecionada['id'] ?>"
                     style="overflow-y:auto; max-height:420px; background:#f5f7fb">
                    <?php foreach ($mensagens as $m): ?>
                        <?= chatBolha($m, $usuarioId) ?>
                    <?php endforeach; ?>
                </div>
                <div class="p-3 border-top">
                    <form id="formEnviarChat" class="d-flex gap-2">
                        <input type="text" id="campoTextoChat" class="form-control" placeholder="Digite uma mensagem..." autocomplete="off" required>
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

    lista.scrollTop = lista.scrollHeight;

    function ultimoIdRenderizado() {
        const bolhas = lista.querySelectorAll('[data-msg-id]');
        let maior = 0;
        bolhas.forEach(function (b) { maior = Math.max(maior, parseInt(b.dataset.msgId, 10) || 0); });
        return maior;
    }

    function montarBolha(m) {
        const minha = String(m.usuario_id) === <?= json_encode((string)$usuarioId) ?>;
        const div = document.createElement('div');
        div.className = 'd-flex mb-2';
        div.style.justifyContent = minha ? 'flex-end' : 'flex-start';
        div.dataset.msgId = m.id;

        const bolha = document.createElement('div');
        bolha.style.cssText = 'max-width:70%; background:' + (minha ? '#dcf8c6' : '#ffffff') + '; border-radius:10px; padding:8px 12px; box-shadow:0 1px 2px rgba(0,0,0,.1);';

        if (!minha) {
            const nome = document.createElement('div');
            nome.className = 'small text-muted mb-1';
            nome.textContent = m.usuario_nome;
            bolha.appendChild(nome);
        }

        const texto = document.createElement('div');
        texto.style.whiteSpace = 'pre-wrap';
        texto.textContent = m.conteudo;
        bolha.appendChild(texto);

        const hora = document.createElement('div');
        hora.className = 'text-muted text-end';
        hora.style.fontSize = '10px';
        hora.textContent = m.criado_em.substring(11, 16);
        bolha.appendChild(hora);

        div.appendChild(bolha);
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

    form.addEventListener('submit', function (evento) {
        evento.preventDefault();
        const texto = campo.value.trim();
        if (!texto) return;

        const botao = form.querySelector('button');
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

    setInterval(buscarNovas, 3000);

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
                    if (evento2.evento === 'mensagem_nova' && String(evento2.dados.conversaId) === String(conversaId)) {
                        buscarNovas();
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

<?php
$conteudo = ob_get_clean();
$titulo = 'Chat';

require __DIR__ . '/../layouts/main.php';
