<?php
ob_start();

use App\Components\Alert;

const WPP_TIPOS_OPCAO = [
    'menu' => 'Abre submenu',
    'resposta_final' => 'Responde e encerra',
    'encaminhar_setor' => 'Encaminha pro setor',
];

/** <option>s de setor reaproveitadas tanto nas linhas já salvas quanto no template de linha nova (JS). */
function wppOpcoesSetor(array $setoresAtivos, ?int $selecionadoId = null): string
{
    $html = '<option value="">Selecione...</option>';
    foreach ($setoresAtivos as $s) {
        $sel = $selecionadoId !== null && $selecionadoId === (int)$s['id'] ? 'selected' : '';
        $html .= '<option value="' . (int)$s['id'] . '" ' . $sel . '>' . htmlspecialchars($s['nome']) . '</option>';
    }
    return $html;
}

/** Bolha de mensagem -- mesmo estilo/cores de atendimento_chat.php (renderizarBolha()). */
function wppBolhaMensagem(array $m): string
{
    $minhas = $m['direcao'] === 'saida';
    $corBolha = $m['origem'] === 'bot' ? '#e0e7ff' : ($minhas ? '#dcf8c6' : '#ffffff');
    $alinhamento = $minhas ? 'flex-end' : 'flex-start';
    $rotulo = $m['origem'] === 'bot' ? 'Bot' : ($m['origem'] === 'cliente' ? '' : 'Você');

    return '<div class="d-flex mb-2" style="justify-content:' . $alinhamento . '">'
        . '<div style="max-width:70%; background:' . $corBolha . '; border-radius:10px; padding:8px 12px; box-shadow:0 1px 2px rgba(0,0,0,.1);">'
        . ($rotulo ? '<div class="small text-muted mb-1">' . htmlspecialchars($rotulo) . '</div>' : '')
        . '<div style="white-space:pre-wrap">' . htmlspecialchars($m['conteudo']) . '</div>'
        . '<div class="text-muted text-end" style="font-size:10px">' . htmlspecialchars($m['criado_em']) . '</div>'
        . '</div></div>';
}

/**
 * Campo de mensagem com templates -- toolbar de "chips" clicáveis
 * ({nome}/{periodo}, insere na posição do cursor) + preview ao vivo.
 * Modo 'bubble': lado a lado, balão estilo WhatsApp (campo único e
 * importante -- boas-vindas, encerramentos). Modo 'compacto': texto
 * pequeno abaixo do campo (usado nas linhas repetidas do fluxo, onde
 * não cabe um preview grande ao lado). Preview inicial já vem
 * renderizado do servidor (funciona mesmo antes do JS carregar); o JS
 * só assume a atualização ao vivo depois.
 */
