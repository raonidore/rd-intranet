<?php
// Janela "Permissões" do gerenciador de arquivos do Samba (botão do escudo em
// Ações). Lê e grava pela permissoes_pasta_samba_web.sh -- ACL do Windows que
// o Samba guarda, a mesma que aparece em Propriedades > Segurança no Windows.
?>
<style>
.perm-tabela td { vertical-align: middle; }
.perm-tabela .perm-quem { font-weight: 500; }
.perm-tabela .perm-aviso { font-size: 12px; }
.perm-add { background: var(--bs-tertiary-bg, #f8fafc); border: 1px dashed var(--bs-border-color, #cbd5e1); border-radius: 10px; }
.perm-ajuda li { margin-bottom: 4px; }
</style>

<div class="modal fade" id="modalPermissoes" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0"><i class="bi bi-shield-lock me-2"></i>Permissões</h5>
                    <small class="text-muted font-monospace" id="perm-caminho"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="perm-carregando" class="text-center text-muted py-4">
                    <div class="spinner-border spinner-border-sm me-2"></div>Lendo as permissões...
                </div>
                <div id="perm-erro" class="alert alert-danger d-none"></div>

                <div id="perm-conteudo" class="d-none">
                    <div id="perm-heranca" class="alert alert-info small py-2"></div>

                    <table class="table table-sm perm-tabela mb-2">
                        <thead>
                            <tr><th>Quem</th><th style="width:300px">Pode</th><th style="width:40px"></th></tr>
                        </thead>
                        <tbody id="perm-linhas"></tbody>
                    </table>

                    <div class="perm-add p-2 mb-3">
                        <div class="row g-2 align-items-center">
                            <div class="col-md-5">
                                <select class="form-select form-select-sm" id="perm-add-quem"></select>
                            </div>
                            <div class="col-md-5">
                                <select class="form-select form-select-sm" id="perm-add-nivel"></select>
                            </div>
                            <div class="col-md-2 d-grid">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="perm-add-btn"><i class="bi bi-plus-lg"></i> Adicionar</button>
                            </div>
                        </div>
                    </div>

                    <div class="form-check mb-3" id="perm-subpastas-box">
                        <input class="form-check-input" type="checkbox" id="perm-subpastas" checked>
                        <label class="form-check-label small" for="perm-subpastas">
                            <strong>Aplicar também a tudo que está dentro</strong> (subpastas e arquivos).
                            Substitui as permissões próprias que existam lá dentro -- desmarque só se alguma subpasta já tem uma regra diferente que deve ser mantida.
                        </label>
                    </div>

                    <details class="small text-muted perm-ajuda">
                        <summary class="mb-2">Como funciona</summary>
                        <ul class="ps-3">
                            <li><strong>Quem não está na lista não vê a pasta</strong> -- ela fica oculta para essa pessoa no Windows.</li>
                            <li><strong>Grupo vale para todos os membros.</strong> Para deixar a pasta só com algumas pessoas, tire o grupo e adicione os usuários um a um.</li>
                            <li><strong>Ler e gravar (sem excluir)</strong> serve para pastas de depósito (digitalizações, exportações de sistema): cria e altera arquivos, mas não apaga nem renomeia.
                                Word e Excel salvam criando um arquivo temporário e trocando pelo original, então para quem edita documentos use <strong>Modificar</strong>.</li>
                            <li><strong>Controle total</strong> permite também mudar as permissões pelo Windows -- normalmente só a TI.</li>
                            <li>Para alguém chegar até esta pasta, precisa ter pelo menos leitura nas pastas de cima e estar liberado no compartilhamento (Samba &gt; Compartilhamentos &gt; Usuários). O aviso aparece na linha quando falta isso.</li>
                            <li>Depois de salvar, esta pasta deixa de herdar da pasta de cima: mudanças futuras em Compartilhamentos &gt; Usuários não passam por cima dela.</li>
                            <li><strong>Acesso anônimo (sem senha)</strong> não é por pasta: o Samba decide isso no compartilhamento inteiro, na conexão. Para uma pasta pública, o caminho é um compartilhamento separado só para ela.</li>
                            <li>Quem já está com a pasta aberta no Windows pode precisar fechar e abrir de novo para ver a mudança.</li>
                        </ul>
                    </details>

                    <div id="perm-progresso" class="d-none mt-3">
                        <div class="small mb-1" id="perm-progresso-texto">Aplicando...</div>
                        <div class="progress" style="height:8px"><div class="progress-bar progress-bar-striped progress-bar-animated" id="perm-progresso-barra" style="width:0%"></div></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="perm-salvar" disabled><i class="bi bi-check-lg me-1"></i>Salvar permissões</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const URL_PERM = '<?= url('/samba/arquivos/permissoes') ?>';
    const URL_PERM_STATUS = '<?= url('/samba/arquivos/permissoes/status') ?>';
    const NIVEIS = [
        ['ler', 'Somente leitura'],
        ['gravar', 'Ler e gravar (sem excluir)'],
        ['modificar', 'Modificar (ler, gravar e excluir)'],
        ['total', 'Controle total (inclui mudar permissões)'],
    ];
    const modalEl = document.getElementById('modalPermissoes');
    // O bootstrap.bundle só carrega no fim do layout, depois deste script.
    const modal = () => bootstrap.Modal.getOrCreateInstance(modalEl);
    let caminho = '', dados = null, linhas = [], pollTimer = null;

    function esc(t) { const d = document.createElement('div'); d.textContent = t == null ? '' : String(t); return d.innerHTML; }
    function el(id) { return document.getElementById(id); }
    function opcoesNivel(sel) {
        return NIVEIS.map(n => '<option value="' + n[0] + '"' + (n[0] === sel ? ' selected' : '') + '>' + n[1] + '</option>').join('');
    }
    function infoDe(sid) {
        return (dados.usuarios.find(u => u.sid === sid)) || (dados.grupos.find(g => g.sid === sid)) || null;
    }

    function desenhar() {
        const corpo = el('perm-linhas');
        if (!linhas.length) {
            corpo.innerHTML = '<tr><td colspan="3" class="text-muted small py-3 text-center">Ninguém na lista -- adicione pelo menos um usuário ou grupo.</td></tr>';
        } else {
            corpo.innerHTML = linhas.map((l, i) => {
                const info = infoDe(l.sid);
                const avisos = [];
                if (info && info.no_compartilhamento === false) avisos.push('<span class="text-warning-emphasis"><i class="bi bi-exclamation-triangle"></i> Não está liberado no compartilhamento ' + esc(dados.compartilhamento) + ' -- não vai conseguir entrar.</span>');
                else if (info && info.chega === false) avisos.push('<span class="text-warning-emphasis"><i class="bi bi-exclamation-triangle"></i> Sem leitura numa pasta de cima -- não vai conseguir chegar até aqui.</span>');
                // Aparece quando alguém deu chmod o+rwx (ex.: 777) direto no servidor.
                if (l.sid === 'S-1-1-0') avisos.push('<span class="text-danger"><i class="bi bi-exclamation-octagon"></i> Qualquer pessoa que conecte no compartilhamento (vem de permissão "outros" no Linux, como um chmod 777). Tire esta linha para fechar.</span>');
                if (l.tipo === 'grupo') avisos.push('<span class="text-muted">Vale para todos os membros do grupo.</span>');
                const icone = l.tipo === 'grupo' ? 'bi-people-fill text-primary' : (l.tipo === 'usuario' ? 'bi-person-fill text-secondary' : 'bi-gear text-muted');
                return '<tr><td><div class="perm-quem"><i class="bi ' + icone + ' me-1"></i>' + esc(l.nome) + '</div>'
                    + (avisos.length ? '<div class="perm-aviso">' + avisos.join('<br>') + '</div>' : '') + '</td>'
                    + '<td><select class="form-select form-select-sm" data-i="' + i + '">' + opcoesNivel(l.nivel) + '</select></td>'
                    + '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger" data-remover="' + i + '" title="Tirar o acesso"><i class="bi bi-x-lg"></i></button></td></tr>';
            }).join('');
        }

        const usados = new Set(linhas.map(l => l.sid));
        const grupos = dados.grupos.filter(g => !usados.has(g.sid));
        const usuarios = dados.usuarios.filter(u => !usados.has(u.sid));
        el('perm-add-quem').innerHTML = '<option value="">Adicionar usuário ou grupo...</option>'
            + (grupos.length ? '<optgroup label="Grupos">' + grupos.map(g => '<option value="' + esc(g.sid) + '" data-tipo="grupo">' + esc(g.nome) + '</option>').join('') + '</optgroup>' : '')
            + (usuarios.length ? '<optgroup label="Usuários">' + usuarios.map(u => '<option value="' + esc(u.sid) + '" data-tipo="usuario">' + esc(u.nome) + '</option>').join('') + '</optgroup>' : '');
        el('perm-salvar').disabled = !linhas.length;
    }

    el('perm-linhas').addEventListener('change', e => {
        const i = e.target.getAttribute('data-i');
        if (i !== null) linhas[+i].nivel = e.target.value;
    });
    el('perm-linhas').addEventListener('click', e => {
        const b = e.target.closest('[data-remover]');
        if (!b) return;
        linhas.splice(+b.getAttribute('data-remover'), 1);
        desenhar();
    });
    el('perm-add-btn').addEventListener('click', () => {
        const sel = el('perm-add-quem');
        const op = sel.options[sel.selectedIndex];
        if (!op || !op.value) return;
        linhas.push({ sid: op.value, nome: op.textContent, tipo: op.getAttribute('data-tipo'), nivel: el('perm-add-nivel').value });
        desenhar();
    });

    async function abrir(path, nome) {
        caminho = path;
        el('perm-caminho').textContent = path;
        el('perm-carregando').classList.remove('d-none');
        el('perm-conteudo').classList.add('d-none');
        el('perm-erro').classList.add('d-none');
        el('perm-progresso').classList.add('d-none');
        el('perm-salvar').disabled = true;
        modal().show();
        try {
            const r = await fetch(URL_PERM + '?path=' + encodeURIComponent(path));
            dados = await r.json();
        } catch (e) { dados = { success: false, message: 'Erro ao falar com o servidor.' }; }
        el('perm-carregando').classList.add('d-none');
        if (!dados.success) {
            el('perm-erro').textContent = dados.message || 'Não foi possível ler as permissões.';
            el('perm-erro').classList.remove('d-none');
            return;
        }
        linhas = dados.entradas.filter(e => e.nivel !== 'nenhum')
            .map(e => ({ sid: e.sid, nome: e.nome, tipo: e.tipo, nivel: e.nivel }));
        const negados = dados.entradas.filter(e => e.negado).map(e => e.nome);
        el('perm-heranca').innerHTML = dados.protegida
            ? '<i class="bi bi-lock-fill me-1"></i><strong>' + (dados.pasta ? 'Esta pasta' : 'Este arquivo') + ' já tem permissões próprias</strong> (não herda da pasta de cima).'
            : '<i class="bi bi-arrow-down-circle me-1"></i>Hoje ' + (dados.pasta ? 'esta pasta' : 'este arquivo') + ' <strong>herda as permissões da pasta de cima</strong>. Ao salvar, passa a ter permissões próprias.';
        if (negados.length) {
            el('perm-heranca').innerHTML += '<div class="text-danger mt-1"><i class="bi bi-slash-circle me-1"></i>Há negação explícita (feita pelo Windows) para: ' + negados.map(esc).join(', ') + '. Ela sai ao salvar -- só fica com acesso quem estiver na lista abaixo.</div>';
        }
        el('perm-subpastas-box').classList.toggle('d-none', !dados.pasta);
        el('perm-add-nivel').innerHTML = opcoesNivel('ler');
        desenhar();
        el('perm-conteudo').classList.remove('d-none');
    }

    function acompanhar() {
        clearInterval(pollTimer);
        pollTimer = setInterval(async () => {
            let s;
            try { s = await (await fetch(URL_PERM_STATUS + '?path=' + encodeURIComponent(caminho))).json(); } catch (e) { return; }
            if (s.status === 'rodando') {
                el('perm-progresso-barra').style.width = (s.percentual || 0) + '%';
                el('perm-progresso-texto').textContent = 'Aplicando... ' + (s.processados || 0).toLocaleString('pt-BR') + ' de ' + (s.total || 0).toLocaleString('pt-BR') + ' itens';
            } else if (s.status === 'concluido' || s.status === 'erro') {
                clearInterval(pollTimer);
                el('perm-progresso-barra').style.width = '100%';
                const ok = s.status === 'concluido';
                const msg = ok ? ('Permissões aplicadas em ' + (s.total || 1).toLocaleString('pt-BR') + ' item(ns).' + (s.mensagem ? ' ' + s.mensagem : '')) : ('Falha: ' + (s.mensagem || 'erro ao aplicar.'));
                el('perm-progresso-texto').textContent = msg;
                el('perm-progresso-barra').classList.remove('progress-bar-animated');
                el('perm-progresso-barra').classList.add(ok ? 'bg-success' : 'bg-danger');
                el('perm-salvar').disabled = false;
                if (ok && !s.mensagem) setTimeout(() => modal().hide(), 1500);
            }
        }, 1000);
    }

    el('perm-salvar').addEventListener('click', async () => {
        if (!linhas.length) return;
        const subpastas = dados.pasta && el('perm-subpastas').checked;
        const fd = new FormData();
        fd.append('path', caminho);
        fd.append('entradas', JSON.stringify(linhas.map(l => ({ sid: l.sid, nivel: l.nivel, nome: l.nome }))));
        if (subpastas) fd.append('subpastas', '1');
        el('perm-salvar').disabled = true;
        const barra = el('perm-progresso-barra');
        barra.className = 'progress-bar progress-bar-striped progress-bar-animated';
        barra.style.width = '0%';
        el('perm-progresso-texto').textContent = 'Aplicando...';
        el('perm-progresso').classList.remove('d-none');
        try {
            const r = await (await fetch(URL_PERM, { method: 'POST', body: fd })).json();
            if (!r.success) {
                el('perm-progresso-texto').textContent = r.message || 'Não foi possível salvar.';
                barra.classList.remove('progress-bar-animated');
                barra.classList.add('bg-danger');
                barra.style.width = '100%';
                el('perm-salvar').disabled = false;
                return;
            }
            acompanhar();
        } catch (e) {
            el('perm-progresso-texto').textContent = 'Erro ao falar com o servidor.';
            el('perm-salvar').disabled = false;
        }
    });

    modalEl.addEventListener('hidden.bs.modal', () => clearInterval(pollTimer));
    document.addEventListener('click', e => {
        const b = e.target.closest('.btn-permissoes');
        if (b) abrir(b.getAttribute('data-path'), b.getAttribute('data-name'));
    });
})();
</script>
