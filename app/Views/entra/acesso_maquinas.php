<?php
ob_start();

use App\Components\Alert;
use App\Services\EntraService;
?>

<style>
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
.pop-callout { display:flex; gap:.6rem; padding:.7rem .9rem; border-radius:8px; font-size:.82rem; margin-top:.6rem; }
.pop-callout-info { background:rgba(12,45,74,.35); border:1px solid rgba(31,111,235,.35); color:#79c0ff; }
.pop-callout-warning { background:rgba(59,47,0,.2); border:1px solid rgba(125,90,0,.35); color:#e3b341; }
.pop-callout i { flex:0 0 auto; }

/* Mockups simulados (não são captura de tela real -- ilustram o layout descrito no passo). */
.pop-mockup { display:flex; gap:14px; background:#010409; border:1px solid #30363d; border-radius:10px; padding:14px; margin-top:.7rem; flex-wrap:wrap; }
.pop-mockup-col { flex:1; min-width:190px; }
.pop-mockup-label { font-size:.68rem; text-transform:uppercase; letter-spacing:.05em; color:#8b949e; margin-bottom:6px; font-weight:700; }
.pop-mockup-check { display:flex; align-items:flex-start; gap:6px; font-size:.76rem; color:#c9d1d9; padding:4px 0; }
.pop-mockup-box { width:13px; height:13px; margin-top:2px; border:1.5px solid #484f58; border-radius:3px; flex:0 0 auto; display:inline-block; position:relative; }
.pop-mockup-box.checked { background:#238636; border-color:#238636; }
.pop-mockup-box.checked::after { content:'\2713'; position:absolute; inset:0; display:flex; align-items:center; justify-content:center; font-size:9px; color:#fff; line-height:1; }
.pop-mockup-tag { display:block; font-size:.68rem; color:#e3b341; margin-top:1px; }
.pop-mockup-btn { display:inline-flex; align-items:center; gap:5px; font-size:.76rem; padding:5px 10px; border-radius:6px; margin-top:8px; font-weight:600; }
.pop-mockup-btn.primary { background:#1f6feb; color:#fff; }
.pop-mockup-btn.danger { background:#21262d; color:#f85149; border:1px solid #6e2c27; }
.pop-mockup-btn.secondary { background:#21262d; color:#c9d1d9; border:1px solid #30363d; }
.pop-mockup-bubble { background:#161b22; border:1px solid #30363d; border-radius:8px; padding:8px 10px; font-size:.72rem; color:#8b949e; margin-top:8px; max-width:320px; }
.pop-mockup-bubble strong { color:#e6edf3; }
.pop-mockup-textarea { background:#0d1117; border:1px solid #30363d; border-radius:6px; padding:6px 8px; font-size:.72rem; color:#7ee787; font-family:ui-monospace,monospace; margin-top:6px; line-height:1.6; }
</style>

<?= Alert::flash() ?>

<div class="mb-4 d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h4 class="mb-1"><i class="bi bi-shield-lock me-1"></i> Microsoft Entra - Acesso às Máquinas</h4>
        <small class="text-muted"><a href="<?= url('/entra/dashboard') ?>"><i class="bi bi-arrow-left"></i> Dashboard</a></small>
    </div>

    <button type="button" class="btn btn-outline-dark text-nowrap" data-bs-toggle="modal" data-bs-target="#modalPopAcessoMaquinas">
        <i class="bi bi-broadcast"></i> POP - Acesso às Máquinas
    </button>
</div>

<!-- POP -- Procedimento Operacional Padrão da tela de Acesso às Máquinas -->
<div class="modal fade pop-modal" id="modalPopAcessoMaquinas" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="pop-topbar">
                <span class="pop-breadcrumb"><i class="bi bi-broadcast"></i> POP -- Acesso às Máquinas</span>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="pop-body">
                <p class="pop-intro">Passo a passo de cada bloco da tela, na ordem em que normalmente se usa: primeiro restringe quem loga, depois (se precisar) desativa contas locais antigas.</p>

                <div class="pop-step">
                    <div class="pop-step-num">1</div>
                    <div>
                        <div class="pop-step-title"><i class="bi bi-shield-lock"></i> Pra que serve essa tela</div>
                        <div class="pop-step-text">
                            Controla <strong>quem consegue logar localmente</strong> (na tela de login do Windows, no
                            teclado/mouse da própria máquina) nos computadores selecionados. Não mexe em acesso remoto
                            (RDP, comando remoto daqui do portal) nem precisa de Intune/licença adicional -- é uma
                            configuração local de cada máquina, aplicada remotamente por aqui.
                        </div>
                    </div>
                </div>

                <div class="pop-step">
                    <div class="pop-step-num">2</div>
                    <div>
                        <div class="pop-step-title"><i class="bi bi-people"></i> Contas autorizadas (coluna esquerda)</div>
                        <div class="pop-step-text">
                            Lista todo mundo cadastrado no Entra ID do tenant. Marque só quem <strong>deve conseguir
                            logar</strong> nas máquinas que você vai selecionar a seguir -- quem não tem o Entra
                            configurado no perfil (ou está com a conta desativada) pode ser marcado, mas não vai
                            conseguir entrar até resolver isso do lado do Entra.
                        </div>
                    </div>
                </div>

                <div class="pop-step">
                    <div class="pop-step-num">3</div>
                    <div>
                        <div class="pop-step-title"><i class="bi bi-pc-display"></i> Máquinas (coluna direita)</div>
                        <div class="pop-step-text">
                            Só aparecem máquinas com o <strong>agente de bandeja (.exe)</strong> instalado -- é o canal
                            que entrega o comando remotamente. Cada linha mostra <code>Logado agora: ...</code> quando
                            tem alguém logado na hora -- confira isso antes de aplicar qualquer ação, principalmente
                            antes de desativar conta (passo 6).
                        </div>
                        <div class="pop-mockup">
                            <div class="pop-mockup-col">
                                <div class="pop-mockup-label">Contas autorizadas</div>
                                <div class="pop-mockup-check"><span class="pop-mockup-box checked"></span> Raoni Dore (raoni.dore@enzilab.net)</div>
                                <div class="pop-mockup-check"><span class="pop-mockup-box"></span> Ana Paula Barbalho</div>
                                <div class="pop-mockup-check"><span class="pop-mockup-box"></span> Débora Sales de Lima</div>
                            </div>
                            <div class="pop-mockup-col">
                                <div class="pop-mockup-label">Máquinas</div>
                                <div class="pop-mockup-check"><span class="pop-mockup-box checked"></span> <span>EP-TRIAGEM (EP-PC-000014)<span class="pop-mockup-tag">Logado agora: EP-TRIAGEM\EP - Triagem</span></span></div>
                                <div class="pop-mockup-check"><span class="pop-mockup-box"></span> <span>EP-RECEP4 (EP-PC-000013)</span></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pop-step">
                    <div class="pop-step-num">4</div>
                    <div>
                        <div class="pop-step-title"><i class="bi bi-shield-check"></i> Aplicar restrição</div>
                        <div class="pop-step-text">
                            Marque as contas (passo 2) e as máquinas (passo 3), clique em <strong>"Aplicar restrição
                            nas máquinas selecionadas"</strong> e confirme. A partir daí, só essas contas conseguem
                            logar localmente nessas máquinas.
                        </div>
                        <div class="pop-callout pop-callout-warning">
                            <i class="bi bi-shield-fill-check"></i>
                            <div>Rede de segurança: os <strong>administradores locais de cada máquina sempre continuam permitidos</strong>, mesmo sem marcar nada -- essa ação nunca tranca o acesso de quem administra a máquina.</div>
                        </div>
                        <div class="pop-mockup">
                            <div class="pop-mockup-col">
                                <span class="pop-mockup-btn primary"><i class="bi bi-shield-check"></i> Aplicar restrição nas máquinas selecionadas</span>
                                <div class="pop-mockup-bubble">
                                    <strong>Confirmar ação</strong><br>
                                    Aplicar a restrição de login nas máquinas selecionadas? Só as contas marcadas (+ administradores locais) vão conseguir logar localmente.
                                    <div style="margin-top:6px"><span class="pop-mockup-btn secondary" style="margin-top:0">Cancelar</span> <span class="pop-mockup-btn primary" style="margin-top:0">OK</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pop-step">
                    <div class="pop-step-num">5</div>
                    <div>
                        <div class="pop-step-title"><i class="bi bi-shield-slash"></i> Remover restrição</div>
                        <div class="pop-step-text">
                            Só precisa marcar as <strong>máquinas</strong> (não precisa marcar conta nenhuma). Volta o
                            login local ao normal (qualquer usuário/administrador local da máquina consegue entrar de
                            novo) -- é o "desfazer" do passo 4.
                        </div>
                    </div>
                </div>

                <div class="pop-step">
                    <div class="pop-step-num">6</div>
                    <div>
                        <div class="pop-step-title"><i class="bi bi-person-x"></i> Desativar contas locais</div>
                        <div class="pop-step-text">
                            Passo complementar, separado da restrição de login -- o Windows <strong>não apaga contas
                            locais</strong> sozinho quando a máquina entra no Entra. Digite o(s) nome(s) da conta local
                            (uma por linha ou separadas por vírgula, ex: <code>Aluno</code>, <code>Admin</code>),
                            marque as máquinas e clique em <strong>"Desativar nas máquinas selecionadas"</strong>.
                        </div>
                        <div class="pop-callout pop-callout-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                            <div>Confira o <strong>"Logado agora"</strong> de cada máquina (passo 3) antes de desativar -- desativar a conta que está em uso ali não derruba a sessão aberta na hora, mas ninguém consegue entrar de novo com ela depois. Contas protegidas do Windows (Administrator, Guest, DefaultAccount e as versões em português) nunca são desativadas, mesmo se digitadas.</div>
                        </div>
                        <div class="pop-mockup">
                            <div class="pop-mockup-col">
                                <div class="pop-mockup-label">Nome(s) da conta local</div>
                                <div class="pop-mockup-textarea">Aluno<br>Admin<br>User</div>
                                <span class="pop-mockup-btn danger"><i class="bi bi-person-x"></i> Desativar nas máquinas selecionadas</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pop-step">
                    <div class="pop-step-num">7</div>
                    <div>
                        <div class="pop-step-title"><i class="bi bi-person-check"></i> Reativar contas locais</div>
                        <div class="pop-step-text">
                            Desfaz o passo 6 -- mesmo campo de nomes, mesmas máquinas marcadas, clique em
                            <strong>"Reativar nas máquinas selecionadas"</strong>. <code>Disable-LocalUser</code>/
                            <code>Enable-LocalUser</code> nunca excluem a conta nem os dados dela, então essa ação é
                            sempre 100% reversível.
                        </div>
                        <div class="pop-callout pop-callout-info">
                            <i class="bi bi-info-circle"></i>
                            <div>Toda ação dessa tela é enfileirada pra máquina e só executa no próximo contato do agente com o servidor (poucos segundos) -- o resultado de cada uma aparece no histórico de comandos da ficha do ativo.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!$configurado): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-plug display-6 text-muted d-block mb-3"></i>
            <p class="text-muted mb-3">Módulo ainda não configurado.</p>
            <a href="<?= url('/entra/configuracao') ?>" class="btn btn-primary"><i class="bi bi-gear"></i> Configurar</a>
        </div>
    </div>
<?php else: ?>

    <div class="alert alert-info small">
        <i class="bi bi-info-circle"></i>
        Restringe quem consegue <strong>logar localmente</strong> (na tela de login do Windows) nas máquinas
        selecionadas -- só as contas do Entra marcadas abaixo passam a conseguir entrar. Os
        <strong>administradores locais de cada máquina sempre continuam permitidos</strong>, mesmo sem marcar
        nada (rede de segurança pra nunca travar o acesso). Não afeta acesso remoto (RDP, comando remoto daqui
        do portal) nem exige Intune/licença adicional. O resultado de cada máquina aparece no histórico de
        comandos da própria ficha do ativo, em poucos segundos. Quer visibilidade/controle remoto extra
        (conformidade, sincronizar, reiniciar, bloquear tela) além disso? Isso é opcional e fica em
        <a href="<?= url('/entra/dispositivos') ?>">Dispositivos (Intune)</a>.
    </div>

    <form method="post" id="formAcessoMaquinas">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <strong>Contas autorizadas</strong>
                        <span class="text-muted small"><?= count($usuarios) ?> usuário(s) no tenant</span>
                    </div>
                    <div class="card-body" style="max-height:420px; overflow-y:auto">
                        <?php if (empty($usuarios)): ?>
                            <p class="text-muted small mb-0">Nenhum usuário encontrado.</p>
                        <?php else: ?>
                            <?php foreach ($usuarios as $u): ?>
                                <?php $upn = $u['userPrincipalName'] ?? ''; ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="upns[]" value="<?= htmlspecialchars($upn) ?>" id="upn-<?= htmlspecialchars($upn) ?>">
                                    <label class="form-check-label small" for="upn-<?= htmlspecialchars($upn) ?>">
                                        <?= htmlspecialchars($u['displayName'] ?? $upn) ?>
                                        <span class="text-muted font-monospace">(<?= htmlspecialchars($upn) ?>)</span>
                                        <?php if (!($u['accountEnabled'] ?? true)): ?><span class="badge text-bg-secondary">desativado</span><?php endif; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <strong>Máquinas</strong>
                        <span class="text-muted small"><?= count($computadores) ?> disponíve(is)</span>
                    </div>
                    <div class="card-body" style="max-height:420px; overflow-y:auto">
                        <?php if (empty($computadores)): ?>
                            <p class="text-muted small mb-0">Nenhum computador com o agente de bandeja (.exe) instalado -- essa ação precisa dele (script .ps1 não recebe comando remoto).</p>
                        <?php else: ?>
                            <?php foreach ($computadores as $c): ?>
                                <div class="form-check">
                                    <input class="form-check-input campo-ativo-restricao" type="checkbox" name="ativos[]" value="<?= (int)$c['id'] ?>" id="ativo-<?= (int)$c['id'] ?>">
                                    <label class="form-check-label small" for="ativo-<?= (int)$c['id'] ?>">
                                        <?= htmlspecialchars($c['nome']) ?>
                                        <span class="text-muted font-monospace">(<?= htmlspecialchars($c['codigo_patrimonio']) ?>)</span>
                                        <?php if (!empty($c['usuario_logado_atual'])): ?>
                                            <br><span class="text-muted">Logado agora: <span class="font-monospace"><?= htmlspecialchars($c['usuario_logado_atual']) ?></span></span>
                                        <?php endif; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <button type="submit" formaction="<?= url('/entra/acesso-maquinas/aplicar') ?>" class="btn btn-primary" id="botaoAplicarRestricao">
                <i class="bi bi-shield-check"></i> Aplicar restrição nas máquinas selecionadas
            </button>
            <button type="submit" formaction="<?= url('/entra/acesso-maquinas/remover') ?>" formnovalidate class="btn btn-outline-secondary" id="botaoRemoverRestricao">
                <i class="bi bi-shield-slash"></i> Remover restrição (liberar login pra todos de novo)
            </button>
        </div>

        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white">
                <strong>Desativar contas locais antigas</strong>
                <span class="text-muted small d-block">
                    Passo complementar -- o Windows não apaga contas locais sozinho quando a máquina entra no Entra.
                    Use <code>Disable-LocalUser</code>/<code>Enable-LocalUser</code> (nunca exclui a conta, sempre reversível).
                </span>
            </div>
            <div class="card-body">
                <div class="alert alert-warning small mb-3">
                    <i class="bi bi-exclamation-triangle"></i>
                    Confira acima quem está <strong>logado agora</strong> em cada máquina selecionada antes de desativar --
                    desativar a conta que está em uso ali impede login de novo com ela (a sessão atual não cai na hora,
                    mas ninguém consegue entrar de novo com essa conta depois). Contas protegidas do Windows
                    (<?= htmlspecialchars(implode(', ', EntraService::CONTAS_LOCAIS_PROTEGIDAS)) ?>)
                    nunca são desativadas, mesmo se digitadas.
                </div>
                <label class="form-label small">Nome(s) da conta local (uma por linha, ou separadas por vírgula)</label>
                <textarea class="form-control form-control-sm font-monospace" name="contas" rows="3" placeholder="Aluno&#10;Admin&#10;User"></textarea>
                <div class="mt-2">
                    <button type="submit" formaction="<?= url('/entra/acesso-maquinas/desativar-contas') ?>" formnovalidate class="btn btn-outline-danger btn-sm" id="botaoDesativarContas">
                        <i class="bi bi-person-x"></i> Desativar nas máquinas selecionadas
                    </button>
                    <button type="submit" formaction="<?= url('/entra/acesso-maquinas/reativar-contas') ?>" formnovalidate class="btn btn-outline-secondary btn-sm" id="botaoReativarContas">
                        <i class="bi bi-person-check"></i> Reativar nas máquinas selecionadas
                    </button>
                </div>
            </div>
        </div>
    </form>

<?php endif; ?>

<script>
(function () {
    const form = document.getElementById('formAcessoMaquinas');
    if (!form) return;

    function algumMarcado(seletor) {
        return Array.from(document.querySelectorAll(seletor)).some(function (c) { return c.checked; });
    }

    document.getElementById('botaoAplicarRestricao').addEventListener('click', function (e) {
        if (!algumMarcado('input[name="upns[]"]') || !algumMarcado('.campo-ativo-restricao')) {
            e.preventDefault();
            alert('Selecione ao menos uma conta e uma máquina.');
            return;
        }
        if (!confirm('Aplicar a restrição de login nas máquinas selecionadas? Só as contas marcadas (+ administradores locais) vão conseguir logar localmente nelas a partir de agora.')) {
            e.preventDefault();
        }
    });

    document.getElementById('botaoRemoverRestricao').addEventListener('click', function (e) {
        if (!algumMarcado('.campo-ativo-restricao')) {
            e.preventDefault();
            alert('Selecione ao menos uma máquina.');
            return;
        }
        if (!confirm('Remover a restrição de login das máquinas selecionadas? Volta a liberar o login local pra qualquer usuário/administrador local dessas máquinas.')) {
            e.preventDefault();
        }
    });

    function contasPreenchidas() {
        return document.querySelector('textarea[name="contas"]').value.trim() !== '';
    }

    document.getElementById('botaoDesativarContas').addEventListener('click', function (e) {
        if (!contasPreenchidas() || !algumMarcado('.campo-ativo-restricao')) {
            e.preventDefault();
            alert('Informe ao menos uma conta local e selecione ao menos uma máquina.');
            return;
        }
        if (!confirm('Desativar essa(s) conta local(is) nas máquinas selecionadas? Confira acima quem está logado agora em cada uma -- desativar a conta em uso impede login de novo com ela. Ação reversível pelo botão "Reativar".')) {
            e.preventDefault();
        }
    });

    document.getElementById('botaoReativarContas').addEventListener('click', function (e) {
        if (!contasPreenchidas() || !algumMarcado('.campo-ativo-restricao')) {
            e.preventDefault();
            alert('Informe ao menos uma conta local e selecione ao menos uma máquina.');
            return;
        }
        if (!confirm('Reativar essa(s) conta local(is) nas máquinas selecionadas?')) {
            e.preventDefault();
        }
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Microsoft Entra - Acesso às Máquinas';

require __DIR__ . '/../layouts/main.php';
