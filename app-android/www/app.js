/**
 * RD.Intranet — app Android da equipe de TI (módulo Chamados).
 *
 * Multi-cliente: cada cliente tem o próprio RD.Intranet; a tela de entrada
 * guarda vários endereços ("Avançado") e um login por endereço. Fala só com
 * a API /api/app/* do servidor escolhido (token no cabeçalho X-RD-Token).
 * As regras (permissões, SLA, fila por setor) são as mesmas do painel web.
 */
(function () {
    'use strict';

    var VERSAO = '1.0.0';
    // Primeiro cliente do app. Outros entram por "Avançado" na tela inicial.
    var SERVIDOR_PADRAO = 'https://sidore.rd.inf.br/rd.intranet';
    var INTERVALO_ATUALIZACAO = 30000;

    var P = (window.Capacitor && window.Capacitor.Plugins) || {};
    var Preferences = P.Preferences;
    var AppPlugin = P.App;
    var StatusBar = P.StatusBar;

    var estado = {
        servidores: [],      // [{ url, empresa, temLogo }]
        atual: null,         // url do servidor escolhido
        sessoes: {},         // url -> { token, conta }
        tela: 'entrada',     // entrada | lista | detalhe | novo
        aba: null,
        busca: '',
        listas: {},          // aba -> { chamados, carregadoEm }
        carregando: false,
        resumo: null,
        vistos: {},          // url -> { fila, aguardando } (ids já avisados)
        detalhe: null,       // resposta de chamados/ver
        segmentoDetalhe: 'conversa',
        rascunho: { tipo: 'publica', texto: '', anexos: [] },
        enviando: false,
        formulario: null,
        novo: null,
        timer: null
    };

    // ------------------------------------------------------------------
    // Armazenamento
    // ------------------------------------------------------------------
    function guardar(chave, valor) {
        var texto = JSON.stringify(valor);
        if (Preferences) return Preferences.set({ key: chave, value: texto });
        try { localStorage.setItem(chave, texto); } catch (e) {}
        return Promise.resolve();
    }
    function ler(chave) {
        if (Preferences) {
            return Preferences.get({ key: chave }).then(function (r) { return r && r.value ? JSON.parse(r.value) : null; });
        }
        try { var v = localStorage.getItem(chave); return Promise.resolve(v ? JSON.parse(v) : null); } catch (e) { return Promise.resolve(null); }
    }
    function salvar() {
        return Promise.all([
            guardar('servidores', estado.servidores), guardar('atual', estado.atual),
            guardar('sessoes', estado.sessoes), guardar('vistos', estado.vistos)
        ]);
    }

    // ------------------------------------------------------------------
    // Utilidades
    // ------------------------------------------------------------------
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function $(sel) { return document.querySelector(sel); }
    function sessao() { return estado.sessoes[estado.atual] || null; }
    function conta() { var s = sessao(); return s ? s.conta : null; }
    function servidorAtual() {
        for (var i = 0; i < estado.servidores.length; i++) if (estado.servidores[i].url === estado.atual) return estado.servidores[i];
        return null;
    }
    function dataDe(texto) {
        if (!texto) return null;
        var p = String(texto).split(/[- :T]/);
        return new Date(+p[0], +p[1] - 1, +p[2], +(p[3] || 0), +(p[4] || 0), +(p[5] || 0));
    }
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    // Diferença entre o relógio do servidor e o do celular (fuso/relógio errado no aparelho).
    var desvioRelogio = 0;
    function agoraServidor() { return Date.now() + desvioRelogio; }
    function quando(texto) {
        var d = dataDe(texto);
        if (!d) return '';
        var seg = (agoraServidor() - d.getTime()) / 1000;
        if (seg < 60) return 'agora';
        if (seg < 3600) return 'há ' + Math.floor(seg / 60) + ' min';
        if (seg < 86400) return 'há ' + Math.floor(seg / 3600) + ' h';
        if (seg < 86400 * 7) return 'há ' + Math.floor(seg / 86400) + ' d';
        return pad(d.getDate()) + '/' + pad(d.getMonth() + 1) + '/' + String(d.getFullYear()).slice(2);
    }
    function dataHora(texto) {
        var d = dataDe(texto);
        return d ? pad(d.getDate()) + '/' + pad(d.getMonth() + 1) + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes()) : '';
    }
    function duracao(min) {
        min = Math.abs(Math.round(min));
        if (min < 60) return min + ' min';
        if (min < 1440) return Math.floor(min / 60) + 'h' + (min % 60 ? ' ' + (min % 60) + 'm' : '');
        return Math.floor(min / 1440) + 'd ' + Math.floor((min % 1440) / 60) + 'h';
    }
    function iniciais(nome) {
        var p = String(nome || '?').trim().split(/\s+/);
        return ((p[0] || '?')[0] + (p.length > 1 ? p[p.length - 1][0] : '')).toUpperCase();
    }
    function vibrar(ms) { try { if (navigator.vibrate) navigator.vibrate(ms || 60); } catch (e) {} }

    var timerAviso = null;
    function aviso(texto, tipo) {
        var el = $('#aviso');
        el.textContent = texto;
        el.className = 'aviso' + (tipo ? ' ' + tipo : '');
        el.hidden = false;
        clearTimeout(timerAviso);
        timerAviso = setTimeout(function () { el.hidden = true; }, tipo === 'erro' ? 6000 : 3200);
    }

    // Ícones (traço, estilo Lucide) — inline para funcionar sem internet.
    var ICONES = {
        busca: '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        mais: '<path d="M12 5v14M5 12h14"/>',
        voltar: '<path d="M15 18l-6-6 6-6"/>',
        fila: '<path d="M4 6h16M4 12h16M4 18h10"/>',
        comigo: '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/>',
        equipe: '<circle cx="9" cy="8" r="3.5"/><circle cx="17" cy="9" r="2.5"/><path d="M2.5 20c1-3.5 3.5-5.5 6.5-5.5s5.5 2 6.5 5.5M15 14.6c2.6-.3 5 1.3 6 4.4"/>',
        encerrados: '<path d="M20 6 9 17l-5-5"/>',
        usuario: '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/>',
        predio: '<path d="M4 21V5a1 1 0 0 1 1-1h9a1 1 0 0 1 1 1v16M15 9h4a1 1 0 0 1 1 1v11M8 8h3M8 12h3M8 16h3M3 21h18"/>',
        monitor: '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>',
        relogio: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        tag: '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
        enviar: '<path d="M22 2 11 13M22 2l-7 20-4-9-9-4z"/>',
        clipe: '<path d="m21.4 11.1-9.2 9.2a6 6 0 0 1-8.5-8.5l9.2-9.2a4 4 0 0 1 5.7 5.7l-9.2 9.2a2 2 0 0 1-2.8-2.8l8.5-8.5"/>',
        camera: '<path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3z"/><circle cx="12" cy="13" r="3.5"/>',
        imagem: '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/>',
        arquivo: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
        servidor: '<rect x="3" y="4" width="18" height="7" rx="2"/><rect x="3" y="13" width="18" height="7" rx="2"/><path d="M7 7.5h.01M7 16.5h.01"/>',
        engrenagem: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        sair: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        lixo: '<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>',
        check: '<path d="M20 6 9 17l-5-5"/>',
        telefone: '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/>',
        email: '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/>',
        transferir: '<path d="M17 3l4 4-4 4M3 7h18M7 21l-4-4 4-4M21 17H3"/>',
        mao: '<path d="M18 11V6a2 2 0 0 0-4 0v5M14 10V4a2 2 0 0 0-4 0v6M10 10.5V6a2 2 0 0 0-4 0v8"/><path d="M18 8a2 2 0 1 1 4 0v6a8 8 0 0 1-8 8h-2c-2.8 0-4.5-.9-6-2.4l-3.6-3.6a2 2 0 0 1 2.8-2.8L7 15"/>',
        status: '<circle cx="12" cy="12" r="9"/><path d="m9 12 2 2 4-4"/>',
        cadeado: '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        x: '<path d="M18 6 6 18M6 6l12 12"/>',
        atualizar: '<path d="M21 12a9 9 0 1 1-2.6-6.4M21 3v6h-6"/>',
        vazio: '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.5 5.1 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.5-6.9A2 2 0 0 0 16.7 4H7.3a2 2 0 0 0-1.8 1.1z"/>'
    };
    function icone(nome) { return '<svg class="i" viewBox="0 0 24 24">' + (ICONES[nome] || '') + '</svg>'; }
    var LOGO_RD = '<img src="logo.png" alt="RD">';

    // ------------------------------------------------------------------
    // Comunicação com o servidor
    // ------------------------------------------------------------------
    function base(url) { return String(url || estado.atual || '').replace(/\/+$/, ''); }

    function requisicao(url, opcoes, limiteMs) {
        var limite = new Promise(function (ok, erro) { setTimeout(function () { erro(new Error('tempo')); }, limiteMs || 25000); });
        return Promise.race([fetch(url, opcoes), limite]).catch(function (e) {
            var erro = new Error(e && e.message === 'tempo'
                ? 'O servidor demorou para responder. Verifique a internet.'
                : 'Sem conexão com o servidor. Verifique a internet ou o endereço em "Avançado".');
            erro.rede = true;
            throw erro;
        });
    }

    function api(caminho, corpo, urlServidor) {
        var s = urlServidor ? estado.sessoes[urlServidor] : sessao();
        var opcoes = {
            method: corpo ? 'POST' : 'GET',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-RD-Token': (s && s.token) || '' }
        };
        if (corpo) opcoes.body = JSON.stringify(corpo);

        return requisicao(base(urlServidor) + '/api/app/' + caminho, opcoes, corpo && corpo.anexos && corpo.anexos.length ? 90000 : 25000)
            .then(function (r) {
                return r.text().then(function (texto) {
                    var json = null;
                    try { json = JSON.parse(texto); } catch (e) {}
                    if (!json) {
                        var e1 = new Error(r.status === 404
                            ? 'Este servidor ainda não tem a versão do RD.Intranet com o app. Peça para aplicar a atualização.'
                            : 'Resposta inesperada do servidor (' + r.status + ').');
                        e1.rede = true;
                        throw e1;
                    }
                    if (r.status === 401 && json.sessao_expirada) {
                        sessaoExpirou();
                        var e2 = new Error(json.message || 'Entre de novo.');
                        e2.sessao = true;
                        throw e2;
                    }
                    if (json.agora) desvioRelogio = dataDe(json.agora).getTime() - Date.now();
                    if (!json.success) throw new Error(json.message || ('Erro ' + r.status));
                    return json;
                });
            });
    }

    /** Aceita "sidore.rd.inf.br", "https://x/rd.intranet/login" etc. e descobre onde está a API. */
    function descobrirServidor(digitado) {
        var url = String(digitado || '').trim();
        if (!url) return Promise.reject(new Error('Informe o endereço do RD.Intranet.'));
        var semEsquema = !/^https?:\/\//i.test(url);
        if (semEsquema) url = 'https://' + url;
        url = url.replace(/[?#].*$/, '').replace(/\/(api\/app.*|login|dashboard|chamados.*)$/i, '').replace(/\/+$/, '');

        var tentativas = [url];
        if (!/\/rd\.intranet$/i.test(url)) tentativas.push(url + '/rd.intranet');
        // Sem http(s):// digitado: se não houver HTTPS, tenta HTTP (ex.: cliente.ddns.net:83).
        if (semEsquema) tentativas = tentativas.concat(tentativas.map(function (t) { return t.replace(/^https:/, 'http:'); }));

        function tentar(i, ultimoErro) {
            if (i >= tentativas.length) return Promise.reject(ultimoErro || new Error('Não encontrei um RD.Intranet nesse endereço.'));
            var candidato = tentativas[i];
            return requisicao(candidato + '/api/app/info', { method: 'GET', headers: { 'Accept': 'application/json' } }, 12000)
                .then(function (r) { return r.text(); })
                .then(function (texto) {
                    var j = null;
                    try { j = JSON.parse(texto); } catch (e) {}
                    if (j && j.produto === 'RD.Intranet') return { url: candidato, info: j };
                    return tentar(i + 1, new Error('Esse endereço respondeu, mas não é um RD.Intranet com o app (ou falta atualizar o servidor).'));
                }, function (e) {
                    var msg = /^https:/i.test(candidato)
                        ? 'Não consegui conectar com segurança. Use o nome do servidor (ex.: cliente.rd.inf.br) em vez do IP — o certificado vale para o nome.'
                        : e.message;
                    return tentar(i + 1, new Error(msg));
                });
        }
        return tentar(0);
    }

    function sessaoExpirou() {
        if (estado.sessoes[estado.atual]) {
            delete estado.sessoes[estado.atual];
            salvar();
        }
        pararAtualizacao();
        fecharFolha();
        estado.tela = 'entrada';
        desenhar();
        aviso('Sua sessão expirou. Entre de novo.', 'erro');
    }

    // ------------------------------------------------------------------
    // Navegação
    // ------------------------------------------------------------------
    var pilha = [];
    function ir(tela) {
        if (estado.tela !== tela) pilha.push(estado.tela);
        estado.tela = tela;
        window.scrollTo(0, 0);
        desenhar();
    }
    function voltar() {
        if (!$('#visor').hidden) { $('#visor').hidden = true; return true; }
        if (!$('#folha').hidden) { fecharFolha(); return true; }
        if (pilha.length && estado.tela !== 'lista' && estado.tela !== 'entrada') {
            estado.tela = pilha.pop();
            if (estado.tela === 'lista') carregarLista(true);
            desenhar();
            return true;
        }
        return false;
    }

    function desenhar() {
        var html = '';
        if (estado.tela === 'entrada') html = telaEntrada();
        else if (estado.tela === 'lista') html = telaLista();
        else if (estado.tela === 'detalhe') html = telaDetalhe();
        else if (estado.tela === 'novo') html = telaNovo();
        $('#app').innerHTML = html;
        depoisDeDesenhar();
    }

    // Imagens do servidor vêm por fetch nativo (a página é https://localhost; <img> direto
    // de um cliente em http:// seria bloqueado) e com o token no cabeçalho. Cache por URL.
    var cacheImagens = {};
    function carregarImagens() {
        Array.prototype.forEach.call(document.querySelectorAll('img[data-img]'), function (img) {
            var url = img.getAttribute('data-img');
            img.removeAttribute('data-img');
            if (cacheImagens[url]) { img.src = cacheImagens[url]; return; }
            var cab = img.getAttribute('data-token') ? { 'X-RD-Token': (sessao() || {}).token || '' } : {};
            fetch(url, { headers: cab }).then(function (r) {
                if (!r.ok) throw new Error(r.status);
                return r.blob();
            }).then(function (b) {
                cacheImagens[url] = URL.createObjectURL(b);
                img.src = cacheImagens[url];
            }).catch(function () { img.style.visibility = 'hidden'; });
        });
    }

    function depoisDeDesenhar() {
        carregarImagens();
        var busca = $('#campo-busca');
        if (busca) busca.addEventListener('input', function () {
            estado.busca = busca.value;
            clearTimeout(busca._t);
            busca._t = setTimeout(function () {
                if (estado.aba === 'encerrados') carregarLista(true); else desenharListaSomente();
            }, 250);
        });
        var texto = $('#texto-msg');
        if (texto) {
            texto.addEventListener('input', function () {
                estado.rascunho.texto = texto.value;
                texto.style.height = 'auto';
                texto.style.height = Math.min(texto.scrollHeight, 130) + 'px';
            });
        }
        if (estado.tela === 'detalhe') ajustarEspacoCompositor();
    }

    // ------------------------------------------------------------------
    // Tela de entrada (login + escolha do cliente)
    // ------------------------------------------------------------------
    function telaEntrada() {
        var srv = servidorAtual();
        var logo = srv && srv.temLogo
            ? '<img data-img="' + esc(base(srv.url)) + '/api/app/logo" alt="">'
            : icone('predio');

        return '<div class="entrada">'
            + '<div class="marca">' + LOGO_RD + '<div><b>RD<span>.Intranet</span></b><div class="subtitulo">Equipe de TI</div></div></div>'
            + (srv
                ? '<button class="cliente" data-acao="servidores" style="color:inherit;text-align:left;width:100%">'
                    + '<div class="logo-cliente">' + logo + '</div>'
                    + '<div style="flex:1;min-width:0"><div class="nome">' + esc(srv.empresa || 'Cliente') + '</div><div class="url">' + esc(srv.url.replace(/^https?:\/\//, '')) + '</div></div>'
                    + icone('engrenagem') + '</button>'
                : '<div class="cliente"><div class="logo-cliente">' + icone('servidor') + '</div><div><div class="nome">Nenhum servidor configurado</div><div class="url">Toque em Avançado para informar o endereço do RD.Intranet</div></div></div>')
            + '<form class="formulario-entrada" id="form-entrada" autocomplete="on">'
            + '<div><label for="login">Usuário</label><input id="login" name="login" autocomplete="username" autocapitalize="none" spellcheck="false" required></div>'
            + '<div><label for="senha">Senha</label><input id="senha" name="senha" type="password" autocomplete="current-password" required></div>'
            + '<button class="botao largo" type="submit" id="botao-entrar"' + (srv ? '' : ' disabled') + '>' + icone('cadeado') + 'Entrar</button>'
            + '</form>'
            + '<div class="rodape"><button class="link-claro" data-acao="servidores">' + icone('engrenagem') + 'Avançado — endereço do RD.Intranet</button>'
            + '<div class="versao">versão ' + VERSAO + ' · RD Tecnologia</div></div>'
            + '</div>';
    }

    function entrar(form) {
        var botao = $('#botao-entrar');
        var login = form.login.value.trim();
        var senha = form.senha.value;
        if (!login || !senha) return;
        botao.disabled = true;
        botao.innerHTML = '<span class="giro"></span>Entrando...';

        var dispositivo = (navigator.userAgent.match(/Android [\d.]+; ([^;)]+)/) || [])[1] || 'Android';
        var url = estado.atual;
        api('login', { login: login, senha: senha, dispositivo: dispositivo.trim(), versao_app: VERSAO }, url)
            .then(function (r) {
                estado.sessoes[url] = { token: r.token, conta: r };
                atualizarServidor(url, { empresa: r.empresa });
                return salvar().then(abrirPrincipal);
            })
            .catch(function (e) {
                botao.disabled = false;
                botao.innerHTML = icone('cadeado') + 'Entrar';
                aviso(e.message, 'erro');
            });
    }

    function atualizarServidor(url, dados) {
        estado.servidores.forEach(function (s) {
            if (s.url === url) Object.keys(dados).forEach(function (k) { if (dados[k] !== undefined) s[k] = dados[k]; });
        });
    }

    // Folha "Avançado": lista de clientes (servidores) salvos + adicionar.
    function folhaServidores() {
        var lista = estado.servidores.map(function (s) {
            var atual = s.url === estado.atual;
            var logado = !!estado.sessoes[s.url];
            return '<div class="servidor' + (atual ? ' atual' : '') + '">'
                + '<button class="lista-opcoes" style="all:unset;flex:1;display:flex;gap:12px;align-items:center;min-width:0" data-acao="escolher-servidor" data-url="' + esc(s.url) + '">'
                + icone('servidor') + '<div class="info"><b>' + esc(s.empresa || 'Cliente') + '</b><small>' + esc(s.url) + (logado ? ' · conectado' : '') + '</small></div></button>'
                + '<button class="remover" data-acao="remover-servidor" data-url="' + esc(s.url) + '" aria-label="Remover">' + icone('lixo') + '</button></div>';
        }).join('');

        abrirFolha('<h2>Endereço do RD.Intranet</h2>'
            + '<p style="color:var(--texto-2);margin:-6px 0 14px;font-size:14px">Cada cliente tem o seu RD.Intranet. Escolha um da lista ou adicione o endereço de outro.</p>'
            + (lista || '<p style="color:var(--texto-3)">Nenhum endereço salvo.</p>')
            + '<form class="form" id="form-servidor" style="margin-top:14px">'
            + '<div class="grupo"><label for="novo-servidor">Adicionar cliente</label><input id="novo-servidor" inputmode="url" autocapitalize="none" spellcheck="false" placeholder="ex.: cliente.rd.inf.br"><div class="dica">Pode colar o link do painel; o app descobre o resto. Prefira o nome (o certificado de segurança vale para o nome, não para o IP).</div></div>'
            + '<button class="botao largo" type="submit" id="botao-servidor">' + icone('check') + 'Testar e adicionar</button>'
            + '</form>');
    }

    function adicionarServidor(form) {
        var botao = $('#botao-servidor');
        botao.disabled = true;
        botao.innerHTML = '<span class="giro"></span>Testando...';
        descobrirServidor(form.querySelector('#novo-servidor').value)
            .then(function (achado) {
                var existente = estado.servidores.filter(function (s) { return s.url === achado.url; })[0];
                if (existente) {
                    existente.empresa = achado.info.empresa;
                    existente.temLogo = achado.info.tem_logo;
                } else {
                    estado.servidores.push({ url: achado.url, empresa: achado.info.empresa, temLogo: achado.info.tem_logo });
                }
                estado.atual = achado.url;
                return salvar().then(function () {
                    fecharFolha();
                    aviso('Conectado a ' + achado.info.empresa + '.', 'ok');
                    escolherServidor(achado.url);
                });
            })
            .catch(function (e) {
                botao.disabled = false;
                botao.innerHTML = icone('check') + 'Testar e adicionar';
                aviso(e.message, 'erro');
            });
    }

    function escolherServidor(url) {
        estado.atual = url;
        estado.listas = {};
        estado.resumo = null;
        estado.formulario = null;
        salvar();
        fecharFolha();
        if (estado.sessoes[url]) abrirPrincipal(); else { pararAtualizacao(); estado.tela = 'entrada'; pilha = []; desenhar(); }
        // Atualiza nome/logo do cliente em segundo plano.
        descobrirServidor(url).then(function (a) {
            atualizarServidor(url, { empresa: a.info.empresa, temLogo: a.info.tem_logo });
            salvar();
            if (estado.tela === 'entrada') desenhar();
        }).catch(function () {});
    }

    function removerServidor(url) {
        var s = estado.sessoes[url];
        if (s) { api('logout', {}, url).catch(function () {}); delete estado.sessoes[url]; }
        estado.servidores = estado.servidores.filter(function (x) { return x.url !== url; });
        if (estado.atual === url) estado.atual = estado.servidores.length ? estado.servidores[0].url : null;
        salvar().then(function () { folhaServidores(); if (estado.tela === 'entrada') desenhar(); });
    }

    // ------------------------------------------------------------------
    // Lista de chamados
    // ------------------------------------------------------------------
    function abasDisponiveis() {
        var p = (conta() && conta().permissoes) || {};
        var abas = [];
        if (p.fila) abas.push({ id: 'fila', nome: 'Fila', icone: 'fila' });
        if (p.atendimentos) abas.push({ id: 'meus', nome: 'Comigo', icone: 'comigo' });
        if (p.equipe) abas.push({ id: 'equipe', nome: 'Equipe', icone: 'equipe' });
        if (p.atendimentos) abas.push({ id: 'encerrados', nome: 'Encerrados', icone: 'encerrados' });
        if (!abas.length) abas.push({ id: 'abertos_por_mim', nome: 'Meus pedidos', icone: 'comigo' });
        return abas;
    }

    function abrirPrincipal() {
        var abas = abasDisponiveis();
        if (!estado.aba || !abas.some(function (a) { return a.id === estado.aba; })) {
            estado.aba = abas.some(function (a) { return a.id === 'meus'; }) ? 'meus' : abas[0].id;
        }
        pilha = [];
        estado.tela = 'lista';
        desenhar();
        carregarLista(true);
        iniciarAtualizacao();
        // Confere a sessão e atualiza permissões (podem ter mudado no painel).
        api('eu').then(function (r) {
            sessao().conta = r;
            salvar();
            if (estado.tela === 'lista') desenhar();
        }).catch(function () {});
    }

    function telaLista() {
        var c = conta() || {};
        var abas = abasDisponiveis();
        var aba = abas.filter(function (a) { return a.id === estado.aba; })[0] || abas[0];
        var r = estado.resumo || {};
        var contadores = { fila: r.fila, meus: r.aguardando_resposta };
        var p = c.permissoes || {};

        return '<header class="topo"><div class="linha">'
            + '<div style="flex:1;min-width:0"><h1>' + esc(aba.nome === 'Comigo' ? 'Comigo' : aba.nome) + '</h1><div class="empresa">' + esc(c.empresa || '') + '</div></div>'
            + '<button class="icone-topo" data-acao="recarregar" aria-label="Atualizar">' + icone('atualizar') + '</button>'
            + '<button class="icone-topo" data-acao="conta" aria-label="Conta">' + '<b style="font-size:14px">' + esc(iniciais(c.usuario && c.usuario.nome)) + '</b></button>'
            + '</div>'
            + '<label class="busca">' + icone('busca') + '<input id="campo-busca" type="search" placeholder="Buscar nº, título, solicitante, equipamento" value="' + esc(estado.busca) + '"></label>'
            + '</header>'
            + '<main class="conteudo" id="lista-conteudo">' + conteudoLista() + '</main>'
            + (p.abrir ? '<button class="fab" data-acao="novo" aria-label="Novo chamado">' + icone('mais') + '</button>' : '')
            + '<nav class="abas" style="--n:' + abas.length + '">' + abas.map(function (a) {
                var n = contadores[a.id];
                return '<button class="' + (a.id === aba.id ? 'ativa' : '') + '" data-acao="aba" data-aba="' + a.id + '">'
                    + icone(a.icone) + a.nome + (n ? '<span class="contador">' + (n > 99 ? '99+' : n) + '</span>' : '') + '</button>';
            }).join('') + '</nav>';
    }

    function filtrar(chamados) {
        var q = estado.busca.trim().toLowerCase();
        if (!q || estado.aba === 'encerrados') return chamados;
        return chamados.filter(function (ch) {
            return [ch.numero, ch.titulo, ch.solicitante, ch.ativo, ch.categoria, ch.atendente].join(' ').toLowerCase().indexOf(q) >= 0;
        });
    }

    function conteudoLista() {
        var dados = estado.listas[estado.aba];
        if (!dados) return '<div class="esqueleto"></div><div class="esqueleto"></div><div class="esqueleto"></div>';
        var chamados = filtrar(dados.chamados);
        if (!chamados.length) {
            var textos = {
                fila: ['Fila vazia', 'Nenhum chamado esperando atendimento.'],
                meus: ['Nada com você', 'Assuma um chamado da fila para começar.'],
                equipe: ['Equipe em dia', 'Nenhum chamado com os colegas agora.'],
                encerrados: ['Nenhum encerrado', 'Os chamados resolvidos aparecem aqui.'],
                abertos_por_mim: ['Nenhum pedido', 'Toque em + para abrir um chamado.']
            }[estado.aba] || ['Nada por aqui', ''];
            if (estado.busca.trim()) textos = ['Nada encontrado', 'Tente outra busca.'];
            return '<div class="vazio">' + icone('vazio') + '<b>' + textos[0] + '</b>' + textos[1] + '</div>';
        }
        return chamados.map(cartaoChamado).join('');
    }

    function desenharListaSomente() {
        var el = $('#lista-conteudo');
        if (el) el.innerHTML = conteudoLista();
    }

    var CORES_PRIORIDADE = { baixa: '#94a3b8', media: '#2563eb', alta: '#d97706', urgente: '#dc2626' };

    function seloSla(sla) {
        if (!sla) return '';
        var nome = sla.tipo === 'resposta' ? '1ª resposta' : 'Resolução';
        if (sla.pausado) return '<span class="selo sla-pausado">' + icone('relogio') + 'SLA pausado</span>';
        var min = (dataDe(sla.prazo).getTime() - agoraServidor()) / 60000;
        if (sla.estourado || min < 0) return '<span class="selo sla-estourado">' + icone('relogio') + nome + ' atrasada ' + duracao(min) + '</span>';
        return '<span class="selo ' + (min < 60 ? 'sla-perto' : 'sla-ok') + '">' + icone('relogio') + nome + ' em ' + duracao(min) + '</span>';
    }

    function seloStatus(status) {
        var nomes = (conta() && conta().status) || {};
        return '<span class="selo ' + esc(status) + '">' + esc(nomes[status] || status) + '</span>';
    }
    function seloPrioridade(p) {
        var nomes = (conta() && conta().prioridades) || {};
        return '<span class="selo ' + esc(p) + '">' + esc(nomes[p] || p) + '</span>';
    }

    function cartaoChamado(ch) {
        var meta = [];
        if (ch.solicitante) meta.push('<span>' + icone('usuario') + esc(ch.solicitante) + '</span>');
        if (ch.unidade) meta.push('<span>' + icone('predio') + esc(ch.unidade) + '</span>');
        if (ch.ativo) meta.push('<span>' + icone('monitor') + esc(ch.ativo) + '</span>');
        if (estado.aba !== 'meus' && ch.atendente) meta.push('<span>' + icone('comigo') + esc(ch.atendente) + '</span>');

        var aguarda = ch.aguardando_resposta && ch.status === 'em_atendimento';
        return '<button class="cartao chamado" style="--cor-prio:' + (CORES_PRIORIDADE[ch.prioridade] || '#94a3b8') + '" data-acao="abrir" data-id="' + ch.id + '">'
            + '<div class="cab">' + (aguarda ? '<span class="novo-ponto"></span>' : '') + '<span class="numero">' + esc(ch.numero) + '</span><span>' + esc(ch.categoria) + '</span><span class="quando">' + quando(ch.atualizado_em) + '</span></div>'
            + '<h3>' + esc(ch.titulo) + '</h3>'
            + '<div class="meta">' + meta.join('') + '</div>'
            + '<div class="rodape">' + seloStatus(ch.status) + seloPrioridade(ch.prioridade)
            + (aguarda ? '<span class="selo aguarda">Aguardando sua resposta</span>' : '') + seloSla(ch.sla) + '</div>'
            + '</button>';
    }

    function carregarLista(mostrarEsqueleto) {
        var aba = estado.aba;
        if (!aba || !sessao()) return Promise.resolve();
        if (mostrarEsqueleto && !estado.listas[aba]) desenharListaSomente();
        var q = aba === 'encerrados' && estado.busca.trim() ? '&q=' + encodeURIComponent(estado.busca.trim()) : '';
        return Promise.all([
            api('chamados?lista=' + aba + q),
            atualizarResumo(true)
        ]).then(function (res) {
            estado.listas[aba] = { chamados: res[0].chamados, carregadoEm: Date.now() };
            if (estado.tela === 'lista' && estado.aba === aba) desenhar();
        }).catch(function (e) {
            if (!e.sessao) aviso(e.message, 'erro');
            if (!estado.listas[aba]) estado.listas[aba] = { chamados: [], carregadoEm: 0 };
            if (estado.tela === 'lista') desenharListaSomente();
        });
    }

    // ------------------------------------------------------------------
    // Atualização periódica + alerta de chamado novo
    // ------------------------------------------------------------------
    /** semAlerta: depois de uma ação do próprio técnico (assumir, abrir...) só atualiza, sem avisar. */
    function atualizarResumo(silencioso, semAlerta) {
        return api('chamados/resumo').then(function (r) {
            var antes = estado.resumo;
            estado.resumo = r;
            var vistos = estado.vistos[estado.atual] || { fila: r.ultimo_fila_id, aguardando: r.ultimo_aguardando_id };
            var novoNaFila = r.ultimo_fila_id > vistos.fila;
            var novaResposta = r.ultimo_aguardando_id > vistos.aguardando;
            estado.vistos[estado.atual] = { fila: Math.max(vistos.fila, r.ultimo_fila_id), aguardando: Math.max(vistos.aguardando, r.ultimo_aguardando_id) };
            salvar();
            if (semAlerta) { novoNaFila = false; novaResposta = false; }
            if (novoNaFila) { vibrar([80, 60, 80]); aviso('Novo chamado na fila.'); }
            else if (novaResposta) { vibrar(80); aviso('Chamado seu aguardando resposta.'); }
            if (!silencioso && estado.tela === 'lista' && (novoNaFila || novaResposta || !antes || antes.fila !== r.fila || antes.meus !== r.meus)) {
                carregarLista(false);
            } else if (estado.tela === 'lista' && antes && (antes.fila !== r.fila || antes.aguardando_resposta !== r.aguardando_resposta)) {
                desenhar();
            }
            return r;
        });
    }
    function iniciarAtualizacao() {
        pararAtualizacao();
        estado.timer = setInterval(function () {
            if (document.hidden || !sessao()) return;
            atualizarResumo(false).catch(function () {});
            if (estado.tela === 'detalhe' && estado.detalhe && !estado.enviando && !estado.rascunho.texto && !estado.rascunho.anexos.length) {
                recarregarDetalhe(true);
            }
        }, INTERVALO_ATUALIZACAO);
    }
    function pararAtualizacao() { if (estado.timer) clearInterval(estado.timer); estado.timer = null; }

    // ------------------------------------------------------------------
    // Detalhe do chamado
    // ------------------------------------------------------------------
    function abrirChamado(id) {
        estado.detalhe = { carregando: true, id: id };
        estado.segmentoDetalhe = 'conversa';
        estado.rascunho = { tipo: 'publica', texto: '', anexos: [] };
        ir('detalhe');
        recarregarDetalhe(false);
    }

    function recarregarDetalhe(silencioso) {
        var id = estado.detalhe && estado.detalhe.id;
        if (!id) return Promise.resolve();
        return api('chamados/ver?id=' + id).then(function (r) {
            if (estado.tela !== 'detalhe' || !estado.detalhe || estado.detalhe.id !== id) return;
            var qtdAntes = estado.detalhe.mensagens ? estado.detalhe.mensagens.length : -1;
            r.id = id;
            estado.detalhe = r;
            var y = window.scrollY;
            desenhar();
            if (silencioso) window.scrollTo(0, y);
            if (qtdAntes >= 0 && r.mensagens.length > qtdAntes) { vibrar(60); aviso('Nova mensagem no chamado.'); }
        }).catch(function (e) {
            if (e.sessao) return;
            if (!silencioso) { aviso(e.message, 'erro'); voltar(); }
        });
    }

    function urlAnexo(a) {
        return base() + '/api/app/chamados/anexo?id=' + a.id + '&token=' + encodeURIComponent((sessao() || {}).token || '');
    }
    function htmlAnexos(lista) {
        if (!lista || !lista.length) return '';
        return '<div class="anexos">' + lista.map(function (a) {
            var urlImg = base() + '/api/app/chamados/anexo?id=' + a.id;
            return a.imagem
                ? '<img data-img="' + esc(urlImg) + '" data-token="1" alt="' + esc(a.nome) + '" data-acao="ver-imagem">'
                : '<a class="anexo-arquivo" href="' + esc(urlAnexo(a)) + '" target="_blank">' + icone('arquivo') + '<span>' + esc(a.nome) + '</span></a>';
        }).join('') + '</div>';
    }

    function telaDetalhe() {
        var d = estado.detalhe;
        var topo = '<header class="topo detalhe-topo"><div class="linha">'
            + '<button class="icone-topo" data-acao="voltar" aria-label="Voltar">' + icone('voltar') + '</button>'
            + '<div style="flex:1;min-width:0"><h1>' + (d && d.chamado ? esc(d.chamado.numero) : 'Chamado') + '</h1><div class="empresa">' + (d && d.chamado ? esc(d.chamado.categoria) : '') + '</div></div>'
            + '<button class="icone-topo" data-acao="recarregar-detalhe" aria-label="Atualizar">' + icone('atualizar') + '</button>'
            + '</div></header>';
        if (!d || d.carregando) return topo + '<main class="conteudo"><div class="esqueleto" style="height:220px"></div><div class="esqueleto"></div></main>';

        var ch = d.chamado;
        var a = d.acoes || {};
        var campos = [
            ['Solicitante', esc(ch.solicitante) + (ch.setor_solicitante ? '<span class="sub">' + esc(ch.setor_solicitante) + '</span>' : '')],
            ['Unidade', esc(ch.unidade)],
            ['Atendente', ch.atendente ? esc(ch.atendente) : '<span style="color:var(--texto-3)">ninguém ainda</span>'],
            ['Equipe', esc(ch.setor || '—')],
            ['Aberto', esc(dataHora(ch.aberto_em)) + '<span class="sub">' + quando(ch.aberto_em) + '</span>']
        ];
        if (ch.ativo) campos.push(['Equipamento', esc(ch.ativo), true]);

        var contato = '';
        if (ch.solicitante_telefone) contato += '<a class="botao secundario pequeno" href="tel:' + esc(ch.solicitante_telefone.replace(/[^\d+]/g, '')) + '">' + icone('telefone') + 'Ligar</a>'
            + '<a class="botao secundario pequeno" href="https://wa.me/' + esc(ch.solicitante_telefone.replace(/\D/g, '').replace(/^(?!55)(\d{10,11})$/, '55$1')) + '" target="_blank">WhatsApp</a>';
        if (ch.solicitante_email && !/@rd\.intranet$/.test(ch.solicitante_email)) contato += '<a class="botao secundario pequeno" href="mailto:' + esc(ch.solicitante_email) + '">' + icone('email') + 'E-mail</a>';

        var botoes = [];
        if (a.assumir) botoes.push('<button class="botao" data-acao="assumir">' + icone('mao') + 'Assumir</button>');
        if (a.pegar_para_mim) botoes.push('<button class="botao secundario" data-acao="pegar">' + icone('mao') + 'Pegar para mim</button>');
        if (a.status && d.status_possiveis.length) botoes.push('<button class="botao secundario" data-acao="status">' + icone('status') + 'Status</button>');
        if (a.transferir) botoes.push('<button class="botao secundario" data-acao="transferir">' + icone('transferir') + 'Transferir</button>');
        if (botoes.length % 2 === 1) botoes[botoes.length - 1] = botoes[botoes.length - 1].replace('class="botao', 'class="botao unico');

        var corpo = '<section class="cartao resumo">'
            + '<div class="selos">' + seloStatus(ch.status) + seloPrioridade(ch.prioridade) + seloSla(ch.sla) + '</div>'
            + '<h2>' + esc(ch.titulo) + '</h2>'
            + '<div class="campos">' + campos.map(function (c) { return '<div class="campo' + (c[2] ? ' inteiro' : '') + '"><small>' + c[0] + '</small><div>' + c[1] + '</div></div>'; }).join('') + '</div>'
            + (contato ? '<div class="contato">' + contato + '</div>' : '')
            + '<div class="descricao">' + esc(ch.descricao) + '</div>'
            + htmlAnexos(d.anexos_avulsos)
            + '</section>'
            + (botoes.length ? '<div class="acoes">' + botoes.join('') + '</div>' : '')
            + '<div class="segmento"><button class="' + (estado.segmentoDetalhe === 'conversa' ? 'ativa' : '') + '" data-acao="segmento" data-seg="conversa">Conversa (' + d.mensagens.length + ')</button>'
            + (d.historico.length ? '<button class="' + (estado.segmentoDetalhe === 'historico' ? 'ativa' : '') + '" data-acao="segmento" data-seg="historico">Histórico</button>' : '') + '</div>'
            + (estado.segmentoDetalhe === 'historico' ? htmlHistorico(d.historico) : htmlConversa(d.mensagens));

        var rodape = a.responder ? compositor(a) : '<div class="encerrado-aviso">' + (['resolvido', 'fechado'].indexOf(ch.status) >= 0 ? 'Chamado encerrado. Para continuar, mude o status para "Em atendimento".' : '') + '</div>';
        return topo + '<main class="conteudo" id="conteudo-detalhe">' + corpo + '</main>' + rodape;
    }

    function htmlConversa(msgs) {
        if (!msgs.length) return '<div class="vazio" style="padding:30px">' + icone('vazio') + '<b>Sem mensagens ainda</b>Responda o solicitante pelo campo abaixo.</div>';
        return '<div class="conversa">' + msgs.map(function (m) {
            var classe = m.tipo === 'interna' ? 'interna' : (m.da_equipe ? 'equipe' : 'solicitante');
            return '<div class="msg ' + classe + '"><div class="autor">' + (m.tipo === 'interna' ? icone('cadeado') : '') + esc(m.sou_eu ? 'Você' : m.autor) + (m.tipo === 'interna' ? ' · nota interna' : '') + '</div>'
                + '<div class="texto">' + esc(m.texto) + '</div>' + htmlAnexos(m.anexos)
                + '<div class="hora">' + esc(dataHora(m.quando)) + '</div></div>';
        }).join('') + '</div>';
    }

    function htmlHistorico(itens) {
        var nomes = { status: 'Status', usuario_id: 'Atendente' };
        return '<ul class="historico">' + itens.map(function (h) {
            return '<li><b>' + esc(nomes[h.campo] || h.campo) + ':</b> ' + esc(h.de) + ' → ' + esc(h.para)
                + '<small>' + esc(h.por) + ' · ' + esc(dataHora(h.quando)) + '</small></li>';
        }).join('') + '</ul>';
    }

    function compositor(a) {
        var r = estado.rascunho;
        return '<div class="compositor" id="compositor">'
            + (a.nota_interna ? '<div class="tipo"><button class="publica' + (r.tipo === 'publica' ? ' ativa' : '') + '" data-acao="tipo-msg" data-tipo="publica">Resposta ao solicitante</button>'
                + '<button class="interna' + (r.tipo === 'interna' ? ' ativa' : '') + '" data-acao="tipo-msg" data-tipo="interna">Nota interna</button></div>' : '')
            + (r.anexos.length ? '<div class="miniaturas">' + r.anexos.map(function (x, i) {
                return '<div class="miniatura">' + (x.previa ? '<img src="' + x.previa + '">' : '<div class="doc">' + esc(x.nome) + '</div>')
                    + '<button data-acao="tirar-anexo" data-i="' + i + '">×</button></div>';
            }).join('') + '</div>' : '')
            + '<div class="linha">'
            + (a.anexar ? '<button class="redondo" data-acao="anexar" aria-label="Anexar">' + icone('clipe') + '</button>' : '')
            + '<textarea id="texto-msg" rows="1" placeholder="' + (r.tipo === 'interna' ? 'Nota só para a equipe...' : 'Escreva ao solicitante...') + '">' + esc(r.texto) + '</textarea>'
            + '<button class="redondo enviar" data-acao="enviar-msg" aria-label="Enviar"' + (estado.enviando ? ' disabled' : '') + '>' + (estado.enviando ? '<span class="giro"></span>' : icone('enviar')) + '</button>'
            + '</div></div>';
    }

    function ajustarEspacoCompositor() {
        var c = $('#compositor');
        var conteudo = $('#conteudo-detalhe');
        if (c && conteudo) conteudo.style.paddingBottom = (c.offsetHeight + 20) + 'px';
    }

    function enviarMensagem() {
        var r = estado.rascunho;
        var texto = (r.texto || '').trim();
        if (!texto && !r.anexos.length) { aviso('Escreva uma mensagem ou anexe algo.'); return; }
        estado.enviando = true;
        desenhar();
        api('chamados/responder', {
            id: estado.detalhe.id, tipo: r.tipo, texto: texto,
            anexos: r.anexos.map(function (x) { return { nome: x.nome, base64: x.base64 }; })
        }).then(function (resp) {
            estado.enviando = false;
            estado.rascunho = { tipo: r.tipo, texto: '', anexos: [] };
            aviso(resp.message, /Mas|mas:/.test(resp.message) ? 'erro' : 'ok');
            return recarregarDetalhe(true).then(function () { window.scrollTo(0, document.body.scrollHeight); });
        }).catch(function (e) {
            estado.enviando = false;
            desenhar();
            if (!e.sessao) aviso(e.message, 'erro');
        });
    }

    function acaoChamado(caminho, corpo, mensagemOk) {
        return api(caminho, corpo).then(function (r) {
            atualizarResumo(true, true).catch(function () {});
            vibrar(40);
            aviso(mensagemOk || r.message, 'ok');
            fecharFolha();
            estado.listas = {};
            return recarregarDetalhe(true);
        }).catch(function (e) {
            if (!e.sessao) aviso(e.message, 'erro');
            throw e;
        });
    }

    function folhaStatus() {
        var d = estado.detalhe;
        var nomes = { em_atendimento: 'Em atendimento', aguardando_cliente: 'Aguardando cliente', resolvido: 'Resolvido', fechado: 'Fechado', fila: 'Devolver para a fila' };
        var dicas = {
            em_atendimento: 'Você está trabalhando nele (reabre se estava encerrado).',
            aguardando_cliente: 'Pausa o SLA até o solicitante responder.',
            resolvido: 'Avisa o solicitante e pede a avaliação.',
            fechado: 'Encerra sem pedir avaliação.',
            fila: 'Tira de você e volta para a fila do setor.'
        };
        abrirFolha('<h2>Mudar status</h2><div class="lista-opcoes">' + d.status_possiveis.map(function (s) {
            var cor = { em_atendimento: '#2563eb', aguardando_cliente: '#d97706', resolvido: '#15803d', fechado: '#64748b', fila: '#6366f1' }[s.id] || '#94a3b8';
            return '<button data-acao="escolher-status" data-status="' + s.id + '"><span style="width:12px;height:12px;border-radius:50%;flex:none;background:' + cor + '"></span>'
                + '<div><b>' + esc(nomes[s.id] || s.nome) + '</b><div style="font-size:13px;color:var(--texto-3)">' + (dicas[s.id] || '') + '</div></div></button>';
        }).join('') + '</div>');
    }

    function confirmarStatus(status) {
        if (status === 'resolvido') {
            abrirFolha('<h2>Resolver chamado</h2><form class="form" id="form-resolver">'
                + '<div class="grupo"><label for="msg-final">Mensagem ao solicitante (opcional)</label><textarea id="msg-final" placeholder="O que foi feito para resolver..."></textarea>'
                + '<div class="dica">Vai como resposta pública, antes de marcar como resolvido.</div></div>'
                + '<button class="botao ok largo" type="submit">' + icone('check') + 'Marcar como resolvido</button></form>');
            return;
        }
        acaoChamado('chamados/status', { id: estado.detalhe.id, status: status }).catch(function () {});
    }

    function folhaTransferir() {
        abrirFolha('<h2>Transferir chamado</h2><div class="carregando"><div class="giro"></div></div>');
        api('chamados/atendentes').then(function (r) {
            var eu = conta().usuario.id;
            var atual = estado.detalhe.chamado.atendente_id;
            abrirFolha('<h2>Transferir chamado</h2><form class="form" id="form-transferir">'
                + '<div class="grupo"><label for="destino">Para quem</label><select id="destino" required><option value="">Escolha...</option>'
                + r.atendentes.filter(function (x) { return x.id !== atual; }).map(function (x) {
                    return '<option value="' + x.id + '">' + esc(x.nome) + (x.id === eu ? ' (eu)' : '') + '</option>';
                }).join('') + '</select></div>'
                + '<div class="grupo"><label for="motivo">Motivo (opcional)</label><input id="motivo" placeholder="ex.: precisa ir ao local"></div>'
                + '<button class="botao largo" type="submit">' + icone('transferir') + 'Transferir</button></form>');
        }).catch(function (e) { fecharFolha(); if (!e.sessao) aviso(e.message, 'erro'); });
    }

    // ------------------------------------------------------------------
    // Anexos (câmera, galeria, arquivos)
    // ------------------------------------------------------------------
    var destinoAnexos = null; // 'rascunho' | 'novo'
    function folhaAnexar(destino) {
        destinoAnexos = destino;
        abrirFolha('<h2>Anexar</h2><div class="lista-opcoes">'
            + '<button data-acao="escolher-arquivo" data-tipo="camera">' + icone('camera') + 'Tirar foto</button>'
            + '<button data-acao="escolher-arquivo" data-tipo="galeria">' + icone('imagem') + 'Foto da galeria</button>'
            + '<button data-acao="escolher-arquivo" data-tipo="arquivo">' + icone('arquivo') + 'Documento (PDF, planilha...)</button></div>');
    }

    function escolherArquivo(tipo) {
        var input = document.createElement('input');
        input.type = 'file';
        if (tipo === 'camera') { input.accept = 'image/*'; input.setAttribute('capture', 'environment'); }
        else if (tipo === 'galeria') { input.accept = 'image/*'; input.multiple = true; }
        else { input.accept = '.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip,image/*'; input.multiple = true; }
        input.style.display = 'none';
        document.body.appendChild(input);
        input.addEventListener('change', function () {
            var arquivos = Array.prototype.slice.call(input.files || []);
            input.remove();
            fecharFolha();
            Promise.all(arquivos.slice(0, 6).map(prepararArquivo)).then(function (prontos) {
                var lista = destinoAnexos === 'novo' ? estado.novo.anexos : estado.rascunho.anexos;
                prontos.forEach(function (p) { if (p) lista.push(p); });
                desenhar();
            });
        });
        input.click();
    }

    /** Foto é reduzida (máx. 1600 px, JPEG) — chamado não precisa de 12 MP e a rede móvel agradece. */
    function prepararArquivo(arquivo) {
        return new Promise(function (ok) {
            if (/^image\/(jpeg|png|webp|heic|heif)/.test(arquivo.type) || /\.(jpe?g|png|webp|heic)$/i.test(arquivo.name)) {
                var url = URL.createObjectURL(arquivo);
                var img = new Image();
                img.onload = function () {
                    var escala = Math.min(1, 1600 / Math.max(img.width, img.height));
                    var canvas = document.createElement('canvas');
                    canvas.width = Math.round(img.width * escala);
                    canvas.height = Math.round(img.height * escala);
                    canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
                    var dataUrl = canvas.toDataURL('image/jpeg', 0.82);
                    URL.revokeObjectURL(url);
                    ok({ nome: (arquivo.name || 'foto').replace(/\.[^.]+$/, '') + '.jpg', base64: dataUrl.split(',')[1], previa: dataUrl });
                };
                img.onerror = function () { URL.revokeObjectURL(url); lerComoBase64(arquivo).then(ok); };
                img.src = url;
                return;
            }
            lerComoBase64(arquivo).then(ok);
        });
    }
    function lerComoBase64(arquivo) {
        return new Promise(function (ok) {
            if (arquivo.size > 15 * 1024 * 1024) { aviso('"' + arquivo.name + '" passa de 15 MB.', 'erro'); ok(null); return; }
            var leitor = new FileReader();
            leitor.onload = function () { ok({ nome: arquivo.name, base64: String(leitor.result).split(',')[1], previa: null }); };
            leitor.onerror = function () { ok(null); };
            leitor.readAsDataURL(arquivo);
        });
    }

    // ------------------------------------------------------------------
    // Novo chamado
    // ------------------------------------------------------------------
    function abrirNovo() {
        estado.novo = {
            titulo: '', descricao: '', categoria_id: '', subcategoria_id: '', prioridade: 'media', unidade_id: '', setor_id: '', setor_solicitante_id: '',
            quem: 'eu', solicitante_nome: '', solicitante_email: '', solicitante_telefone: '', ativo: null, assumir: false, anexos: [], enviando: false
        };
        ir('novo');
        var carregar = estado.formulario ? Promise.resolve(estado.formulario) : api('chamados/formulario');
        carregar.then(function (f) {
            estado.formulario = f;
            if (f.unidades.length === 1) estado.novo.unidade_id = String(f.unidades[0].id);
            if (estado.tela === 'novo') desenhar();
        }).catch(function (e) { if (!e.sessao) { aviso(e.message, 'erro'); voltar(); } });
    }

    function opcoesSelect(lista, valor, vazio) {
        return (vazio !== false ? '<option value="">' + esc(vazio || 'Escolha...') + '</option>' : '')
            + lista.map(function (x) { return '<option value="' + esc(x.id) + '"' + (String(x.id) === String(valor) ? ' selected' : '') + '>' + esc(x.nome) + '</option>'; }).join('');
    }

    function telaNovo() {
        var f = estado.formulario;
        var n = estado.novo;
        var topo = '<header class="topo"><div class="linha"><button class="icone-topo" data-acao="voltar" aria-label="Voltar">' + icone('x') + '</button>'
            + '<div style="flex:1"><h1>Novo chamado</h1><div class="empresa">' + esc((conta() || {}).empresa || '') + '</div></div></div></header>';
        if (!f) return topo + '<main class="conteudo"><div class="esqueleto" style="height:300px"></div></main>';

        var subs = (f.subcategorias && f.subcategorias[n.categoria_id]) || [];
        var cat = f.categorias.filter(function (c) { return String(c.id) === String(n.categoria_id); })[0];
        var setoresSol = f.setores_solicitantes.filter(function (s) { return s.unidade_id === null || String(s.unidade_id) === String(n.unidade_id); });
        var podeAssumir = conta().permissoes.fila;

        return topo + '<main class="conteudo"><form class="form" id="form-novo" novalidate>'
            + '<div class="grupo"><label for="n-titulo">Título</label><input id="n-titulo" data-campo="titulo" maxlength="200" value="' + esc(n.titulo) + '" placeholder="Resumo do problema"></div>'
            + '<div class="grupo"><label for="n-desc">Descrição</label><textarea id="n-desc" data-campo="descricao" placeholder="O que está acontecendo, desde quando, mensagem de erro...">' + esc(n.descricao) + '</textarea></div>'
            + '<div class="grupo"><label>Prioridade</label><div class="opcoes">' + f.prioridades.map(function (p) {
                return '<button type="button" class="' + p.id + (n.prioridade === p.id ? ' ativa' : '') + '" data-acao="prioridade" data-p="' + p.id + '">' + esc(p.nome) + '</button>';
            }).join('') + '</div></div>'
            + '<div class="grupo"><label for="n-cat">Categoria</label><select id="n-cat" data-campo="categoria_id">' + opcoesSelect(f.categorias, n.categoria_id) + '</select></div>'
            + (subs.length ? '<div class="grupo"><label for="n-sub">Subcategoria' + (cat && cat.exige_subcategoria ? '' : ' (opcional)') + '</label><select id="n-sub" data-campo="subcategoria_id">' + opcoesSelect(subs, n.subcategoria_id) + '</select></div>' : '')
            + (f.unidades.length > 1 ? '<div class="grupo"><label for="n-uni">Unidade</label><select id="n-uni" data-campo="unidade_id">' + opcoesSelect(f.unidades, n.unidade_id) + '</select></div>' : '')
            + (setoresSol.length ? '<div class="grupo"><label for="n-ss">Setor do solicitante' + (f.setor_solicitante_obrigatorio ? '' : ' (opcional)') + '</label><select id="n-ss" data-campo="setor_solicitante_id">' + opcoesSelect(setoresSol, n.setor_solicitante_id) + '</select></div>' : '')

            + '<div class="secao-titulo">Solicitante</div>'
            + '<div class="segmento" style="margin:0"><button type="button" class="' + (n.quem === 'eu' ? 'ativa' : '') + '" data-acao="quem" data-quem="eu">Eu mesmo</button>'
            + '<button type="button" class="' + (n.quem === 'outro' ? 'ativa' : '') + '" data-acao="quem" data-quem="outro">Outra pessoa</button></div>'
            + (n.quem === 'outro'
                ? '<div class="grupo"><label for="n-sol">Nome</label><input id="n-sol" data-campo="solicitante_nome" value="' + esc(n.solicitante_nome) + '" placeholder="Busque um usuário ou digite o nome" autocomplete="off"><div id="res-usuarios"></div></div>'
                    + '<div class="grupo"><label for="n-email">E-mail</label><input id="n-email" type="email" inputmode="email" data-campo="solicitante_email" value="' + esc(n.solicitante_email) + '"></div>'
                    + '<div class="grupo"><label for="n-tel">Telefone / WhatsApp</label><input id="n-tel" type="tel" inputmode="tel" data-campo="solicitante_telefone" value="' + esc(n.solicitante_telefone) + '"><div class="dica">Informe pelo menos o e-mail ou o telefone.</div></div>'
                : '')

            + '<div class="secao-titulo">Equipamento (opcional)</div>'
            + (n.ativo
                ? '<div class="escolhido">' + icone('monitor') + '<div><b>' + esc(n.ativo.codigo) + '</b><small>' + esc(n.ativo.nome) + '</small></div><button type="button" data-acao="tirar-ativo">' + icone('x') + '</button></div>'
                : '<div class="grupo"><input id="n-ativo" placeholder="Código, nome ou nº de série" autocomplete="off"><div id="res-ativos"></div></div>')

            + '<div class="secao-titulo">Fotos e arquivos</div>'
            + '<div class="miniaturas">' + n.anexos.map(function (x, i) {
                return '<div class="miniatura">' + (x.previa ? '<img src="' + x.previa + '">' : '<div class="doc">' + esc(x.nome) + '</div>') + '<button type="button" data-acao="tirar-anexo-novo" data-i="' + i + '">×</button></div>';
            }).join('') + '<button type="button" class="botao fantasma" style="height:58px" data-acao="anexar-novo">' + icone('camera') + 'Adicionar</button></div>'

            + (podeAssumir ? '<label class="cartao chave"><div><b>Já assumir este chamado</b><div style="font-size:13px;color:var(--texto-3)">Vai direto para "Comigo", sem passar pela fila.</div></div><input type="checkbox" id="n-assumir"' + (n.assumir ? ' checked' : '') + '></label>' : '')
            + '<button class="botao largo" type="submit" id="botao-abrir"' + (n.enviando ? ' disabled' : '') + '>' + (n.enviando ? '<span class="giro"></span>Abrindo...' : icone('check') + 'Abrir chamado') + '</button>'
            + '</form></main>';
    }

    function ligarFormularioNovo() {
        var form = $('#form-novo');
        if (!form) return;
        form.addEventListener('input', function (e) {
            var campo = e.target.getAttribute('data-campo');
            if (campo) estado.novo[campo] = e.target.value;
            if (e.target.id === 'n-assumir') estado.novo.assumir = e.target.checked;
            if (e.target.id === 'n-ativo') buscarDebounce('ativos', e.target.value);
            if (e.target.id === 'n-sol') buscarDebounce('usuarios', e.target.value);
        });
        form.addEventListener('change', function (e) {
            if (e.target.id === 'n-assumir') estado.novo.assumir = e.target.checked;
            if (e.target.id === 'n-cat') { estado.novo.subcategoria_id = ''; desenhar(); }
            if (e.target.id === 'n-uni') { estado.novo.setor_solicitante_id = ''; desenhar(); }
        });
    }

    var timerBusca = null;
    var resultadosBusca = { ativos: [], usuarios: [] };
    function buscarDebounce(tipo, termo) {
        clearTimeout(timerBusca);
        var alvo = $(tipo === 'ativos' ? '#res-ativos' : '#res-usuarios');
        if (!alvo) return;
        if (String(termo).trim().length < 2) { alvo.innerHTML = ''; return; }
        timerBusca = setTimeout(function () {
            api('chamados/' + tipo + '?q=' + encodeURIComponent(termo.trim())).then(function (r) {
                var lista = r[tipo] || [];
                resultadosBusca[tipo] = lista;
                var el = $(tipo === 'ativos' ? '#res-ativos' : '#res-usuarios');
                if (!el) return;
                el.innerHTML = lista.length ? '<div class="resultados">' + lista.map(function (x, i) {
                    return tipo === 'ativos'
                        ? '<button type="button" data-acao="pegar-ativo" data-i="' + i + '"><b>' + esc(x.codigo) + '</b><small>' + esc(x.nome) + (x.tipo ? ' · ' + esc(x.tipo) : '') + '</small></button>'
                        : '<button type="button" data-acao="pegar-usuario" data-i="' + i + '"><b>' + esc(x.nome) + '</b><small>' + esc(x.email || '') + '</small></button>';
                }).join('') + '</div>' : '';
            }).catch(function () {});
        }, 300);
    }

    function enviarNovo() {
        var n = estado.novo;
        var f = estado.formulario;
        if (!n.titulo.trim()) return aviso('Informe o título.', 'erro');
        if (!n.descricao.trim()) return aviso('Descreva o problema.', 'erro');
        if (!n.categoria_id) return aviso('Escolha a categoria.', 'erro');
        if (!n.unidade_id) return aviso('Escolha a unidade.', 'erro');
        if (n.quem === 'outro' && !n.solicitante_nome.trim()) return aviso('Informe o nome do solicitante.', 'erro');
        if (n.quem === 'outro' && !n.solicitante_email.trim() && !n.solicitante_telefone.trim()) return aviso('Informe o e-mail ou o telefone do solicitante.', 'erro');
        if (n.quem === 'eu' && !(f.eu && f.eu.email)) {
            // Sem e-mail no cadastro do usuário, o chamado precisa de outro contato.
            return aviso('Seu usuário não tem e-mail cadastrado. Use "Outra pessoa" e informe um contato.', 'erro');
        }

        n.enviando = true;
        desenhar();
        api('chamados/abrir', {
            titulo: n.titulo, descricao: n.descricao, categoria_id: +n.categoria_id, subcategoria_id: +n.subcategoria_id || null,
            prioridade: n.prioridade, unidade_id: +n.unidade_id, setor_solicitante_id: +n.setor_solicitante_id || null,
            ativo_id: n.ativo ? n.ativo.id : null, assumir: n.assumir,
            solicitante_nome: n.quem === 'outro' ? n.solicitante_nome : '',
            solicitante_email: n.quem === 'outro' ? n.solicitante_email : '',
            solicitante_telefone: n.quem === 'outro' ? n.solicitante_telefone : '',
            anexos: n.anexos.map(function (x) { return { nome: x.nome, base64: x.base64 }; })
        }).then(function (r) {
            atualizarResumo(true, true).catch(function () {});
            aviso(r.message, 'ok');
            vibrar(50);
            estado.listas = {};
            pilha = [];
            estado.tela = 'lista';
            abrirChamado(r.id);
        }).catch(function (e) {
            n.enviando = false;
            desenhar();
            if (!e.sessao) aviso(e.message, 'erro');
        });
    }

    // ------------------------------------------------------------------
    // Conta
    // ------------------------------------------------------------------
    function folhaConta() {
        var c = conta();
        var srv = servidorAtual() || {};
        var outros = estado.servidores.length > 1;
        abrirFolha('<div class="menu-conta"><div class="avatar">' + esc(iniciais(c.usuario.nome)) + '</div><b>' + esc(c.usuario.nome) + '</b>'
            + '<small>' + esc(c.usuario.login) + ' · ' + esc(srv.empresa || c.empresa) + '</small><small>' + esc(estado.atual) + '</small></div>'
            + '<div class="lista-opcoes">'
            + '<button data-acao="servidores">' + icone('servidor') + (outros ? 'Trocar de cliente' : 'Endereço do RD.Intranet') + '</button>'
            + '<button data-acao="sair" style="color:var(--perigo)">' + icone('sair') + 'Sair deste cliente</button>'
            + '</div><div class="versao" style="text-align:center;color:var(--texto-3);margin-top:10px">RD.Intranet para Android · versão ' + VERSAO + '</div>');
    }

    function sair() {
        var url = estado.atual;
        api('logout', {}).catch(function () {});
        delete estado.sessoes[url];
        estado.listas = {};
        estado.resumo = null;
        pararAtualizacao();
        salvar();
        fecharFolha();
        pilha = [];
        estado.tela = 'entrada';
        desenhar();
    }

    // ------------------------------------------------------------------
    // Folha (bottom sheet)
    // ------------------------------------------------------------------
    function abrirFolha(html) {
        var f = $('#folha');
        f.innerHTML = '<div class="painel"><div class="alca"></div>' + html + '</div>';
        f.hidden = false;
    }
    function fecharFolha() { var f = $('#folha'); f.hidden = true; f.innerHTML = ''; }

    // ------------------------------------------------------------------
    // Eventos
    // ------------------------------------------------------------------
    var ACOES = {
        servidores: folhaServidores,
        'escolher-servidor': function (el) { escolherServidor(el.dataset.url); },
        'remover-servidor': function (el) { if (confirm('Remover este cliente do app?')) removerServidor(el.dataset.url); },
        aba: function (el) {
            estado.aba = el.dataset.aba;
            window.scrollTo(0, 0);
            desenhar();
            carregarLista(true);
        },
        recarregar: function () { estado.listas[estado.aba] = null; desenhar(); carregarLista(true); },
        conta: folhaConta,
        sair: function () { if (confirm('Sair deste cliente?')) sair(); },
        abrir: function (el) { abrirChamado(+el.dataset.id); },
        voltar: voltar,
        'recarregar-detalhe': function () { recarregarDetalhe(false); },
        segmento: function (el) { estado.segmentoDetalhe = el.dataset.seg; desenhar(); },
        assumir: function () { acaoChamado('chamados/assumir', { id: estado.detalhe.id }).catch(function () {}); },
        pegar: function () {
            if (confirm('Pegar este chamado para você? Ele está com ' + (estado.detalhe.chamado.atendente || 'outra pessoa') + '.')) {
                acaoChamado('chamados/transferir', { id: estado.detalhe.id, usuario_id: conta().usuario.id }, 'Chamado assumido.').catch(function () {});
            }
        },
        status: folhaStatus,
        'escolher-status': function (el) { confirmarStatus(el.dataset.status); },
        transferir: folhaTransferir,
        'tipo-msg': function (el) { estado.rascunho.tipo = el.dataset.tipo; desenhar(); var t = $('#texto-msg'); if (t) t.focus(); },
        anexar: function () { folhaAnexar('rascunho'); },
        'anexar-novo': function () { folhaAnexar('novo'); },
        'escolher-arquivo': function (el) { escolherArquivo(el.dataset.tipo); },
        'tirar-anexo': function (el) { estado.rascunho.anexos.splice(+el.dataset.i, 1); desenhar(); },
        'tirar-anexo-novo': function (el) { estado.novo.anexos.splice(+el.dataset.i, 1); desenhar(); },
        'enviar-msg': enviarMensagem,
        'ver-imagem': function (el) {
            if (!el.src) return;
            var v = $('#visor');
            v.innerHTML = '<img src="' + esc(el.src) + '"><button class="icone-topo" data-acao="fechar-visor">' + icone('x') + '</button>';
            v.hidden = false;
        },
        'fechar-visor': function () { $('#visor').hidden = true; },
        novo: abrirNovo,
        prioridade: function (el) { estado.novo.prioridade = el.dataset.p; desenhar(); },
        quem: function (el) { estado.novo.quem = el.dataset.quem; desenhar(); },
        'pegar-ativo': function (el) {
            var a = resultadosBusca.ativos[+el.dataset.i];
            estado.novo.ativo = a;
            if (a.unidade_id && estado.formulario.unidades.some(function (u) { return u.id === a.unidade_id; })) estado.novo.unidade_id = String(a.unidade_id);
            desenhar();
        },
        'tirar-ativo': function () { estado.novo.ativo = null; desenhar(); },
        'pegar-usuario': function (el) {
            var u = resultadosBusca.usuarios[+el.dataset.i];
            estado.novo.solicitante_nome = u.nome;
            estado.novo.solicitante_email = u.email || '';
            desenhar();
        }
    };

    document.addEventListener('click', function (e) {
        var folha = $('#folha');
        if (e.target === folha) { fecharFolha(); return; }
        if (e.target === $('#visor')) { $('#visor').hidden = true; return; }
        var el = e.target.closest('[data-acao]');
        if (!el || !ACOES[el.dataset.acao]) return;
        e.preventDefault();
        ACOES[el.dataset.acao](el);
    });

    document.addEventListener('submit', function (e) {
        e.preventDefault();
        var id = e.target.id;
        if (id === 'form-entrada') entrar(e.target);
        else if (id === 'form-servidor') adicionarServidor(e.target);
        else if (id === 'form-novo') enviarNovo();
        else if (id === 'form-resolver') {
            var msg = $('#msg-final').value.trim();
            acaoChamado('chamados/status', { id: estado.detalhe.id, status: 'resolvido', mensagem: msg }).catch(function () {});
        } else if (id === 'form-transferir') {
            var destino = +$('#destino').value;
            if (!destino) { aviso('Escolha para quem transferir.', 'erro'); return; }
            acaoChamado('chamados/transferir', { id: estado.detalhe.id, usuario_id: destino, motivo: $('#motivo').value }).catch(function () {});
        }
    });

    // O formulário de novo chamado é redesenhado a cada escolha; religa os eventos.
    var desenharOriginal = desenhar;
    desenhar = function () {
        // Preserva o foco/cursor do campo que está sendo digitado.
        var ativo = document.activeElement;
        var idAtivo = ativo && ativo.id;
        var cursor = ativo && typeof ativo.selectionStart === 'number' ? ativo.selectionStart : null;
        desenharOriginal();
        if (estado.tela === 'novo') ligarFormularioNovo();
        if (idAtivo && idAtivo !== 'campo-busca') {
            var novo = document.getElementById(idAtivo);
            if (novo && novo !== document.body && novo.tagName !== 'BUTTON') {
                novo.focus();
                try { if (cursor !== null) novo.setSelectionRange(cursor, cursor); } catch (e) {}
            }
        }
    };

    // ------------------------------------------------------------------
    // Início
    // ------------------------------------------------------------------
    function iniciar() {
        if (StatusBar) {
            StatusBar.setBackgroundColor({ color: '#0b1b33' }).catch(function () {});
            StatusBar.setStyle({ style: 'DARK' }).catch(function () {});
        }
        if (AppPlugin) {
            AppPlugin.addListener('backButton', function () {
                if (!voltar()) AppPlugin.minimizeApp ? AppPlugin.minimizeApp() : AppPlugin.exitApp();
            });
            AppPlugin.addListener('resume', function () {
                if (sessao() && estado.tela !== 'entrada') {
                    atualizarResumo(false).catch(function () {});
                    if (estado.tela === 'detalhe') recarregarDetalhe(true);
                }
            });
        }

        Promise.all([ler('servidores'), ler('atual'), ler('sessoes'), ler('vistos')]).then(function (v) {
            estado.servidores = v[0] || [];
            estado.atual = v[1];
            estado.sessoes = v[2] || {};
            estado.vistos = v[3] || {};
            if (!estado.servidores.length) {
                estado.servidores.push({ url: SERVIDOR_PADRAO, empresa: 'Sidore', temLogo: false });
                estado.atual = SERVIDOR_PADRAO;
                salvar();
            }
            if (!estado.atual || !servidorAtual()) estado.atual = estado.servidores[0].url;

            if (sessao()) abrirPrincipal();
            else { estado.tela = 'entrada'; desenhar(); }
            // Nome e logo do cliente atualizados.
            descobrirServidor(estado.atual).then(function (a) {
                atualizarServidor(estado.atual, { empresa: a.info.empresa, temLogo: a.info.tem_logo });
                salvar();
                if (estado.tela === 'entrada') desenhar();
            }).catch(function () {});
        });
    }

    iniciar();
})();
