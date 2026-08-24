<?php
ob_start();

use App\Components\Alert;
use App\Services\ContratoService;

$statusInfo = [
    'vigente'  => ['label' => 'Vigente',   'classe' => 'text-bg-success'],
    'a_vencer' => ['label' => 'A vencer',  'classe' => 'text-bg-warning'],
    'vencido'  => ['label' => 'Vencido',   'classe' => 'text-bg-danger'],
    'sem_data' => ['label' => 'Sem prazo', 'classe' => 'text-bg-light border'],
];
?>

<?= Alert::flash() ?>

<div class="mb-4 d-flex justify-content-between align-items-start">
    <div>
        <a href="<?= url('/fornecedores') ?>" class="text-decoration-none small text-muted d-block mb-1">
            <i class="bi bi-arrow-left"></i> Fornecedores
        </a>
        <h4 class="mb-1"><i class="bi bi-truck me-1"></i> <?= htmlspecialchars($fornecedor['nome_fantasia']) ?></h4>
        <div class="text-muted small">
            <?= htmlspecialchars($fornecedor['razao_social']) ?>
            <?php if (!empty($fornecedor['tipo_servico_nome'])): ?> &middot; <?= htmlspecialchars($fornecedor['tipo_servico_nome']) ?><?php endif; ?>
            <?= $fornecedor['ativo'] ? '' : ' &middot; <span class="badge text-bg-secondary">Inativo</span>' ?>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('/fornecedores/editar?id=' . (int)$fornecedor['id']) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-pencil"></i> Editar
        </a>
        <form method="post" action="<?= url('/fornecedores/excluir') ?>" onsubmit="return confirm('Excluir este fornecedor?');">
            <input type="hidden" name="id" value="<?= (int)$fornecedor['id'] ?>">
            <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
        </form>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h6 class="card-title mb-3">Dados gerais</h6>
                <ul class="list-unstyled small mb-0">
                    <li class="mb-2"><span class="text-muted">CNPJ/CPF:</span> <?= htmlspecialchars($fornecedor['cnpj_cpf'] ?? '-') ?></li>
                    <li class="mb-2"><span class="text-muted">Inscrição estadual:</span>
                        <?= !empty($fornecedor['inscricao_estadual_isento']) ? 'Isento' : htmlspecialchars($fornecedor['inscricao_estadual'] ?? '-') ?>
                    </li>
                    <li class="mb-2"><span class="text-muted">Inscrição municipal:</span> <?= htmlspecialchars($fornecedor['inscricao_municipal'] ?? '-') ?></li>
                    <li class="mb-2"><span class="text-muted">Porte:</span> <?= htmlspecialchars($fornecedor['porte'] ?? '-') ?></li>
                </ul>
            </div>
        </div>

        <?php if (!empty($fornecedor['logradouro']) || !empty($fornecedor['cidade'])): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h6 class="card-title mb-3">Endereço</h6>
                <p class="small mb-0">
                    <?= htmlspecialchars(trim(($fornecedor['logradouro'] ?? '') . ', ' . ($fornecedor['numero'] ?? ''), ', ')) ?>
                    <?= !empty($fornecedor['complemento']) ? ' - ' . htmlspecialchars($fornecedor['complemento']) : '' ?><br>
                    <?= htmlspecialchars($fornecedor['bairro'] ?? '') ?>
                    <?= !empty($fornecedor['cep']) ? ' &middot; CEP ' . htmlspecialchars($fornecedor['cep']) : '' ?><br>
                    <?= htmlspecialchars(trim(($fornecedor['cidade'] ?? '') . '/' . ($fornecedor['uf'] ?? ''), '/')) ?>
                    <?= !empty($fornecedor['pais']) && $fornecedor['pais'] !== 'Brasil' ? ' &middot; ' . htmlspecialchars($fornecedor['pais']) : '' ?>
                </p>
            </div>
        </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h6 class="card-title mb-3">Contato</h6>
                <ul class="list-unstyled small mb-0">
                    <?php if (!empty($fornecedor['contato_nome'])): ?>
                        <li class="mb-2"><i class="bi bi-person text-muted me-2"></i><?= htmlspecialchars($fornecedor['contato_nome']) ?></li>
                    <?php endif; ?>
                    <li class="mb-2"><i class="bi bi-telephone text-muted me-2"></i><?= htmlspecialchars($fornecedor['telefone'] ?? '-') ?></li>
                    <li class="mb-2"><i class="bi bi-envelope text-muted me-2"></i><?= htmlspecialchars($fornecedor['email'] ?? '-') ?></li>
                    <li class="mb-2"><i class="bi bi-globe text-muted me-2"></i><?= htmlspecialchars($fornecedor['site'] ?? '-') ?></li>
                </ul>
            </div>
        </div>

        <?php if (!empty($fornecedor['canal_abertura_chamado'])): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="card-title mb-2"><i class="bi bi-headset me-1"></i> Como abrir chamado</h6>
                <p class="small text-muted mb-0" style="white-space: pre-wrap;"><?= htmlspecialchars($fornecedor['canal_abertura_chamado']) ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0">Contratos</h6>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNovoContrato">
                <i class="bi bi-plus-lg"></i> Novo contrato
            </button>
        </div>

        <?php if (empty($contratos)): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center text-muted py-5">
                    <i class="bi bi-file-earmark-text" style="font-size:2rem;"></i>
                    <p class="mb-0 mt-2">Nenhum contrato cadastrado ainda.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="accordion" id="acordeaoContratos">
                <?php foreach ($contratos as $contrato):
                    $status = ContratoService::status($contrato);
                    $info = $statusInfo[$status];
                    $collapseId = 'contrato' . (int)$contrato['id'];
                ?>
                <div class="accordion-item border-0 shadow-sm mb-2">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>">
                            <div class="d-flex justify-content-between align-items-center w-100 me-3">
                                <span>
                                    <strong><?= htmlspecialchars($contrato['numero'] ?: 'Contrato #' . $contrato['id']) ?></strong>
                                    <?php if (!empty($contrato['descricao'])): ?>
                                        <span class="text-muted small ms-2"><?= htmlspecialchars(mb_strimwidth($contrato['descricao'], 0, 60, '...')) ?></span>
                                    <?php endif; ?>
                                </span>
                                <span class="badge <?= $info['classe'] ?>"><?= $info['label'] ?></span>
                            </div>
                        </button>
                    </h2>
                    <div id="<?= $collapseId ?>" class="accordion-collapse collapse" data-bs-parent="#acordeaoContratos">
                        <div class="accordion-body">
                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <div class="text-muted small">Início</div>
                                    <div><?= !empty($contrato['data_inicio']) ? date('d/m/Y', strtotime($contrato['data_inicio'])) : '-' ?></div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-muted small">Término</div>
                                    <div><?= !empty($contrato['data_termino']) ? date('d/m/Y', strtotime($contrato['data_termino'])) : '-' ?></div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-muted small">Valor</div>
                                    <div><?= $contrato['valor'] !== null ? 'R$ ' . number_format((float)$contrato['valor'], 2, ',', '.') : '-' ?></div>
                                </div>
                                <?php if (!empty($contrato['descricao'])): ?>
                                <div class="col-12">
                                    <div class="text-muted small">Descrição</div>
                                    <div style="white-space: pre-wrap;"><?= htmlspecialchars($contrato['descricao']) ?></div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="border-top pt-3">
                                <div class="text-muted small mb-2">Anexo</div>
                                <?php if (!empty($contrato['anexo_origem'])): ?>
                                    <a href="<?= url('/contratos/anexo?id=' . (int)$contrato['id']) ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-<?= $contrato['anexo_origem'] === 'samba' ? 'folder-symlink' : 'paperclip' ?>"></i>
                                        <?= htmlspecialchars($contrato['anexo_nome_original']) ?>
                                        <?= $contrato['anexo_origem'] === 'samba' ? '<span class="badge text-bg-light border ms-1">Samba</span>' : '' ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">Nenhum anexo.</span>
                                <?php endif; ?>

                                <div class="mt-2 d-flex gap-2">
                                    <button type="button" class="btn btn-link btn-sm p-0" onclick="document.getElementById('uploadAnexo<?= $contrato['id'] ?>').click()">
                                        Enviar arquivo
                                    </button>
                                    <span class="text-muted">&middot;</span>
                                    <button type="button" class="btn btn-link btn-sm p-0" onclick="abrirSeletorSamba(<?= (int)$contrato['id'] ?>)">
                                        Escolher do Samba
                                    </button>
                                </div>
                                <form id="formUpload<?= $contrato['id'] ?>" method="post" action="<?= url('/contratos/anexo-upload') ?>" enctype="multipart/form-data" class="d-none">
                                    <input type="hidden" name="id" value="<?= (int)$contrato['id'] ?>">
                                    <input type="file" id="uploadAnexo<?= $contrato['id'] ?>" name="arquivo" onchange="document.getElementById('formUpload<?= $contrato['id'] ?>').submit()">
                                </form>
                            </div>

                            <div class="border-top pt-3 mt-3 d-flex gap-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modalEditarContrato<?= $contrato['id'] ?>">
                                    <i class="bi bi-pencil"></i> Editar
                                </button>
                                <form method="post" action="<?= url('/contratos/excluir') ?>" onsubmit="return confirm('Excluir este contrato?');">
                                    <input type="hidden" name="id" value="<?= (int)$contrato['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i> Excluir</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal editar contrato -->
                <div class="modal fade" id="modalEditarContrato<?= $contrato['id'] ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="post" action="<?= url('/contratos/atualizar') ?>">
                                <input type="hidden" name="id" value="<?= (int)$contrato['id'] ?>">
                                <div class="modal-header">
                                    <h5 class="modal-title">Editar contrato</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <?php include __DIR__ . '/_campos_contrato.php'; ?>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-primary">Salvar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal novo contrato -->
