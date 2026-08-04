<?php

use App\Components\Alert;
use App\Components\Badge;

ob_start();
?>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-1">
                <i class="bi bi-hdd-network"></i> Conexões SSH
            </h5>
            <small class="text-muted">
                Servidores locais ou atrás de NAT. As credenciais ficam encriptadas neste servidor;
                o terminal abre direto pelo navegador, sem precisar de cliente SSH nem de acesso externo à porta.
            </small>
        </div>

        <a href="<?= url('/ssh/conexoes/novo') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Nova conexão
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Host</th>
                    <th>Porta</th>
                    <th>Usuário</th>
                    <th>Autenticação</th>
                    <th>Status</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($conexoes)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Nenhuma conexão cadastrada.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($conexoes as $c): ?>
                    <tr>
                        <td>
                            <?= htmlspecialchars($c['nome']) ?>
                            <?php if (!empty($c['observacoes'])): ?>
                                <br><small class="text-muted"><?= htmlspecialchars($c['observacoes']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><code><?= htmlspecialchars($c['host']) ?></code></td>
                        <td><?= (int)$c['porta'] ?></td>
                        <td><?= htmlspecialchars($c['usuario']) ?></td>
                        <td>
                            <?php if ($c['tipo_autenticacao'] === 'chave_privada'): ?>
                                <i class="bi bi-file-earmark-lock"></i> Chave privada
                            <?php elseif ($c['tipo_autenticacao'] === 'perguntar'): ?>
                                <i class="bi bi-question-circle"></i> Perguntar na hora
                            <?php else: ?>
                                <i class="bi bi-key"></i> Senha
                            <?php endif; ?>
                        </td>
                        <td><?= (int)$c['ativo'] === 1 ? Badge::make('Ativo', 'success') : Badge::make('Desativado', 'danger') ?></td>
                        <td class="text-end">
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-sm btn-outline-success botao-conectar"
                                        data-id="<?= $c['id'] ?>" data-nome="<?= htmlspecialchars($c['nome']) ?>"
                                        data-tipo="<?= htmlspecialchars($c['tipo_autenticacao']) ?>" title="Abrir terminal">
                                    <i class="bi bi-terminal"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-info botao-testar" data-id="<?= $c['id'] ?>" title="Testar conexão">
                                    <i class="bi bi-plug"></i>
                                </button>
                                <a href="<?= url('/ssh/conexoes/editar?id=' . $c['id']) ?>"
                                   class="btn btn-sm btn-outline-primary" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="<?= url('/ssh/conexoes/credencial?id=' . $c['id']) ?>"
                                   class="btn btn-sm btn-outline-secondary" title="Redefinir credencial">
                                    <i class="bi bi-key"></i>
                                </a>
                                <?php if ((int)$c['ativo'] === 1): ?>
                                    <a href="<?= url('/ssh/conexoes/desativar?id=' . $c['id']) ?>"
                                       class="btn btn-sm btn-outline-warning" title="Desativar">
                                        <i class="bi bi-lock"></i>
                                    </a>
                                <?php else: ?>
                                    <a href="<?= url('/ssh/conexoes/ativar?id=' . $c['id']) ?>"
                                       class="btn btn-sm btn-outline-success" title="Ativar">
                                        <i class="bi bi-unlock"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="<?= url('/ssh/conexoes/excluir?id=' . $c['id']) ?>"
                                   class="btn btn-sm btn-outline-danger" title="Excluir">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalTeste" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Teste de conexão</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalTesteCorpo">
                <div class="text-center text-muted"><i class="bi bi-hourglass-split"></i> Testando...</div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalTerminal" tabindex="-1">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title" id="tituloTerminal"><i class="bi bi-terminal"></i> Terminal</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0 d-flex align-items-center justify-content-center bg-dark" id="corpoTerminal">
                <div class="text-white-50">
                    <i class="bi bi-hourglass-split"></i> Verificando...
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const baseUrlTestar = <?= json_encode(url('/ssh/conexoes/testar')) ?>;

document.querySelectorAll('.botao-testar').forEach(function (botao) {
    botao.addEventListener('click', function () {
        const modalTeste = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalTeste'));
        const id = botao.dataset.id;
        const corpo = document.getElementById('modalTesteCorpo');
        corpo.innerHTML = '<div class="text-center text-muted"><i class="bi bi-hourglass-split"></i> Testando...</div>';
        modalTeste.show();

        fetch(baseUrlTestar, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + encodeURIComponent(id)
        })
            .then(function (r) { return r.json(); })
            .then(function (dados) {
                const cor = dados.success ? 'success' : 'danger';
                const icone = dados.success ? 'check-circle' : 'x-circle';
                corpo.innerHTML = '<div class="alert alert-' + cor + ' mb-0"><i class="bi bi-' + icone + '"></i> ' + dados.message + '</div>';
            })
            .catch(function () {
                corpo.innerHTML = '<div class="alert alert-danger mb-0">Erro ao testar conexão.</div>';
            });
    });
});
</script>

