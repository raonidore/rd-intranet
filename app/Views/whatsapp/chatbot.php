<?php
ob_start();

use App\Components\Alert;

const WPP_CHATBOT_ROTULOS_TIPO = [
    'menu' => 'Menu (leva a mais opções)',
    'resposta_final' => 'Resposta e encerra',
    'encaminhar_setor' => 'Encaminha pro setor',
];

const WPP_CHATBOT_BADGE_TIPO = [
    'menu' => 'text-bg-primary',
    'resposta_final' => 'text-bg-secondary',
    'encaminhar_setor' => 'text-bg-success',
];

/**
 * Renderiza um nó da árvore (recursivo) -- cada nó mostra sua posição
 * (1, 2, 3... entre os irmãos ativos), que é o número que o admin
 * precisa escrever na mensagem do nó PAI pro cliente escolher (o motor
 * não gera a lista numerada sozinho, a mensagem é texto livre).
 */
function renderizarNoChatbot(array $no, int $profundidade, array $setoresAtivos): void
{
    $idColapso = 'no' . (int)$no['id'];
    $badge = WPP_CHATBOT_BADGE_TIPO[$no['tipo']] ?? 'text-bg-secondary';
    ?>
    <div class="border rounded mb-2" style="margin-left:<?= $profundidade * 28 ?>px">
        <div class="p-2 d-flex justify-content-between align-items-center" style="cursor:pointer" data-bs-toggle="collapse" data-bs-target="#<?= $idColapso ?>">
            <div>
                <span class="badge text-bg-light border me-1">#<?= (int)$no['posicao'] ?></span>
                <strong><?= htmlspecialchars($no['rotulo']) ?></strong>
                <span class="badge <?= $badge ?> ms-1"><?= htmlspecialchars(WPP_CHATBOT_ROTULOS_TIPO[$no['tipo']] ?? $no['tipo']) ?></span>
                <?= $no['ativo'] ? '' : '<span class="badge text-bg-secondary ms-1">Inativa</span>' ?>
            </div>
            <i class="bi bi-chevron-down text-muted"></i>
        </div>
        <div class="collapse" id="<?= $idColapso ?>">
            <div class="p-3 border-top">
                <form method="post" action="<?= url('/whatsapp/chatbot/atualizar') ?>" class="mb-3">
                    <input type="hidden" name="id" value="<?= (int)$no['id'] ?>">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label small">Rótulo (uso interno, não vai pro cliente)</label>
                            <input type="text" name="rotulo" class="form-control form-control-sm" value="<?= htmlspecialchars($no['rotulo']) ?>" required maxlength="150">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Tipo</label>
                            <select name="tipo" class="form-select form-select-sm campo-tipo-chatbot" required>
                                <?php foreach (WPP_CHATBOT_ROTULOS_TIPO as $valor => $rotuloTipo): ?>
                                    <option value="<?= $valor ?>" <?= $no['tipo'] === $valor ? 'selected' : '' ?>><?= htmlspecialchars($rotuloTipo) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Mensagem enviada ao cliente ao entrar aqui</label>
                            <textarea name="mensagem" class="form-control form-control-sm" rows="3" required><?= htmlspecialchars($no['mensagem']) ?></textarea>
                        </div>
                        <div class="col-md-6 campo-setor-chatbot" style="<?= $no['tipo'] === 'encaminhar_setor' ? '' : 'display:none' ?>">
                            <label class="form-label small">Setor de destino</label>
                            <select name="setor_destino_id" class="form-select form-select-sm">
                                <option value="">Selecione...</option>
                                <?php foreach ($setoresAtivos as $s): ?>
                                    <option value="<?= (int)$s['id'] ?>" <?= (int)($no['setor_destino_id'] ?? 0) === (int)$s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check">
                                <input type="checkbox" name="ativo" class="form-check-input" id="ativo<?= (int)$no['id'] ?>" <?= $no['ativo'] ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="ativo<?= (int)$no['id'] ?>">Ativa</label>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-sm btn-outline-primary mt-2">
                        <i class="bi bi-check-lg"></i> Salvar
                    </button>
                </form>

                <form method="post" action="<?= url('/whatsapp/chatbot/excluir') ?>" class="d-inline" onsubmit="return confirm('Excluir esta opção e tudo que estiver abaixo dela na árvore?');">
                    <input type="hidden" name="id" value="<?= (int)$no['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-trash"></i> Excluir
                    </button>
                </form>

                <?php if ($no['tipo'] === 'menu'): ?>
                    <hr>
                    <h6 class="small text-muted">Adicionar opção abaixo de "<?= htmlspecialchars($no['rotulo']) ?>"</h6>
                    <?php renderizarFormularioNovaOpcao((int)$no['id'], $setoresAtivos); ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
    foreach ($no['filhos'] as $filho) {
        renderizarNoChatbot($filho, $profundidade + 1, $setoresAtivos);
    }
}