<div class="modal fade" id="modalNovoContrato" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="<?= url('/contratos/criar') ?>">
                <input type="hidden" name="fornecedor_id" value="<?= (int)$fornecedor['id'] ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Novo contrato</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php $contrato = null; include __DIR__ . '/_campos_contrato.php'; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Cadastrar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal seletor de arquivo do Samba -->
<div class="modal fade" id="modalSeletorSamba" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" action="<?= url('/contratos/anexo-samba') ?>" id="formAnexoSamba">
                <input type="hidden" name="id" id="sambaContratoId">
                <input type="hidden" name="compartilhamento_id" id="sambaCompartilhamentoId">
                <input type="hidden" name="subcaminho" id="sambaSubcaminho">
                <input type="hidden" name="nome_arquivo" id="sambaNomeArquivo">
                <div class="modal-header">
                    <h5 class="modal-title">Escolher arquivo do Samba</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <nav aria-label="breadcrumb" class="mb-2">
                        <ol class="breadcrumb mb-0 small" id="sambaBreadcrumb"></ol>
                    </nav>
                    <div id="sambaListaItens" class="list-group" style="max-height: 350px; overflow-y: auto;">
                        <div class="text-center text-muted py-4" id="sambaCarregando">
                            <div class="spinner-border spinner-border-sm"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="sambaBtnAnexar" disabled>Anexar arquivo selecionado</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let sambaEstado = { compartilhamentoId: null, compartilhamentoNome: null, subcaminho: '', arquivoSelecionado: null };

