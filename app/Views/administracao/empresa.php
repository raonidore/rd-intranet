<?php
ob_start();

use App\Components\Alert;
?>

<?= Alert::flash() ?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-building me-1"></i> Dados da Empresa</h4>
    <small class="text-muted">Usados no código de patrimônio dos ativos (ex: <code>SIGLA-UNIDADE-PC-000001</code>) e no rodapé das etiquetas impressas.</small>
</div>

<div class="card border-0 shadow-sm" style="max-width:560px">
    <div class="card-body">
        <form method="post" action="<?= url('/administracao/empresa/salvar') ?>">
            <div class="mb-3">
                <label class="form-label">Nome da empresa</label>
                <input type="text" name="nome" class="form-control" required value="<?= htmlspecialchars($nome) ?>" placeholder="Ex: RD Tecnologia">
            </div>

            <div class="mb-3">
                <label class="form-label">Sigla (usada no código dos ativos)</label>
                <input type="text" name="sigla" class="form-control font-monospace text-uppercase" required
                       maxlength="6" style="max-width:160px" value="<?= htmlspecialchars($sigla) ?>" placeholder="RD">
                <div class="form-text">2 a 6 letras, sem números ou símbolos. Só afeta ativos cadastrados <strong>a partir de agora</strong> -- os já existentes mantêm o código original.</div>
            </div>

            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm mt-3" style="max-width:560px">
    <div class="card-body">
        <strong class="d-block mb-1">Unidades Existentes</strong>
        <div class="form-text mb-3">
            Filiais/sites da empresa -- entram no código do patrimônio (<code>SIGLA-UNIDADE-TIPO-000001</code>) e, mais adiante, na
            abertura de chamados. Empresa com uma sede só precisa de 1 unidade cadastrada; quem tem várias filiais cadastra uma linha por filial.
        </div>

        <form method="post" action="<?= url('/administracao/empresa/unidade-novo') ?>" class="d-flex gap-2 mb-3">
            <input type="text" name="nome" class="form-control form-control-sm" placeholder="Ex: Filial Nordeste" required>
            <input type="text" name="sigla" class="form-control form-control-sm font-monospace text-uppercase" style="max-width:110px" maxlength="6" placeholder="NE" required>
            <button class="btn btn-sm btn-primary text-nowrap"><i class="bi bi-plus-lg"></i> Adicionar</button>
        </form>

        <?php if (empty($unidades)): ?>
            <p class="text-muted small mb-0">Nenhuma unidade cadastrada ainda.</p>
        <?php else: ?>
            <ul class="list-group list-group-flush">
                <?php foreach ($unidades as $u): ?>
                    <li class="list-group-item px-0">
                        <div class="d-flex justify-content-between align-items-center linha-view-unidade">
                            <span>
                                <?= htmlspecialchars($u['nome']) ?>
                                <span class="badge text-bg-light border font-monospace ms-1"><?= htmlspecialchars($u['sigla']) ?></span>
                                <?php if ($u['padrao']): ?>
                                    <span class="badge text-bg-light border ms-1">Padrão</span>
                                <?php endif; ?>
                            </span>
                            <div class="d-flex gap-1">
                                <?php if (!$u['padrao']): ?>
                                    <form method="post" action="<?= url('/administracao/empresa/unidade-padrao') ?>"
                                          onsubmit="return confirm('Definir &quot;<?= htmlspecialchars(addslashes($u['nome'])) ?>&quot; como unidade padrão?');">
                                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                        <button class="btn btn-sm btn-outline-secondary" title="Definir como padrão"><i class="bi bi-star"></i></button>
                                    </form>
                                <?php endif; ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary botao-editar-unidade"><i class="bi bi-pencil"></i></button>
                                <?php if (!$u['padrao']): ?>
                                    <form method="post" action="<?= url('/administracao/empresa/unidade-excluir') ?>"
                                          onsubmit="return confirm('Excluir a unidade &quot;<?= htmlspecialchars(addslashes($u['nome'])) ?>&quot;?');">
                                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <form method="post" action="<?= url('/administracao/empresa/unidade-editar') ?>" class="linha-edit-unidade d-none gap-2 mt-1">
                            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                            <input type="text" name="nome" class="form-control form-control-sm" value="<?= htmlspecialchars($u['nome']) ?>" required>
                            <input type="text" name="sigla" class="form-control form-control-sm font-monospace text-uppercase" style="max-width:110px" maxlength="6" value="<?= htmlspecialchars($u['sigla']) ?>" required>
                            <button class="btn btn-sm btn-primary text-nowrap">Salvar</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary botao-cancelar-edicao-unidade">Cancelar</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    document.querySelectorAll('.botao-editar-unidade').forEach(function (botao) {
        botao.addEventListener('click', function () {
            const li = botao.closest('li');
            li.querySelector('.linha-view-unidade').classList.add('d-none');
            const edicao = li.querySelector('.linha-edit-unidade');
            edicao.classList.remove('d-none');
            edicao.classList.add('d-flex');
            edicao.querySelector('input[name="nome"]').focus();
        });
    });

    document.querySelectorAll('.botao-cancelar-edicao-unidade').forEach(function (botao) {
        botao.addEventListener('click', function () {
            const li = botao.closest('li');
            const edicao = li.querySelector('.linha-edit-unidade');
            edicao.classList.add('d-none');
            edicao.classList.remove('d-flex');
            li.querySelector('.linha-view-unidade').classList.remove('d-none');
        });
    });
})();
</script>

