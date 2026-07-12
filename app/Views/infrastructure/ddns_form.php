<?php
ob_start();

use App\Components\Alert;

$editando = $conta !== null;
$acaoUrl = $editando ? url('/infraestrutura/ddns/editar') : url('/infraestrutura/ddns/novo');
?>

<?= Alert::flash() ?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-globe2 me-1"></i> <?= $editando ? 'Editar conta' : 'Nova conta' ?> de DNS Dinâmico</h4>
    <small class="text-muted">
        <a href="<?= url('/infraestrutura/ddns') ?>"><i class="bi bi-arrow-left"></i> Voltar</a>
    </small>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="post" action="<?= $acaoUrl ?>">
            <?php if ($editando): ?>
                <input type="hidden" name="id" value="<?= (int)$conta['id'] ?>">
            <?php endif; ?>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label">Provedor</label>
                    <select name="provedor" id="campoProvedor" class="form-select" required>
                        <?php foreach ($provedoresLabel as $chave => $label): ?>
                            <option value="<?= htmlspecialchars($chave) ?>" <?= $editando && $conta['provedor'] === $chave ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Apelido</label>
                    <input type="text" name="apelido" class="form-control" required
                           value="<?= htmlspecialchars($conta['apelido'] ?? '') ?>" placeholder="Ex: Casa, Matriz...">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Hostname</label>
                    <input type="text" name="hostname" class="form-control" required
                           value="<?= htmlspecialchars($conta['hostname'] ?? '') ?>" placeholder="Ex: meunome.ddns.net">
                    <div class="form-text" id="ajudaHostname"></div>
                </div>
            </div>

            <hr>

            <?php if ($editando): ?>
                <div class="alert alert-secondary small">
                    <i class="bi bi-info-circle"></i> As credenciais não são exibidas por segurança. Deixe os campos
                    abaixo em branco para manter as credenciais salvas, ou preencha novamente para substituí-las.
                </div>
            <?php endif; ?>

            <div class="campos-provedor" data-provedor="noip">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Usuário</label>
                        <input type="text" name="usuario" class="form-control" autocomplete="off">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Senha</label>
                        <input type="password" name="senha" class="form-control" autocomplete="new-password">
                    </div>
                </div>
            </div>

            <div class="campos-provedor" data-provedor="dyndns">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Usuário</label>
                        <input type="text" name="usuario" class="form-control" autocomplete="off">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Senha</label>
                        <input type="password" name="senha" class="form-control" autocomplete="new-password">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Servidor de atualização</label>
                        <input type="text" name="servidor" class="form-control" placeholder="members.dyndns.org">
                        <div class="form-text">
                            O serviço gratuito original da Dyn.com foi descontinuado — use este campo para apontar
                            para um provedor compatível com o protocolo DynDNS2, se necessário.
                        </div>
                    </div>
                </div>
            </div>

            <div class="campos-provedor" data-provedor="cloudflare">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">API Token</label>
                        <input type="password" name="api_token" class="form-control" autocomplete="new-password">
                        <div class="form-text">Token com permissão de edição de DNS na zone (não use a Global API Key).</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Zone ID</label>
                        <input type="text" name="zone_id" class="form-control" autocomplete="off">
                    </div>
                </div>
                <div class="alert alert-warning small">
                    <i class="bi bi-exclamation-triangle"></i> O registro A do hostname precisa já existir na zone —
                    crie-o uma vez pelo painel da Cloudflare antes de ativar aqui.
                </div>
            </div>

            <div class="campos-provedor" data-provedor="duckdns">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Token</label>
                        <input type="password" name="token" class="form-control" autocomplete="new-password">
                    </div>
                </div>
            </div>

            <div class="campos-provedor" data-provedor="freedns">
                <div class="row g-3 mb-3">
                    <div class="col-md-8">
                        <label class="form-label">URL de atualização</label>
                        <input type="text" name="update_url" class="form-control" autocomplete="off"
                               placeholder="https://freedns.afraid.org/dynamic/update.php?...">
                        <div class="form-text">Copie a URL única do seu hostname na seção "Dynamic DNS" do painel do afraid.org.</div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> Salvar
                </button>
                <a href="<?= url('/infraestrutura/ddns') ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const AJUDA_HOSTNAME = {
        noip: 'Hostname configurado na sua conta No-IP.',
        dyndns: 'Hostname configurado no provedor DynDNS2.',
        cloudflare: 'Nome completo do registro (ex: casa.seudominio.com).',
        duckdns: 'Apenas o subdomínio, sem ".duckdns.org" (ex: "meunome").',
        freedns: 'Usado só como referência na listagem — a URL de atualização já identifica o host.',
    };

    const select = document.getElementById('campoProvedor');
    const grupos = document.querySelectorAll('.campos-provedor');
    const ajudaHostname = document.getElementById('ajudaHostname');

    function atualizar() {
        const atual = select.value;
        grupos.forEach(function (grupo) {
            grupo.style.display = grupo.dataset.provedor === atual ? '' : 'none';
        });
        ajudaHostname.textContent = AJUDA_HOSTNAME[atual] || '';
    }

    select.addEventListener('change', atualizar);
    atualizar();
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Infraestrutura - DNS Dinâmico';

require __DIR__ . '/../layouts/main.php';