function abrirSeletorSamba(contratoId) {
    document.getElementById('sambaContratoId').value = contratoId;
    document.getElementById('sambaBtnAnexar').disabled = true;
    sambaEstado = { compartilhamentoId: null, compartilhamentoNome: null, subcaminho: '', arquivoSelecionado: null };

    new bootstrap.Modal(document.getElementById('modalSeletorSamba')).show();
    carregarCompartilhamentos();
}

function carregarCompartilhamentos() {
    const lista = document.getElementById('sambaListaItens');
    lista.innerHTML = '<div class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm"></div></div>';
    document.getElementById('sambaBreadcrumb').innerHTML = '';

    fetch('<?= url('/contratos/samba-compartilhamentos') ?>')
        .then(r => r.json())
        .then(dados => {
            if (!dados.success || !dados.compartilhamentos.length) {
                lista.innerHTML = '<div class="text-center text-muted py-4">Nenhum compartilhamento disponível pra você. Peça acesso ao administrador.</div>';
                return;
            }
            lista.innerHTML = '';
            dados.compartilhamentos.forEach(c => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'list-group-item list-group-item-action';
                item.innerHTML = '<i class="bi bi-hdd-network me-2"></i>' + escapeHtml(c.nome);
                item.onclick = () => {
                    sambaEstado.compartilhamentoId = c.id;
                    sambaEstado.compartilhamentoNome = c.nome;
                    sambaEstado.subcaminho = '';
                    navegarSamba();
                };
                lista.appendChild(item);
            });
        });
}