function renderizarFormularioNovaOpcao(int $noPaiId, array $setoresAtivos): void
{
    ?>
    <form method="post" action="<?= url('/whatsapp/chatbot/criar') ?>">
        <input type="hidden" name="no_pai_id" value="<?= $noPaiId ?>">
        <div class="row g-2">
            <div class="col-md-6">
                <label class="form-label small">Rótulo (uso interno)</label>
                <input type="text" name="rotulo" class="form-control form-control-sm" placeholder="Ex: Suporte técnico" required maxlength="150">
            </div>
            <div class="col-md-6">
                <label class="form-label small">Tipo</label>
                <select name="tipo" class="form-select form-select-sm campo-tipo-chatbot" required>
                    <?php foreach (WPP_CHATBOT_ROTULOS_TIPO as $valor => $rotuloTipo): ?>
                        <option value="<?= $valor ?>"><?= htmlspecialchars($rotuloTipo) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label small">Mensagem enviada ao cliente ao escolher esta opção</label>
                <textarea name="mensagem" class="form-control form-control-sm" rows="2" placeholder="Ex: Você foi encaminhado para o Suporte técnico. Aguarde um instante." required></textarea>
            </div>
            <div class="col-md-6 campo-setor-chatbot" style="display:none">
                <label class="form-label small">Setor de destino</label>
                <select name="setor_destino_id" class="form-select form-select-sm">
                    <option value="">Selecione...</option>
                    <?php foreach ($setoresAtivos as $s): ?>
                        <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <button type="submit" class="btn btn-sm btn-primary mt-2">
            <i class="bi bi-plus-lg"></i> Adicionar opção
        </button>
    </form>
    <?php
}
?>

<?= Alert::flash() ?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-robot me-1"></i> WhatsApp - Chatbot</h4>
    <small class="text-muted">Menu automático em árvore: o cliente responde com o número da opção. A numeração usada pelo motor é a posição de cada opção aqui embaixo -- escreva esse número na mensagem do nível acima.</small>
</div>

<div class="card border-0 shadow-sm mb-3" style="max-width:720px">
    <div class="card-body">
        <h6 class="mb-2">Mensagem de boas-vindas</h6>
        <p class="text-muted small">Enviada automaticamente na primeira mensagem de um contato novo. Inclua aqui a lista numerada das opções que você for cadastrar abaixo.</p>
        <form method="post" action="<?= url('/whatsapp/chatbot/boas-vindas') ?>">
            <textarea name="mensagem" class="form-control mb-2" rows="4" placeholder="Ex: Olá! Bem-vindo ao atendimento. Digite o número da opção desejada:&#10;1 - Suporte técnico&#10;2 - Financeiro" required><?= htmlspecialchars($raiz['mensagem'] ?? '') ?></textarea>
            <div class="form-check mb-2">
                <input type="checkbox" name="ativo" class="form-check-input" id="chatbotAtivo" <?= (!$raiz || $raiz['ativo']) ? 'checked' : '' ?>>
                <label class="form-check-label small" for="chatbotAtivo">
                    Chatbot ativo (desmarcado: toda mensagem nova cai direto na fila geral, sem menu)
                </label>
            </div>
            <button type="submit" class="btn btn-sm btn-primary">
                <i class="bi bi-check-lg"></i> Salvar
            </button>
        </form>
    </div>
</div>

<?php if ($raiz): ?>
    <div style="max-width:900px">
        <h6 class="mb-2">Opções do menu</h6>
        <?php if (empty($opcoes)): ?>
            <p class="text-muted small">Nenhuma opção cadastrada ainda.</p>
        <?php endif; ?>
        <?php foreach ($opcoes as $indice => $no): $no['posicao'] = $indice + 1; renderizarNoChatbot($no, 0, $setoresAtivos); endforeach; ?>

        <div class="card border-0 shadow-sm mt-3">
            <div class="card-body">
                <h6 class="small text-muted mb-2">Adicionar opção de primeiro nível (direto no menu de boas-vindas)</h6>
                <?php renderizarFormularioNovaOpcao((int)$raiz['id'], $setoresAtivos); ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
document.addEventListener('change', function (evento) {
    if (!evento.target.classList.contains('campo-tipo-chatbot')) {
        return;
    }
    const container = evento.target.closest('form');
    const campoSetor = container.querySelector('.campo-setor-chatbot');
    if (campoSetor) {
        campoSetor.style.display = evento.target.value === 'encaminhar_setor' ? '' : 'none';
    }
});
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'WhatsApp - Chatbot';

require __DIR__ . '/../layouts/main.php';