<script type="module">
import Guacamole from <?= json_encode(url('/assets/js/guacamole-common.min.js')) ?>;

(function () {
    const modalEl = document.getElementById('modalTerminal');
    const corpo = document.getElementById('corpoTerminal');
    const titulo = document.getElementById('tituloTerminal');
    const statusInicial = corpo.innerHTML;

    let clienteAtivo = null;
    let idAtual = null;
    let tipoAtual = null;
    let pararDeEscalar = null;

    // Mesmo raciocínio do RDP em Ativos > ver.php: um só teclado pra vida
    // inteira do modal, senão a digitação para de funcionar na página
    // inteira depois de fechar/reabrir o modal.
    const tecladoGlobal = new Guacamole.Keyboard(document);

    function desconectar() {
        tecladoGlobal.onkeydown = null;
        tecladoGlobal.onkeyup = null;

        if (pararDeEscalar) {
            pararDeEscalar();
            pararDeEscalar = null;
        }

        if (clienteAtivo) {
            const c = clienteAtivo;
            clienteAtivo = null;
            try { c.disconnect(); } catch (e) { /* já desconectado */ }
        }
    }

    function mostrarErro(mensagem) {
        desconectar();

        if (tipoAtual === 'perguntar') {
            corpo.innerHTML = '';
            const aviso = document.createElement('div');
            aviso.className = 'text-white-50 text-center p-4';
            aviso.innerHTML = `
                <p>${mensagem || 'A sessão foi encerrada, sem detalhe do motivo -- confira a senha.'}</p>
                <button type="button" class="btn btn-sm btn-outline-light" id="botaoTentarNovamenteSsh">
                    <i class="bi bi-arrow-repeat"></i> Tentar de novo
                </button>
            `;
            corpo.appendChild(aviso);
            aviso.querySelector('#botaoTentarNovamenteSsh').addEventListener('click', formularioSenha);
            return;
        }

        corpo.innerHTML = '<div class="text-white-50 text-center p-4">' +
            (mensagem || 'A sessão foi encerrada, sem detalhe do motivo.') + '</div>';
    }

    function telaPreparo(senhaDigitada) {
        corpo.innerHTML = '';
        const wrap = document.createElement('div');
        wrap.className = 'text-white-50 text-center p-4';
        wrap.innerHTML = `
            <p>O suporte a terminal pelo navegador ainda não está pronto neste servidor (guacd + ponte de conexão).</p>
            <button type="button" class="btn btn-sm btn-outline-light" id="botaoPrepararSsh">
                <i class="bi bi-gear"></i> Preparar suporte a terminal no navegador
            </button>
        `;
        corpo.appendChild(wrap);

        wrap.querySelector('#botaoPrepararSsh').addEventListener('click', async function () {
            this.disabled = true;
            this.innerHTML = '<i class="bi bi-hourglass-split"></i> Instalando (pode levar alguns minutos)...';

            try {
                const res = await fetch(<?= json_encode(url('/ssh/conexoes/gateway/instalar')) ?>, { method: 'POST' });
                const resultado = await res.json();

                if (!resultado.success) {
                    corpo.innerHTML = '<div class="text-white-50 text-center p-4">' + (resultado.message || 'Falha ao instalar.') + '</div>';
                    return;
                }

                conectar(senhaDigitada);
            } catch (e) {
                corpo.innerHTML = '<div class="text-white-50 text-center p-4">Erro ao comunicar com o servidor.</div>';
            }
        });
    }

    function formularioSenha() {
        corpo.innerHTML = '';
        const wrap = document.createElement('div');
        wrap.className = 'p-4';
        wrap.style.maxWidth = '360px';
        wrap.style.width = '100%';
        wrap.innerHTML = `
            <p class="text-white-50 text-center small">Essa conexão não guarda senha neste servidor -- digite pra conectar agora.</p>
            <form id="formSenhaSsh">
                <div class="mb-3">
                    <label class="form-label small text-white-50">Senha SSH</label>
                    <input type="password" name="senha" class="form-control form-control-sm" autofocus required>
                </div>
                <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-terminal"></i> Conectar</button>
            </form>
        `;
        corpo.appendChild(wrap);

        wrap.querySelector('#formSenhaSsh').addEventListener('submit', function (e) {
            e.preventDefault();
            conectar(this.senha.value);
        });
    }

    async function conectar(senhaDigitada) {
        if (location.protocol !== 'https:') {
            corpo.innerHTML = '<div class="text-white-50 text-center p-4"><p>Acesse este painel via <strong>HTTPS</strong> pra usar terminal pelo navegador -- agora você está em HTTP (' + location.protocol + '//' + location.host + ').</p></div>';
            return;
        }

        corpo.innerHTML = '<div class="text-white-50 text-center p-4"><i class="bi bi-hourglass-split"></i> Verificando...</div>';

        try {
            const resStatus = await fetch(<?= json_encode(url('/ssh/conexoes/gateway/status')) ?>);
            const dadosStatus = await resStatus.json();
            const g = dadosStatus.gateway || {};

            if (!(g.guacd_ativo && g.bridge_ativo && g.proxy_configurado)) {
                telaPreparo(senhaDigitada);
                return;
            }

            corpo.innerHTML = '<div class="text-white-50 text-center p-4"><i class="bi bi-hourglass-split"></i> Conectando...</div>';

            const dados = new URLSearchParams();
            dados.set('id', idAtual);
            dados.set('largura', corpo.clientWidth);
            dados.set('altura', corpo.clientHeight);
            if (senhaDigitada) {
                dados.set('senha_digitada', senhaDigitada);
            }

            const res = await fetch(<?= json_encode(url('/ssh/conexoes/conectar')) ?>, { method: 'POST', body: dados });
            const resultado = await res.json();

            if (!resultado.success) {
                corpo.innerHTML = '<div class="text-white-50 text-center p-4">' + (resultado.message || 'Falha ao conectar.') + '</div>';
                return;
            }

            corpo.innerHTML = '';
            const display = document.createElement('div');
            display.style.width = '100%';
            display.style.height = '100%';
            display.style.overflow = 'auto';
            display.style.alignSelf = 'stretch';
            display.style.position = 'relative';
            display.style.zIndex = '0';
            corpo.appendChild(display);

            const tunnel = new Guacamole.WebSocketTunnel(
                'wss://' + location.host + '/rdp-ws?token=' + encodeURIComponent(resultado.token)
            );
            const client = new Guacamole.Client(tunnel);
            clienteAtivo = client;
            tecladoGlobal.onkeydown = function (codigo) { if (clienteAtivo) clienteAtivo.sendKeyEvent(1, codigo); };
            tecladoGlobal.onkeyup = function (codigo) { if (clienteAtivo) clienteAtivo.sendKeyEvent(0, codigo); };

            client.onclipboard = function (stream, mimetype) {
                if (mimetype !== 'text/plain') return;
                const leitor = new Guacamole.StringReader(stream);
                let texto = '';
                leitor.ontext = function (fragmento) { texto += fragmento; };
                leitor.onend = function () {
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(texto).catch(function () { /* sem permissão -- ignora */ });
                    }
                };
            };

            client.onerror = function (status) {
                mostrarErro(status && status.message ? ('Falha na sessão: ' + status.message) : null);
            };

            client.onstatechange = function (estado) {
                if (estado === 5 && clienteAtivo === client) {
                    mostrarErro(null);
                }
            };

            const guacDisplay = client.getDisplay();
            const elementoDisplay = guacDisplay.getElement();
            display.appendChild(elementoDisplay);
            client.connect();

            display.style.cursor = 'none';

            const mouse = new Guacamole.Mouse(elementoDisplay);
            mouse.onmousedown = mouse.onmouseup = mouse.onmousemove = function (estado) {
                const escalaAtual = guacDisplay.getScale() || 1;
                client.sendMouseState({
                    x: estado.x / escalaAtual,
                    y: estado.y / escalaAtual,
                    left: estado.left,
                    middle: estado.middle,
                    right: estado.right,
                    up: estado.up,
                    down: estado.down,
                });
            };

            elementoDisplay.tabIndex = -1;
            elementoDisplay.addEventListener('mousedown', function () { elementoDisplay.focus(); });
            elementoDisplay.addEventListener('paste', function (e) {
                const texto = (e.clipboardData || window.clipboardData).getData('text/plain');
                if (!texto || !clienteAtivo) return;
                const fluxo = clienteAtivo.createClipboardStream('text/plain');
                const escritor = new Guacamole.StringWriter(fluxo);
                escritor.sendText(texto);
                escritor.sendEnd();
            });

            function ajustarEscala() {
                const larguraNativa = guacDisplay.getWidth();
                const alturaNativa = guacDisplay.getHeight();
                if (!larguraNativa || !alturaNativa) return;
                const escala = Math.min(corpo.clientWidth / larguraNativa, corpo.clientHeight / alturaNativa);
                guacDisplay.scale(escala > 0 ? escala : 1);
            }
            guacDisplay.onresize = ajustarEscala;
            window.addEventListener('resize', ajustarEscala);
            pararDeEscalar = function () { window.removeEventListener('resize', ajustarEscala); };
        } catch (e) {
            corpo.innerHTML = '<div class="text-white-50 text-center p-4">Erro ao comunicar com o servidor.</div>';
        }
    }

    document.querySelectorAll('.botao-conectar').forEach(function (botao) {
        botao.addEventListener('click', function () {
            idAtual = botao.dataset.id;
            tipoAtual = botao.dataset.tipo;
            titulo.innerHTML = '<i class="bi bi-terminal"></i> Terminal -- ' + botao.dataset.nome;
            corpo.innerHTML = statusInicial;
            bootstrap.Modal.getOrCreateInstance(modalEl).show();

            if (tipoAtual === 'perguntar') {
                formularioSenha();
            } else {
                conectar();
            }
        });
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
        desconectar();
        corpo.innerHTML = statusInicial;
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'SSH - Conexões';

require __DIR__ . '/../layouts/main.php';
