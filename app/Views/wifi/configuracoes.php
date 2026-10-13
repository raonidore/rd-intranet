<?php
ob_start();

use App\Components\Alert;

/** @var \App\Services\WifiConfigService $c */
$e = fn ($k) => htmlspecialchars($c->get($k));
$abas = ['portal' => 'Portal', 'lgpd' => 'LGPD e retenção', 'filtro' => 'Filtro de sites', 'https' => 'HTTPS'];
$aba = isset($abas[$aba]) ? $aba : 'portal';
$filtroAtual = $c->json('filtro_global');
?>

<?= Alert::flash() ?>

<div class="mb-3 d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h4 class="mb-1"><i class="bi bi-gear me-1"></i> Configurações do Wi-Fi</h4>
        <small class="text-muted">Portal: <code><?= htmlspecialchars($c->urlPortal()) ?>/guest/</code></small>
    </div>
    <a href="<?= url('/wifi/rede') ?>" class="btn btn-outline-primary"><i class="bi bi-diagram-3"></i> Assistente de rede (VLAN, Wi-Fi, switches)</a>
</div>

<ul class="nav nav-tabs mb-3">
    <?php foreach ($abas as $k => $rot): ?>
    <li class="nav-item"><a class="nav-link <?= $aba === $k ? 'active' : '' ?>" href="<?= url('/wifi/configuracoes?aba=' . $k) ?>"><?= $rot ?></a></li>
    <?php endforeach; ?>
</ul>

<?php if ($aba === 'portal'): ?>
<form method="post" action="<?= url('/wifi/configuracoes/salvar') ?>" class="card border-0 shadow-sm" style="max-width:820px">
    <input type="hidden" name="aba" value="portal">
    <div class="card-body row g-3">
        <div class="col-12">
            <div class="form-check form-switch fs-5">
                <input class="form-check-input" type="checkbox" name="portal_ativo" value="1" id="portalAtivo" <?= $c->bool('portal_ativo') ? 'checked' : '' ?>>
                <label class="form-check-label" for="portalAtivo">Portal ligado</label>
            </div>
            <div class="form-text">Ligar publica o endereço <code>/guest</code> no servidor e agenda a manutenção automática (a cada 5 minutos).</div>
        </div>
        <div class="col-md-8"><label class="form-label">Título do portal</label><input name="portal_titulo" class="form-control" value="<?= $e('portal_titulo') ?>" maxlength="60"></div>
        <div class="col-md-4"><label class="form-label">Cor</label><input type="color" name="portal_cor" class="form-control form-control-color w-100" value="<?= $e('portal_cor') ?>"></div>
        <div class="col-12"><label class="form-label">Mensagem de boas-vindas</label><textarea name="portal_boas_vindas" class="form-control" rows="2" maxlength="300"><?= $e('portal_boas_vindas') ?></textarea></div>
        <div class="col-md-6"><label class="form-label">IP deste servidor visto pelos aparelhos</label><input name="ip_portal" class="form-control" value="<?= $e('ip_portal') ?>" placeholder="<?= htmlspecialchars($c->ipPortal()) ?> (automático)"></div>
        <div class="col-md-6"><label class="form-label">Equipamento que libera os aparelhos</label>
            <select name="controlador" class="form-select">
                <?php foreach ($controladores as $k => [$rot]): ?><option value="<?= $k ?>" <?= $c->get('controlador') === $k ? 'selected' : '' ?>><?= htmlspecialchars($rot) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-md-6"><label class="form-label">Bloquear login após (erros)</label><input type="number" min="3" name="tentativas_max" class="form-control" value="<?= $e('tentativas_max') ?>"></div>
        <div class="col-md-6"><label class="form-label">... por (minutos)</label><input type="number" min="5" name="tentativas_janela_min" class="form-control" value="<?= $e('tentativas_janela_min') ?>"></div>
    </div>
    <div class="card-footer bg-transparent"><button class="btn btn-primary">Salvar</button></div>
</form>

