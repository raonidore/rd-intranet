<?php
ob_start();

use App\Components\Alert;
use App\Services\EntraService;

$noEntra = array_filter($computadores, fn($c) => $c['entra']['entra']);

/** Lista de grupos com caixa de seleção + botão "Membros" (janela), usada nos passos 1 e 2. */
$listaGrupos = function (string $campo, string $classe) use ($grupos): string {
    ob_start();
    if (empty($grupos)): ?>
        <p class="text-muted small mb-2">Nenhum grupo ainda -- use <strong>Novo grupo</strong>.</p>
    <?php endif;
    foreach ($grupos as $g): ?>
        <div class="d-flex align-items-start gap-2 py-1 border-bottom">
            <div class="form-check flex-grow-1 mb-0">
                <input class="form-check-input <?= $classe ?>" type="checkbox" name="<?= $campo ?>[]" value="<?= htmlspecialchars($g['id']) ?>" id="<?= $campo ?>-<?= htmlspecialchars($g['id']) ?>">
                <label class="form-check-label small" for="<?= $campo ?>-<?= htmlspecialchars($g['id']) ?>">
                    <strong><?= htmlspecialchars($g['displayName']) ?></strong>
                    <span class="text-muted">(<?= count($g['membros']) ?>)</span>
                    <?php if ($g['membros']): ?>
                        <br><span class="text-muted"><?= htmlspecialchars(implode(', ', array_map(fn($m) => $m['displayName'] ?? $m['userPrincipalName'] ?? '', $g['membros']))) ?></span>
                    <?php endif; ?>
                </label>
            </div>
            <button type="button" class="btn btn-sm btn-link p-0 text-nowrap" data-bs-toggle="modal" data-bs-target="#modalMembros-<?= htmlspecialchars($g['id']) ?>">
                <i class="bi bi-people"></i> Membros
            </button>
        </div>
    <?php endforeach;
    return ob_get_clean();
};

$listaAvulsos = function (string $campo, string $classe) use ($usuarios): string {
    ob_start(); ?>
    <details class="mt-2">
        <summary class="text-muted small">Pessoas avulsas (exceção para uma máquina específica)</summary>
        <?php foreach ($usuarios as $u): ?>
            <div class="form-check">
                <input class="form-check-input <?= $classe ?>" type="checkbox" name="<?= $campo ?>[]" value="<?= htmlspecialchars($u['id']) ?>" id="<?= $campo ?>-<?= htmlspecialchars($u['id']) ?>">
                <label class="form-check-label small" for="<?= $campo ?>-<?= htmlspecialchars($u['id']) ?>">
                    <?= htmlspecialchars($u['displayName'] ?? '') ?> <span class="text-muted font-monospace">(<?= htmlspecialchars($u['userPrincipalName'] ?? '') ?>)</span>
                    <?php if (!($u['accountEnabled'] ?? true)): ?><span class="badge text-bg-secondary">desativado</span><?php endif; ?>
                </label>
            </div>
        <?php endforeach; ?>
    </details>
    <?php return ob_get_clean();
};
?>