<div class="card border-0 shadow-sm mt-3" style="max-width:560px">
    <div class="card-body">
        <label class="form-label">Logo do sistema</label>
        <div class="form-text mb-2">
            A imagem grande no topo do menu, acima de "Painel Administrativo" -- é a identidade visual do próprio RD Intranet
            (diferente da logo da empresa abaixo, que é do cliente). Opcional; sem enviar nada, fica a padrão.
            Ao escolher um arquivo, abre uma tela pra ajustar o enquadramento (arrastar/zoom) antes de salvar -- final sempre <strong>480&times;480px</strong>.
        </div>

        <div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2">
            <img src="<?= $logoSistemaConfigurada ? url('/administracao/empresa/logo-sistema') : url('/assets/img/logord.png') ?>" alt="Logo do sistema" style="max-height:60px;max-width:220px">
            <?php if ($logoSistemaConfigurada): ?>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="document.getElementById('formRemoverLogoSistema').submit()" title="Voltar pra padrão"><i class="bi bi-trash"></i></button>
            <?php endif; ?>
        </div>
        <?php if ($logoSistemaConfigurada): ?>
            <form method="post" action="<?= url('/administracao/empresa/logo-sistema/remover') ?>" id="formRemoverLogoSistema" class="d-none"></form>
        <?php endif; ?>

        <form method="post" action="<?= url('/administracao/empresa/logo-sistema/upload') ?>" enctype="multipart/form-data" class="d-flex gap-2" id="formUploadLogoSistema">
            <input type="file" name="logo" id="inputLogoSistema" accept=".jpg,.jpeg,.png" class="form-control form-control-sm" required>
            <button type="submit" class="btn btn-sm btn-outline-secondary text-nowrap"><i class="bi bi-upload"></i> <?= $logoSistemaConfigurada ? 'Trocar' : 'Enviar' ?></button>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm mt-3" style="max-width:560px">
    <div class="card-body">
        <label class="form-label">Logo da empresa</label>
        <div class="form-text mb-2">
            Aparece pequena, abaixo de "RD Intranet / Painel Administrativo" no menu lateral. Opcional.
            Ao escolher um arquivo, abre uma tela pra ajustar o enquadramento (arrastar/zoom) antes de salvar -- final sempre <strong>320&times;120px</strong>, PNG com fundo transparente funciona melhor.
        </div>

        <?php if ($logoConfigurada): ?>
            <div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2">
                <img src="<?= url('/administracao/empresa/logo') ?>" alt="Logo da empresa" style="max-height:36px;max-width:180px">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="document.getElementById('formRemoverLogoEmpresa').submit()"><i class="bi bi-trash"></i></button>
            </div>
            <form method="post" action="<?= url('/administracao/empresa/logo/remover') ?>" id="formRemoverLogoEmpresa" class="d-none"></form>
        <?php endif; ?>

        <form method="post" action="<?= url('/administracao/empresa/logo/upload') ?>" enctype="multipart/form-data" class="d-flex gap-2" id="formUploadLogoEmpresa">
            <input type="file" name="logo" id="inputLogoEmpresa" accept=".jpg,.jpeg,.png" class="form-control form-control-sm" required>
            <button type="submit" class="btn btn-sm btn-outline-secondary text-nowrap"><i class="bi bi-upload"></i> <?= $logoConfigurada ? 'Trocar' : 'Enviar' ?></button>
        </form>
    </div>
