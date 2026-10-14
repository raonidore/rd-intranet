<?php
/**
 * Monitor de conexões, processos e inicialização (agente 1.0.56+) --
 * cartão da aba Segurança do ativo. Dados vêm de /ativos/seguranca/monitor
 * (último retrato de cada tipo); ações viram solicitações pro agente e a
 * tela acompanha o resultado. Precisa de: $ativo, $podeEditarAtivo.
 */
?>
<div class="col-12">
    <div class="card border-0 shadow-sm" id="monSeg" data-ativo="<?= (int)$ativo['id'] ?>" data-pode-editar="<?= $podeEditarAtivo ? '1' : '0' ?>">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <strong><i class="bi bi-diagram-3"></i> Conexões, processos e inicialização</strong>
                <span class="small text-muted ms-1" id="monSegStatus"></span>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="monSegAtualizar" title="Pede à máquina um retrato novo agora">
                <i class="bi bi-arrow-repeat"></i> Atualizar agora
            </button>
        </div>
        <div class="card-body p-0">
            <div id="monSegAviso"></div>
            <div class="mon-resumo px-3 py-3 border-bottom" id="monSegResumo">
                <span class="text-muted small"><span class="spinner-border spinner-border-sm"></span> Carregando...</span>
            </div>

            <ul class="nav nav-underline mon-abas px-3 pt-2 border-bottom" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-mon-aba="programas" type="button">Programas com rede <span class="badge rounded-pill text-bg-light border" data-mon-contador="programas"></span></button></li>
                <li class="nav-item"><button class="nav-link" data-mon-aba="suspeitos" type="button">Processos suspeitos <span class="badge rounded-pill" data-mon-contador="suspeitos"></span></button></li>
                <li class="nav-item"><button class="nav-link" data-mon-aba="inicializacao" type="button">Inicialização <span class="badge rounded-pill" data-mon-contador="inicializacao"></span></button></li>
                <li class="nav-item"><button class="nav-link" data-mon-aba="firewall" type="button">Firewall <span class="badge rounded-pill" data-mon-contador="firewall"></span></button></li>
            </ul>

            <div class="mon-painel" data-mon-painel="programas">
                <div class="d-flex flex-wrap gap-2 align-items-center px-3 py-2">
                    <input type="search" class="form-control form-control-sm" style="max-width:280px" placeholder="Filtrar programa, IP ou porta..." data-mon-busca="programas">
                    <div class="form-check form-switch small mb-0">
                        <input class="form-check-input" type="checkbox" id="monSegSoAtencao" data-mon-filtro="programas">
                        <label class="form-check-label" for="monSegSoAtencao">Só o que merece atenção</label>
                    </div>
                </div>
                <div class="table-responsive"><table class="table table-sm align-middle mb-0 mon-tabela"><tbody id="monSegProgramas"></tbody></table></div>
            </div>

            <div class="mon-painel d-none" data-mon-painel="suspeitos"><div id="monSegSuspeitos" class="p-3"></div></div>

            <div class="mon-painel d-none" data-mon-painel="inicializacao">
                <div class="d-flex flex-wrap gap-2 align-items-center px-3 py-2">
                    <input type="search" class="form-control form-control-sm" style="max-width:280px" placeholder="Filtrar nome ou caminho..." data-mon-busca="inicializacao">
                    <div class="form-check form-switch small mb-0">
                        <input class="form-check-input" type="checkbox" id="monSegMostrarMs" data-mon-filtro="inicializacao">
                        <label class="form-check-label" for="monSegMostrarMs">Mostrar itens da Microsoft</label>
                    </div>
                    <span class="small text-muted ms-auto" id="monSegInicioInfo"></span>
                </div>
                <div class="table-responsive"><table class="table table-sm align-middle mb-0 mon-tabela"><tbody id="monSegInicializacao"></tbody></table></div>
            </div>

            <div class="mon-painel d-none" data-mon-painel="firewall"><div id="monSegFirewall" class="p-3"></div></div>
        </div>
    </div>
</div>