<style>
.passo-num { width:30px; height:30px; border-radius:8px; background:var(--bs-primary); color:#fff; display:inline-flex; align-items:center; justify-content:center; font-weight:700; margin-right:.5rem; }
.passo-ajuda { font-size:.82rem; }
.tabela-maquinas td { vertical-align: middle; }
.pop-modal .modal-content { background:#0d1117; color:#c9d1d9; border:1px solid #30363d; border-radius:14px; }
.pop-topbar { display:flex; justify-content:space-between; align-items:center; padding:14px 20px; background:#161b22; border-bottom:1px solid #30363d; border-radius:14px 14px 0 0; }
.pop-topbar .pop-breadcrumb { font-weight:600; color:#58a6ff; display:flex; align-items:center; gap:8px; font-size:1rem; }
.pop-body { padding:1.3rem 1.6rem; max-height:72vh; overflow-y:auto; }
.pop-intro { color:#8b949e; font-size:.88rem; margin-bottom:1.2rem; padding-bottom:1rem; border-bottom:1px dashed #30363d; }
.pop-step { display:flex; gap:1rem; padding:.85rem 0; border-bottom:1px solid #21262d; }
.pop-step:last-child { border-bottom:0; padding-bottom:0; }
.pop-step-num { flex:0 0 auto; width:34px; height:34px; border-radius:9px; background:#132030; color:#58a6ff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:.95rem; border:1px solid #1f3b57; }
.pop-step-title { font-weight:600; color:#e6edf3; font-size:.92rem; display:flex; align-items:center; gap:6px; }
.pop-step-text { color:#8b949e; font-size:.83rem; margin-top:3px; line-height:1.55; }
.pop-step-text strong { color:#c9d1d9; }
.pop-step-text code { background:#1c2733; color:#7ee787; padding:1px 5px; border-radius:4px; font-size:.78rem; }
.pop-callout { display:flex; gap:.6rem; padding:.7rem .9rem; border-radius:8px; font-size:.82rem; margin-top:.6rem; background:rgba(59,47,0,.2); border:1px solid rgba(125,90,0,.35); color:#e3b341; }
</style>

<?= Alert::flash() ?>

<div class="mb-3 d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h4 class="mb-1"><i class="bi bi-shield-lock me-1"></i> Microsoft Entra - Acesso às Máquinas</h4>
        <small class="text-muted">Quem entra e quem administra os computadores de cada unidade -- tudo por aqui, sem abrir o Entra.</small>
    </div>
    <button type="button" class="btn btn-outline-dark text-nowrap" data-bs-toggle="modal" data-bs-target="#modalPopAcessoMaquinas">
        <i class="bi bi-broadcast"></i> POP - Acesso às Máquinas
    </button>
</div>

<!-- POP -- Procedimento Operacional Padrão -->
<div class="modal fade pop-modal" id="modalPopAcessoMaquinas" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="pop-topbar">
                <span class="pop-breadcrumb"><i class="bi bi-broadcast"></i> POP -- Acesso às Máquinas</span>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="pop-body">
                <p class="pop-intro">Como deixar os computadores de uma unidade acessíveis só por quem trabalha nela, com a TI administrando -- e com registro de tudo para a auditoria.</p>

                <div class="pop-step"><div class="pop-step-num">0</div><div>
                    <div class="pop-step-title"><i class="bi bi-list-check"></i> Antes de começar (uma vez por máquina)</div>
                    <div class="pop-step-text">Agente da RD instalado (1.0.77+). A máquina <strong>ingressada no Entra</strong> e aparecendo no <strong>Intune</strong>
                        -- a coluna "Situação" mostra. O passo a passo do ingresso está em Administração &gt; Integrações &gt; Microsoft Entra.</div>
                </div></div>

                <div class="pop-step"><div class="pop-step-num">1</div><div>
                    <div class="pop-step-title"><i class="bi bi-pc-display"></i> Marque as máquinas</div>
                    <div class="pop-step-text">Na tabela "Máquinas". As marcadas valem para os três passos abaixo. <strong>"Marcar as que estão no Entra"</strong> seleciona só as prontas.</div>
                </div></div>

                <div class="pop-step"><div class="pop-step-num">2</div><div>
                    <div class="pop-step-title"><i class="bi bi-door-open"></i> Quem pode entrar</div>
                    <div class="pop-step-text">Marque o grupo da unidade (ex.: <code>NTO - Acesso às Máquinas</code>) e clique em <strong>Salvar nas máquinas marcadas</strong>.
                        Para criar o grupo ou mudar quem está nele, use <strong>Novo grupo</strong> / <strong>Membros</strong> ali mesmo.
                        Quem estiver fora do grupo recebe no Windows: <em>"O método de entrada que você está tentando usar não é permitido"</em>.</div>
                    <div class="pop-callout"><i class="bi bi-shield-fill-check"></i><div>Administradores da máquina sempre entram. Máquina fora do Entra é recusada pelo portal -- lá a restrição trancaria todo mundo.</div></div>
                </div></div>

                <div class="pop-step"><div class="pop-step-num">3</div><div>
                    <div class="pop-step-title"><i class="bi bi-person-gear"></i> Quem administra</div>
                    <div class="pop-step-text">Marque o grupo da TI (ex.: <code>TI - Administradores NTO</code>) e clique em <strong>Salvar nas máquinas marcadas</strong> (pode ser junto com o passo 2).
                        O portal só tira quem ele mesmo colocou -- o Administrador interno (senha no LAPS) e os administradores globais ficam sempre.</div>
                </div></div>

                <div class="pop-step"><div class="pop-step-num">4</div><div>
                    <div class="pop-step-title"><i class="bi bi-person-x"></i> Contas locais antigas</div>
                    <div class="pop-step-text">Depois que o LAPS estiver ativo, desative as contas locais antigas (ex.: <code>Enzilab</code>). Nunca exclui -- dá para reativar.
                        Confira o "Logado agora" antes.</div>
                </div></div>

                <div class="pop-step"><div class="pop-step-num">5</div><div>
                    <div class="pop-step-title"><i class="bi bi-arrow-repeat"></i> Depois, no dia a dia</div>
                    <div class="pop-step-text">Entrou ou saiu alguém? Só mude os <strong>Membros</strong> do grupo -- o portal atualiza as máquinas sozinho
                        (na hora por aqui; em até 15 min se mudarem direto no Entra). Quem entrou em qual máquina e quando, inclusive os acessos negados,
                        fica em Segurança &gt; Trilha de acesso, com exportação para a auditoria.</div>
                </div></div>
            </div>
        </div>
    </div>
</div>

<?php if (!$configurado): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-plug display-6 text-muted d-block mb-3"></i>
            <p class="text-muted mb-3">Módulo ainda não configurado.</p>
            <a href="<?= url('/administracao/integracoes/entra') ?>" class="btn btn-primary"><i class="bi bi-gear"></i> Configurar</a>
        </div>
    </div>
<?php else: ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2">
            <details>
                <summary class="small fw-semibold"><i class="bi bi-info-circle text-primary"></i> Como funciona (leia antes da primeira vez)</summary>
                <ul class="small text-muted mt-2 mb-1">
                    <li><strong>Pré-requisito:</strong> a máquina precisa estar <strong>no Entra</strong> (e de preferência no Intune). Veja a coluna "Situação". Fora do Entra o portal não deixa aplicar a restrição.</li>
                    <li><strong>Grupos:</strong> você escolhe o grupo; o portal libera <strong>cada pessoa</strong> dele na máquina (o Windows não reconhece grupo do Entra no login) e <strong>reaplica sozinho</strong> quando alguém entra ou sai do grupo.</li>
                    <li><strong>Rede de segurança:</strong> administradores da máquina sempre entram -- nunca fica todo mundo de fora. Tudo é desfazível pelos botões "Remover"/"Retirar".</li>
                    <li><strong>Resultado:</strong> cada ação vira um comando para o agente da máquina (segundos); o resultado fica no histórico do ativo. A coluna da tabela mostra o que está aplicado.</li>
                    <li><strong>Auditoria:</strong> logins, logoffs e acessos negados ficam em <a href="<?= url('/seguranca/trilha-acesso') ?>">Segurança &gt; Trilha de acesso</a>. Guia completo do tenant em <a href="<?= url('/administracao/integracoes/entra') ?>">Integrações &gt; Microsoft Entra</a>.</li>
                </ul>
            </details>
        </div>
    </div>

    <form method="post" id="formAcessoMaquinas">

        <!-- Máquinas (valem pros 3 passos) -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div><span class="passo-num">1</span><strong>Máquinas</strong> <span class="text-muted small">-- as marcadas valem para os passos 2, 3 e 4</span></div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="botaoMarcarEntra"><i class="bi bi-check2-square"></i> Marcar as que estão no Entra (<?= count($noEntra) ?>)</button>
            </div>
            <div class="card-body p-0">
                <?php if (empty($computadores)): ?>
                    <p class="text-muted small p-3 mb-0">Nenhum computador com o agente da RD instalado.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0 small tabela-maquinas">
                            <thead><tr><th style="width:30px"></th><th>Máquina</th><th>Situação</th><th>Quem pode entrar</th><th>Administradores (portal)</th></tr></thead>
                            <tbody>
                                <?php foreach ($computadores as $c): ?>
                                    <?php $id = (int)$c['id']; $ap = $aplicados[$id] ?? null; $adm = $admins[$id] ?? null; ?>
                                    <tr>
                                        <td><input class="form-check-input campo-ativo-restricao" type="checkbox" name="ativos[]" value="<?= $id ?>" id="ativo-<?= $id ?>"
                                                   data-entra="<?= $c['entra']['entra'] ? '1' : '0' ?>" data-nome="<?= htmlspecialchars($c['nome']) ?>"></td>
                                        <td>
                                            <label for="ativo-<?= $id ?>" class="mb-0"><strong><?= htmlspecialchars($c['nome']) ?></strong>
                                                <span class="text-muted font-monospace">(<?= htmlspecialchars($c['codigo_patrimonio']) ?>)</span></label>
                                            <?php if ($c['usuario_logado_atual']): ?><div class="text-muted">Logado agora: <span class="font-monospace"><?= htmlspecialchars($c['usuario_logado_atual']) ?></span></div><?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($c['entra']['entra']): ?>
                                                <span class="badge text-bg-success">No Entra</span>
                                                <?= $c['entra']['intune'] ? '<span class="badge text-bg-success">Intune</span>' : '<span class="badge text-bg-warning" title="Fora do Intune: Hello e LAPS não chegam. Use Forçar inscrição em Dispositivos (Intune).">Fora do Intune</span>' ?>
                                            <?php else: ?>
                                                <span class="badge text-bg-secondary" title="Ainda usa conta local. Ingresse no Entra antes de aplicar a restrição.">Fora do Entra</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($ap && $ap['autorizados'] !== null): ?>
                                                <span class="text-success"><i class="bi bi-shield-check"></i> <?= htmlspecialchars(implode(', ', array_column($ap['autorizados'], 'nome'))) ?></span>
                                                <span class="text-muted">(<?= date('d/m H:i', strtotime($ap['aplicado_em'])) ?>)</span>
                                            <?php else: ?>
                                                <span class="text-warning"><i class="bi bi-unlock"></i> Qualquer um</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($adm): ?>
                                                <span class="text-primary"><i class="bi bi-person-gear"></i> <?= htmlspecialchars(implode(', ', array_column($adm['autorizados'], 'nome'))) ?></span>
                                                <span class="text-muted">(<?= count($adm['sids']) ?>)</span>
                                            <?php else: ?>
                                                <span class="text-muted">--</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="row g-3">
            <!-- Passo 2 -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <div><span class="passo-num">2</span><strong>Quem pode entrar</strong></div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalNovoGrupo"><i class="bi bi-plus-lg"></i> Novo grupo</button>
                    </div>
                    <div class="card-body">
                        <p class="text-muted passo-ajuda mb-2">Marque o grupo da unidade. Só as pessoas dele (e os administradores) entram nas máquinas marcadas. Máquinas fora do Entra são ignoradas.</p>
                        <?= $listaGrupos('grupos', 'campo-autorizado') ?>
                        <?= $listaAvulsos('usuarios', 'campo-autorizado') ?>
                        <div class="mt-3 d-flex flex-wrap gap-2">
                            <button type="submit" formaction="<?= url('/entra/acesso-maquinas/remover') ?>" formnovalidate class="btn btn-outline-secondary btn-sm" id="botaoRemoverRestricao">
                                <i class="bi bi-shield-slash"></i> Remover restrição
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Passo 3 -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <div><span class="passo-num">3</span><strong>Quem administra</strong></div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalNovoGrupo"><i class="bi bi-plus-lg"></i> Novo grupo</button>
                    </div>
                    <div class="card-body">
                        <p class="text-muted passo-ajuda mb-2">Marque o grupo da TI. As pessoas dele viram administradoras das máquinas marcadas (instalar programas, mudar configurações).
                            O Administrador interno (LAPS) e os administradores globais do Entra ficam sempre.</p>
                        <?= $listaGrupos('admin_grupos', 'campo-admin') ?>
                        <?= $listaAvulsos('admin_usuarios', 'campo-admin') ?>
                        <div class="mt-3 d-flex flex-wrap gap-2">
                            <button type="submit" formaction="<?= url('/entra/acesso-maquinas/admins/remover') ?>" formnovalidate class="btn btn-outline-secondary btn-sm" id="botaoRemoverAdmins">
                                <i class="bi bi-person-dash"></i> Retirar os definidos pelo portal
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-3 border-start border-primary border-4">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="small text-muted">Aplica de uma vez, nas máquinas marcadas no passo 1, o que estiver marcado no passo 2 (quem entra) e no passo 3 (quem administra).
                    Pode marcar só um dos dois. Máquinas fora do Entra ficam de fora da restrição.</div>
                <button type="submit" formaction="<?= url('/entra/acesso-maquinas/salvar') ?>" formnovalidate class="btn btn-primary" id="botaoSalvarAcesso">
                    <i class="bi bi-check2-circle"></i> Salvar nas máquinas marcadas
                </button>
            </div>
        </div>

        <!-- Passo 4 -->
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-white"><span class="passo-num">4</span><strong>Contas locais antigas</strong></div>
            <div class="card-body">
                <p class="text-muted passo-ajuda mb-2">
                    Depois que o LAPS estiver ativo, desative as contas locais antigas das máquinas marcadas (ex.: <code>Enzilab</code>) -- o Windows não as apaga ao entrar no Entra.
                    Nunca exclui (dá para reativar). Confira o "Logado agora" na tabela antes. Contas protegidas do Windows
                    (<?= htmlspecialchars(implode(', ', EntraService::CONTAS_LOCAIS_PROTEGIDAS)) ?>) nunca são desativadas.
                </p>
                <label class="form-label small">Nome(s) da conta local (uma por linha, ou separadas por vírgula)</label>
                <textarea class="form-control form-control-sm font-monospace" name="contas" rows="2" placeholder="Enzilab"></textarea>
                <div class="mt-2 d-flex flex-wrap gap-2">
                    <button type="submit" formaction="<?= url('/entra/acesso-maquinas/desativar-contas') ?>" formnovalidate class="btn btn-outline-danger btn-sm" id="botaoDesativarContas">
                        <i class="bi bi-person-x"></i> Desativar
                    </button>
                    <button type="submit" formaction="<?= url('/entra/acesso-maquinas/reativar-contas') ?>" formnovalidate class="btn btn-outline-secondary btn-sm" id="botaoReativarContas">
                        <i class="bi bi-person-check"></i> Reativar
                    </button>
                </div>
            </div>
        </div>
    </form>

    <?php $voltarPara = '/entra/acesso-maquinas'; require __DIR__ . '/_modais_grupos.php'; ?>

<?php endif; ?>

<script>
(function () {
    const form = document.getElementById('formAcessoMaquinas');
    if (!form) return;

    const maquinas = () => Array.from(document.querySelectorAll('.campo-ativo-restricao'));
    const marcadas = () => maquinas().filter(c => c.checked);
    const algumMarcado = sel => Array.from(document.querySelectorAll(sel)).some(c => c.checked);

    document.getElementById('botaoMarcarEntra').addEventListener('click', function () {
        maquinas().forEach(c => { c.checked = c.dataset.entra === '1'; });
    });

    function exigir(e, sel, msg) {
        if ((sel && !algumMarcado(sel)) || !marcadas().length) { e.preventDefault(); alert(msg); return false; }
        return true;
    }

    document.getElementById('botaoSalvarAcesso').addEventListener('click', function (e) {
        const entrada = algumMarcado('.campo-autorizado');
        const admins = algumMarcado('.campo-admin');
        if (!marcadas().length) { e.preventDefault(); alert('Marque ao menos uma máquina no passo 1.'); return; }
        if (!entrada && !admins) { e.preventDefault(); alert('Marque um grupo no passo 2 (quem entra) e/ou no passo 3 (quem administra).'); return; }
        const fora = marcadas().filter(c => c.dataset.entra !== '1').map(c => c.dataset.nome);
        const dentro = marcadas().length - fora.length;
        const linhas = [];
        if (admins) linhas.push('- Quem administra: ' + marcadas().length + ' máquina(s).');
        if (entrada) linhas.push(dentro
            ? '- Quem pode entrar: ' + dentro + ' máquina(s) -- só o grupo (e os administradores) entram.' + (fora.length ? ' Ignoradas por estarem fora do Entra: ' + fora.join(', ') + '.' : '')
            : '- Quem pode entrar: NENHUMA (todas as marcadas estão fora do Entra: ' + fora.join(', ') + ').');
        if (!confirm('Salvar nas máquinas marcadas?

' + linhas.join('
'))) e.preventDefault();
    });

    document.getElementById('botaoRemoverRestricao').addEventListener('click', function (e) {
        if (!exigir(e, null, 'Marque ao menos uma máquina no passo 1.')) return;
        if (!confirm('Remover a restrição das máquinas marcadas? Qualquer conta volta a conseguir entrar.')) e.preventDefault();
    });

    document.getElementById('botaoRemoverAdmins').addEventListener('click', function (e) {
        if (!exigir(e, null, 'Marque ao menos uma máquina no passo 1.')) return;
        if (!confirm('Retirar os administradores que o portal definiu nas máquinas marcadas? O Administrador interno e os administradores globais continuam.')) e.preventDefault();
    });

    const contas = () => document.querySelector('textarea[name="contas"]').value.trim() !== '';
    document.getElementById('botaoDesativarContas').addEventListener('click', function (e) {
        if (!contas() || !marcadas().length) { e.preventDefault(); alert('Informe a conta local e marque ao menos uma máquina.'); return; }
        if (!confirm('Desativar essa(s) conta(s) local(is) nas máquinas marcadas? Confira o "Logado agora". Dá para reativar.')) e.preventDefault();
    });
    document.getElementById('botaoReativarContas').addEventListener('click', function (e) {
        if (!contas() || !marcadas().length) { e.preventDefault(); alert('Informe a conta local e marque ao menos uma máquina.'); return; }
        if (!confirm('Reativar essa(s) conta(s) local(is) nas máquinas marcadas?')) e.preventDefault();
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Microsoft Entra - Acesso às Máquinas';

require __DIR__ . '/../layouts/main.php';
