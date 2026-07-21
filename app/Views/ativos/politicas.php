<?php
ob_start();

use App\Components\Alert;
use App\Services\PoliticaService;
?>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1"><i class="bi bi-shield-check me-1"></i> Regras de Segurança</h4>
        <small class="text-muted"><a href="<?= url('/ativos') ?>"><i class="bi bi-arrow-left"></i> Dashboard de Ativos</a></small>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <strong><i class="bi bi-question-circle"></i> O que é isso e como usar</strong>
        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#ajudaPoliticas">
            Ver explicação
        </button>
    </div>
    <div class="collapse" id="ajudaPoliticas">
        <div class="card-body">
            <p class="small text-muted">
                Essas regras são aplicadas <strong>pelo nosso próprio agente Windows</strong> -- diferente do módulo
                Microsoft Entra, não depende de nenhuma conta/licença da Microsoft. Funciona em qualquer computador
                com o agente instalado. Você pode marcar as regras direto na tela de cada máquina (aba "Regras de
                Segurança" na página do ativo) ou aplicar/remover uma regra em várias máquinas de uma vez por aqui.
            </p>
            <strong class="small">O que cada regra faz</strong>
            <ul class="small text-muted">
                <li><strong>Bloquear portas USB</strong> -- impede o uso de pen drives e HDs externos (armazenamento).</li>
                <li><strong>Bloquear Painel de Controle e Configurações / Bloquear CMD</strong> -- essas duas
                    dependem também de uma chave por usuário (não só por máquina); valem garantidamente pro usuário
                    que estiver logado no momento em que a regra for aplicada -- outra conta que logar depois pode
                    precisar que a regra seja reaplicada (a máquina toda também recebe a chave, mas alguns desses
                    bloqueios só o Windows aplica de fato olhando a conta logada).</li>
                <li><strong>Bloquear CMD / Bloquear PowerShell</strong> -- impede o usuário de abrir o Prompt de
                    Comando/PowerShell pelo Menu Iniciar ou "Executar". <strong>Não afeta o nosso agente</strong>:
                    esse bloqueio age no nível do Explorer (o que aparece pro usuário abrir), e o agente nunca passa
                    pelo Explorer pra rodar seus próprios comandos -- por isso continua funcionando normalmente
                    mesmo com essas regras marcadas.</li>
                <li><strong>Bloquear navegadores</strong> -- impede abrir Chrome, Edge, Firefox, Opera e Brave.</li>
                <li><strong>Firewall sempre ativo / Senha forte</strong> -- essas duas só <em>reforçam</em>: marcar
                    aplica, desmarcar só para de forçar (nunca desliga o firewall nem afrouxa a senha de propósito).</li>
                <li><strong>Papel de parede corporativo</strong> -- envie a imagem abaixo primeiro; a regra entrega
                    o arquivo na máquina e define como papel de parede fixo (usuário não troca).</li>
                <li><strong>Impedir alterar IP/rede</strong> -- bloqueia a tela de propriedades da conexão de rede.
                    Como essa é uma política por usuário (não por máquina), só vale pro usuário que estiver logado
                    no momento em que a regra for aplicada -- outra conta que logar depois pode precisar que a
                    regra seja reaplicada.</li>
            </ul>
            <div class="alert alert-warning small mb-0">
                <i class="bi bi-exclamation-triangle"></i> Isso é uma política do Windows, não uma trava
                indestrutível -- um usuário técnico pode conseguir contornar algumas dessas regras. Serve bem pra
                evitar que o usuário comum mexa onde não deve, não substitui um antivírus/EDR de verdade.
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white"><strong>Papel de parede corporativo</strong></div>
    <div class="card-body">
        <p class="text-muted small">Uma imagem só, entregue igual ao Company Portal/pacote de provisionamento -- envie aqui antes de aplicar a regra "Papel de parede corporativo" numa máquina.</p>
        <?php if ($wallpaperInfo): ?>
            <div class="d-flex justify-content-between align-items-center border rounded p-2" style="max-width:480px">
                <span class="small">
                    <i class="bi bi-file-earmark-image"></i> <?= htmlspecialchars($wallpaperInfo['nome']) ?>
                    <span class="text-muted">(enviado em <?= htmlspecialchars($wallpaperInfo['enviado_em']) ?>)</span>
                </span>
                <button type="button" class="btn btn-sm btn-outline-danger" id="botaoRemoverWallpaper"><i class="bi bi-trash"></i></button>
            </div>
            <form method="post" action="<?= url('/ativos/politicas/wallpaper/remover') ?>" id="formRemoverWallpaper" class="d-none"></form>
        <?php else: ?>
            <form method="post" action="<?= url('/ativos/politicas/wallpaper/upload') ?>" enctype="multipart/form-data" class="d-flex gap-2" style="max-width:480px">
                <input type="file" name="arquivo" accept=".jpg,.jpeg,.png" class="form-control form-control-sm" required>
                <button type="submit" class="btn btn-sm btn-outline-secondary text-nowrap"><i class="bi bi-upload"></i> Enviar</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white"><strong>Recursos de rede por setor (impressora / unidade de rede)</strong></div>
    <div class="card-body">
        <p class="text-muted small">
            Cadastre aqui qual impressora e qual unidade de rede cada setor usa. Na tela de cada máquina (que já
            tem um setor definido em Ativos &gt; Cadastros), o botão "Aplicar mapeamentos do setor" mapeia tudo isso
            de uma vez, sem precisar saber o caminho de rede na hora.
        </p>
        <form method="post" action="<?= url('/ativos/politicas/recursos-setor/novo') ?>" class="row g-2 align-items-end mb-3">
            <div class="col-md-2">
                <label class="form-label small mb-0">Setor</label>
                <select name="setor_id" class="form-select form-select-sm" required>
                    <?php foreach ($setores as $s): ?>
                        <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-0">Tipo</label>
                <select name="tipo" class="form-select form-select-sm" id="campoTipoRecurso">
                    <?php foreach ($tiposRecurso as $valor => $label): ?>
                        <option value="<?= htmlspecialchars($valor) ?>"><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-0">Nome</label>
                <input type="text" name="nome_exibicao" class="form-control form-control-sm" placeholder="Ex: Impressora RH" required>
            </div>
            <div class="col-md-1" id="colunaLetraUnidade">
                <label class="form-label small mb-0">Letra</label>
                <input type="text" name="letra_unidade" maxlength="1" class="form-control form-control-sm" placeholder="Z">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-0">Caminho de rede (UNC)</label>
                <input type="text" name="caminho_unc" class="form-control form-control-sm" placeholder="\\servidor\compartilhamento" required>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-plus-lg"></i> Adicionar</button>
            </div>
        </form>

        <?php if (empty($recursosSetor)): ?>
            <p class="text-muted small mb-0">Nenhum recurso cadastrado ainda.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Setor</th><th>Tipo</th><th>Nome</th><th>Letra/Caminho</th><th class="text-end">Ações</th></tr></thead>
                    <tbody>
                        <?php foreach ($recursosSetor as $r): ?>
                            <tr>
                                <td class="small"><?= htmlspecialchars($r['setor_nome']) ?></td>
                                <td class="small"><?= htmlspecialchars($tiposRecurso[$r['tipo']] ?? $r['tipo']) ?></td>
                                <td class="small"><?= htmlspecialchars($r['nome_exibicao']) ?></td>
                                <td class="small font-monospace"><?= $r['tipo'] === 'unidade_rede' ? htmlspecialchars($r['letra_unidade']) . ': ' : '' ?><?= htmlspecialchars($r['caminho_unc']) ?></td>
                                <td class="text-end">
                                    <form method="post" action="<?= url('/ativos/politicas/recursos-setor/excluir') ?>" onsubmit="return confirm('Remover este recurso?');">
                                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white"><strong>Aplicar/remover regras em lote</strong></div>
    <div class="card-body">
        <p class="text-muted small mb-2">A seleção de máquinas abaixo também vale pra instalação de software, no card seguinte.</p>
        <strong class="small">Máquinas</strong>
        <div class="border rounded p-2 mb-3 mt-1" style="max-height:220px; overflow-y:auto; max-width:480px">
            <?php if (empty($maquinas)): ?>
                <p class="text-muted small mb-0">Nenhum computador com o agente instalado.</p>
            <?php else: ?>
                <?php foreach ($maquinas as $m): ?>
                    <div class="form-check">
                        <input class="form-check-input campo-ativo-lote" type="checkbox" value="<?= (int)$m['id'] ?>" id="lote-ativo-<?= (int)$m['id'] ?>">
                        <label class="form-check-label small" for="lote-ativo-<?= (int)$m['id'] ?>">
                            <?= htmlspecialchars($m['nome']) ?>
                            <span class="text-muted font-monospace">(<?= htmlspecialchars($m['codigo_patrimonio']) ?>)</span>
                        </label>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr><th>Regra</th><th>Categoria</th><th class="text-end">Ações nas máquinas marcadas acima</th></tr>
                </thead>
                <tbody>
                    <?php foreach (PoliticaService::CATALOGO as $regraId => $info): ?>
                        <tr>
                            <td class="small"><?= htmlspecialchars($info['label']) ?></td>
                            <td class="small text-muted"><?= htmlspecialchars($info['categoria']) ?></td>
                            <td class="text-end">
                                <form method="post" action="<?= url('/ativos/politicas/aplicar-em-lote') ?>" class="d-inline form-aplicar-regra">
                                    <input type="hidden" name="regra_id" value="<?= htmlspecialchars($regraId) ?>">
                                    <input type="hidden" name="acao" value="aplicar">
                                    <button type="submit" class="btn btn-sm btn-outline-primary">Aplicar</button>
                                </form>
                                <?php if ($info['reversivel']): ?>
                                    <form method="post" action="<?= url('/ativos/politicas/aplicar-em-lote') ?>" class="d-inline form-aplicar-regra">
                                        <input type="hidden" name="regra_id" value="<?= htmlspecialchars($regraId) ?>">
                                        <input type="hidden" name="acao" value="remover">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">Remover</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white"><strong>Instalação de software</strong></div>
    <div class="card-body">
        <p class="text-muted small">
            Envie um instalador (.exe ou .msi) e os argumentos de instalação silenciosa (ex: <code>/S</code> pra
            muitos instaladores NSIS/Inno Setup, ou deixe em branco pra usar o padrão -- <code>/quiet /norestart</code>
            pra .msi, <code>/S</code> pra .exe). Depois selecione as máquinas na tabela acima e instale.
        </p>
        <form method="post" action="<?= url('/ativos/politicas/pacotes/upload') ?>" enctype="multipart/form-data" class="row g-2 align-items-end mb-3">
            <div class="col-md-3">
                <label class="form-label small mb-0">Nome</label>
                <input type="text" name="nome" class="form-control form-control-sm" placeholder="Ex: Adobe Reader" required>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-0">Instalador (.exe/.msi)</label>
                <input type="file" name="arquivo" accept=".exe,.msi" class="form-control form-control-sm" required>
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-0">Argumentos silenciosos (opcional)</label>
                <input type="text" name="argumentos" class="form-control form-control-sm" placeholder="/S">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-upload"></i> Enviar</button>
            </div>
        </form>

        <?php if (empty($pacotes)): ?>
            <p class="text-muted small mb-0">Nenhum pacote de software enviado ainda.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Nome</th><th>Arquivo</th><th>Argumentos</th><th class="text-end">Ações nas máquinas marcadas acima</th></tr></thead>
                    <tbody>
                        <?php foreach ($pacotes as $p): ?>
                            <tr>
                                <td class="small"><?= htmlspecialchars($p['nome']) ?></td>
                                <td class="small font-monospace"><?= htmlspecialchars($p['arquivo_nome_original']) ?></td>
                                <td class="small font-monospace"><?= htmlspecialchars($p['argumentos_silenciosos'] ?? '') ?: '<span class="text-muted">(padrão)</span>' ?></td>
                                <td class="text-end">
                                    <form method="post" action="<?= url('/ativos/politicas/pacotes/instalar-em-lote') ?>" class="d-inline form-instalar-pacote">
                                        <input type="hidden" name="pacote_id" value="<?= (int)$p['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-primary">Instalar</button>
                                    </form>
                                    <form method="post" action="<?= url('/ativos/politicas/pacotes/excluir') ?>" class="d-inline" onsubmit="return confirm('Remover o pacote &quot;<?= htmlspecialchars(addslashes($p['nome'])) ?>&quot;?');">
                                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white"><strong>Script de login personalizado</strong></div>
    <div class="card-body">
        <p class="text-muted small">
            Um script PowerShell (.ps1) que roda sozinho toda vez que qualquer usuário fizer login na máquina (ex:
            mapear algo específico, ajustar uma configuração). Registrado via Agendador de Tarefas do Windows --
            envie aqui, depois selecione as máquinas na tabela acima e instale.
        </p>
        <?php if ($loginScriptInfo): ?>
            <div class="d-flex justify-content-between align-items-center border rounded p-2 mb-3" style="max-width:480px">
                <span class="small">
                    <i class="bi bi-file-earmark-code"></i> <?= htmlspecialchars($loginScriptInfo['nome']) ?>
                    <span class="text-muted">(enviado em <?= htmlspecialchars($loginScriptInfo['enviado_em']) ?>)</span>
                </span>
                <button type="button" class="btn btn-sm btn-outline-danger" id="botaoRemoverLoginScript"><i class="bi bi-trash"></i></button>
            </div>
            <form method="post" action="<?= url('/ativos/politicas/login-script/remover') ?>" id="formRemoverLoginScript" class="d-none"></form>

            <form method="post" action="<?= url('/ativos/politicas/login-script/instalar-em-lote') ?>" class="d-inline form-instalar-pacote" data-confirmacao="Instalar o script de login em">
                <button type="submit" class="btn btn-sm btn-outline-primary">Instalar nas selecionadas</button>
            </form>
            <form method="post" action="<?= url('/ativos/politicas/login-script/remover-em-lote') ?>" class="d-inline form-instalar-pacote" data-confirmacao="Remover o script de login de">
                <button type="submit" class="btn btn-sm btn-outline-secondary">Remover das selecionadas</button>
            </form>
        <?php else: ?>
            <form method="post" action="<?= url('/ativos/politicas/login-script/upload') ?>" enctype="multipart/form-data" class="d-flex gap-2" style="max-width:480px">
                <input type="file" name="arquivo" accept=".ps1" class="form-control form-control-sm" required>
                <button type="submit" class="btn btn-sm btn-outline-secondary text-nowrap"><i class="bi bi-upload"></i> Enviar</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    const botaoRemoverWallpaper = document.getElementById('botaoRemoverWallpaper');
    if (botaoRemoverWallpaper) {
        botaoRemoverWallpaper.addEventListener('click', function () {
            if (confirm('Remover a imagem do papel de parede corporativo?')) {
                document.getElementById('formRemoverWallpaper').submit();
            }
        });
    }

    const botaoRemoverLoginScript = document.getElementById('botaoRemoverLoginScript');
    if (botaoRemoverLoginScript) {
        botaoRemoverLoginScript.addEventListener('click', function () {
            if (confirm('Remover o script de login do servidor? As tarefas já registradas nas máquinas continuam até serem removidas individualmente.')) {
                document.getElementById('formRemoverLoginScript').submit();
            }
        });
    }

    const campoTipoRecurso = document.getElementById('campoTipoRecurso');
    const colunaLetraUnidade = document.getElementById('colunaLetraUnidade');
    function atualizarColunaLetra() {
        colunaLetraUnidade.classList.toggle('d-none', campoTipoRecurso.value !== 'unidade_rede');
    }
    if (campoTipoRecurso) {
        campoTipoRecurso.addEventListener('change', atualizarColunaLetra);
        atualizarColunaLetra();
    }

    document.querySelectorAll('.form-aplicar-regra').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            const marcadas = Array.from(document.querySelectorAll('.campo-ativo-lote')).filter(function (c) { return c.checked; });

            if (!marcadas.length) {
                e.preventDefault();
                alert('Selecione ao menos uma máquina.');
                return;
            }

            const acao = form.querySelector('input[name="acao"]').value;
            if (!confirm((acao === 'aplicar' ? 'Aplicar' : 'Remover') + ' essa regra em ' + marcadas.length + ' máquina(s)?')) {
                e.preventDefault();
                return;
            }

            form.querySelectorAll('.ativo-injetado').forEach(function (i) { i.remove(); });
            marcadas.forEach(function (c) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ativos[]';
                input.value = c.value;
                input.className = 'ativo-injetado';
                form.appendChild(input);
            });
        });
    });

    document.querySelectorAll('.form-instalar-pacote').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            const marcadas = Array.from(document.querySelectorAll('.campo-ativo-lote')).filter(function (c) { return c.checked; });

            if (!marcadas.length) {
                e.preventDefault();
                alert('Selecione ao menos uma máquina (tabela do card "Aplicar/remover regras em lote").');
                return;
            }

            const mensagemConfirmacao = form.dataset.confirmacao || 'Instalar esse pacote em';
            if (!confirm(mensagemConfirmacao + ' ' + marcadas.length + ' máquina(s)?')) {
                e.preventDefault();
                return;
            }

            form.querySelectorAll('.ativo-injetado').forEach(function (i) { i.remove(); });
            marcadas.forEach(function (c) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ativos[]';
                input.value = c.value;
                input.className = 'ativo-injetado';
                form.appendChild(input);
            });
        });
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Regras de Segurança';

require __DIR__ . '/../layouts/main.php';