function wppCampoMensagem(string $nomeCampo, string $valor, string $modo, int $rows, string $placeholder = '', bool $obrigatorio = true): void
{
    $hora = (int)date('G');
    $periodoExemplo = $hora < 12 ? 'Bom dia' : ($hora < 18 ? 'Boa tarde' : 'Boa noite');
    $previewInicial = str_replace(['{nome}', '{periodo}'], ['Maria', $periodoExemplo], $valor);
    ?>
    <div class="rd-editor-template rd-editor-template-<?= $modo ?>">
        <div class="rd-chips-template">
            <button type="button" class="rd-chip" data-insere="{nome}"><i class="bi bi-person-fill"></i> Nome do cliente</button>
            <button type="button" class="rd-chip" data-insere="{periodo}"><i class="bi bi-brightness-high-fill"></i> Bom dia / tarde / noite</button>
        </div>
        <div class="rd-editor-template-corpo">
            <textarea name="<?= htmlspecialchars($nomeCampo) ?>" class="form-control campo-mensagem-template" rows="<?= $rows ?>"
                      placeholder="<?= htmlspecialchars($placeholder) ?>" <?= $obrigatorio ? 'required' : '' ?>><?= htmlspecialchars($valor) ?></textarea>
            <?php if ($modo === 'bubble'): ?>
                <div class="rd-preview-whatsapp">
                    <div class="rd-preview-whatsapp-cabecalho"><i class="bi bi-whatsapp"></i> Pré-visualização</div>
                    <div class="rd-preview-whatsapp-fundo">
                        <div class="rd-preview-bolha rd-preview-alvo"><?= htmlspecialchars($previewInicial ?: '(mensagem vazia)') ?></div>
                    </div>
                </div>
            <?php else: ?>
                <div class="rd-preview-compacta rd-preview-alvo"><?= htmlspecialchars($previewInicial ?: '(mensagem vazia)') ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

function wppLinhaOpcao(array $opcao, array $setoresAtivos): void
{
    $ehExistente = !empty($opcao['id']);
    ?>
    <div class="row g-2 align-items-start mb-2 linha-opcao pb-2 border-bottom" data-existente="<?= $ehExistente ? '1' : '0' ?>">
        <input type="hidden" name="id[]" value="<?= $ehExistente ? (int)$opcao['id'] : '' ?>">
        <div class="col-md-1 pt-2 text-muted small">#<?= (int)($opcao['posicao'] ?? 0) ?: '' ?></div>
        <div class="col-md-3">
            <input type="text" name="rotulo[]" class="form-control form-control-sm" placeholder="Rótulo (ex: Suporte Técnico)" value="<?= htmlspecialchars($opcao['rotulo'] ?? '') ?>">
        </div>
        <div class="col-md-3">
            <select name="tipo[]" class="form-select form-select-sm campo-tipo">
                <?php foreach (WPP_TIPOS_OPCAO as $valor => $rotulo): ?>
                    <option value="<?= $valor ?>" <?= ($opcao['tipo'] ?? 'encaminhar_setor') === $valor ? 'selected' : '' ?>><?= htmlspecialchars($rotulo) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 campo-setor">
            <select name="setor_destino_id[]" class="form-select form-select-sm">
                <?= wppOpcoesSetor($setoresAtivos, isset($opcao['setor_destino_id']) ? (int)$opcao['setor_destino_id'] : null) ?>
            </select>
        </div>
        <div class="col-md-1 pt-1 text-end">
            <?php if ($ehExistente && ($opcao['tipo'] ?? '') === 'menu'): ?>
                <a href="<?= url('/whatsapp/chatbot?aba=fluxo&no_pai_id=' . (int)$opcao['id']) ?>" class="btn btn-sm btn-outline-secondary" title="Sub-opções">
                    <i class="bi bi-arrow-return-right"></i>
                </a>
            <?php endif; ?>
            <button type="button" class="btn btn-sm btn-outline-danger botao-remover" title="Remover">
                <i class="bi bi-trash"></i>
            </button>
        </div>
        <div class="col-md-11 offset-md-1">
            <?php wppCampoMensagem('mensagem[]', $opcao['mensagem'] ?? '', 'compacto', 2, 'Mensagem enviada ao cliente ao escolher/entrar aqui (opcional pra "Encaminha pro setor")', false); ?>
        </div>
    </div>
    <?php
}
?>

<style>
.rd-chips-template { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:8px; }
.rd-chip {
    display:inline-flex; align-items:center; gap:6px;
    padding:5px 12px; border-radius:999px; border:1px solid #c7d2fe;
    background:linear-gradient(135deg,#eef2ff,#e0e7ff); color:#4338ca;
    font-size:.78rem; font-weight:600; cursor:pointer; transition:.15s ease;
}
.rd-chip:hover { background:linear-gradient(135deg,#e0e7ff,#c7d2fe); transform:translateY(-1px); box-shadow:0 3px 8px rgba(67,56,202,.25); }
.rd-chip:active { transform:translateY(0); box-shadow:none; }

.rd-editor-template-bubble .rd-editor-template-corpo { display:flex; gap:16px; align-items:stretch; flex-wrap:wrap; }
.rd-editor-template-bubble textarea { flex:1 1 320px; min-height:130px; }
.rd-preview-whatsapp { flex:1 1 260px; border-radius:12px; overflow:hidden; border:1px solid #d1d5db; display:flex; flex-direction:column; }
.rd-preview-whatsapp-cabecalho { background:#075e54; color:#fff; padding:8px 12px; font-size:.78rem; font-weight:600; }
.rd-preview-whatsapp-fundo { flex:1; background:#e5ddd5; padding:14px; display:flex; align-items:flex-start; min-height:110px; }
.rd-preview-bolha {
    background:#dcf8c6; border-radius:8px; padding:8px 10px; font-size:.85rem;
    white-space:pre-wrap; box-shadow:0 1px 1px rgba(0,0,0,.1); max-width:100%;
}
.rd-preview-compacta { white-space:pre-wrap; font-style:italic; font-size:.8rem; color:#6c757d; margin-top:2px; }
</style>

<?= Alert::flash() ?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-robot me-1"></i> WhatsApp - Chatbot</h4>
    <small class="text-muted">Menu automático em árvore: a numeração das opções é gerada sozinha, o cliente só digita o número.</small>
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link <?= $aba === 'espera' ? 'active' : '' ?>" href="<?= url('/whatsapp/chatbot?aba=espera') ?>">
            Em espera no chatbot
            <?php if (!empty($emEspera)): ?>
                <span class="badge rounded-pill bg-danger ms-1"><?= count($emEspera) ?></span>
            <?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $aba === 'fluxo' ? 'active' : '' ?>" href="<?= url('/whatsapp/chatbot?aba=fluxo') ?>">Fluxo</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $aba === 'finalizacao' ? 'active' : '' ?>" href="<?= url('/whatsapp/chatbot?aba=finalizacao') ?>">Finalização</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $aba === 'mensagens-rapidas' ? 'active' : '' ?>" href="<?= url('/whatsapp/chatbot?aba=mensagens-rapidas') ?>">Mensagens Rápidas</a>
    </li>
</ul>

<?php if ($aba === 'espera'): ?>

    <?php if (empty($emEspera)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center text-muted py-4">
                <i class="bi bi-robot" style="font-size:2rem;"></i>
                <p class="mb-0 mt-2">Nenhuma conversa com o bot no momento.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm" style="max-width:1300px">
            <div class="row g-0" style="min-height:520px">
                <div class="col-4 border-end">
                    <div class="p-2 border-bottom">
                        <input type="text" id="buscaEmEspera" class="form-control form-control-sm" placeholder="Buscar conversa...">
                    </div>
                    <div id="listaEmEspera" style="max-height:520px; overflow-y:auto">
                        <?php foreach ($emEspera as $item): ?>
                            <?php $selecionado = $emEsperaSelecionado && (int)$emEsperaSelecionado['id'] === (int)$item['id']; ?>
                            <a href="<?= url('/whatsapp/chatbot?aba=espera&atendimento_id=' . (int)$item['id']) ?>"
                               class="d-block text-decoration-none text-reset px-3 py-2 border-bottom item-em-espera <?= $selecionado ? 'bg-light' : '' ?>"
                               data-busca="<?= htmlspecialchars(mb_strtolower(($item['contato_nome'] ?: '') . ' ' . $item['numero'])) ?>">
                                <div class="d-flex justify-content-between">
                                    <strong class="text-truncate"><?= htmlspecialchars($item['contato_nome'] ?: '(sem nome)') ?></strong>
                                    <small class="text-muted text-nowrap ms-2"><?= htmlspecialchars(substr($item['ultima_mensagem_em'], 11, 5)) ?></small>
                                </div>
                                <div class="text-muted small"><?= htmlspecialchars($item['numero']) ?></div>
                                <div class="text-muted small text-truncate">
                                    <?= $item['ultima_mensagem'] !== null ? htmlspecialchars($item['ultima_mensagem']) : '(sem mensagens)' ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-8 d-flex flex-column">
                    <?php if (!$emEsperaSelecionado): ?>
                        <div class="d-flex align-items-center justify-content-center flex-grow-1 text-muted">
                            <div class="text-center">
                                <i class="bi bi-chat-square-text" style="font-size:2rem;"></i>
                                <p class="mb-0 mt-2">Selecione uma conversa pra ver o histórico completo.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="p-3 border-bottom">
                            <strong><?= htmlspecialchars($emEsperaSelecionado['contato_nome'] ?: '(sem nome)') ?></strong>
                            <span class="text-muted small ms-1"><?= htmlspecialchars($emEsperaSelecionado['numero']) ?></span>
                        </div>
                        <div class="p-3 flex-grow-1" style="overflow-y:auto; max-height:420px; background:#f5f7fb">
                            <?php foreach ($emEsperaMensagens as $m): ?>
                                <?= wppBolhaMensagem($m) ?>
                            <?php endforeach; ?>
                        </div>
                        <div class="p-3 border-top text-end">
                            <form method="post" action="<?= url('/whatsapp/chatbot/atender') ?>">
                                <input type="hidden" name="id" value="<?= (int)$emEsperaSelecionado['id'] ?>">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-hand-index-thumb"></i> Atender
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <script>
        document.getElementById('buscaEmEspera').addEventListener('input', function () {
            const filtro = this.value.trim().toLowerCase();
            document.querySelectorAll('#listaEmEspera .item-em-espera').forEach(function (item) {
                item.style.display = item.dataset.busca.includes(filtro) ? '' : 'none';
            });
        });
        </script>
    <?php endif; ?>

<?php elseif ($aba === 'fluxo'): ?>

    <?php if (!$raiz): ?>
        <div class="card border-0 shadow-sm" style="max-width:900px">
            <div class="card-body">
                <h6 class="mb-2">Mensagem de boas-vindas</h6>
                <p class="text-muted small">Enviada automaticamente na primeira mensagem de um contato novo. Depois de salvar, você cadastra as opções do menu logo abaixo.</p>
                <form method="post" action="<?= url('/whatsapp/chatbot/boas-vindas') ?>">
                    <?php wppCampoMensagem('mensagem', '', 'bubble', 4, 'Ex: {periodo}, {nome}! Bem-vindo ao atendimento.'); ?>
                    <div class="form-check my-2">
                        <input type="checkbox" name="ativo" class="form-check-input" id="chatbotAtivo" checked>
                        <label class="form-check-label small" for="chatbotAtivo">Chatbot ativo</label>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
                </form>
            </div>
        </div>
    <?php else: ?>

        <?php if ((int)$noAtual['id'] === (int)$raiz['id']): ?>
            <div class="card border-0 shadow-sm mb-3" style="max-width:1100px">
                <div class="card-body">
                    <h6 class="mb-1">Saudação</h6>
                    <p class="text-muted small mb-2">
                        Clique numa das opções abaixo pra inserir no texto -- a lista numerada das opções do menu é acrescentada sozinha depois desta mensagem, não precisa escrever aqui.
                    </p>
                    <form method="post" action="<?= url('/whatsapp/chatbot/boas-vindas') ?>">
                        <?php wppCampoMensagem('mensagem', $raiz['mensagem'], 'bubble', 4); ?>
                        <div class="form-check my-2">
                            <input type="checkbox" name="ativo" class="form-check-input" id="chatbotAtivo" <?= $raiz['ativo'] ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="chatbotAtivo">
                                Chatbot ativo (desmarcado: toda mensagem nova cai direto na fila geral, sem menu)
                            </label>
                        </div>
                        <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-check-lg"></i> Salvar saudação</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <nav class="mb-3">
                <?php foreach ($caminho as $indice => $no): ?>
                    <?php if ($indice > 0): ?><span class="text-muted mx-1">/</span><?php endif; ?>
                    <?php if ($indice === count($caminho) - 1): ?>
                        <strong><?= htmlspecialchars($indice === 0 ? 'Menu principal' : $no['rotulo']) ?></strong>
                    <?php else: ?>
                        <a href="<?= url('/whatsapp/chatbot?aba=fluxo&no_pai_id=' . (int)$no['id']) ?>"><?= htmlspecialchars($indice === 0 ? 'Menu principal' : $no['rotulo']) ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>

        <div class="card border-0 shadow-sm" style="max-width:1100px">
            <div class="card-body">
                <h6 class="mb-1">Opções do menu</h6>
                <p class="text-muted small">O número que o cliente digita é a posição (#1, #2...) mostrada em cada linha -- some sozinha na mensagem acima quando a opção é do tipo "Abre submenu".</p>

                <form method="post" action="<?= url('/whatsapp/chatbot/opcoes') ?>">
                    <input type="hidden" name="no_pai_id" value="<?= (int)$noAtual['id'] ?>">

                    <div id="listaOpcoes">
                        <?php foreach ($opcoes as $opcao): ?>
                            <?php wppLinhaOpcao($opcao, $setoresAtivos); ?>
                        <?php endforeach; ?>
                    </div>

                    <button type="button" id="botaoAdicionarOpcao" class="btn btn-sm btn-outline-secondary mb-3">
                        <i class="bi bi-plus-lg"></i> Adicionar opção
                    </button>
                    <br>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar fluxo</button>
                </form>
            </div>
        </div>

        <template id="templateLinhaOpcao">
            <?php wppLinhaOpcao([], $setoresAtivos); ?>
        </template>

        <script>
        (function () {
            const lista = document.getElementById('listaOpcoes');
            const template = document.getElementById('templateLinhaOpcao');

            function ligarLinha(linha) {
                const campoTipo = linha.querySelector('.campo-tipo');
                const campoSetor = linha.querySelector('.campo-setor');

                function atualizar() {
                    campoSetor.style.display = campoTipo.value === 'encaminhar_setor' ? '' : 'none';
                }

                campoTipo.addEventListener('change', atualizar);
                atualizar();

                linha.querySelector('.botao-remover').addEventListener('click', function () {
                    if (linha.dataset.existente === '1' && !confirm('Remover esta opção? Se ela tiver sub-opções, todas serão removidas também.')) {
                        return;
                    }
                    linha.remove();
                });

                const editorTemplate = linha.querySelector('.rd-editor-template');
                if (editorTemplate && window.rdInicializarEditorTemplate) {
                    window.rdInicializarEditorTemplate(editorTemplate);
                }
            }

            document.querySelectorAll('.linha-opcao').forEach(ligarLinha);

            document.getElementById('botaoAdicionarOpcao').addEventListener('click', function () {
                const clone = template.content.cloneNode(true);
                lista.appendChild(clone);
                ligarLinha(lista.lastElementChild);
            });
        })();
        </script>
    <?php endif; ?>

<?php elseif ($aba === 'finalizacao'): ?>

    <div class="card border-0 shadow-sm" style="max-width:1100px">
        <div class="card-body">
            <form method="post" action="<?= url('/whatsapp/chatbot/finalizacao') ?>">
                <h6 class="mb-2">Tempo geral de inatividade</h6>
                <div class="mb-3 d-flex align-items-center gap-2">
                    <input type="number" name="timeout_minutos" class="form-control form-control-sm" style="max-width:120px" min="5" max="1440" value="<?= (int)$timeoutMinutos ?>" required>
                    <span class="text-muted small">minutos</span>
                </div>

                <h6 class="mb-1">Encerramento normal</h6>
                <p class="text-muted small">Mandada ao cliente quando um atendente clica "Encerrar" na conversa.</p>
                <div class="mb-3">
                    <?php wppCampoMensagem('encerramento_normal', $encerramentoNormal, 'bubble', 3); ?>
                </div>

                <h6 class="mb-1">Encerramento por inatividade</h6>
                <p class="text-muted small">Mandada quando o atendimento é encerrado sozinho por falta de mensagem.</p>
                <div class="mb-3">
                    <?php wppCampoMensagem('encerramento_inatividade', $encerramentoInatividade, 'bubble', 3); ?>
                </div>

                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-3" style="max-width:1100px">
        <div class="card-body">
            <form method="post" action="<?= url('/whatsapp/chatbot/expediente') ?>">
                <h6 class="mb-1">Horário de expediente</h6>
                <p class="text-muted small">
                    Fora desse horário, quem manda a primeira mensagem recebe direto o aviso abaixo, sem passar pelo menu do bot
                    (quem já está na fila ou com um atendente não é afetado).
                </p>

                <div class="form-check mb-2">
                    <input type="checkbox" name="ativo" class="form-check-input" id="expedienteAtivo" <?= $expedienteAtivo ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="expedienteAtivo">Aplicar horário de expediente</label>
                </div>

                <div class="row g-2 mb-3" style="max-width:320px">
                    <div class="col-6">
                        <label class="form-label small text-muted mb-1">Início</label>
                        <input type="time" name="inicio" class="form-control form-control-sm" value="<?= htmlspecialchars($expedienteInicio) ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label small text-muted mb-1">Fim</label>
                        <input type="time" name="fim" class="form-control form-control-sm" value="<?= htmlspecialchars($expedienteFim) ?>">
                    </div>
                </div>

                <h6 class="mb-1">Mensagem fora do expediente</h6>
                <div class="mb-3">
                    <?php wppCampoMensagem('mensagem', $mensagemForaExpediente, 'bubble', 3); ?>
                </div>

                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
            </form>
        </div>
    </div>

<?php elseif ($aba === 'mensagens-rapidas'): ?>

    <div class="card border-0 shadow-sm mb-3" style="max-width:720px">
        <div class="card-body">
            <h6 class="mb-2">Nova mensagem rápida</h6>
            <form method="post" action="<?= url('/whatsapp/mensagens-rapidas/criar') ?>" class="row g-2">
                <div class="col-md-3">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">/</span>
                        <input type="text" name="comando" class="form-control" placeholder="comando" required maxlength="50">
                    </div>
                </div>
                <div class="col-md-7">
                    <input type="text" name="mensagem" class="form-control form-control-sm" placeholder="Mensagem completa" required>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-plus-lg"></i> Criar</button>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($mensagensRapidas)): ?>
        <p class="text-muted">Nenhuma mensagem rápida cadastrada ainda.</p>
    <?php endif; ?>

    <?php foreach ($mensagensRapidas as $item): ?>
        <?php $idColapso = 'mr' . (int)$item['id']; ?>
        <div class="card border-0 shadow-sm mb-2" style="max-width:900px">
            <div class="card-header bg-white" style="cursor:pointer" data-bs-toggle="collapse" data-bs-target="#<?= $idColapso ?>">
                <code>/<?= htmlspecialchars($item['comando']) ?></code>
                <span class="text-muted small ms-2 text-truncate d-inline-block align-middle" style="max-width:500px"><?= htmlspecialchars($item['mensagem']) ?></span>
            </div>
            <div class="collapse" id="<?= $idColapso ?>">
                <div class="card-body">
                    <form method="post" action="<?= url('/whatsapp/mensagens-rapidas/atualizar') ?>" class="mb-2">
                        <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                        <div class="row g-2 mb-2">
                            <div class="col-md-3">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">/</span>
                                    <input type="text" name="comando" class="form-control" value="<?= htmlspecialchars($item['comando']) ?>" required maxlength="50">
                                </div>
                            </div>
                            <div class="col-md-9">
                                <input type="text" name="mensagem" class="form-control form-control-sm" value="<?= htmlspecialchars($item['mensagem']) ?>" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-check-lg"></i> Salvar</button>
                    </form>
                    <form method="post" action="<?= url('/whatsapp/mensagens-rapidas/excluir') ?>" onsubmit="return confirm('Excluir esta mensagem rápida?');">
                        <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Excluir</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

<?php endif; ?>

<script>
(function () {
    function periodoAtual() {
        const hora = new Date().getHours();
        return hora < 12 ? 'Bom dia' : (hora < 18 ? 'Boa tarde' : 'Boa noite');
    }

    function renderizarPreview(texto) {
        return texto.replace(/\{periodo\}/g, periodoAtual()).replace(/\{nome\}/g, 'Maria');
    }

    // Global (não dentro da IIFE) -- o script do fluxo, que roda antes
    // deste no HTML, precisa chamar isso quando o usuário clica "+
    // Adicionar opção" e clona uma linha nova. Como isso só acontece
    // num clique (depois da página inteira já ter carregado), a ordem
    // de execução dos <script> não é problema: esta função já existe
    // no momento em que o clique acontece.
    window.rdInicializarEditorTemplate = function (container) {
        const textarea = container.querySelector('.campo-mensagem-template');
        const preview = container.querySelector('.rd-preview-alvo');
        if (!textarea) {
            return;
        }

        function atualizar() {
            if (preview) {
                preview.textContent = renderizarPreview(textarea.value) || '(mensagem vazia)';
            }
        }

        textarea.addEventListener('input', atualizar);

        container.querySelectorAll('.rd-chip').forEach(function (chip) {
            chip.addEventListener('click', function () {
                const trecho = chip.dataset.insere;
                const inicio = textarea.selectionStart != null ? textarea.selectionStart : textarea.value.length;
                const fim = textarea.selectionEnd != null ? textarea.selectionEnd : textarea.value.length;
                const valor = textarea.value;

                textarea.value = valor.slice(0, inicio) + trecho + valor.slice(fim);

                const novaPosicao = inicio + trecho.length;
                textarea.focus();
                textarea.setSelectionRange(novaPosicao, novaPosicao);

                atualizar();
            });
        });
    };

    document.querySelectorAll('.rd-editor-template').forEach(window.rdInicializarEditorTemplate);
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'WhatsApp - Chatbot';

require __DIR__ . '/../layouts/main.php';
