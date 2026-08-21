/**
 * Overlay de "executando..." + exportação (HTML/PDF/JPG) dos resultados
 * das ferramentas de diagnóstico de rede (Ping, Traceroute, MTR, DNS).
 * Reaproveitado pelas 4 views -- ver app/Views/infrastructure/rede_*.php.
 */
(function () {
    'use strict';

    function mostrarCarregando(mensagem) {
        var overlay = document.createElement('div');
        overlay.className = 'rd-loading-overlay';
        overlay.innerHTML =
            '<div class="rd-loading-card">' +
                '<div class="rd-loading-radar"><i class="bi bi-broadcast"></i></div>' +
                '<div class="rd-loading-texto">' + mensagem + '</div>' +
                '<div class="rd-loading-barra"><div></div></div>' +
            '</div>';
        document.body.appendChild(overlay);
    }

    function armarFormulario(form, mensagem) {
        if (!form) {
            return;
        }

        form.addEventListener('submit', function () {
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                return;
            }
            mostrarCarregando(mensagem);
        });
    }

    var ESTILO_EXPORT =
        'body{font-family:Arial,Helvetica,sans-serif;color:#1e293b;background:#fff;margin:0;padding:24px}' +
        '.rd-export-cabecalho{display:flex;align-items:center;gap:16px;border-bottom:2px solid #2563eb;padding-bottom:16px;margin-bottom:20px}' +
        '.rd-export-cabecalho img{max-width:160px;max-height:64px;object-fit:contain}' +
        '.rd-export-cabecalho h2{margin:0;font-size:20px;color:#0f172a}' +
        '.rd-export-cabecalho small{color:#64748b}' +
        '.rd-export-rodape{margin-top:24px;padding-top:12px;border-top:1px solid #e2e8f0;color:#94a3b8;font-size:12px;text-align:center}' +
        '.card{border:1px solid #e2e8f0;border-radius:10px;overflow:hidden}' +
        '.card-header{background:#f8fafc;padding:10px 16px;border-bottom:1px solid #e2e8f0;font-weight:600}' +
        '.card-body{padding:16px}' +
        'table{width:100%;border-collapse:collapse;font-size:13px}' +
        'table th,table td{padding:6px 10px;border-bottom:1px solid #e2e8f0;text-align:left}' +
        'table th{background:#f1f5f9}' +
        'pre{white-space:pre-wrap;background:#0f172a;color:#e2e8f0;padding:12px;border-radius:8px;font-size:12px}' +
        '.badge{display:inline-block;padding:2px 8px;border-radius:6px;font-size:11px;color:#fff}' +
        '.text-bg-success{background:#16a34a}.text-bg-danger{background:#dc2626}' +
        '.table-danger{background:#fef2f2}';

    function sanitizarNomeArquivo(texto) {
        return String(texto || 'resultado')
            .toLowerCase()
            .replace(/[^a-z0-9.-]+/g, '-')
            .replace(/^-+|-+$/g, '') || 'resultado';
    }

    function montarWrapper(opcoes) {
        var logo = document.querySelector('.sidebar img[alt="RD Intranet"]');
        var origem = document.querySelector(opcoes.container);

        if (!origem) {
            return null;
        }

        var clone = origem.cloneNode(true);

        clone.querySelectorAll('.collapse').forEach(function (el) {
            el.classList.add('show');
        });
        clone.querySelectorAll('[data-rd-export-ignore]').forEach(function (el) {
            el.remove();
        });

        var wrapper = document.createElement('div');
        wrapper.className = 'rd-export-documento';
        wrapper.style.position = 'absolute';
        wrapper.style.left = '-9999px';
        wrapper.style.top = '0';
        wrapper.style.width = '860px';

        var estilo = document.createElement('style');
        estilo.textContent = ESTILO_EXPORT;
        wrapper.appendChild(estilo);

        // Construído via createElement/textContent (nunca innerHTML com
        // string concatenada) -- opcoes.titulo/opcoes.alvo podem conter
        // o texto exatamente como o usuário digitou no formulário
        // (inclusive quando a validação do servidor rejeitou o valor),
        // então precisam ser tratados como texto puro, não HTML.
        var cabecalho = document.createElement('div');
        cabecalho.className = 'rd-export-cabecalho';

        if (logo) {
            var imgLogo = document.createElement('img');
            imgLogo.src = logo.src;
            imgLogo.alt = 'Logo';
            cabecalho.appendChild(imgLogo);
        }

        var textos = document.createElement('div');

        var h2 = document.createElement('h2');
        h2.textContent = opcoes.titulo || '';
        textos.appendChild(h2);

        var small = document.createElement('small');
        small.textContent = (opcoes.alvo || '') + ' — ' + new Date().toLocaleString('pt-BR');
        textos.appendChild(small);

        cabecalho.appendChild(textos);
        wrapper.appendChild(cabecalho);

        wrapper.appendChild(clone);

        var rodape = document.createElement('div');
        rodape.className = 'rd-export-rodape';
        rodape.textContent = 'Desenvolvido por RD.Tecnologia — www.rd.inf.br';
        wrapper.appendChild(rodape);

        document.body.appendChild(wrapper);

        return wrapper;
    }

    function baixarBlob(blob, nomeArquivo) {
        var url = URL.createObjectURL(blob);
        var link = document.createElement('a');
        link.href = url;
        link.download = nomeArquivo;
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
    }

    function exportarHtml(wrapper, nomeBase) {
        // Reseta o posicionamento "fora da tela" antes de serializar --
        // só serve pra esconder o wrapper enquanto ele existe nesta
        // página, não faz sentido no arquivo exportado.
        wrapper.style.position = 'static';
        wrapper.style.left = 'auto';
        wrapper.style.top = 'auto';

        var doc = '<!doctype html><html lang="pt-br"><head><meta charset="UTF-8">' +
            '<title>' + nomeBase + '</title></head><body>' +
            wrapper.outerHTML +
            '</body></html>';

        baixarBlob(new Blob([doc], { type: 'text/html;charset=utf-8' }), nomeBase + '.html');
    }

    function exportarJpg(wrapper, nomeBase) {
        return window.html2canvas(wrapper, { backgroundColor: '#ffffff', scale: 2 }).then(function (canvas) {
            canvas.toBlob(function (blob) {
                baixarBlob(blob, nomeBase + '.jpg');
            }, 'image/jpeg', 0.92);
        });
    }

    function exportarPdf(wrapper, nomeBase) {
        var jsPDF = window.jspdf && window.jspdf.jsPDF;
        if (!jsPDF) {
            return Promise.reject(new Error('jsPDF não carregado.'));
        }

        var doc = new jsPDF('p', 'pt', 'a4');

        return new Promise(function (resolve) {
            doc.html(wrapper, {
                margin: [24, 24, 24, 24],
                autoPaging: 'text',
                html2canvas: { scale: 2, backgroundColor: '#ffffff' },
                callback: function (pdf) {
                    pdf.save(nomeBase + '.pdf');
                    resolve();
                },
            });
        });
    }

    function exportar(formato, opcoes) {
        var wrapper = montarWrapper(opcoes);

        if (!wrapper) {
            return;
        }

        var nomeBase = sanitizarNomeArquivo(opcoes.ferramenta) + '-' +
            sanitizarNomeArquivo(opcoes.alvo) + '-' +
            new Date().toISOString().slice(0, 10);

        var terminado = function () {
            wrapper.remove();
        };

        try {
            if (formato === 'html') {
                exportarHtml(wrapper, nomeBase);
                terminado();
            } else if (formato === 'jpg') {
                exportarJpg(wrapper, nomeBase).then(terminado).catch(terminado);
            } else if (formato === 'pdf') {
                exportarPdf(wrapper, nomeBase).then(terminado).catch(terminado);
            } else {
                terminado();
            }
        } catch (e) {
            terminado();
            throw e;
        }
    }

    // Delegação de clique nos itens "Exportar" -- os dados vêm de
    // atributos data-* (escapados normalmente via htmlspecialchars() no
    // PHP), nunca de HTML/JS montado por string no PHP, pra não abrir
    // brecha de XSS caso o valor exportado (destino/domínio) contenha
    // caracteres especiais que passaram pela validação do servidor com
    // sucesso=false (mensagem de erro ainda usa o valor bruto digitado).
    document.addEventListener('click', function (evento) {
        var item = evento.target.closest('[data-rd-export-formato]');
        if (!item) {
            return;
        }

        var painel = item.closest('[data-rd-export-container]');
        if (!painel) {
            return;
        }

        evento.preventDefault();

        exportar(item.getAttribute('data-rd-export-formato'), {
            container: painel.getAttribute('data-rd-export-container'),
            titulo: painel.getAttribute('data-rd-export-titulo'),
            ferramenta: painel.getAttribute('data-rd-export-ferramenta'),
            alvo: painel.getAttribute('data-rd-export-alvo'),
        });
    });

    window.RdDiagnostico = {
        mostrarCarregando: mostrarCarregando,
        armarFormulario: armarFormulario,
        exportar: exportar,
    };
})();
