<?php
ob_start();

use App\Components\Alert;
use App\Services\ChamadoService;
?>

<?= Alert::flash() ?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-plus-circle me-1"></i> Abrir Chamado</h4>
    <small class="text-muted"><a href="<?= url('/chamados/atendimentos') ?>"><i class="bi bi-arrow-left"></i> Voltar</a></small>
</div>

<form method="post" action="<?= url('/chamados/atendimentos/novo') ?>">
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white"><strong>O chamado</strong></div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-8">
                    <label class="form-label">Título</label>
                    <input type="text" name="titulo" class="form-control" required maxlength="200" placeholder="Ex: Impressora não imprime -- 2º andar financeiro">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Prioridade</label>
                    <select name="prioridade" class="form-select">
                        <?php foreach (ChamadoService::PRIORIDADES as $chave => $label): ?>
                            <option value="<?= $chave ?>" <?= $chave === 'media' ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label">Categoria</label>
                    <select name="categoria_id" class="form-select" required>
                        <option value="">— Selecione —</option>
                        <?php foreach ($categorias as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Setor responsável <span class="text-muted">(opcional)</span></label>
                    <select name="setor_id" class="form-select">
                        <option value="">— Usar o padrão da categoria —</option>
                        <?php foreach ($setores as $s): ?>
                            <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Unidade</label>
                    <select name="unidade_id" class="form-select" required>
                        <?php foreach ($unidades as $u): ?>
                            <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mb-3 position-relative">
                <label class="form-label">Ativo relacionado <span class="text-muted fw-normal">(opcional -- se o chamado for sobre um equipamento)</span></label>
                <input type="hidden" name="ativo_id" id="campoAtivoId">
                <input type="text" id="campoAtivoBusca" class="form-control" autocomplete="off" placeholder="Busque por código, nome ou nº de série...">
                <div id="listaAtivosSugeridos" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index:10; max-height:220px; overflow-y:auto"></div>
            </div>

            <div class="mb-0 position-relative">
                <label class="form-label">Descrição</label>
                <textarea name="descricao" id="campoDescricao" class="form-control" rows="4" required placeholder="Descreva o problema com o máximo de detalhe possível."></textarea>
                <div id="listaKbSugerida" class="mt-2"></div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white"><strong>Solicitante</strong></div>
        <div class="card-body">
            <div class="mb-3 position-relative">
                <label class="form-label">Buscar usuário cadastrado <span class="text-muted fw-normal">(opcional -- preenche os campos abaixo automaticamente)</span></label>
                <input type="text" id="campoUsuarioBusca" class="form-control" autocomplete="off" placeholder="Busque por nome, login ou e-mail...">
                <div id="listaUsuariosSugeridos" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index:10; max-height:220px; overflow-y:auto"></div>
            </div>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Nome</label>
                    <input type="text" name="solicitante_nome" id="campoSolicitanteNome" class="form-control" required maxlength="150">
                </div>
                <div class="col-md-4">
                    <label class="form-label">E-mail</label>
                    <input type="email" name="solicitante_email" id="campoSolicitanteEmail" class="form-control" maxlength="150">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Telefone</label>
                    <input type="text" name="solicitante_telefone" id="campoSolicitanteTelefone" class="form-control" maxlength="30" placeholder="(83) 99104-3598">
                </div>
            </div>
            <div id="avisoContato" class="form-text mt-2">Informe pelo menos um contato (e-mail ou telefone) -- é por ele que o solicitante recebe as atualizações do chamado.</div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> Abrir chamado</button>
    <a href="<?= url('/chamados/atendimentos') ?>" class="btn btn-secondary">Cancelar</a>
</form>

<script>
(function () {
    // --- Solicitante: exige e-mail ou telefone sem perder o restante do formulário preenchido ---
    const formChamado = document.querySelector('form[action="<?= url('/chamados/atendimentos/novo') ?>"]');
    const campoEmail = document.getElementById('campoSolicitanteEmail');
    const campoTelefone = document.getElementById('campoSolicitanteTelefone');
    const avisoContato = document.getElementById('avisoContato');

    function limparAvisoContato() {
        campoEmail.classList.remove('is-invalid');
        campoTelefone.classList.remove('is-invalid');
        avisoContato.classList.remove('text-danger', 'fw-semibold');
    }

    campoEmail.addEventListener('input', limparAvisoContato);
    campoTelefone.addEventListener('input', limparAvisoContato);

    formChamado.addEventListener('submit', (ev) => {
        if (campoEmail.value.trim() !== '' || campoTelefone.value.trim() !== '') {
            limparAvisoContato();
            return;
        }
        ev.preventDefault();
        campoEmail.classList.add('is-invalid');
        campoTelefone.classList.add('is-invalid');
        avisoContato.classList.add('text-danger', 'fw-semibold');
        campoEmail.focus();
        campoEmail.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });

    // --- Ativo relacionado: autocomplete simples (debounce + fetch) ---
    const campoBusca = document.getElementById('campoAtivoBusca');
    const campoId = document.getElementById('campoAtivoId');
    const lista = document.getElementById('listaAtivosSugeridos');
    let timerBusca = null;

    campoBusca.addEventListener('input', () => {
        campoId.value = '';
        clearTimeout(timerBusca);
        const termo = campoBusca.value.trim();
        if (termo.length < 2) {
            lista.classList.add('d-none');
            return;
        }
        timerBusca = setTimeout(() => buscarAtivos(termo), 300);
    });

    async function buscarAtivos(termo) {
        try {
            const resp = await fetch('<?= url('/chamados/atendimentos/ativos-buscar') ?>?q=' + encodeURIComponent(termo));
            const dados = await resp.json();
            if (!dados.success || dados.ativos.length === 0) {
                lista.classList.add('d-none');
                return;
            }
            lista.innerHTML = dados.ativos.map((a) =>
                '<button type="button" class="list-group-item list-group-item-action opcao-ativo" data-id="' + a.id + '" data-texto="' + escapeAttr(a.codigo + ' -- ' + a.nome) + '">'
                + '<span class="font-monospace small">' + escapeHtml(a.codigo) + '</span> ' + escapeHtml(a.nome)
                + ' <span class="text-muted small">(' + escapeHtml(a.tipo) + ')</span></button>'
            ).join('');
            lista.classList.remove('d-none');
        } catch (e) { /* rede instável -- só não sugere nada */ }
    }

    lista.addEventListener('click', (ev) => {
        const botao = ev.target.closest('.opcao-ativo');
        if (!botao) return;
        campoId.value = botao.dataset.id;
        campoBusca.value = botao.dataset.texto;
        lista.classList.add('d-none');
    });

    document.addEventListener('click', (ev) => {
        if (!lista.contains(ev.target) && ev.target !== campoBusca) {
            lista.classList.add('d-none');
        }
    });

    function escapeHtml(texto) {
        const div = document.createElement('div');
        div.textContent = texto;
        return div.innerHTML;
    }
    function escapeAttr(texto) {
        return escapeHtml(texto).replace(/"/g, '&quot;');
    }

    // --- Solicitante: busca usuário cadastrado no sistema (mesmo raciocínio do Ativo relacionado) ---
    const campoUsuarioBusca = document.getElementById('campoUsuarioBusca');
    const listaUsuarios = document.getElementById('listaUsuariosSugeridos');
    let timerUsuarioBusca = null;

    campoUsuarioBusca.addEventListener('input', () => {
        clearTimeout(timerUsuarioBusca);
        const termo = campoUsuarioBusca.value.trim();
        if (termo.length < 2) {
            listaUsuarios.classList.add('d-none');
            return;
        }
        timerUsuarioBusca = setTimeout(() => buscarUsuarios(termo), 300);
    });

    async function buscarUsuarios(termo) {
        try {
            const resp = await fetch('<?= url('/chamados/atendimentos/usuarios-buscar') ?>?q=' + encodeURIComponent(termo));
            const dados = await resp.json();
            if (!dados.success || dados.usuarios.length === 0) {
                listaUsuarios.classList.add('d-none');
                return;
            }
            listaUsuarios.innerHTML = dados.usuarios.map((u) =>
                '<button type="button" class="list-group-item list-group-item-action opcao-usuario" data-nome="' + escapeAttr(u.nome) + '" data-email="' + escapeAttr(u.email || '') + '">'
                + escapeHtml(u.nome) + (u.email ? ' <span class="text-muted small">(' + escapeHtml(u.email) + ')</span>' : '')
                + '</button>'
            ).join('');
            listaUsuarios.classList.remove('d-none');
        } catch (e) { /* rede instável -- só não sugere nada */ }
    }

    listaUsuarios.addEventListener('click', (ev) => {
        const botao = ev.target.closest('.opcao-usuario');
        if (!botao) return;
        document.getElementById('campoSolicitanteNome').value = botao.dataset.nome;
        document.getElementById('campoSolicitanteEmail').value = botao.dataset.email;
        campoUsuarioBusca.value = botao.dataset.nome;
        listaUsuarios.classList.add('d-none');
    });

    document.addEventListener('click', (ev) => {
        if (!listaUsuarios.contains(ev.target) && ev.target !== campoUsuarioBusca) {
            listaUsuarios.classList.add('d-none');
        }
    });

    // --- Base de Conhecimento: sugestão enquanto descreve o problema ---
    const campoDescricao = document.getElementById('campoDescricao');
    const listaKb = document.getElementById('listaKbSugerida');
    let timerKb = null;

    campoDescricao.addEventListener('input', () => {
        clearTimeout(timerKb);
        const termo = campoDescricao.value.trim();
        if (termo.length < 8) {
            listaKb.innerHTML = '';
            return;
        }
        timerKb = setTimeout(() => buscarKb(termo), 500);
    });

    async function buscarKb(termo) {
        try {
            const resp = await fetch('<?= url('/chamados/atendimentos/kb-sugestoes') ?>?q=' + encodeURIComponent(termo));
            const dados = await resp.json();
            if (!dados.success || dados.artigos.length === 0) {
                listaKb.innerHTML = '';
                return;
            }
            listaKb.innerHTML = '<div class="small text-muted mb-1">💡 Talvez isso já esteja resolvido na Base de Conhecimento:</div>'
                + dados.artigos.map((a) =>
                    '<div class="small"><i class="bi bi-journal-text"></i> ' + escapeHtml(a.titulo)
                    + (a.categoria ? ' <span class="text-muted">(' + escapeHtml(a.categoria) + ')</span>' : '') + '</div>'
                ).join('');
        } catch (e) { /* rede instável -- só não sugere nada */ }
    }
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Abrir Chamado';

require __DIR__ . '/../layouts/main.php';