function navegarSamba() {
    const lista = document.getElementById('sambaListaItens');
    lista.innerHTML = '<div class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm"></div></div>';
    document.getElementById('sambaBtnAnexar').disabled = true;
    sambaEstado.arquivoSelecionado = null;
    atualizarBreadcrumb();

    const params = new URLSearchParams({
        compartilhamento_id: sambaEstado.compartilhamentoId,
        subcaminho: sambaEstado.subcaminho,
    });

    fetch('<?= url('/contratos/samba-listar') ?>?' + params.toString())
        .then(r => r.json())
        .then(dados => {
            if (!dados.success) {
                lista.innerHTML = '<div class="text-center text-muted py-4">' + escapeHtml(dados.message || 'Erro ao listar.') + '</div>';
                return;
            }
            lista.innerHTML = '';
            if (!dados.itens.length) {
                lista.innerHTML = '<div class="text-center text-muted py-4">Pasta vazia.</div>';
            }
            dados.itens.forEach(item => {
                const el = document.createElement('button');
                el.type = 'button';
                el.className = 'list-group-item list-group-item-action d-flex align-items-center';
                const isDir = item.type === 'dir' || item.type === 'directory' || item.type === 'folder';
                el.innerHTML = '<i class="bi bi-' + (isDir ? 'folder-fill text-warning' : 'file-earmark') + ' me-2"></i>' + escapeHtml(item.name);
                el.onclick = () => {
                    if (isDir) {
                        sambaEstado.subcaminho = sambaEstado.subcaminho ? sambaEstado.subcaminho + '/' + item.name : item.name;
                        navegarSamba();
                    } else {
                        document.querySelectorAll('#sambaListaItens .list-group-item').forEach(n => n.classList.remove('active'));
                        el.classList.add('active');
                        sambaEstado.arquivoSelecionado = item.name;
                        document.getElementById('sambaBtnAnexar').disabled = false;
                    }
                };
                lista.appendChild(el);
            });
        });
}

function atualizarBreadcrumb() {
    const bc = document.getElementById('sambaBreadcrumb');
    bc.innerHTML = '';

    const raiz = document.createElement('li');
    raiz.className = 'breadcrumb-item';
    const raizLink = document.createElement('a');
    raizLink.href = '#';
    raizLink.textContent = sambaEstado.compartilhamentoNome;
    raizLink.onclick = (e) => { e.preventDefault(); sambaEstado.subcaminho = ''; navegarSamba(); };
    raiz.appendChild(raizLink);
    bc.appendChild(raiz);

    if (!sambaEstado.subcaminho) return;

    const partes = sambaEstado.subcaminho.split('/');
    let acumulado = '';
    partes.forEach((parte, i) => {
        acumulado = acumulado ? acumulado + '/' + parte : parte;
        const caminhoAteAqui = acumulado;
        const li = document.createElement('li');
        li.className = 'breadcrumb-item';
        if (i === partes.length - 1) {
            li.classList.add('active');
            li.textContent = parte;
        } else {
            const a = document.createElement('a');
            a.href = '#';
            a.textContent = parte;
            a.onclick = (e) => { e.preventDefault(); sambaEstado.subcaminho = caminhoAteAqui; navegarSamba(); };
            li.appendChild(a);
        }
        bc.appendChild(li);
    });
}

document.getElementById('formAnexoSamba').addEventListener('submit', function () {
    document.getElementById('sambaCompartilhamentoId').value = sambaEstado.compartilhamentoId;
    document.getElementById('sambaSubcaminho').value = sambaEstado.subcaminho;
    document.getElementById('sambaNomeArquivo').value = sambaEstado.arquivoSelecionado;
});

function escapeHtml(texto) {
    const div = document.createElement('div');
    div.textContent = texto;
    return div.innerHTML;
}
</script>

<?php
$conteudo = ob_get_clean();
$titulo = $fornecedor['nome_fantasia'];

require __DIR__ . '/../layouts/main.php';
