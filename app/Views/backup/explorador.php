<?php

use App\Components\Alert;

ob_start();
?>

<style>
.expl-crumbs { display:flex; flex-wrap:wrap; align-items:center; gap:4px; row-gap:6px; }
.expl-crumb {
    display:inline-flex; align-items:center; gap:6px;
    padding:5px 12px; border-radius:8px; font-size:13.5px;
    color:#475569; text-decoration:none; background:#fff; border:1px solid #e2e8f0;
}
button.expl-crumb { cursor:pointer; }
button.expl-crumb:hover { background:#eff6ff; color:#2563eb; }
.expl-crumb-atual { background:#0d6efd; color:#fff; border-color:#0d6efd; font-weight:600; }
.expl-sep { color:#cbd5e1; font-size:11px; }
.expl-item-nome { cursor:pointer; }
.expl-item-nome:hover { color:#2563eb; }
.expl-modo-card {
    display:block; text-decoration:none; border:1px solid #e2e8f0; border-radius:12px;
    padding:20px; text-align:center; color:#1e293b; transition:.15s;
}
.expl-modo-card:hover { border-color:#0d6efd; background:#eff6ff; color:#1e293b; }
.expl-modo-card i { font-size:28px; display:block; margin-bottom:8px; }
</style>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-clock-history me-1"></i> Explorador de Versões</h4>
        <small class="text-muted">Navega direto no que já foi enviado à nuvem -- funciona pra qualquer arquivo, de qualquer época, mesmo antes desta tela existir.</small>
    </div>
    <a href="<?= url('/backup/historico') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Histórico
    </a>
</div>

<?php if (empty($destinos)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-muted py-5">
            Nenhum destino de backup cadastrado ainda. Configure um em
            <a href="<?= url('/backup/configuracao') ?>">Backup > Configuração</a>.
        </div>
    </div>
<?php else: ?>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-center">
            <div class="col-auto">
                <label class="col-form-label col-form-label-sm">Destino</label>
            </div>
            <div class="col-md-4">
                <select class="form-select form-select-sm" id="selectDestino">
                    <?php foreach ($destinos as $d): ?>
                        <option value="<?= (int)$d['id'] ?>"><?= htmlspecialchars($d['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
</div>

<nav class="mb-3">
    <div class="expl-crumbs" id="explBreadcrumb"></div>
</nav>

<div class="alert alert-secondary py-2 small" id="explAvisoRestaurar">
    <i class="bi bi-info-circle me-1"></i>
    "Restaurar" sobrescreve o arquivo atual do compartilhamento com esta versão -- o que estiver lá agora é
    arquivado automaticamente antes, então dá pra desfazer se restaurar a errada.
</div>


<div class="row g-3 mb-3 d-none" id="explEscolhaModo">
    <div class="col-md-6">
        <a href="#" class="expl-modo-card" id="botaoModoAtual">
            <i class="bi bi-cloud-check text-primary"></i>
            <strong>Cópia atual</strong>
            <div class="small text-muted mt-1">O que está backupeado agora na nuvem -- útil pra recuperar algo apagado hoje, mesmo antes do próximo backup rodar.</div>
        </a>
    </div>
    <div class="col-md-6">
        <a href="#" class="expl-modo-card" id="botaoModoVersoes">
            <i class="bi bi-clock-history text-primary"></i>
            <strong>Versões antigas</strong>
            <div class="small text-muted mt-1">Arquivos que já foram substituídos ou excluídos em algum momento, organizados por data do backup.</div>
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div id="explCarregando" class="text-center text-muted py-5">
            <div class="spinner-border spinner-border-sm me-2"></div>Carregando...
        </div>
        <div id="explVazio" class="text-center text-muted py-5 d-none">
            <i class="bi bi-inbox display-6 d-block mb-2"></i>
            Nada aqui ainda.
        </div>
        <table class="table table-hover align-middle mb-0 d-none" id="explTabela">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Tamanho</th>
                    <th>Modificado</th>
                    <th class="text-end">Ação</th>
                </tr>
            </thead>
            <tbody id="explCorpo"></tbody>
        </table>
    </div>
</div>

<div id="explResultado" class="mt-2"></div>

<script>
(function () {
    const URLS = {
        compartilhamentos: <?= json_encode(url('/backup/explorador/compartilhamentos')) ?>,
        datas: <?= json_encode(url('/backup/explorador/datas')) ?>,
        itens: <?= json_encode(url('/backup/explorador/itens')) ?>,
        itensAtuais: <?= json_encode(url('/backup/explorador/itens-atuais')) ?>,
        restaurar: <?= json_encode(url('/backup/explorador/restaurar')) ?>,
        baixar: <?= json_encode(url('/backup/explorador/baixar')) ?>,
    };

    // estado da navegacao -- null = ainda nao escolhido. modo: null | 'atual' | 'versoes'
    // (depois de escolher o compartilhamento, o admin escolhe entre navegar
    // a copia ATUAL na nuvem ou as VERSOES antigas em .versoes/)
    let estado = { destinoId: null, compartilhamento: null, modo: null, timestamp: null, subpath: '' };

    // "timestamp" que os endpoints de baixar/restaurar entendem -- na copia
    // atual nao existe pasta de data nenhuma, o script trata a palavra
    // reservada "atual" como "fora de .versoes/"
    function timestampParaAcao() {
        return estado.modo === 'atual' ? 'atual' : estado.timestamp;
    }

    function formatarBytes(bytes) {
        if (!bytes || bytes <= 0) return '-';
        const unidades = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), unidades.length - 1);
        return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + unidades[i];
    }

    function escapeHtml(s) {
        const div = document.createElement('div');
        div.textContent = (s == null) ? '' : String(s);
        return div.innerHTML;
    }

    // "20260801_190001" -> "01/08/2026 19:00:01"; "restauracao_20260801_190001" -> "Cópia de segurança -- 01/08 19:00"
    function formatarTimestamp(ts) {
        const seguranca = ts.startsWith('restauracao_');
        const bruto = seguranca ? ts.replace('restauracao_', '') : ts;
        const m = bruto.match(/^(\d{4})(\d{2})(\d{2})_(\d{2})(\d{2})(\d{2})$/);
        if (!m) return ts;
        const [, ano, mes, dia, h, min] = m;
        const data = `${dia}/${mes}/${ano} ${h}:${min}`;
        return seguranca ? `Cópia de segurança -- ${data}` : data;
    }

    function mostrarResultado(msg, ok) {
        const el = document.getElementById('explResultado');
        el.innerHTML = '<div class="alert alert-' + (ok ? 'success' : 'danger') + ' py-2 mb-0">' + escapeHtml(msg) + '</div>';
        setTimeout(function () { el.innerHTML = ''; }, 5000);
    }

    function renderBreadcrumb() {
        const bc = document.getElementById('explBreadcrumb');
        bc.innerHTML = '';

        function crumb(label, ativo, onClick) {
            const el = document.createElement(onClick ? 'button' : 'span');
            el.type = onClick ? 'button' : undefined;
            el.className = 'expl-crumb' + (ativo ? ' expl-crumb-atual' : '');
            el.textContent = label;
            if (onClick) el.addEventListener('click', onClick);
            return el;
        }

        function sep() {
            const s = document.createElement('span');
            s.className = 'expl-sep';
            s.innerHTML = '<i class="bi bi-chevron-right"></i>';
            return s;
        }

        bc.appendChild(crumb('Compartilhamentos', estado.compartilhamento === null, function () {
            estado.compartilhamento = null; estado.modo = null; estado.timestamp = null; estado.subpath = '';
            carregar();
        }));

        if (estado.compartilhamento) {
            bc.appendChild(sep());
            bc.appendChild(crumb(estado.compartilhamento, estado.modo === null, function () {
                estado.modo = null; estado.timestamp = null; estado.subpath = '';
                carregar();
            }));
        }

        if (estado.modo === 'atual') {
            bc.appendChild(sep());
            bc.appendChild(crumb('Cópia atual', estado.subpath === '', function () {
                estado.subpath = '';
                carregar();
            }));
        }

        if (estado.modo === 'versoes' && estado.timestamp) {
            bc.appendChild(sep());
            bc.appendChild(crumb(formatarTimestamp(estado.timestamp), estado.subpath === '', function () {
                estado.subpath = '';
                carregar();
            }));
        }

        if (estado.subpath) {
            const partes = estado.subpath.split('/').filter(Boolean);
            let acumulado = '';
            partes.forEach(function (parte, i) {
                acumulado = acumulado ? acumulado + '/' + parte : parte;
                const alvo = acumulado;
                bc.appendChild(sep());
                bc.appendChild(crumb(parte, i === partes.length - 1, function () {
                    estado.subpath = alvo;
                    carregar();
                }));
            });
        }
    }

    function celulaAcao(item) {
        if (item.IsDir) return '';
        const params = new URLSearchParams({
            destino_id: estado.destinoId, compartilhamento: estado.compartilhamento,
            timestamp: timestampParaAcao(), caminho: item.Path,
        });
        return '<a class="btn btn-sm btn-outline-primary" href="' + URLS.baixar + '?' + params.toString() + '" title="Baixar esta versão">' +
            '<i class="bi bi-download"></i></a> ' +
            '<button type="button" class="btn btn-sm btn-outline-warning botao-restaurar-expl" data-caminho="' + escapeHtml(item.Path) + '" title="Restaurar esta versão">' +
            '<i class="bi bi-arrow-counterclockwise"></i></button>';
    }

    async function carregar() {
        renderBreadcrumb();

        const carregando = document.getElementById('explCarregando');
        const vazio = document.getElementById('explVazio');
        const tabela = document.getElementById('explTabela');
        const aviso = document.getElementById('explAvisoRestaurar');
        const escolhaModo = document.getElementById('explEscolhaModo');
        const card = tabela.closest('.card');

        // "escolher modo" (Copia atual / Versoes antigas) e um passo a parte,
        // sem tabela nenhuma
        if (estado.compartilhamento !== null && estado.modo === null) {
            carregando.classList.add('d-none');
            vazio.classList.add('d-none');
            tabela.classList.add('d-none');
            aviso.classList.add('d-none');
            card.classList.add('d-none');
            escolhaModo.classList.remove('d-none');
            return;
        }
        escolhaModo.classList.add('d-none');
        card.classList.remove('d-none');

        carregando.classList.remove('d-none');
        vazio.classList.add('d-none');
        tabela.classList.add('d-none');
        aviso.classList.toggle('d-none', !(estado.modo === 'atual' || (estado.modo === 'versoes' && estado.timestamp)));

        try {
            let itens, tipo;

            if (estado.compartilhamento === null) {
                tipo = 'compartilhamento';
                const res = await fetch(URLS.compartilhamentos + '?destino_id=' + estado.destinoId);
                itens = (await res.json()).map(function (nome) { return { Name: nome, IsDir: true, tipo: 'compartilhamento' }; });
            } else if (estado.modo === 'versoes' && estado.timestamp === null) {
                tipo = 'data';
                const res = await fetch(URLS.datas + '?destino_id=' + estado.destinoId + '&compartilhamento=' + encodeURIComponent(estado.compartilhamento));
                itens = (await res.json()).map(function (ts) { return { Name: formatarTimestamp(ts), Path: ts, IsDir: true, tipo: 'data' }; });
            } else if (estado.modo === 'atual') {
                tipo = 'item';
                const params = new URLSearchParams({
                    destino_id: estado.destinoId, compartilhamento: estado.compartilhamento, subpath: estado.subpath,
                });
                const res = await fetch(URLS.itensAtuais + '?' + params.toString());
                const dados = await res.json();
                itens = Array.isArray(dados) ? dados : [];
            } else {
                tipo = 'item';
                const params = new URLSearchParams({
                    destino_id: estado.destinoId, compartilhamento: estado.compartilhamento,
                    timestamp: estado.timestamp, subpath: estado.subpath,
                });
                const res = await fetch(URLS.itens + '?' + params.toString());
                const dados = await res.json();
                itens = Array.isArray(dados) ? dados : [];
            }

            carregando.classList.add('d-none');

            if (!itens.length) {
                vazio.classList.remove('d-none');
                return;
            }

            document.getElementById('explCorpo').innerHTML = itens.map(function (item) {
                const nome = item.Name;
                const icone = item.IsDir ? 'bi-folder-fill text-warning' : 'bi-file-earmark';
                let onClickAttr = '';
                if (item.IsDir) {
                    if (tipo === 'compartilhamento') onClickAttr = 'data-nav="compartilhamento" data-valor="' + escapeHtml(nome) + '"';
                    else if (tipo === 'data') onClickAttr = 'data-nav="data" data-valor="' + escapeHtml(item.Path) + '"';
                    else onClickAttr = 'data-nav="subpasta" data-valor="' + escapeHtml(item.Path) + '"';
                }
                return '<tr>' +
                    '<td><i class="bi ' + icone + ' me-2"></i>' +
                    (item.IsDir
                        ? '<span class="expl-item-nome" ' + onClickAttr + '>' + escapeHtml(nome) + '</span>'
                        : escapeHtml(nome)) +
                    '</td>' +
                    '<td class="small text-muted">' + (item.IsDir ? '-' : formatarBytes(item.Size)) + '</td>' +
                    '<td class="small text-muted">' + (item.ModTime ? escapeHtml(item.ModTime.substring(0, 16).replace('T', ' ')) : '-') + '</td>' +
                    '<td class="text-end">' + celulaAcao(item) + '</td>' +
                    '</tr>';
            }).join('');

            tabela.classList.remove('d-none');
        } catch (e) {
            carregando.classList.add('d-none');
            vazio.textContent = 'Erro de rede ao carregar.';
            vazio.classList.remove('d-none');
        }
    }

    document.getElementById('explCorpo').addEventListener('click', async function (e) {
        const nav = e.target.closest('[data-nav]');
        if (nav) {
            const tipo = nav.dataset.nav;
            const valor = nav.dataset.valor;
            if (tipo === 'compartilhamento') estado.compartilhamento = valor;
            else if (tipo === 'data') estado.timestamp = valor;
            else if (tipo === 'subpasta') estado.subpath = valor;
            carregar();
            return;
        }

        const btnRestaurar = e.target.closest('.botao-restaurar-expl');
        if (btnRestaurar) {
            const caminho = btnRestaurar.dataset.caminho;
            if (!confirm('Restaurar esta versão de "' + estado.compartilhamento + '/' + caminho + '"? O arquivo atual no compartilhamento será substituído (uma cópia dele é arquivada automaticamente antes, então dá pra desfazer).')) {
                return;
            }
            btnRestaurar.disabled = true;
            try {
                const dados = new URLSearchParams({
                    destino_id: estado.destinoId, compartilhamento: estado.compartilhamento,
                    timestamp: timestampParaAcao(), caminho: caminho,
                });
                const res = await fetch(URLS.restaurar, { method: 'POST', body: dados });
                const resposta = await res.json();
                mostrarResultado(resposta.message || (resposta.success ? 'Restaurado.' : 'Falha ao restaurar.'), !!resposta.success);
            } catch (e2) {
                mostrarResultado('Erro de rede ao restaurar.', false);
            } finally {
                btnRestaurar.disabled = false;
            }
        }
    });

    document.getElementById('botaoModoAtual').addEventListener('click', function (e) {
        e.preventDefault();
        estado.modo = 'atual'; estado.subpath = '';
        carregar();
    });

    document.getElementById('botaoModoVersoes').addEventListener('click', function (e) {
        e.preventDefault();
        estado.modo = 'versoes'; estado.timestamp = null; estado.subpath = '';
        carregar();
    });

    document.getElementById('selectDestino').addEventListener('change', function () {
        estado = { destinoId: this.value, compartilhamento: null, modo: null, timestamp: null, subpath: '' };
        carregar();
    });

    estado.destinoId = document.getElementById('selectDestino').value;
    carregar();
})();
</script>

<?php endif; ?>

<?php
$conteudo = ob_get_clean();
$titulo = 'Backup - Explorador de Versões';

require __DIR__ . '/../layouts/main.php';
