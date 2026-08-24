<?php
ob_start();

use App\Components\Alert;

$severidadeInfo = [
    'informativo' => ['label' => 'Informativo', 'classe' => 'text-bg-info'],
    'atencao'     => ['label' => 'Atenção',     'classe' => 'text-bg-warning'],
    'urgente'     => ['label' => 'Urgente',     'classe' => 'text-bg-danger'],
];
?>

<?= Alert::flash() ?>

<div class="mb-4 d-flex justify-content-between align-items-start">
    <div>
        <a href="<?= url('/avisos') ?>" class="text-decoration-none small text-muted d-block mb-1">
            <i class="bi bi-arrow-left"></i> Mural de Avisos
        </a>
        <h4 class="mb-1"><i class="bi bi-gear me-1"></i> Gerenciar avisos</h4>
    </div>
    <button type="button" class="btn btn-primary text-nowrap" onclick="abrirModalNovo()">
        <i class="bi bi-plus-lg"></i> Novo aviso
    </button>
</div>

<?php if (empty($avisos)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-muted py-5">Nenhum aviso publicado ainda.</div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Severidade</th>
                        <th>Visto / Confirmado</th>
                        <th>Status</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($avisos as $aviso): $sev = $severidadeInfo[$aviso['severidade']] ?? $severidadeInfo['informativo']; ?>
                        <tr>
                            <td>
                                <?php if ($aviso['fixado']): ?><i class="bi bi-pin-angle-fill text-muted me-1" title="Fixado"></i><?php endif; ?>
                                <strong><?= htmlspecialchars($aviso['titulo']) ?></strong>
                                <div class="text-muted small"><?= date('d/m/Y H:i', strtotime($aviso['criado_em'])) ?> &middot; <?= htmlspecialchars($aviso['criado_por_nome'] ?? 'Sistema') ?></div>
                            </td>
                            <td><span class="badge <?= $sev['classe'] ?>"><?= $sev['label'] ?></span></td>
                            <td>
                                <span class="small"><?= (int)$aviso['total_vistos'] ?> viram</span>
                                <?php if ($aviso['confirmacao_obrigatoria']): ?>
                                    <span class="small text-muted"> &middot; <?= (int)$aviso['total_confirmados'] ?> confirmaram</span>
                                <?php endif; ?>
                                <button type="button" class="btn btn-link btn-sm p-0 ms-2" onclick="abrirRelatorio(<?= (int)$aviso['id'] ?>, <?= $aviso['confirmacao_obrigatoria'] ? 'true' : 'false' ?>)">
                                    <i class="bi bi-list-check"></i> ver
                                </button>
                            </td>
                            <td><?= $aviso['ativo'] ? '<span class="badge text-bg-success">Ativo</span>' : '<span class="badge text-bg-secondary">Inativo</span>' ?></td>
                            <td class="text-end">
                                <button type="button" class="btn btn-outline-secondary btn-sm"
                                        onclick='abrirModalEditar(<?= json_encode($aviso, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="post" action="<?= url('/avisos/gerenciar/excluir') ?>" class="d-inline" onsubmit="return confirm('Excluir este aviso?');">
                                    <input type="hidden" name="id" value="<?= (int)$aviso['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Modal novo/editar aviso -->
<div class="modal fade" id="modalAviso" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" action="<?= url('/avisos/gerenciar/criar') ?>" id="formAviso">
                <input type="hidden" name="id" id="campoAvisoId">
                <div class="modal-header">
                    <h5 class="modal-title" id="tituloModalAviso">Novo aviso</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Título *</label>
                            <input type="text" name="titulo" id="campoTitulo" class="form-control" required maxlength="200">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Severidade</label>
                            <select name="severidade" id="campoSeveridade" class="form-select">
                                <option value="informativo">Informativo</option>
                                <option value="atencao">Atenção</option>
                                <option value="urgente">Urgente</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Conteúdo *</label>
                            <textarea name="conteudo" id="campoConteudo" class="form-control" rows="5" required></textarea>
                        </div>

                        <div class="col-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="fixado" value="1" id="campoFixado">
                                <label class="form-check-label" for="campoFixado">Fixar no topo</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="confirmacao_obrigatoria" value="1" id="campoConfirmacao">
                                <label class="form-check-label" for="campoConfirmacao">Exigir confirmação de leitura</label>
                            </div>
                        </div>
                        <div class="col-md-4" id="blocoAtivo" style="display:none;">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="ativo" value="1" id="campoAtivo" checked>
                                <label class="form-check-label" for="campoAtivo">Ativo</label>
                            </div>
                        </div>

                        <div class="col-12"><hr></div>

                        <div class="col-12">
                            <label class="form-label">Destinatários *</label>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="destinatario_todos" value="1" id="campoTodos">
                                <label class="form-check-label" for="campoTodos"><strong>Enviar para todos os usuários</strong></label>
                            </div>
                        </div>

                        <div class="col-md-6" id="blocoGrupos">
                            <label class="form-label small text-muted">Grupos específicos</label>
                            <div class="border rounded p-2" style="max-height:180px; overflow-y:auto;">
                                <?php foreach ($grupos as $grupo): ?>
                                    <div class="form-check">
                                        <input class="form-check-input campo-destinatario-grupo" type="checkbox" name="destinatario_grupos[]" value="<?= (int)$grupo['id'] ?>" id="grupo<?= (int)$grupo['id'] ?>">
                                        <label class="form-check-label small" for="grupo<?= (int)$grupo['id'] ?>"><?= htmlspecialchars($grupo['nome']) ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-md-6" id="blocoUsuarios">
                            <label class="form-label small text-muted">Usuários específicos</label>
                            <div class="border rounded p-2" style="max-height:180px; overflow-y:auto;">
                                <?php foreach ($usuarios as $usuario): ?>
                                    <div class="form-check">
                                        <input class="form-check-input campo-destinatario-usuario" type="checkbox" name="destinatario_usuarios[]" value="<?= (int)$usuario['id'] ?>" id="usuario<?= (int)$usuario['id'] ?>">
                                        <label class="form-check-label small" for="usuario<?= (int)$usuario['id'] ?>"><?= htmlspecialchars($usuario['nome']) ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal relatório de leitura -->
<div class="modal fade" id="modalRelatorio" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Quem já viu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="conteudoRelatorio" class="text-center text-muted py-4">
                    <div class="spinner-border spinner-border-sm"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const modalAvisoEl = document.getElementById('modalAviso');
const formAviso = document.getElementById('formAviso');

function obterModalAviso() {
    return bootstrap.Modal.getOrCreateInstance(modalAvisoEl);
}
const urlDestinatarios = <?= json_encode(url('/avisos/gerenciar/destinatarios')) ?>;
const urlCriar = <?= json_encode(url('/avisos/gerenciar/criar')) ?>;
const urlAtualizar = <?= json_encode(url('/avisos/gerenciar/atualizar')) ?>;

function limparFormAviso() {
    formAviso.reset();
    document.getElementById('campoAvisoId').value = '';
    document.querySelectorAll('.campo-destinatario-grupo, .campo-destinatario-usuario').forEach(c => c.checked = false);
    aplicarEstadoTodos();
}

function aplicarEstadoTodos() {
    const marcado = document.getElementById('campoTodos').checked;
    document.getElementById('blocoGrupos').style.opacity = marcado ? '0.4' : '1';
    document.getElementById('blocoUsuarios').style.opacity = marcado ? '0.4' : '1';
    document.querySelectorAll('.campo-destinatario-grupo, .campo-destinatario-usuario').forEach(c => c.disabled = marcado);
}
document.getElementById('campoTodos').addEventListener('change', aplicarEstadoTodos);

function abrirModalNovo() {
    limparFormAviso();
    document.getElementById('tituloModalAviso').textContent = 'Novo aviso';
    document.getElementById('blocoAtivo').style.display = 'none';
    formAviso.action = urlCriar;
    obterModalAviso().show();
}

function abrirModalEditar(aviso) {
    limparFormAviso();
    document.getElementById('tituloModalAviso').textContent = 'Editar aviso';
    document.getElementById('blocoAtivo').style.display = '';
    formAviso.action = urlAtualizar;

    document.getElementById('campoAvisoId').value = aviso.id;
    document.getElementById('campoTitulo').value = aviso.titulo;
    document.getElementById('campoConteudo').value = aviso.conteudo;
    document.getElementById('campoSeveridade').value = aviso.severidade;
    document.getElementById('campoFixado').checked = aviso.fixado == 1;
    document.getElementById('campoConfirmacao').checked = aviso.confirmacao_obrigatoria == 1;
    document.getElementById('campoAtivo').checked = aviso.ativo == 1;

    obterModalAviso().show();

    fetch(urlDestinatarios + '?id=' + aviso.id)
        .then(r => r.json())
        .then(dados => {
            if (!dados.success) return;
            dados.destinatarios.forEach(d => {
                if (d.tipo === 'todos') {
                    document.getElementById('campoTodos').checked = true;
                } else if (d.tipo === 'grupo') {
                    const el = document.getElementById('grupo' + d.destinatario_id);
                    if (el) el.checked = true;
                } else if (d.tipo === 'usuario') {
                    const el = document.getElementById('usuario' + d.destinatario_id);
                    if (el) el.checked = true;
                }
            });
            aplicarEstadoTodos();
        });
}

function abrirRelatorio(avisoId, exigeConfirmacao) {
    const conteudo = document.getElementById('conteudoRelatorio');
    conteudo.innerHTML = '<div class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm"></div></div>';
    new bootstrap.Modal(document.getElementById('modalRelatorio')).show();

    fetch(<?= json_encode(url('/avisos/gerenciar/relatorio')) ?> + '?id=' + avisoId)
        .then(r => r.json())
        .then(dados => {
            if (!dados.success || !dados.relatorio.length) {
                conteudo.innerHTML = '<p class="text-muted text-center mb-0">Nenhum destinatário resolvido pra esse aviso.</p>';
                return;
            }
            let html = '<ul class="list-group list-group-flush">';
            dados.relatorio.forEach(r => {
                let status;
                if (exigeConfirmacao) {
                    status = r.confirmado_em
                        ? '<span class="badge text-bg-success">Confirmou</span>'
                        : (r.visto_em ? '<span class="badge text-bg-warning">Viu, não confirmou</span>' : '<span class="badge text-bg-light border">Não viu</span>');
                } else {
                    status = r.visto_em ? '<span class="badge text-bg-success">Viu</span>' : '<span class="badge text-bg-light border">Não viu</span>';
                }
                html += '<li class="list-group-item d-flex justify-content-between align-items-center px-0">' +
                    '<span>' + escapeHtmlAviso(r.nome) + '</span>' + status + '</li>';
            });
            html += '</ul>';
            conteudo.innerHTML = html;
        });
}

function escapeHtmlAviso(texto) {
    const div = document.createElement('div');
    div.textContent = texto;
    return div.innerHTML;
}
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Gerenciar Avisos';

require __DIR__ . '/../layouts/main.php';