<?php elseif ($aba === 'lgpd'): ?>
<form method="post" action="<?= url('/wifi/configuracoes/salvar') ?>" class="card border-0 shadow-sm" style="max-width:820px">
    <input type="hidden" name="aba" value="lgpd">
    <div class="card-body row g-3">
        <div class="col-md-6"><label class="form-label">Encarregado de dados (DPO)</label><input name="encarregado_nome" class="form-control" value="<?= $e('encarregado_nome') ?>"></div>
        <div class="col-md-6"><label class="form-label">E-mail do encarregado</label><input type="email" name="encarregado_email" class="form-control" value="<?= $e('encarregado_email') ?>"></div>
        <div class="col-md-6"><label class="form-label">Guardar registros de conexão por (dias)</label><input type="number" min="180" name="retencao_dias" class="form-control" value="<?= $e('retencao_dias') ?>">
            <div class="form-text">Depois disso, MAC, IP e a pessoa são removidos do registro (fica só o número agregado). Mínimo 180 dias.</div></div>
        <div class="col-md-6"><label class="form-label">Guardar sites consultados por (dias)</label><input type="number" min="1" max="90" name="filtro_log_dias" class="form-control" value="<?= $e('filtro_log_dias') ?>">
            <div class="form-text">Só para diagnóstico. Recomendado: 7.</div></div>
        <div class="col-12 small text-muted">O texto que o visitante vê está em <code>/guest/privacidade</code> e usa estes valores. Os termos de uso de cada perfil ficam em Perfis e regras.</div>
    </div>
    <div class="card-footer bg-transparent"><button class="btn btn-primary">Salvar</button></div>
</form>

<?php elseif ($aba === 'filtro'): ?>
<div class="card border-0 shadow-sm mb-3" style="max-width:980px"><div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div>
        <strong>Motor do filtro (AdGuard Home no servidor)</strong><br>
        <?php if (empty($filtroStatus['instalado'])): ?>
            <span class="text-muted small">Não instalado. Ele vira o DNS da rede do portal; cada pessoa recebe as regras do perfil dela.</span>
        <?php elseif (!empty($filtroStatus['rodando'])): ?>
            <span class="badge text-bg-success">funcionando</span> <span class="small text-muted"><?= htmlspecialchars((string)$filtroStatus['versao']) ?> &middot; DNS em <?= htmlspecialchars(implode(', ', (array)$filtroStatus['dns'])) ?></span>
        <?php else: ?>
            <span class="badge text-bg-danger">fora do ar</span> <span class="small text-muted"><?= htmlspecialchars((string)($filtroStatus['erro'] ?? '')) ?></span>
        <?php endif; ?>
    </div>
    <form method="post" action="<?= url('/wifi/configuracoes/filtro-instalar') ?>" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').innerText='Instalando...'">
        <button class="btn btn-outline-primary"><?= empty($filtroStatus['instalado']) ? 'Instalar' : 'Reinstalar / reparar' ?></button>
    </form>
</div></div>
<form method="post" action="<?= url('/wifi/configuracoes/salvar') ?>" class="card border-0 shadow-sm" style="max-width:980px">
    <input type="hidden" name="aba" value="filtro">
    <div class="card-body">
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="filtro_ativo" value="1" id="filtroAtivo" <?= $c->bool('filtro_ativo') ? 'checked' : '' ?>>
            <label class="form-check-label" for="filtroAtivo">Filtro ativo</label>
        </div>
        <p class="small text-muted">Regras abaixo valem para <strong>todos</strong> os perfis, somadas às regras de cada perfil. Para o filtro funcionar, a rede do portal precisa entregar o DNS <code><?= htmlspecialchars($c->ipPortal()) ?></code> (o assistente de rede faz isso) e o gateway deve bloquear DNS externo nessa rede.</p>
        <?php if (!$servicos): ?><p class="small text-warning">A lista de serviços aparece depois que o filtro estiver instalado.</p><?php endif; ?>
        <?php require __DIR__ . '/_filtro_campos.php'; ?>
    </div>
    <div class="card-footer bg-transparent"><button class="btn btn-primary">Salvar</button></div>
</form>

