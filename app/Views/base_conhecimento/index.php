<?php

use App\Components\Alert;
use App\Components\Badge;

ob_start();

$statusLabel = [
    'nao_enviado' => Badge::make('Só local', 'secondary'),
    'proposto' => Badge::make('Aguardando moderação', 'warning'),
    'aprovado' => Badge::make('Aprovado (público)', 'success'),
    'rejeitado' => Badge::make('Rejeitado', 'danger'),
];

$subcategoriasPorCategoria = [];
foreach ($subcategorias as $s) {
    $subcategoriasPorCategoria[$s['categoria_id']][] = $s;
}

$abasValidas = ['meus', 'publicos'];
if ($podeCriar) {
    $abasValidas[] = 'novo';
}
$abaAtiva = in_array($_GET['aba'] ?? '', $abasValidas, true) ? $_GET['aba'] : ($edicao ? 'novo' : 'meus');
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/prismjs@1/themes/prism-tomorrow.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/prismjs@1/plugins/toolbar/prism-toolbar.min.css">

<style>
.kb-code-bloco { position: relative; margin: 8px 0; }
.kb-code { background: #212529; color: #e9ecef; padding: 12px 44px 12px 14px; border-radius: 8px; font-size: 13px; white-space: pre-wrap; word-break: break-word; margin: 0; }
.kb-botao-copiar { position: absolute; top: 8px; right: 8px; }
.kb-imagem-thumb { width: 90px; height: 90px; object-fit: cover; border-radius: 6px; border: 1px solid #dee2e6; margin: 4px; cursor: pointer; }

.kb-toolbar { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 6px; }
.kb-editor {
    min-height: 160px; height: auto; padding: 10px 12px; overflow-y: auto;
    font-size: 14px; line-height: 1.5;
}
.kb-editor:empty:before { content: attr(data-placeholder); color: #adb5bd; }
.kb-editor pre { background: #212529; color: #e9ecef; padding: 10px 12px; border-radius: 6px; margin: 6px 0; }
.kb-editor pre code { font-size: 13px; outline: none; }
.kb-solucao-render pre { margin: 8px 0; border-radius: 8px; }
.kb-solucao-render :is(p, div) { margin-bottom: 6px; }
</style>

<div class="mb-3 d-flex justify-content-between align-items-end flex-wrap gap-2">
    <div>
        <h4 class="mb-1"><i class="bi bi-journal-text me-1"></i> Base de Conhecimento</h4>
        <small class="text-muted">Registre a solução de um problema pra não precisar redescobrir da próxima vez. Marque como "Pública" pra propor pra todos os outros clientes (passa por moderação antes de aparecer).</small>
    </div>
    <form method="get" action="<?= url('/base-conhecimento') ?>" class="d-flex gap-2" id="formBusca">
        <input type="hidden" name="aba" id="campoAbaBusca" value="<?= htmlspecialchars($abaAtiva) ?>">
        <input type="search" name="q" class="form-control form-control-sm" placeholder="Pesquisar em qualquer aba..." value="<?= htmlspecialchars($busca) ?>" style="width:260px">
        <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
    </form>
</div>

<?= Alert::flash() ?>

<ul class="nav nav-tabs mb-3" id="abasKb">
    <li class="nav-item">
        <button class="nav-link <?= $abaAtiva === 'meus' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#abaMeus" type="button">
            <i class="bi bi-person-lines-fill me-1"></i> Meus artigos <span class="badge bg-secondary ms-1"><?= count($meus) ?></span>
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link <?= $abaAtiva === 'publicos' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#abaPublicos" type="button">
            <i class="bi bi-globe2 me-1"></i> Base pública <span class="badge bg-secondary ms-1"><?= count($publicos) ?></span>
        </button>
    </li>
    <?php if ($podeCriar): ?>
    <li class="nav-item">
        <button class="nav-link <?= $abaAtiva === 'novo' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#abaNovo" type="button">
            <i class="bi bi-plus-lg me-1"></i> Novo artigo
        </button>
    </li>
    <?php endif; ?>
</ul>

<div class="tab-content">

<div class="tab-pane fade <?= $abaAtiva === 'meus' ? 'show active' : '' ?>" id="abaMeus">
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <?php if (empty($meus)): ?>
                <p class="text-muted small mb-0">Nenhum artigo encontrado<?= $busca !== '' ? ' pra "' . htmlspecialchars($busca) . '"' : '' ?>.</p>
            <?php endif; ?>
            <?php foreach ($meus as $a): ?>
                <div class="border rounded p-2 mt-2">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <strong><?= htmlspecialchars($a['titulo']) ?></strong>
                            <?php if ($a['categoria_nome']): ?><span class="badge bg-light text-dark border ms-1"><?= htmlspecialchars($a['categoria_nome']) ?><?= $a['subcategoria_nome'] ? ' / ' . htmlspecialchars($a['subcategoria_nome']) : '' ?></span><?php endif; ?>
                        </div>
                        <?= $statusLabel[$a['status_central']] ?? '' ?>
                    </div>
                    <?php if ($a['problema']): ?><div class="small text-muted mt-1"><strong>Problema:</strong> <?= kbRenderizarTexto($a['problema']) ?></div><?php endif; ?>
                    <div class="small"><strong>Solução:</strong></div>
                    <div class="small kb-solucao-render"><?= $a['solucao'] ?></div>
                    <?php if (!empty($a['imagens'])): ?>
                        <div class="mt-2">
                            <?php foreach ($a['imagens'] as $img): ?>
                                <a href="<?= url('/base-conhecimento/imagem?id=' . (int)$img['id']) ?>" target="_blank">
                                    <img src="<?= url('/base-conhecimento/imagem?id=' . (int)$img['id']) ?>" class="kb-imagem-thumb" alt="">
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($podeCriar): ?>
                        <div class="d-flex gap-2 mt-2">
                            <a href="<?= url('/base-conhecimento?aba=novo&editar=' . (int)$a['id']) ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil"></i> Editar
                            </a>
                            <form method="post" action="<?= url('/base-conhecimento/excluir') ?>" onsubmit="return confirm('Excluir este artigo?');">
                                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Excluir</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="tab-pane fade <?= $abaAtiva === 'publicos' ? 'show active' : '' ?>" id="abaPublicos">
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted small">De todos os clientes que usam a base central.</span>
                <button type="button" class="btn btn-sm btn-outline-primary" id="botaoSincronizar" <?= $centralConfigurada ? '' : 'disabled' ?>>
                    <i class="bi bi-arrow-repeat"></i> Sincronizar agora
                </button>
            </div>
            <?php if (!$centralConfigurada): ?>
                <p class="text-muted small mt-2 mb-0">Base central não configurada (fale com um administrador em Sistema > Integrações).</p>
            <?php elseif (empty($publicos)): ?>
                <p class="text-muted small mt-2 mb-0">Nenhum artigo público encontrado<?= $busca !== '' ? ' pra "' . htmlspecialchars($busca) . '"' : '' ?>.</p>
            <?php endif; ?>
            <?php foreach ($publicos as $a): ?>
                <div class="border rounded p-2 mt-2">
                    <strong><?= htmlspecialchars($a['titulo']) ?></strong>
                    <?php if ($a['categoria']): ?><span class="badge bg-light text-dark border ms-1"><?= htmlspecialchars($a['categoria']) ?></span><?php endif; ?>
                    <?php if ($a['problema']): ?><div class="small text-muted mt-1"><strong>Problema:</strong> <?= kbRenderizarTexto($a['problema']) ?></div><?php endif; ?>
                    <div class="small"><strong>Solução:</strong></div>
                    <div class="small kb-solucao-render"><?= $a['solucao'] ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php if ($podeCriar): ?>
<div class="tab-pane fade <?= $abaAtiva === 'novo' ? 'show active' : '' ?>" id="abaNovo">
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <p class="text-muted small mb-3">
                Envolva comandos com <code>```</code> (três crases, antes e depois) que eles viram um bloco com botão de copiar automaticamente.
            </p>
            <?php if ($edicao): ?>
                <div class="alert alert-info py-2 small d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-pencil"></i> Editando "<?= htmlspecialchars($edicao['titulo']) ?>"</span>
                    <a href="<?= url('/base-conhecimento?aba=novo') ?>" class="link-dark">Cancelar edição / criar novo</a>
                </div>
            <?php endif; ?>
            <form method="post" action="<?= url($edicao ? '/base-conhecimento/atualizar' : '/base-conhecimento/criar') ?>" enctype="multipart/form-data">
                <?php if ($edicao): ?><input type="hidden" name="id" value="<?= (int)$edicao['id'] ?>"><?php endif; ?>
                <div class="row g-2">
                    <div class="col-md-8">
                        <label class="form-label small mb-1">Título</label>
                        <input type="text" name="titulo" class="form-control form-control-sm" value="<?= htmlspecialchars($edicao['titulo'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Categoria</label>
                        <select name="categoria_id" id="selectCategoria" class="form-select form-select-sm">
                            <option value="">--</option>
                            <?php foreach ($categorias as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= (int)($edicao['categoria_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Subcategoria</label>
                        <select name="subcategoria_id" id="selectSubcategoria" class="form-select form-select-sm" data-selecionada="<?= (int)($edicao['subcategoria_id'] ?? 0) ?>">
                            <option value="">--</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small mb-1">Problema (opcional)</label>
                        <textarea name="problema" class="form-control form-control-sm" rows="2"><?= htmlspecialchars($edicao['problema'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label small mb-1">Solução</label>
                        <div class="kb-toolbar">
                            <select class="form-select form-select-sm" id="kbFonte" style="width:110px" title="Tamanho da fonte">
                                <option value="12">Pequena</option>
                                <option value="14" selected>Normal</option>
                                <option value="18">Grande</option>
                                <option value="24">Enorme</option>
                            </select>
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-outline-secondary" id="kbNegrito" title="Negrito"><i class="bi bi-type-bold"></i></button>
                                <button type="button" class="btn btn-outline-secondary" id="kbItalico" title="Itálico"><i class="bi bi-type-italic"></i></button>
                                <button type="button" class="btn btn-outline-secondary" id="kbSublinhado" title="Sublinhado"><i class="bi bi-type-underline"></i></button>
                            </div>
                            <div class="dropdown">
                                <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                                    <i class="bi bi-terminal"></i> Inserir comando
                                </button>
                                <ul class="dropdown-menu" id="kbListaLinguagens">
                                    <?php foreach ($linguagensComando as $slug => $rotulo): ?>
                                        <li><a class="dropdown-item" href="#" data-lang="<?= htmlspecialchars($slug) ?>"><?= htmlspecialchars($rotulo) ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                        <div id="kbEditorSolucao" class="form-control form-control-sm kb-editor" contenteditable="true" data-placeholder="Descreva a solução. Use o botão “Inserir comando” pra adicionar um trecho de código colorido."><?= $edicao['solucao'] ?? '' ?></div>
                        <input type="hidden" name="solucao" id="kbCampoSolucaoOculto">
                    </div>
                    <div class="col-12">
                        <label class="form-label small mb-1">Imagens (opcional)</label>
                        <input type="file" name="imagens[]" accept=".jpg,.jpeg,.png,.gif,.webp" multiple class="form-control form-control-sm">
                        <?php if ($edicao): ?><small class="text-muted">Novas imagens se somam às já existentes -- excluir imagem antiga ainda não tem botão, avise se precisar.</small><?php endif; ?>
                    </div>
                    <div class="col-12 d-flex justify-content-between align-items-center mt-2">
                        <?php if ($edicao): ?>
                            <span class="small text-muted">
                                <?= $edicao['visibilidade'] === 'publico' ? 'Este artigo é público -- editar aqui só muda a cópia local (veja aviso acima do formulário).' : 'Artigo privado.' ?>
                            </span>
                        <?php else: ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="visibilidade" value="publico" id="chkPublico" <?= $centralConfigurada ? '' : 'disabled' ?>>
                                <label class="form-check-label small" for="chkPublico">
                                    Propor como pública (visível pra todos os clientes, depois de aprovada)
                                    <?php if (!$centralConfigurada): ?><span class="text-muted">-- base central não configurada</span><?php endif; ?>
                                </label>
                            </div>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-sm btn-primary"><?= $edicao ? 'Salvar alterações' : 'Salvar' ?></button>
                    </div>
                </div>
            </form>

            <details class="mt-3">
                <summary class="text-primary small" style="cursor:pointer">Gerenciar categorias/subcategorias</summary>
                <div class="row g-3 mt-1">
                    <div class="col-md-6">
                        <form method="post" action="<?= url('/base-conhecimento/categoria/criar') ?>" class="d-flex gap-2 mb-2">
                            <input type="text" name="nome" class="form-control form-control-sm" placeholder="Nova categoria" required>
                            <button type="submit" class="btn btn-sm btn-outline-primary text-nowrap">Criar</button>
                        </form>
                        <?php foreach ($categorias as $c): ?>
                            <div class="d-flex justify-content-between align-items-center small border-bottom py-1">
                                <?= htmlspecialchars($c['nome']) ?>
                                <form method="post" action="<?= url('/base-conhecimento/categoria/excluir') ?>" onsubmit="return confirm('Excluir esta categoria (e suas subcategorias)?');">
                                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-link text-danger p-0">excluir</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="col-md-6">
                        <form method="post" action="<?= url('/base-conhecimento/subcategoria/criar') ?>" class="d-flex gap-2 mb-2">
                            <select name="categoria_id" class="form-select form-select-sm" required>
                                <option value="">Categoria...</option>
                                <?php foreach ($categorias as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="nome" class="form-control form-control-sm" placeholder="Nova subcategoria" required>
                            <button type="submit" class="btn btn-sm btn-outline-primary text-nowrap">Criar</button>
                        </form>
                        <?php foreach ($subcategorias as $s): ?>
                            <div class="d-flex justify-content-between align-items-center small border-bottom py-1">
                                <?= htmlspecialchars($s['categoria_nome']) ?> / <?= htmlspecialchars($s['nome']) ?>
                                <form method="post" action="<?= url('/base-conhecimento/subcategoria/excluir') ?>" onsubmit="return confirm('Excluir esta subcategoria?');">
                                    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-link text-danger p-0">excluir</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </details>
        </div>
    </div>
</div>
<?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/prismjs@1/components/prism-core.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/prismjs@1/plugins/autoloader/prism-autoloader.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/prismjs@1/plugins/toolbar/prism-toolbar.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/prismjs@1/plugins/copy-to-clipboard/prism-copy-to-clipboard.min.js"></script>
<script>
(function () {
    const subcategoriasPorCategoria = <?= json_encode($subcategoriasPorCategoria) ?>;
    const selectCategoria = document.getElementById('selectCategoria');
    const selectSubcategoria = document.getElementById('selectSubcategoria');

    function preencherSubcategorias(categoriaId, subcategoriaSelecionada) {
        selectSubcategoria.innerHTML = '<option value="">--</option>';
        const itens = subcategoriasPorCategoria[categoriaId] || [];
        itens.forEach(function (s) {
            const opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = s.nome;
            if (subcategoriaSelecionada && String(s.id) === String(subcategoriaSelecionada)) {
                opt.selected = true;
            }
            selectSubcategoria.appendChild(opt);
        });
    }

    if (selectCategoria && selectSubcategoria) {
        selectCategoria.addEventListener('change', function () {
            preencherSubcategorias(this.value, null);
        });

        // Modo edicao: categoria ja vem pre-selecionada no HTML -- preenche
        // a subcategoria correspondente na carga da pagina tambem.
        if (selectCategoria.value) {
            preencherSubcategorias(selectCategoria.value, selectSubcategoria.dataset.selecionada);
        }
    }
})();

// Mantem a busca associada a aba atual mesmo depois de trocar de aba --
// sem isso, pesquisar sempre voltava pra "Meus artigos" (aba padrao).
document.querySelectorAll('#abasKb button[data-bs-toggle="tab"]').forEach(function (botaoAba) {
    botaoAba.addEventListener('shown.bs.tab', function () {
        const alvo = this.getAttribute('data-bs-target').replace('#aba', '').toLowerCase();
        const mapa = { meus: 'meus', publicos: 'publicos', novo: 'novo' };
        document.getElementById('campoAbaBusca').value = mapa[alvo] || 'meus';
    });
});

(function () {
    const botao = document.getElementById('botaoSincronizar');
    if (!botao) return;

    botao.addEventListener('click', async function () {
        botao.disabled = true;
        botao.innerHTML = '<i class="bi bi-hourglass-split"></i> Sincronizando...';

        try {
            const res = await fetch(<?= json_encode(url('/base-conhecimento/sincronizar')) ?>, { method: 'POST' });
            const resultado = await res.json();
            alert(resultado.message || (resultado.success ? 'Sincronizado.' : 'Falha ao sincronizar.'));
            if (resultado.success) location.reload();
        } catch (e) {
            alert('Erro ao comunicar com o servidor.');
        } finally {
            botao.disabled = false;
            botao.innerHTML = '<i class="bi bi-arrow-repeat"></i> Sincronizar agora';
        }
    });
})();

// -- Editor rico da Solução (negrito/itálico/sublinhado/tamanho + blocos de comando) --
(function () {
    const editor = document.getElementById('kbEditorSolucao');
    if (!editor) return;

    const campoOculto = document.getElementById('kbCampoSolucaoOculto');
    const form = editor.closest('form');

    // Cola sempre como texto puro -- sem isso, colar algo com marcacao
    // (copiado de um site, de um editor de codigo, ate de outro programa)
    // faz o navegador inserir como ELEMENTOS DE VERDADE dentro do editor
    // (ex: <html>, <head>, <title> viram nos reais, nao texto), e o
    // sanitizador do servidor entao remove essas tags (nao sao permitidas)
    // mantendo so o texto de dentro -- perde a formatacao/estrutura toda.
    editor.addEventListener('paste', function (e) {
        e.preventDefault();
        const texto = (e.clipboardData || window.clipboardData).getData('text/plain');
        document.execCommand('insertText', false, texto);
    });

    // -- Colorir ao vivo enquanto digita dentro de um bloco de comando --
    // Prism.highlightElement() reescreve o innerHTML do <code> (troca o
    // texto puro por spans coloridos) -- se fizer isso a cada tecla sem
    // cuidado, o cursor "pula" pro inicio a cada letra digitada. Por isso:
    // guarda a posicao do cursor em NUMERO DE CARACTERES antes de
    // realcar, e reposiciona depois pelo mesmo numero.
    function kbOffsetDoCursor(elemento) {
        const sel = window.getSelection();
        if (!sel.rangeCount) return 0;
        const preRange = sel.getRangeAt(0).cloneRange();
        preRange.selectNodeContents(elemento);
        preRange.setEnd(sel.getRangeAt(0).endContainer, sel.getRangeAt(0).endOffset);
        return preRange.toString().length;
    }

    function kbRestaurarCursor(elemento, offset) {
        const sel = window.getSelection();
        const range = document.createRange();
        let restante = offset;
        let encontrado = false;

        (function percorrer(no) {
            if (encontrado) return;
            if (no.nodeType === Node.TEXT_NODE) {
                if (restante <= no.length) {
                    range.setStart(no, restante);
                    range.collapse(true);
                    encontrado = true;
                } else {
                    restante -= no.length;
                }
            } else {
                for (const filho of no.childNodes) {
                    percorrer(filho);
                    if (encontrado) return;
                }
            }
        })(elemento);

        if (!encontrado) {
            range.selectNodeContents(elemento);
            range.collapse(false);
        }
        sel.removeAllRanges();
        sel.addRange(range);
    }

    function kbRealcarCodigo(elementoCode, manterCursor) {
        if (!window.Prism) return;
        const offset = manterCursor ? kbOffsetDoCursor(elementoCode) : null;
        elementoCode.textContent = elementoCode.textContent; // normaliza (remove spans antigos do Prism)
        Prism.highlightElement(elementoCode);

        // O plugin de toolbar/copiar do Prism (carregado global, pra
        // funcionar na exibicao dos artigos salvos) reage a QUALQUER
        // highlight, inclusive estes daqui de dentro do editor -- ele
        // embrulha o <pre> num <div class="code-toolbar"> e pendura um
        // botao "Copy" do lado, DENTRO da area editavel. Sem desfazer
        // isso na hora, esse HTML do botao vira parte do que e' salvo
        // (o "Copy" aparecia como texto de verdade dentro do artigo).
        // So o highlight() final na exibicao (fora do editor) deve manter
        // o toolbar de verdade.
        const pre = elementoCode.closest('pre');
        const wrapper = pre ? pre.parentElement : null;
        if (wrapper && wrapper.classList.contains('code-toolbar')) {
            wrapper.replaceWith(pre);
        }

        if (manterCursor) {
            kbRestaurarCursor(elementoCode, offset);
        }
    }

    function kbBlocoDeCodigoAtual() {
        const sel = window.getSelection();
        const no = sel.anchorNode;
        if (!no) return null;
        const elemento = no.nodeType === Node.TEXT_NODE ? no.parentElement : no;
        return elemento ? elemento.closest('code[class*="language-"]') : null;
    }

    let kbTimerRealce = null;
    editor.addEventListener('input', function () {
        const blocoAtual = kbBlocoDeCodigoAtual();
        if (!blocoAtual) return;

        clearTimeout(kbTimerRealce);
        kbTimerRealce = setTimeout(function () { kbRealcarCodigo(blocoAtual, true); }, 400);
    });

    // Enter dentro de um bloco de comando so deve quebrar linha ali dentro
    // (comando de varias linhas) -- sem isso o navegador tende a criar um
    // <div> novo FORA do <code>, "escapando" do bloco.
    editor.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        if (!kbBlocoDeCodigoAtual()) return;

        e.preventDefault();
        document.execCommand('insertText', false, '\n');
    });

    document.getElementById('kbNegrito').addEventListener('click', function () {
        editor.focus();
        document.execCommand('bold');
    });
    document.getElementById('kbItalico').addEventListener('click', function () {
        editor.focus();
        document.execCommand('italic');
    });
    document.getElementById('kbSublinhado').addEventListener('click', function () {
        editor.focus();
        document.execCommand('underline');
    });

    document.getElementById('kbFonte').addEventListener('change', function () {
        editor.focus();
        const sel = window.getSelection();
        if (!sel.rangeCount || sel.isCollapsed) {
            alert('Selecione o texto que quer aumentar/diminuir antes de escolher o tamanho.');
            return;
        }
        const range = sel.getRangeAt(0);
        const span = document.createElement('span');
        span.style.fontSize = this.value + 'px';
        try {
            range.surroundContents(span);
        } catch (e) {
            const conteudo = range.extractContents();
            span.appendChild(conteudo);
            range.insertNode(span);
        }
        sel.removeAllRanges();
    });

    document.querySelectorAll('#kbListaLinguagens [data-lang]').forEach(function (item) {
        item.addEventListener('click', function (e) {
            e.preventDefault();
            editor.focus();

            const sel = window.getSelection();
            let range;
            if (sel.rangeCount && editor.contains(sel.anchorNode)) {
                range = sel.getRangeAt(0);
            } else {
                range = document.createRange();
                range.selectNodeContents(editor);
                range.collapse(false);
            }

            const textoSelecionado = range.toString();
            const pre = document.createElement('pre');
            const code = document.createElement('code');
            code.className = 'language-' + this.dataset.lang;
            code.textContent = textoSelecionado !== '' ? textoSelecionado : '// digite o comando aqui';

            pre.appendChild(code);
            range.deleteContents();
            range.insertNode(pre);
            pre.after(document.createElement('br'));
            kbRealcarCodigo(code, false);

            const novoRange = document.createRange();
            novoRange.selectNodeContents(code);
            sel.removeAllRanges();
            sel.addRange(novoRange);
        });
    });

    // O editor e' um <div contenteditable>, nao um campo de formulario --
    // sem isso, "solucao" nunca chegaria no POST.
    form.addEventListener('submit', function () {
        campoOculto.value = editor.innerHTML.trim();
    });
})();

if (window.Prism) {
    Prism.highlightAll();
}

document.querySelectorAll('.kb-botao-copiar').forEach(function (botao) {
    botao.addEventListener('click', async function () {
        const texto = botao.getAttribute('data-texto');
        let copiou = false;

        if (window.isSecureContext && navigator.clipboard) {
            try {
                await navigator.clipboard.writeText(texto);
                copiou = true;
            } catch (e) {
                copiou = false;
            }
        }

        if (!copiou) {
            const area = document.createElement('textarea');
            area.value = texto;
            area.style.position = 'fixed';
            area.style.opacity = '0';
            document.body.appendChild(area);
            area.select();
            try {
                copiou = document.execCommand('copy');
            } catch (e) {
                copiou = false;
            }
            document.body.removeChild(area);
        }

        botao.innerHTML = copiou ? '<i class="bi bi-check-lg"></i>' : '<i class="bi bi-x-lg"></i>';
        setTimeout(function () { botao.innerHTML = '<i class="bi bi-clipboard"></i>'; }, 1500);
    });
});
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Base de Conhecimento';

require __DIR__ . '/../layouts/main.php';
