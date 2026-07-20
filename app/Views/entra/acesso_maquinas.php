<?php
ob_start();

use App\Components\Alert;
?>

<?= Alert::flash() ?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-shield-lock me-1"></i> Microsoft Entra - Acesso às Máquinas</h4>
    <small class="text-muted"><a href="<?= url('/entra/dashboard') ?>"><i class="bi bi-arrow-left"></i> Dashboard</a></small>
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
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Microsoft Entra - Acesso às Máquinas';

require __DIR__ . '/../layouts/main.php';