<?php else: ?>
<form method="post" action="<?= url('/wifi/configuracoes/salvar') ?>" class="card border-0 shadow-sm mb-3" style="max-width:820px">
    <input type="hidden" name="aba" value="https">
    <div class="card-body row g-3">
        <div class="col-12 small text-muted">Sem HTTPS o navegador mostra "não seguro" na tela de login. O portal ganha um nome (ex.: <code>wifi-cliente.rd.inf.br</code>) que aponta para o IP interno do servidor, com certificado gratuito (Let's Encrypt) validado pelo DNS do cPanel. A renovação é automática.</div>
        <div class="col-md-6"><label class="form-label">Nome do portal</label><input name="https_dominio" class="form-control" value="<?= $e('https_dominio') ?>" placeholder="wifi-cliente.rd.inf.br"></div>
        <div class="col-md-6"><label class="form-label">Zona DNS no cPanel</label><input name="cpanel_zona" class="form-control" value="<?= $e('cpanel_zona') ?>"></div>
        <div class="col-md-6"><label class="form-label">URL do cPanel</label><input name="cpanel_url" class="form-control" value="<?= $e('cpanel_url') ?>"></div>
        <div class="col-md-6"><label class="form-label">Usuário do cPanel</label><input name="cpanel_usuario" class="form-control" value="<?= $e('cpanel_usuario') ?>"></div>
        <div class="col-12"><label class="form-label">Token da API do cPanel <?= $temToken ? '<span class="badge text-bg-success">salvo</span>' : '' ?></label>
            <input type="password" name="cpanel_token" class="form-control" autocomplete="off" placeholder="<?= $temToken ? 'deixe vazio para manter' : 'cPanel > Segurança > Gerenciar tokens de API' ?>"></div>
    </div>
    <div class="card-footer bg-transparent"><button class="btn btn-primary">Salvar</button></div>
</form>
<?php if ($httpsAndamento): $rodando = ($httpsAndamento['success'] ?? null) === null; ?>
<div class="alert <?= $rodando ? 'alert-info' : ($httpsAndamento['success'] ? 'alert-success' : 'alert-danger') ?> small" style="max-width:820px">
    <?php if ($rodando): ?><span class="spinner-border spinner-border-sm me-1"></span><?php endif; ?>
    <strong>Última emissão (<?= htmlspecialchars(date('d/m H:i', strtotime($httpsAndamento['quando']))) ?>):</strong> <?= htmlspecialchars($httpsAndamento['message']) ?>
</div>
<?php if ($rodando): ?><script>setTimeout(function () { location.reload(); }, 10000);</script><?php endif; ?>
<?php endif; ?>
<div class="card border-0 shadow-sm" style="max-width:820px"><div class="card-body d-flex flex-wrap gap-2 align-items-center">
    <div class="me-auto">
        <?php if ($c->bool('https_ativo')): ?><span class="badge text-bg-success">HTTPS ativo</span> <code>https://<?= $e('https_dominio') ?>/guest/</code>
        <?php else: ?><span class="badge text-bg-secondary">HTTPS desligado</span> <span class="small text-muted">o portal abre por <code><?= htmlspecialchars($c->urlPortal()) ?></code></span><?php endif; ?>
    </div>
    <form method="post" action="<?= url('/wifi/configuracoes/cpanel-testar') ?>"><button class="btn btn-outline-secondary btn-sm">Testar cPanel</button></form>
    <form method="post" action="<?= url('/wifi/configuracoes/https-emitir') ?>" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').innerText='Iniciando...'">
        <button class="btn btn-primary btn-sm" <?= $c->get('https_dominio') === '' || !$temToken ? 'disabled' : '' ?>><?= $c->bool('https_ativo') ? 'Renovar / reemitir' : 'Criar DNS e emitir certificado' ?></button>
    </form>
    <?php if ($c->bool('https_ativo')): ?>
    <form method="post" action="<?= url('/wifi/configuracoes/https-desligar') ?>"><button class="btn btn-outline-danger btn-sm">Voltar para HTTP</button></form>
    <?php endif; ?>
</div></div>
<?php endif; ?>

<?php
$conteudo = ob_get_clean();
$titulo = 'Wi-Fi - Configurações';

require __DIR__ . '/../layouts/main.php';
