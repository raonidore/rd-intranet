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
        // Moldura em <div> (não direto no <img>) porque html2canvas tem
        // suporte fraco a border-radius/object-fit aplicados num <img> --
        // recorte funciona de forma bem mais confiável num container com
        // overflow:hidden.
        '.rd-export-logo-frame{width:150px;height:60px;flex:none;display:flex;align-items:center;justify-content:center;border-radius:10px;overflow:hidden;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.15)}' +
        '.rd-export-logo-frame img{max-width:90%;max-height:90%;object-fit:contain}' +
        '.rd-export-cabecalho h2{margin:0;font-size:20px;color:#0f172a}' +
        '.rd-export-cabecalho small{color:#64748b}' +
        '.rd-export-rodape{margin-top:24px;padding-top:12px;border-top:1px solid #e2e8f0;color:#94a3b8;font-size:12px;text-align:center}' +
        '.rd-export-rodape a{color:#2563eb;text-decoration:underline}' +
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

    /**
     * Busca a logo já renderizada no sidebar e converte pra data: URI --
     * necessário porque o HTML exportado é um arquivo standalone (aberto
     * via file:// depois de baixado, ex: anexado num e-mail pro suporte
     * do provedor), sem acesso à intranet pra buscar a imagem por URL; e
     * também deixa a captura do html2canvas (JPG/PDF) mais confiável, sem
     * depender de um fetch de rede terminar a tempo durante a captura.
     */
    function carregarLogoComoDataUri() {
        var logo = document.querySelector('.sidebar img[alt="RD Intranet"]');

        if (!logo || !logo.src) {
            return Promise.resolve(null);
        }

        return fetch(logo.src, { credentials: 'same-origin' })
            .then(function (resposta) {
                return resposta.ok ? resposta.blob() : null;
            })
            .then(function (blob) {
                if (!blob) {
                    return null;
                }

                return new Promise(function (resolve) {
                    var leitor = new FileReader();
                    leitor.onload = function () { resolve(leitor.result); };
                    leitor.onerror = function () { resolve(null); };
                    leitor.readAsDataURL(blob);
                });
            })
            .catch(function () { return null; });
    }

    function montarWrapper(opcoes, logoDataUri) {
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

        if (logoDataUri) {
            var moldura = document.createElement('div');
            moldura.className = 'rd-export-logo-frame';

            var imgLogo = document.createElement('img');
            imgLogo.src = logoDataUri;
            imgLogo.alt = 'Logo';
            moldura.appendChild(imgLogo);

            cabecalho.appendChild(moldura);
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
        rodape.appendChild(document.createTextNode('Desenvolvido por RD.Tecnologia — '));
        var linkSite = document.createElement('a');
        linkSite.href = 'https://www.rd.inf.br';
        linkSite.target = '_blank';
        linkSite.rel = 'noopener';
        linkSite.textContent = 'www.rd.inf.br';
        rodape.appendChild(linkSite);
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

    /**
     * Não usa doc.html() do jsPDF -- com o wrapper fora da tela
     * (position:absolute;left:-9999px), o cálculo interno de paginação
     * dele sai errado e gera dezenas de páginas em branco (visto ao vivo:
     * MTR de 12 saltos virou um PDF de 27 páginas vazias). Em vez disso,
     * captura o conteúdo uma vez com html2canvas (mesmo caminho que já
     * funciona pro JPG) e fatia a imagem resultante manualmente em
     * páginas A4 -- padrão simples e previsível pra imagem "alta".
     */
    function exportarPdf(wrapper, nomeBase) {
        var jsPDF = window.jspdf && window.jspdf.jsPDF;
        if (!jsPDF) {
            return Promise.reject(new Error('jsPDF não carregado.'));
        }

        // O rodapé vira texto real (clicável) via pdf.textWithLink() logo
        // abaixo, em vez de pixel dentro da imagem -- por isso não entra
        // na captura do html2canvas.
        var rodapeNode = wrapper.querySelector('.rd-export-rodape');
        if (rodapeNode) {
            rodapeNode.remove();
        }

        return window.html2canvas(wrapper, { backgroundColor: '#ffffff', scale: 2 }).then(function (canvas) {
            var pdf = new jsPDF('p', 'pt', 'a4');
            var margem = 24;
            var pageWidth = pdf.internal.pageSize.getWidth();
            var pageHeight = pdf.internal.pageSize.getHeight();
            var imgWidth = pageWidth - margem * 2;
            var imgHeight = (canvas.height * imgWidth) / canvas.width;
            var imgData = canvas.toDataURL('image/jpeg', 0.92);
            var areaUtil = pageHeight - margem * 2;

            var restante = imgHeight;
            var deslocamento = 0;

            pdf.addImage(imgData, 'JPEG', margem, margem, imgWidth, imgHeight);
            restante -= areaUtil;

            while (restante > 0) {
                deslocamento += areaUtil;
                pdf.addPage();
                pdf.addImage(imgData, 'JPEG', margem, margem - deslocamento, imgWidth, imgHeight);
                restante -= areaUtil;
            }

            var alturaUltimaFatia = imgHeight - (Math.ceil(imgHeight / areaUtil) - 1) * areaUtil;
            if (alturaUltimaFatia > areaUtil - 40) {
                pdf.addPage();
            }

            pdf.setFontSize(9);
            pdf.setTextColor(148, 163, 184);
            pdf.textWithLink('Desenvolvido por RD.Tecnologia — www.rd.inf.br', pageWidth / 2, pageHeight - 20, {
                align: 'center',
                url: 'https://www.rd.inf.br',
            });

            pdf.save(nomeBase + '.pdf');
        });
    }

    function exportar(formato, opcoes) {
        carregarLogoComoDataUri().then(function (logoDataUri) {
            var wrapper = montarWrapper(opcoes, logoDataUri);

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
        });
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