</div>

<!-- Ajustar logo (arrastar + zoom) antes de enviar -- reaproveitado pros dois uploads (logo do sistema 480x480, logo da empresa 320x120), só muda o tamanho-alvo. -->
<div class="modal fade" id="modalAjustarLogo" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title mb-0">Ajustar logo</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <div id="ajustarLogoCanvasWrap" style="display:inline-block; overflow:hidden; border:1px solid #dee2e6; border-radius:4px; cursor:move; touch-action:none; background-image:linear-gradient(45deg,#e9ecef 25%,transparent 25%),linear-gradient(-45deg,#e9ecef 25%,transparent 25%),linear-gradient(45deg,transparent 75%,#e9ecef 75%),linear-gradient(-45deg,transparent 75%,#e9ecef 75%); background-size:16px 16px; background-position:0 0,0 8px,8px -8px,-8px 0px;">
                    <canvas id="ajustarLogoCanvas"></canvas>
                </div>
                <div class="d-flex align-items-center gap-2 mt-3 mx-auto" style="max-width:360px">
                    <i class="bi bi-zoom-out text-muted"></i>
                    <input type="range" class="form-range" id="ajustarLogoZoom" min="0" max="100" step="0.1" value="30">
                    <i class="bi bi-zoom-in text-muted"></i>
                </div>
                <div class="form-text">Arraste a imagem pra posicionar; use o controle pra aproximar/afastar (dá pra deixar espaço em volta ou preencher tudo).</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-primary" id="ajustarLogoConfirmar"><i class="bi bi-check-lg"></i> Aplicar</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const canvas = document.getElementById('ajustarLogoCanvas');
    const wrap = document.getElementById('ajustarLogoCanvasWrap');
    const zoom = document.getElementById('ajustarLogoZoom');
    const modalEl = document.getElementById('modalAjustarLogo');
    const botaoConfirmar = document.getElementById('ajustarLogoConfirmar');
    if (!canvas || typeof HTMLCanvasElement === 'undefined') return;

    const ctx = canvas.getContext('2d');
    let estado = null; // { imagem, escalaConter, escalaCobrir, escala, offsetX, offsetY, larguraAlvo, alturaAlvo, aoConfirmar, inputOrigem }
    let arrastando = false;
    let inicioX = 0, inicioY = 0, offsetInicialX = 0, offsetInicialY = 0;

    function redesenhar() {
        if (!estado) return;
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(estado.imagem, estado.offsetX, estado.offsetY, estado.imagem.width * estado.escala, estado.imagem.height * estado.escala);
    }

    function centralizarEscala(novaEscala) {
        const centroX = estado.larguraAlvo / 2;
        const centroY = estado.alturaAlvo / 2;
        const relX = (centroX - estado.offsetX) / estado.escala;
        const relY = (centroY - estado.offsetY) / estado.escala;
        estado.escala = novaEscala;
        estado.offsetX = centroX - relX * novaEscala;
        estado.offsetY = centroY - relY * novaEscala;
    }

    function abrir(dataUrl, larguraAlvo, alturaAlvo, aoConfirmar, inputOrigem) {
        const imagem = new Image();
        imagem.onerror = function () {
            // Imagem não decodificável no navegador -- deixa passar o arquivo original sem ajuste (o input já está com ele), o servidor ainda revalida.
        };
        imagem.onload = function () {
            canvas.width = larguraAlvo;
            canvas.height = alturaAlvo;

            const escalaExibicao = 360 / larguraAlvo;
            canvas.style.width = Math.round(larguraAlvo * escalaExibicao) + 'px';
            canvas.style.height = Math.round(alturaAlvo * escalaExibicao) + 'px';

            const escalaConter = Math.min(larguraAlvo / imagem.width, alturaAlvo / imagem.height);
            const escalaCobrir = Math.max(larguraAlvo / imagem.width, alturaAlvo / imagem.height);

            estado = {
                imagem: imagem,
                escalaConter: escalaConter,
                escalaCobrir: escalaCobrir,
                escala: escalaCobrir,
                offsetX: (larguraAlvo - imagem.width * escalaCobrir) / 2,
                offsetY: (alturaAlvo - imagem.height * escalaCobrir) / 2,
                larguraAlvo: larguraAlvo,
                alturaAlvo: alturaAlvo,
                aoConfirmar: aoConfirmar,
                inputOrigem: inputOrigem,
                confirmado: false,
            };

            // Controle vai de "conter tudo" (0) a "bem de perto" (100), com "cobrir o quadro" perto do meio -- faixa não-linear pra dar mais precisão perto do ponto que interessa.
            zoom.value = 30;
            redesenhar();

            new bootstrap.Modal(modalEl).show();
        };
        imagem.src = dataUrl;
    }

    function escalaDoControle(valorControle) {
        // 0-30 vai de "conter" até "cobrir"; 30-100 vai de "cobrir" até 3x "cobrir" (zoom bem de perto).
        const t = valorControle / 100;
        if (t <= 0.3) {
            return estado.escalaConter + (estado.escalaCobrir - estado.escalaConter) * (t / 0.3);
        }
        return estado.escalaCobrir + (estado.escalaCobrir * 2) * ((t - 0.3) / 0.7);
    }

    zoom.addEventListener('input', function () {
        if (!estado) return;
        centralizarEscala(escalaDoControle(parseFloat(zoom.value)));
        redesenhar();
    });

    function posicaoPonteiro(ev) {
        return { x: ev.clientX, y: ev.clientY };
    }

    wrap.addEventListener('pointerdown', function (ev) {
        if (!estado) return;
        arrastando = true;
        const p = posicaoPonteiro(ev);
        inicioX = p.x;
        inicioY = p.y;
        offsetInicialX = estado.offsetX;
        offsetInicialY = estado.offsetY;
        wrap.setPointerCapture(ev.pointerId);
    });

    wrap.addEventListener('pointermove', function (ev) {
        if (!arrastando || !estado) return;
        const p = posicaoPonteiro(ev);
        const fator = canvas.width / canvas.clientWidth;
        estado.offsetX = offsetInicialX + (p.x - inicioX) * fator;
        estado.offsetY = offsetInicialY + (p.y - inicioY) * fator;
        redesenhar();
    });

    ['pointerup', 'pointercancel', 'pointerleave'].forEach(function (evento) {
        wrap.addEventListener(evento, function () { arrastando = false; });
    });

    botaoConfirmar.addEventListener('click', function () {
        if (!estado) return;
        canvas.toBlob(function (blob) {
            if (!blob || !estado) return;
            estado.confirmado = true;
            estado.aoConfirmar(blob);
            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        }, 'image/png');
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
        // Fechou sem clicar "Aplicar" (Cancelar, X, Esc, clique fora) --
        // limpa o input pra não deixar o arquivo ORIGINAL (sem ajuste)
        // pronto pra ser enviado sozinho se a pessoa clicar "Enviar" depois.
        if (estado && !estado.confirmado && estado.inputOrigem) {
            estado.inputOrigem.value = '';
        }
        estado = null;
    });

    window.kbConfigurarAjusteLogo = function (idInput, larguraAlvo, alturaAlvo) {
        const input = document.getElementById(idInput);
        if (!input) return;

        input.addEventListener('change', function () {
            const arquivo = input.files[0];
            if (!arquivo) return;

            const leitor = new FileReader();
            leitor.onload = function (eLeitor) {
                abrir(eLeitor.target.result, larguraAlvo, alturaAlvo, function (blob) {
                    const ajustada = new File([blob], 'logo.png', { type: 'image/png' });
                    const transferencia = new DataTransfer();
                    transferencia.items.add(ajustada);
                    input.files = transferencia.files;
                }, input);
            };
            leitor.readAsDataURL(arquivo);
        });
    };
})();

kbConfigurarAjusteLogo('inputLogoSistema', 480, 480);
kbConfigurarAjusteLogo('inputLogoEmpresa', 320, 120);
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Dados da Empresa';

require __DIR__ . '/../layouts/main.php';
