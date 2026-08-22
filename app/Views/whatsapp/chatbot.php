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
            <textarea name="mensagem[]" class="form-control form-control-sm" rows="2" placeholder="Mensagem enviada ao cliente ao escolher/entrar aqui (opcional pra &quot;Encaminha pro setor&quot;)"><?= htmlspecialchars($opcao['mensagem'] ?? '') ?></textarea>
        </div>
    </div>
    <?php
}
?>

<?= Alert::flash() ?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-robot me-1"></i> WhatsApp - Chatbot</h4>
    <small class="text-muted">Menu automático em árvore: a numeração das opções é gerada sozinha, o cliente só digita o número.</small>
</div>

<ul class="nav nav-tabs mb-3">
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

<?php if ($aba === 'fluxo'): ?>

    <?php if (!$raiz): ?>
        <div class="card border-0 shadow-sm" style="max-width:720px">
            <div class="card-body">
                <h6 class="mb-2">Mensagem de boas-vindas</h6>
                <p class="text-muted small">Enviada automaticamente na primeira mensagem de um contato novo. Depois de salvar, você cadastra as opções do menu logo abaixo.</p>
                <form method="post" action="<?= url('/whatsapp/chatbot/boas-vindas') ?>">
                    <textarea name="mensagem" class="form-control mb-2" rows="4" placeholder="Ex: {periodo}, {nome}! Bem-vindo ao atendimento." required></textarea>
                    <div class="form-check mb-2">
                        <input type="checkbox" name="ativo" class="form-check-input" id="chatbotAtivo" checked>
                        <label class="form-check-label small" for="chatbotAtivo">Chatbot ativo</label>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
                </form>
            </div>
        </div>
    <?php else: ?>

        <?php if ((int)$noAtual['id'] === (int)$raiz['id']): ?>
            <div class="card border-0 shadow-sm mb-3" style="max-width:900px">
                <div class="card-body">
                    <h6 class="mb-2">Saudação</h6>
                    <p class="text-muted small mb-2">
                        Use <code>{nome}</code> pro primeiro nome do cliente e <code>{periodo}</code> pra "Bom dia/Boa tarde/Boa noite" automático.
                        A lista numerada das opções abaixo é acrescentada sozinha, não precisa escrever aqui.
                    </p>
                    <form method="post" action="<?= url('/whatsapp/chatbot/boas-vindas') ?>">
                        <textarea name="mensagem" class="form-control mb-2" rows="3" required><?= htmlspecialchars($raiz['mensagem']) ?></textarea>
                        <div class="form-check mb-2">
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

        <div class="card border-0 shadow-sm" style="max-width:900px">
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

    <div class="card border-0 shadow-sm" style="max-width:720px">
        <div class="card-body">
            <form method="post" action="<?= url('/whatsapp/chatbot/finalizacao') ?>">
                <h6 class="mb-2">Tempo geral de inatividade</h6>
                <div class="mb-3 d-flex align-items-center gap-2">
                    <input type="number" name="timeout_minutos" class="form-control form-control-sm" style="max-width:120px" min="5" max="1440" value="<?= (int)$timeoutMinutos ?>" required>
                    <span class="text-muted small">minutos</span>
                </div>

                <h6 class="mb-2">Encerramento normal</h6>
                <p class="text-muted small">Mandada ao cliente quando um atendente clica "Encerrar" na conversa. Use <code>{nome}</code>.</p>
                <textarea name="encerramento_normal" class="form-control mb-3" rows="3" required><?= htmlspecialchars($encerramentoNormal) ?></textarea>

                <h6 class="mb-2">Encerramento por inatividade</h6>
                <p class="text-muted small">Mandada quando o atendimento é encerrado sozinho por falta de mensagem. Use <code>{nome}</code>.</p>
                <textarea name="encerramento_inatividade" class="form-control mb-3" rows="3" required><?= htmlspecialchars($encerramentoInatividade) ?></textarea>

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

<?php
$conteudo = ob_get_clean();
$titulo = 'WhatsApp - Chatbot';

require __DIR__ . '/../layouts/main.php';