<style>
.mon-resumo { display:flex; flex-wrap:wrap; gap:2rem; }
.mon-resumo .valor { font-size:1.35rem; font-weight:600; line-height:1.1; }
.mon-resumo .rotulo { font-size:.72rem; color:#6c757d; text-transform:uppercase; letter-spacing:.03em; }
.mon-resumo .ruim .valor { color:#c0392b; }
.mon-abas .nav-link { font-size:.9rem; padding:.45rem .1rem; margin-right:1.1rem; color:#5c6670; }
.mon-abas .nav-link.active { color:#1b2733; font-weight:600; }
.mon-tabela td { border-color:#eef0f2; }
.mon-tabela tr.mon-linha { cursor:pointer; }
.mon-tabela tr.mon-linha:hover { background:#f8f9fb; }
.mon-tabela tr.mon-detalhe > td { background:#fafbfc; padding:.75rem 1rem 1rem 2.6rem; }
.mon-nivel { width:9px; height:9px; border-radius:50%; display:inline-block; }
.mon-nivel.ok { background:#cfd5db; } .mon-nivel.atencao { background:#e0a800; } .mon-nivel.suspeito { background:#e8590c; } .mon-nivel.critico { background:#c92a2a; box-shadow:0 0 0 3px rgba(201,42,42,.15); }
.mon-nome { font-weight:600; }
.mon-caminho { font-family:var(--bs-font-monospace); font-size:.75rem; color:#7a838c; word-break:break-all; }
.mon-chip { display:inline-block; font-size:.72rem; padding:.05rem .45rem; border-radius:999px; border:1px solid #dfe3e7; color:#5c6670; background:#fff; margin:.1rem .2rem .1rem 0; white-space:nowrap; }
.mon-chip.ruim { border-color:#f1c0c0; color:#b02a2a; background:#fff5f5; }
.mon-chip.bom { border-color:#c7e6d2; color:#1f7a45; background:#f3fbf6; }
.mon-motivos { margin:.25rem 0 .5rem; padding-left:1.1rem; font-size:.85rem; }
.mon-motivos li { margin:.1rem 0; }
.mon-acoes { opacity:.45; transition:opacity .15s; white-space:nowrap; }
.mon-tabela tr:hover .mon-acoes, .mon-acoes:focus-within, .mon-cartao:hover .mon-acoes { opacity:1; }
.mon-acoes .btn { padding:.1rem .35rem; }
.mon-cartao { border:1px solid #e6e9ec; border-radius:.5rem; padding:.85rem 1rem; margin-bottom:.75rem; }
.mon-cartao.critico { border-left:4px solid #c92a2a; } .mon-cartao.suspeito { border-left:4px solid #e8590c; }
.mon-kv { font-size:.82rem; color:#5c6670; }
.mon-kv b { color:#2b3640; font-weight:600; }
.mon-vazio { color:#7a838c; font-size:.9rem; padding:1.25rem 1rem; }
</style>

<script>
(function () {
    const raiz = document.getElementById('monSeg');
    if (!raiz) return;
    const ativoId = raiz.dataset.ativo;
    const podeEditar = raiz.dataset.podeEditar === '1';
    const urlDados = <?= json_encode(url('/ativos/seguranca/monitor')) ?>;
    const urlAcao = <?= json_encode(url('/ativos/seguranca/monitor/acao')) ?>;
    const urlResultado = <?= json_encode(url('/ativos/solicitacoes/resultado')) ?>;
    let dados = null;
    let carregado = false;

    const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const fmtNum = (n) => Number(n || 0).toLocaleString('pt-BR');
    const ha = (iso) => {
        if (!iso) return 'nunca';
        const seg = Math.max(0, (Date.now() - new Date(iso.replace(' ', 'T')).getTime()) / 1000);
        if (seg < 90) return 'agora há pouco';
        if (seg < 3600) return 'há ' + Math.round(seg / 60) + ' min';
        if (seg < 86400) return 'há ' + Math.round(seg / 3600) + ' h';
        return 'há ' + Math.round(seg / 86400) + ' dia(s)';
    };
    const nivelPonto = (n) => '<span class="mon-nivel ' + esc(n || 'ok') + '" title="' + esc(({ ok: 'Normal', atencao: 'Atenção', suspeito: 'Suspeito', critico: 'Crítico' })[n] || n) + '"></span>';
    const assinaturaChip = (p) => {
        if (p.assinatura === 'valida' || p.assinatura === 'catalogo' || p.assinatura === 'pacote') return '<span class="mon-chip bom" title="Assinatura digital válida"><i class="bi bi-patch-check"></i> ' + esc(p.assinante || 'assinado') + '</span>';
        if (p.assinatura === 'invalida') return '<span class="mon-chip ruim"><i class="bi bi-patch-exclamation"></i> assinatura inválida</span>';
        if (p.assinatura === 'expirada') return '<span class="mon-chip" title="Assinado, mas o certificado venceu">' + esc(p.assinante || 'assinado') + ' · expirado</span>';
        if (p.assinatura === 'nao_confiavel') return '<span class="mon-chip" title="Assinado por certificado que o Windows não reconhece (ex.: certificado próprio)">' + esc(p.assinante || 'assinado') + ' · certificado próprio</span>';
        if (p.assinatura === 'ausente') return '<span class="mon-chip ruim">sem assinatura</span>';
        return '';
    };
    const motivosLista = (m) => (m && m.length) ? '<ul class="mon-motivos">' + m.map((x) => '<li>' + esc(x) + '</li>').join('') + '</ul>' : '';

    function aviso(html, tipo) {
        document.getElementById('monSegAviso').innerHTML = html ? '<div class="alert alert-' + (tipo || 'info') + ' small rounded-0 border-0 border-bottom mb-0 py-2 px-3">' + html + '</div>' : '';
    }

    // ---------------------------------------------------------------- ações

    function botaoAcao(acao, rotulo, icone, extra, classe) {
        if (!podeEditar) return '';
        const attrs = Object.entries(extra || {}).map(([k, v]) => ' data-' + k + '="' + esc(v) + '"').join('');
        return '<button type="button" class="btn btn-sm btn-link ' + (classe || 'text-secondary') + '" data-mon-acao="' + acao + '"' + attrs + ' title="' + esc(rotulo) + '"><i class="bi ' + icone + '"></i></button>';
    }

    const confirmacoes = {
        bloquear_programa: (d) => 'Bloquear TODA a rede (entrada e saída) do programa\n' + d.valor + '\nno firewall do Windows desta máquina?\n\nSe o firewall estiver desligado, ele é ligado com tudo liberado por padrão (só os bloqueios do RD.Intranet passam a valer).',
        bloquear_ip: (d) => 'Bloquear a saída desta máquina para ' + d.valor + ' no firewall do Windows?',
        desbloquear: (d) => 'Remover o bloqueio "' + d.regra + '"?',
        desativar_servico: (d) => 'Parar e DESATIVAR o serviço "' + d.servico + '"? Ele não volta sozinho no próximo reinício.',
        encerrar_processo: (d) => 'Encerrar agora o processo ' + d.pid + (d.caminho ? ' (' + d.caminho + ')' : '') + '?\nSe algo o relança (serviço/vigia), ele volta -- bloqueie ou desative a origem.'
    };

    async function executarAcao(acao, d) {
        const pergunta = confirmacoes[acao] ? confirmacoes[acao](d) : null;
        if (pergunta && !confirm(pergunta)) return;

        const corpo = new FormData();
        corpo.set('id', ativoId);
        corpo.set('acao', acao);
        Object.entries(d).forEach(([k, v]) => corpo.set(k, v));
        if (acao.startsWith('bloquear')) corpo.set('motivo', 'Bloqueado pela aba Segurança do RD.Intranet');

        aviso('<span class="spinner-border spinner-border-sm"></span> Enviando o pedido à máquina...', 'secondary');
        let r;
        try {
            r = await (await fetch(urlAcao, { method: 'POST', body: corpo })).json();
        } catch (e) {
            aviso('Falha ao enviar o pedido.', 'danger');
            return;
        }
        if (!r.success || !r.id) {
            aviso(esc(r.message || 'Não foi possível enviar o pedido.'), 'danger');
            return;
        }

        aviso('<span class="spinner-border spinner-border-sm"></span> Aguardando a máquina executar (ela recebe no próximo heartbeat)...', 'secondary');
        const limite = Date.now() + 180000;
        while (Date.now() < limite) {
            await new Promise((ok) => setTimeout(ok, 2500));
            let s;
            try {
                s = await (await fetch(urlResultado + '?id=' + r.id + '&ativo_id=' + ativoId)).json();
            } catch (e) { continue; }
            if (!s.success || s.status === 'pendente') continue;
            if (s.status === 'erro') {
                aviso('<i class="bi bi-x-circle"></i> ' + esc(s.mensagem || 'A máquina devolveu erro.'), 'danger');
                return;
            }
            const res = s.resultado || {};
            if (res.sucesso === false) {
                aviso('<i class="bi bi-x-circle"></i> ' + esc(res.mensagem || 'A máquina recusou.'), 'danger');
                return;
            }
            aviso('<i class="bi bi-check-circle"></i> ' + esc(res.mensagem || 'Feito.') + ' O retrato é atualizado em instantes.', 'success');
            setTimeout(carregar, 9000);
            return;
        }
        aviso('A máquina não respondeu em 3 minutos (desligada ou sem o agente 1.0.56). O pedido continua na fila.', 'warning');
    }

    raiz.addEventListener('click', (ev) => {
        const botao = ev.target.closest('[data-mon-acao]');
        if (botao) {
            ev.stopPropagation();
            const d = Object.assign({}, botao.dataset);
            const acao = d.monAcao;
            delete d.monAcao;
            executarAcao(acao, d);
            return;
        }
        const aba = ev.target.closest('[data-mon-aba]');
        if (aba) {
            raiz.querySelectorAll('[data-mon-aba]').forEach((b) => b.classList.toggle('active', b === aba));
            raiz.querySelectorAll('[data-mon-painel]').forEach((p) => p.classList.toggle('d-none', p.dataset.monPainel !== aba.dataset.monAba));
            return;
        }
        const linha = ev.target.closest('tr.mon-linha');
        if (linha && linha.nextElementSibling && linha.nextElementSibling.classList.contains('mon-detalhe')) {
            linha.nextElementSibling.classList.toggle('d-none');
        }
    });
    raiz.querySelectorAll('[data-mon-busca], [data-mon-filtro]').forEach((el) => el.addEventListener('input', desenhar));
    document.getElementById('monSegAtualizar').addEventListener('click', () => executarAcao('atualizar', {}));

    // ---------------------------------------------------------------- desenho

    function desenharProgramas(rede) {
        const busca = (raiz.querySelector('[data-mon-busca="programas"]').value || '').toLowerCase();
        const soAtencao = raiz.querySelector('[data-mon-filtro="programas"]').checked;
        const lista = (rede.processos || []).filter((p) => {
            if (soAtencao && (p.pontos || 0) < 15 && !p.tentativas) return false;
            if (!busca) return true;
            return [p.nome, p.caminho, p.servico, (p.estabelecidas || []).map((e) => e.ip + ':' + e.porta).join(' '), (p.escutando || []).join(' ')].join(' ').toLowerCase().includes(busca);
        });
        document.querySelector('[data-mon-contador="programas"]').textContent = (rede.processos || []).length;

        document.getElementById('monSegProgramas').innerHTML = lista.map((p) => {
            const internet = (p.estabelecidas || []).filter((e) => e.publico);
            const conversa = internet.length
                ? internet.slice(0, 2).map((e) => '<span class="mon-chip">' + esc(e.ip) + ':' + e.porta + '</span>').join('') + (internet.length > 2 ? '<span class="small text-muted">+' + (internet.length - 2) + '</span>' : '')
                : '<span class="small text-muted">' + (p.estabelecidas_total ? fmtNum(p.estabelecidas_total) + ' na rede local' : '—') + '</span>';
            const t = p.tentativas;
            const tentativas = t ? '<span class="mon-chip ruim" title="Tentativas de conexão nos últimos minutos"><i class="bi bi-broadcast"></i> ' + fmtNum(t.ips_distintos) + ' IPs · portas ' + esc((t.portas || []).slice(0, 3).map((x) => x.porta).join(', ')) + '</span>' : '';
            const escuta = (p.escutando || []).length ? '<span class="small text-muted" title="Portas abertas esperando conexão">escuta ' + esc(p.escutando.slice(0, 4).join(', ')) + (p.escutando.length > 4 ? '…' : '') + '</span>' : '';

            const acoes = (p.caminho ? botaoAcao('bloquear_programa', 'Bloquear a rede deste programa', 'bi-slash-circle', { valor: p.caminho }, 'text-danger') : '')
                + (p.pid > 4 ? botaoAcao('encerrar_processo', 'Encerrar processo', 'bi-x-octagon', { pid: p.pid, caminho: p.caminho || '' }) : '');

            const conexoes = (p.estabelecidas || []).map((e) => '<tr><td class="font-monospace small">' + esc(e.ip) + ':' + e.porta + '</td><td class="small text-muted">' + (e.publico ? 'internet' : 'rede local') + (e.qtd > 1 ? ' · ' + e.qtd + ' conexões' : '') + '</td><td class="text-end">' + (e.publico ? botaoAcao('bloquear_ip', 'Bloquear este IP no firewall da máquina', 'bi-slash-circle', { valor: e.ip }, 'text-danger') : '') + '</td></tr>').join('');
            const detalheTentativas = t ? '<div class="mon-kv mt-2"><b>Tentativas</b> (' + esc(rede.janela_minutos || 5) + ' min): ' + fmtNum(t.ips_distintos) + ' IPs, ' + fmtNum(t.internet) + ' na internet. Faixas: ' + esc((t.faixas || []).map((f) => f.faixa + ' (' + f.qtd + ')').join(', ')) + '. Portas: ' + esc((t.portas || []).map((x) => x.porta + ' (' + x.qtd + ')').join(', ')) + '</div>' : '';

            return '<tr class="mon-linha">'
                + '<td style="width:22px">' + nivelPonto(p.nivel) + '</td>'
                + '<td><div class="mon-nome">' + esc(p.nome) + (p.servico ? ' <span class="mon-chip" title="Serviço do Windows">' + esc(p.servico) + '</span>' : '') + '</div><div class="mon-caminho">' + esc(p.caminho || 'caminho indisponível') + '</div></td>'
                + '<td>' + assinaturaChip(p) + '</td>'
                + '<td>' + conversa + ' ' + tentativas + '</td>'
                + '<td>' + escuta + '</td>'
                + '<td class="text-end"><span class="mon-acoes">' + acoes + '</span></td>'
                + '</tr>'
                + '<tr class="mon-detalhe d-none"><td colspan="6">'
                + motivosLista(p.motivos)
                + '<div class="mon-kv"><b>PID</b> ' + esc(p.pid) + (p.pai_nome ? ' · <b>aberto por</b> ' + esc(p.pai_nome) + ' (' + esc(p.pai_pid) + ')' : '') + (p.como_system ? ' · <b>roda como SYSTEM</b>' : '') + '</div>'
                + (p.linha_comando ? '<div class="mon-caminho mt-1">' + esc(p.linha_comando) + '</div>' : '')
                + detalheTentativas
                + (conexoes ? '<table class="table table-sm mb-0 mt-2" style="max-width:640px">' + conexoes + '</table>' : '')
                + '</td></tr>';
        }).join('') || '<tr><td class="mon-vazio">' + (busca || soAtencao ? 'Nada com esse filtro.' : 'Nenhum programa com rede no último retrato.') + '</td></tr>';
    }

    function desenharSuspeitos(rede) {
        const lista = rede.processos_suspeitos || [];
        const contador = document.querySelector('[data-mon-contador="suspeitos"]');
        contador.textContent = lista.length || '';
        contador.className = 'badge rounded-pill ' + (lista.some((s) => s.nivel === 'critico') ? 'text-bg-danger' : 'text-bg-warning');

        document.getElementById('monSegSuspeitos').innerHTML = lista.map((s) => {
            const acoes = botaoAcao('encerrar_processo', 'Encerrar processo', 'bi-x-octagon', { pid: s.pid, caminho: s.caminho || '' })
                + (s.caminho ? botaoAcao('bloquear_programa', 'Bloquear a rede deste programa', 'bi-slash-circle', { valor: s.caminho }, 'text-danger') : '')
                + (s.servico ? botaoAcao('desativar_servico', 'Parar e desativar o serviço ' + s.servico, 'bi-power', { servico: String(s.servico).split(',')[0].trim() }, 'text-danger') : '');
            return '<div class="mon-cartao ' + esc(s.nivel) + '">'
                + '<div class="d-flex justify-content-between gap-2"><div>' + nivelPonto(s.nivel) + ' <span class="mon-nome ms-1">' + esc(s.nome) + '</span> <span class="small text-muted">PID ' + esc(s.pid) + '</span>'
                + (s.servico ? ' <span class="mon-chip">serviço ' + esc(s.servico) + '</span>' : '') + (s.como_system ? ' <span class="mon-chip">SYSTEM</span>' : '') + ' ' + assinaturaChip(s) + '</div>'
                + '<span class="mon-acoes">' + acoes + '</span></div>'
                + '<div class="mon-caminho mt-1">' + esc(s.caminho) + '</div>'
                + motivosLista(s.motivos)
                + '<div class="mon-kv">' + (s.pai_nome ? '<b>Aberto por</b> ' + esc(s.pai_nome) + ' (' + esc(s.pai_pid) + ') · ' : '') + ((s.filhos || []).length ? '<b>Abriu</b> ' + esc(s.filhos.join(', ')) + ' · ' : '')
                + (s.arquivo_criado_em ? '<b>Arquivo criado</b> ' + esc(new Date(s.arquivo_criado_em).toLocaleDateString('pt-BR')) : '') + '</div>'
                + (s.linha_comando ? '<div class="mon-caminho mt-1">' + esc(s.linha_comando) + '</div>' : '')
                + (s.sha256 ? '<div class="mon-kv mt-1"><b>SHA256</b> <span class="font-monospace small">' + esc(s.sha256) + '</span> <a class="small" target="_blank" rel="noopener" href="https://www.virustotal.com/gui/file/' + esc(s.sha256) + '">consultar no VirusTotal</a></div>' : '')
                + '</div>';
        }).join('') || '<div class="mon-vazio p-0"><i class="bi bi-check-circle text-success"></i> Nenhum processo suspeito em execução no último retrato.</div>';
    }

    function desenharInicializacao(inicio) {
        const itens = (inicio && inicio.dados && inicio.dados.itens) || [];
        const busca = (raiz.querySelector('[data-mon-busca="inicializacao"]').value || '').toLowerCase();
        const mostrarMs = raiz.querySelector('[data-mon-filtro="inicializacao"]').checked;
        const ruins = itens.filter((i) => i.nivel === 'suspeito' || i.nivel === 'critico');
        const contador = document.querySelector('[data-mon-contador="inicializacao"]');
        contador.textContent = ruins.length || '';
        contador.className = 'badge rounded-pill ' + (ruins.some((i) => i.nivel === 'critico') ? 'text-bg-danger' : 'text-bg-warning');
        document.getElementById('monSegInicioInfo').textContent = inicio ? itens.length + ' itens · inventário ' + ha(inicio.recebido_em) : '';

        const rotulosTipo = { servico: 'serviço', tarefa: 'tarefa agendada', registro: 'registro (Run)', pasta_inicializar: 'pasta Inicializar', winlogon: 'Winlogon', ifeo: 'IFEO', wmi: 'WMI', appinit: 'AppInit' };
        const lista = itens.filter((i) => (mostrarMs || !i.ms) && (!busca || [i.nome, i.caminho, i.comando, i.origem].join(' ').toLowerCase().includes(busca)));

        document.getElementById('monSegInicializacao').innerHTML = lista.map((i) => {
            const nomeServico = i.tipo === 'servico' ? ((String(i.nome).match(/\(([^()]+)\)$/) || [null, i.nome])[1]) : null;
            const acoes = nomeServico && i.inicio !== 'Disabled' ? botaoAcao('desativar_servico', 'Parar e desativar este serviço', 'bi-power', { servico: nomeServico }, 'text-danger') : '';
            return '<tr class="mon-linha">'
                + '<td style="width:22px">' + nivelPonto(i.ms ? 'ok' : i.nivel) + '</td>'
                + '<td><div class="mon-nome">' + esc(i.nome) + '</div><div class="mon-caminho">' + esc(i.caminho || i.comando || '') + '</div></td>'
                + '<td><span class="mon-chip">' + esc(rotulosTipo[i.tipo] || i.tipo) + '</span>' + (i.estado ? '<span class="small text-muted">' + esc(i.estado) + (i.inicio ? ' · ' + esc(i.inicio) : '') + '</span>' : '') + '</td>'
                + '<td>' + (i.ms ? '<span class="mon-chip bom">Microsoft</span>' : assinaturaChip(i)) + (i.oculto ? '<span class="mon-chip ruim">oculto</span>' : '') + '</td>'
                + '<td class="text-end"><span class="mon-acoes">' + acoes + '</span></td>'
                + '</tr>'
                + '<tr class="mon-detalhe d-none"><td colspan="5">' + motivosLista(i.motivos)
                + (i.comando ? '<div class="mon-caminho">' + esc(i.comando) + '</div>' : '')
                + (i.origem ? '<div class="mon-kv mt-1"><b>Configurado em</b> ' + esc(i.origem) + (i.conta ? ' · <b>conta</b> ' + esc(i.conta) : '') + '</div>' : '')
                + (i.sha256 ? '<div class="mon-kv mt-1"><b>SHA256</b> <span class="font-monospace small">' + esc(i.sha256) + '</span> <a class="small" target="_blank" rel="noopener" href="https://www.virustotal.com/gui/file/' + esc(i.sha256) + '">VirusTotal</a></div>' : '')
                + '</td></tr>';
        }).join('') || '<tr><td class="mon-vazio">' + (inicio ? 'Nada com esse filtro.' : 'Inventário ainda não recebido (agente 1.0.56+).') + '</td></tr>';
    }

    function desenharFirewall(rede) {
        const fw = rede.firewall || {};
        const contador = document.querySelector('[data-mon-contador="firewall"]');
        const desligados = (fw.perfis || []).filter((p) => !p.ligado);
        contador.textContent = desligados.length ? '!' : '';
        contador.className = 'badge rounded-pill text-bg-danger';
        if (fw.erro) {
            document.getElementById('monSegFirewall').innerHTML = '<div class="mon-vazio p-0">Não foi possível ler o firewall: ' + esc(fw.erro) + '</div>';
            return;
        }
        const nomes = { dominio: 'Domínio', privado: 'Privado', publico: 'Público' };
        const perfis = (fw.perfis || []).map((p) => '<div class="mon-cartao mb-0" style="min-width:190px"><div class="d-flex justify-content-between"><b>' + esc(nomes[p.perfil] || p.perfil) + '</b>'
            + (p.ligado ? '<span class="mon-chip bom">ligado</span>' : '<span class="mon-chip ruim">desligado</span>') + '</div>'
            + '<div class="mon-kv mt-1">entrada: ' + esc(p.entrada_padrao) + ' · saída: ' + esc(p.saida_padrao) + '</div>'
            + (String(fw.perfil_ativo || '').includes(p.perfil) ? '<div class="small text-primary mt-1">em uso agora</div>' : '') + '</div>').join('');
        const regras = (fw.regras_rd || []).map((r) => '<tr><td>' + esc(r.nome) + '<div class="mon-caminho">' + esc(r.programa || r.ips || '') + '</div>' + (r.descricao ? '<div class="small text-muted">' + esc(r.descricao) + '</div>' : '') + '</td>'
            + '<td class="small">' + esc(r.direcao) + '</td><td class="small">' + (r.habilitada ? 'ativa' : 'desativada') + '</td>'
            + '<td class="text-end">' + botaoAcao('desbloquear', 'Remover este bloqueio', 'bi-trash', { regra: r.nome }, 'text-danger') + '</td></tr>').join('');

        document.getElementById('monSegFirewall').innerHTML =
            (desligados.length ? '<div class="alert alert-warning small py-2">O firewall do Windows está <b>desligado</b> em ' + desligados.map((p) => nomes[p.perfil]).join(', ') + '. Bloquear um programa ou IP pela tela liga o firewall com tudo liberado por padrão -- só os bloqueios do RD.Intranet passam a valer.</div>' : '')
            + '<div class="d-flex flex-wrap gap-2 mb-3">' + perfis + '</div>'
            + '<div class="mon-kv mb-2">' + fmtNum(fw.regras_total) + ' regras no total · ' + fmtNum(fw.bloqueios_ativos) + ' de bloqueio ativas</div>'
            + '<div class="fw-semibold small mb-1">Bloqueios feitos pelo RD.Intranet</div>'
            + (regras ? '<table class="table table-sm align-middle mb-3"><tbody>' + regras + '</tbody></table>' : '<div class="mon-vazio p-0 mb-3">Nenhum.</div>')
            + (podeEditar ? '<div class="d-flex flex-wrap gap-2 align-items-center"><input type="text" class="form-control form-control-sm" style="max-width:220px" placeholder="IP ou rede (1.2.3.0/24)" id="monSegIpManual"><button type="button" class="btn btn-sm btn-outline-danger" id="monSegBloquearIp"><i class="bi bi-slash-circle"></i> Bloquear saída para este IP</button></div>' : '');

        const botaoIp = document.getElementById('monSegBloquearIp');
        if (botaoIp) botaoIp.addEventListener('click', () => {
            const valor = document.getElementById('monSegIpManual').value.trim();
            if (valor) executarAcao('bloquear_ip', { valor });
        });
    }

    function desenhar() {
        if (!dados) return;
        const rede = (dados.retratos.rede || {}).dados || {};
        const recebidoRede = (dados.retratos.rede || {}).recebido_em;
        const t = rede.totais || {};

        document.getElementById('monSegStatus').textContent = dados.monitor.ativo
            ? '· verificação a cada ' + dados.monitor.intervalo + ' s · retrato ' + ha(recebidoRede)
            : '· desligado nesta máquina';

        const suspeitos = (rede.processos_suspeitos || []).length;
        const comTentativa = (rede.processos || []).filter((p) => p.tentativas).length;
        document.getElementById('monSegResumo').innerHTML = !recebidoRede
            ? '<span class="small text-muted">Nenhum retrato recebido ainda. Precisa do agente 1.0.56 ou mais novo' + (dados.monitor.ativo ? '' : ' e do monitor ligado (Central de Segurança ou acima, nesta máquina)') + '.</span>'
            : [
                ['Conexões', fmtNum(t.conexoes), false],
                ['Na internet', fmtNum(t.estabelecidas_internet), false],
                ['Tentativas agora', fmtNum(t.tentativas_em_andamento), (t.tentativas_em_andamento || 0) > 50],
                ['Programas varrendo', fmtNum(comTentativa), comTentativa > 0],
                ['Processos suspeitos', fmtNum(suspeitos), suspeitos > 0]
            ].map(([r, v, ruim]) => '<div class="' + (ruim ? 'ruim' : '') + '"><div class="valor">' + v + '</div><div class="rotulo">' + r + '</div></div>').join('');

        desenharProgramas(rede);
        desenharSuspeitos(rede);
        desenharInicializacao(dados.retratos.inicializacao);
        desenharFirewall(rede);
    }

    async function carregar() {
        try {
            const r = await (await fetch(urlDados + '?id=' + ativoId)).json();
            if (!r.success) {
                document.getElementById('monSegResumo').innerHTML = '<span class="text-danger small">' + esc(r.message || 'Falha ao carregar.') + '</span>';
                return;
            }
            dados = r;
            desenhar();
        } catch (e) {
            document.getElementById('monSegResumo').innerHTML = '<span class="text-danger small">Falha ao carregar o monitor.</span>';
        }
    }

    // Carrega só quando a aba Segurança abre (e de novo a cada 60 s enquanto aberta).
    const nav = document.querySelector('.nav-link[data-bs-target="#abaSeguranca"]');
    const abrir = () => { if (!carregado) { carregado = true; carregar(); } };
    if (nav) nav.addEventListener('shown.bs.tab', abrir);
    if (location.hash === '#abaSeguranca') abrir();
    setInterval(() => { if (carregado && document.getElementById('abaSeguranca').classList.contains('active')) carregar(); }, 60000);
})();
</script>
